 
@if(config('fee_presets.simple_menu', true) && !isset($invoiceInfo))
@php
    $feePlanService = app(\App\Services\FeeMonthlyPlanService::class);
    $academicMeta = $academicScheduleMeta ?? $feePlanService->academicYearMeta(getAcademicId());
    $monthsList = $academicMeta['months'] ?? [];
    $monthCount = max(1, count($monthsList));
    $defaultDueDay = (int) ($academicMeta['default_due_day'] ?? config('fee_presets.monthly_due_day', 25));
    $prefs = $invoicePrefs ?? [];
    $defaultFromIndex = (int) ($prefs['schedule_from_index'] ?? $feePlanService->currentAcademicMonthIndex(getAcademicId()));
    $defaultFromIndex = max(1, min($monthCount, $defaultFromIndex));
    $defaultPayMonths = (int) ($prefs['pay_months'] ?? 1);
    $defaultDueDayPref = (int) ($prefs['fee_due_day'] ?? $defaultDueDay);
    $defaultUnitId = $prefs['unit_id'] ?? null;
@endphp
<div class="fiw-quick-form" id="fiwQuickForm">
    <div class="fiw-search-bar">
        <label class="primary_input_label" for="fiwStudentSearch">Find student</label>
        <div class="fiw-search-wrap">
            <i class="ti-search fiw-search-icon" aria-hidden="true"></i>
            <input type="search" class="primary_input_field form-control fiw-search-input" id="fiwStudentSearch"
                placeholder="Name, admission no, or mobile…" autocomplete="off" aria-autocomplete="list"
                aria-controls="fiwSearchResults" aria-expanded="false">
            <kbd class="fiw-kbd-hint" aria-hidden="true">Ctrl+K</kbd>
            <div class="fiw-search-results" id="fiwSearchResults" role="listbox" hidden></div>
        </div>
        <button type="button" class="fiw-repeat-btn" id="fiwRepeatLast" style="display:none">
            <i class="ti-reload"></i> Repeat last invoice
        </button>
    </div>

    <div class="fiw-student-pill" id="fiwStudentPill" style="display:none" aria-live="polite"></div>

    <div class="d-none">
        @include('backEnd.common.search_criteria', [
            'mt' => 'mt-0',
            'div' => 'col-lg-12',
            'required' => [],
            'visiable' => ['shift'],
            'selected' => ['shift_id' => isset($shift_id) ? $shift_id : null],
        ])
    </div>

    {{-- Unit is the only control that stays visible after picking a student; it auto-fills from the student. --}}
    <div class="row fiw-unit-row" id="feeUnitRow" @if(!old('class')) style="display:none" @endif>
        <div class="col-md-6 fiw-field" id="feeUnitSelectDiv">
            <label class="primary_input_label">Unit / package <span class="text-danger">*</span></label>
            <select class="primary_select form-control" id="feeUnitSelect" name="fee_unit_select">
                <option value="">Select unit</option>
                @foreach(($units ?? []) as $u)
                    <option value="{{ $u->id }}" data-service="{{ $u->service_line }}" data-code="{{ $u->code ?? $u->short_name }}"
                        @selected((string) $defaultUnitId === (string) $u->id)>
                        {{ app(\App\Services\HostelPlacementService::class)->unitLabel($u) }}
                    </option>
                @endforeach
            </select>
            <small class="text-muted">Auto-selected from the student — change only if needed.</small>
        </div>
    </div>

    <details class="fiw-manual-pick" id="fiwManualPick" @if(old('class') || $errors->any()) open @endif>
        <summary>Pick student manually (no search)</summary>
        <div class="row fiw-cascade mt-10">
            <div class="col-xl-3 col-lg-4 col-md-6 fiw-field" id="id-card-div">
                <label for="select_class">{{ __('common.class') }} <span class="text-danger">*</span></label>
                <select class="primary_select form-control{{ $errors->has('class') ? ' is-invalid' : '' }}" id="select_class" name="class">
                    <option data-display="@lang('common.select_class') *" value="">@lang('common.select_class')</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" {{ old('class') == $class->id ? 'selected' : '' }}>{{ @$class->class_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-3 col-lg-4 col-md-6 fiw-field" id="selectSectionDiv">
                <label class="primary_input_label">{{ __('common.section') }} <span class="text-danger">*</span></label>
                <select class="primary_select form-control" id="select_section" name="section_id">
                    <option data-display="@lang('common.select_section') *" value="">@lang('common.select_section')</option>
                </select>
            </div>
            <div class="col-xl-3 col-lg-4 col-md-6 fiw-field" id="selectStudentDiv">
                <label class="primary_input_label">{{ __('common.select_student') }} <span class="text-danger">*</span></label>
                @php
                    $students = collect();
                    if (old('class')) {
                        $students = App\Models\StudentRecord::where('class_id', old('class'))
                            ->where('school_id', Auth::user()->school_id)
                            ->where('academic_id', getAcademicId())
                            ->get();
                    }
                @endphp
                <select class="primary_select form-control{{ $errors->has('student') ? ' is-invalid' : '' }}" id="selectStudent" name="student">
                    <option data-display="@lang('common.select_student') *" value="">@lang('common.select_student')</option>
                    @if ($students->isNotEmpty())
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}" {{ old('student') == $student->id ? 'selected' : '' }}>
                                {{ $student->studentDetail->full_name }} ({{ $student->section->section_name }} - {{ $student->roll_no }})
                            </option>
                        @endforeach
                    @endif
                </select>
                <div class="pull-right loader" id="selectStudentLoader" style="margin-top:-30px;padding-right:12px;display:none;">
                    <img src="{{ assetPath('Modules/Fees/Resources/assets/img/pre-loader.gif') }}" alt="" style="width:24px;height:24px;">
                </div>
            </div>
        </div>
    </details>

    <details class="fiw-advanced-schedule" id="fiwAdvancedSchedule">
        <summary>Schedule <span class="fiw-advanced-hint">— {{ $defaultPayMonths }} month{{ $defaultPayMonths > 1 ? 's' : '' }}, {{ $monthsList[$defaultFromIndex - 1]['label'] ?? '' }}</span></summary>
        <div class="row mt-10">
            <div class="col-md-6 fiw-field">
                <label class="primary_input_label">Start from month</label>
                <select class="primary_select form-control" id="scheduleFromIndex" name="schedule_from_index">
                    @foreach($monthsList as $month)
                        <option value="{{ $month['index'] }}" @selected($month['index'] === $defaultFromIndex)>{{ $month['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 fiw-field">
                <label class="primary_input_label">Months <span class="text-danger">*</span></label>
                <select class="primary_select form-control" id="payMonths" name="pay_months">
                    @for($m = 1; $m <= $monthCount; $m++)
                        <option value="{{ $m }}" @selected($m === $defaultPayMonths)>{{ $m }} month{{ $m > 1 ? 's' : '' }}</option>
                    @endfor
                </select>
            </div>
        </div>
        <div class="fiw-full-year-line mt-10">
            <button type="button" class="primary-btn small fix-gr-bg" id="fiwFullYearBtn"
                data-month-count="{{ $monthCount }}">
                <i class="ti-calendar"></i> Full academic year ({{ $monthCount }} months)
            </button>
            <small class="text-muted d-block mt-5" id="fiwFullYearHint">Bills all {{ $monthCount }} months — {{ $monthsList[0]['label'] ?? '' }} through {{ $monthsList[$monthCount - 1]['label'] ?? '' }} ({{ $academicMeta['title'] ?? 'academic year' }}).</small>
        </div>
        <div class="fiw-due-line">
            <span class="fiw-due-text">First due date: <strong id="fiwDueDisplay">—</strong> <span class="text-muted">(auto)</span></span>
            <button type="button" class="fiw-due-adjust" id="fiwDueAdjustBtn">Adjust due day</button>
            <span class="fiw-due-day-wrap" id="fiwDueDayWrap" style="display:none">
                <label for="fiwDueDayInput">Day of month</label>
                <input type="number" class="primary_input_field form-control" id="fiwDueDayInput" min="1" max="28" value="{{ $defaultDueDayPref }}">
            </span>
            @if ($errors->has('due_date'))
                <span class="text-danger d-block">{{ $errors->first('due_date') }}</span>
            @endif
        </div>
    </details>

    <div class="fiw-quick-summary" id="feeQuickSummary">
        <span id="feeSummaryLine">Search or select student and unit to see total</span>
    </div>

    <input type="hidden" name="due_date" id="due_date" value="">
    <input type="hidden" id="scheduleToIndex" name="schedule_to_index" value="{{ $monthCount }}">
    <input type="hidden" id="feeDueDay" name="fee_due_day" value="{{ $defaultDueDayPref }}">
    <input type="hidden" name="create_date" id="create_date" value="{{ todayForInput() }}">
    <input type="hidden" name="payment_status" id="paymentStatus" value="not">
    <input type="hidden" name="monthly_total_amount" id="monthly_total_amount" value="0">
    <input type="hidden" name="preset_unit_id" id="preset_unit_id" value="">
    <input type="hidden" name="preset_service_line" id="preset_service_line" value="">
    <input type="hidden" name="fees_type" value="preset">

    @include('fees::feesInvoice._fiwCollectPanel')

    <script type="application/json" id="academicYearMonthsData">@json($academicMeta)</script>
    <script type="application/json" id="feeDateInputPattern">@json(systemDateFormatPattern())</script>
    <script type="application/json" id="fiwInvoicePrefs">@json($prefs)</script>
</div>
@endif
