@extends('backEnd.master')
@section('title')
@lang('kitchen.report_monthly')
@endsection
@section('mainContent')
@include('backEnd.inventory.kitchen.partials.kitchen_theme')
@php
    $monthNames = ['', 'January','February','March','April','May','June','July','August','September','October','November','December'];
@endphp
<section class="admin-visitor-area up_admin_visitor inv-kitchen-page">
    <div class="container-fluid p-0">
        <div class="inv-kitchen-hero">
            <span class="inv-kitchen-hero__icon"><span class="ti-calendar"></span></span>
            <h1>@lang('kitchen.report_monthly')</h1>
            <p>@lang('kitchen.report_monthly_hint')</p>
        </div>

        @include('backEnd.inventory.kitchen.partials.kitchen_nav', ['activeTab' => 'report_monthly'])

        {{-- Filters --}}
        <form method="get" class="inv-kit-card mb-20">
            <div class="inv-kit-card__body">
                <div class="row align-items-end">
                    <div class="col-lg-2 col-md-4 mb-10">
                        <label class="form-control-label" style="font-size:0.8rem;font-weight:600;color:#78716c;">@lang('kitchen.year')</label>
                        <select name="year" class="primary_select form-control">
                            @for($y = now()->year; $y >= now()->year - 4; $y--)
                                <option value="{{ $y }}" @selected($year == $y)>{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4 mb-10">
                        <label class="form-control-label" style="font-size:0.8rem;font-weight:600;color:#78716c;">@lang('kitchen.month')</label>
                        <select name="month" class="primary_select form-control">
                            @foreach($monthNames as $m => $mName)
                                @if($m > 0)
                                <option value="{{ $m }}" @selected($month == $m)>{{ $mName }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    @if($unitsEnabled && $units->isNotEmpty())
                    <div class="col-lg-3 col-md-4 mb-10">
                        <label class="form-control-label" style="font-size:0.8rem;font-weight:600;color:#78716c;">@lang('kitchen.unit')</label>
                        <select name="unit_id" class="primary_select form-control">
                            <option value="">@lang('kitchen.all_units')</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}" @selected($selectedUnitId == $unit->id)>{{ $unit->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div class="col-lg-2 col-md-4 mb-10 d-flex align-items-end" style="gap:8px;">
                        <button type="submit" class="primary-btn fix-gr-bg" style="padding:10px 18px;">
                            <span class="ti-search mr-1"></span>@lang('common.view')
                        </button>
                        <button type="button" onclick="window.print()" class="primary-btn tr-bg" style="padding:10px 18px;">
                            <span class="ti-printer"></span>
                        </button>
                    </div>
                </div>
            </div>
        </form>

        {{-- Monthly totals --}}
        <div class="row mb-20">
            <div class="col-lg-3 col-md-6 mb-15">
                <div class="inv-kit-stat">
                    <div class="inv-kit-stat__icon inv-kit-stat__icon--gold"><span class="ti-calendar"></span></div>
                    <div class="inv-kit-stat__content">
                        <div class="inv-kit-stat__value">{{ $monthlyTotals['services'] }}</div>
                        <div class="inv-kit-stat__label">@lang('kitchen.total_services')</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-15">
                <div class="inv-kit-stat">
                    <div class="inv-kit-stat__icon inv-kit-stat__icon--blue"><span class="ti-user"></span></div>
                    <div class="inv-kit-stat__content">
                        <div class="inv-kit-stat__value">{{ number_format($monthlyTotals['headcount']) }}</div>
                        <div class="inv-kit-stat__label">@lang('kitchen.total_served')</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-15">
                <div class="inv-kit-stat">
                    <div class="inv-kit-stat__icon inv-kit-stat__icon--green"><span class="ti-package"></span></div>
                    <div class="inv-kit-stat__content">
                        <div class="inv-kit-stat__value">{{ $monthlyTotals['items_used'] }}</div>
                        <div class="inv-kit-stat__label">@lang('kitchen.unique_items')</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-15">
                <div class="inv-kit-stat">
                    <div class="inv-kit-stat__icon inv-kit-stat__icon--red"><span class="ti-alert"></span></div>
                    <div class="inv-kit-stat__content">
                        <div class="inv-kit-stat__value inv-kit-stat__value--danger">{{ $itemSummary->sum('alert_count') }}</div>
                        <div class="inv-kit-stat__label">@lang('kitchen.low_stock_alerts')</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            {{-- Item consumption table --}}
            <div class="col-lg-8 mb-20">
                <div class="inv-kit-card">
                    <div class="inv-kit-card__head">
                        <span class="ti-package" style="color:var(--kit-warm);font-size:1.2rem;"></span>
                        <h3>@lang('kitchen.monthly_consumption') — {{ $monthNames[$month] }} {{ $year }}</h3>
                    </div>
                    @if($itemSummary->isEmpty())
                        <div class="inv-kit-empty">
                            <span class="ti-package"></span>
                            <p>@lang('kitchen.no_data_month')</p>
                        </div>
                    @else
                    <div class="inv-kit-card__body p-0">
                        <div class="table-responsive">
                            <table class="table inv-kit-table mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>@lang('inventory.item_name')</th>
                                        <th>@lang('kitchen.total_used')</th>
                                        <th>@lang('kitchen.uom')</th>
                                        <th>@lang('kitchen.low_stock_alerts')</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($itemSummary as $i => $item)
                                    <tr>
                                        <td style="color:#a8a29e;font-size:0.8rem;">{{ $i + 1 }}</td>
                                        <td><strong>{{ $item->item_name }}</strong></td>
                                        <td>{{ number_format($item->total_used, 2) }}</td>
                                        <td>{{ $item->uom ?? '—' }}</td>
                                        <td>
                                            @if($item->alert_count > 0)
                                                <span class="inv-kit-low-pill">
                                                    <span class="ti-alert"></span>{{ $item->alert_count }}
                                                </span>
                                            @else
                                                <span style="color:var(--kit-success);font-size:0.8rem;">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Meal service breakdown + daily headcount --}}
            <div class="col-lg-4 mb-20">
                <div class="inv-kit-card mb-20">
                    <div class="inv-kit-card__head">
                        <span class="ti-pie-chart" style="color:var(--kit-warm);font-size:1.2rem;"></span>
                        <h3>@lang('kitchen.meal_breakdown')</h3>
                    </div>
                    <div class="inv-kit-card__body">
                        @php $totalServices = $mealBreakdown->sum('cnt'); @endphp
                        @forelse($mealServices as $key => $label)
                            @php $row = $mealBreakdown->get($key); $cnt = $row ? $row->cnt : 0; $pct = $totalServices > 0 ? round($cnt / $totalServices * 100) : 0; @endphp
                            <div class="inv-kit-meal-bar">
                                <div class="inv-kit-meal-bar__label">
                                    <span>{{ $label }}</span>
                                    <strong>{{ $cnt }} <span style="font-weight:400;color:#a8a29e;font-size:0.8rem;">({{ $pct }}%)</span></strong>
                                </div>
                                <div class="inv-kit-meal-bar__track">
                                    <div class="inv-kit-meal-bar__fill" style="width:{{ $pct }}%;"></div>
                                </div>
                            </div>
                        @empty
                        @endforelse
                    </div>
                </div>

                <div class="inv-kit-card">
                    <div class="inv-kit-card__head">
                        <span class="ti-calendar" style="color:var(--kit-warm);font-size:1.2rem;"></span>
                        <h3>@lang('kitchen.daily_headcount')</h3>
                    </div>
                    @if($dailyHeadcount->isEmpty())
                        <div class="inv-kit-empty" style="padding:24px;">
                            <p>@lang('kitchen.no_activity')</p>
                        </div>
                    @else
                    <div class="inv-kit-card__body p-0">
                        <div style="max-height:300px;overflow-y:auto;">
                            <table class="table inv-kit-table mb-0">
                                <thead>
                                    <tr>
                                        <th>@lang('kitchen.date')</th>
                                        <th>@lang('kitchen.services')</th>
                                        <th>@lang('kitchen.headcount')</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($dailyHeadcount as $day)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($day->date)->format('d M') }}</td>
                                        <td>{{ $day->service_count }}</td>
                                        <td><strong>{{ $day->total_headcount }}</strong></td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
