@extends('backEnd.master')
@section('title')
@lang('kitchen.all_utilizations')
@endsection
@section('mainContent')
@include('backEnd.inventory.kitchen.partials.kitchen_theme')
<section class="admin-visitor-area up_admin_visitor inv-kitchen-page">
    <div class="container-fluid p-0">
        <div class="inv-kitchen-hero">
            <span class="inv-kitchen-hero__icon"><span class="ti-calendar"></span></span>
            <h1>@lang('kitchen.all_utilizations')</h1>
            <p>@lang('kitchen.all_utilizations_hint')</p>
        </div>

        @include('backEnd.inventory.kitchen.partials.kitchen_nav', ['activeTab' => 'utilization_list'])

        {{-- Filters --}}
        <form method="get" class="inv-kit-card mb-20">
            <div class="inv-kit-card__head">
                <span class="ti-filter" style="color:var(--kit-warm);font-size:1.1rem;"></span>
                <h3>@lang('common.filter')</h3>
            </div>
            <div class="inv-kit-card__body">
                <div class="row">
                    @if($unitsEnabled && $units->isNotEmpty())
                    <div class="col-lg-2 col-md-4 mb-10">
                        <label class="form-control-label" style="font-size:0.8rem;font-weight:600;color:#78716c;">@lang('kitchen.unit')</label>
                        <select name="unit_id" class="primary_select form-control">
                            <option value="">@lang('kitchen.all_units')</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}" @selected(($filters['unit_id'] ?? '') == $unit->id)>{{ $unit->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div class="col-lg-2 col-md-4 mb-10">
                        <label class="form-control-label" style="font-size:0.8rem;font-weight:600;color:#78716c;">@lang('kitchen.meal_service')</label>
                        <select name="meal_service" class="primary_select form-control">
                            <option value="">@lang('common.all')</option>
                            @foreach($mealServices as $key => $label)
                                <option value="{{ $key }}" @selected(($filters['meal_service'] ?? '') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4 mb-10">
                        <label class="form-control-label" style="font-size:0.8rem;font-weight:600;color:#78716c;">@lang('kitchen.from_date')</label>
                        <input type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}">
                    </div>
                    <div class="col-lg-2 col-md-4 mb-10">
                        <label class="form-control-label" style="font-size:0.8rem;font-weight:600;color:#78716c;">@lang('kitchen.to_date')</label>
                        <input type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] ?? '' }}">
                    </div>
                    <div class="col-lg-2 col-md-4 mb-10 d-flex align-items-end" style="gap:8px;">
                        <button type="submit" class="primary-btn fix-gr-bg" style="padding:10px 18px;font-size:0.875rem;">
                            <span class="ti-search mr-1"></span>@lang('common.filter')
                        </button>
                        <a href="{{ route('inv-kitchen-utilization-index') }}" class="primary-btn tr-bg" style="padding:10px 18px;font-size:0.875rem;">
                            @lang('common.reset')
                        </a>
                    </div>
                </div>
            </div>
        </form>

        {{-- Table --}}
        <div class="inv-kit-card">
            <div class="inv-kit-card__head" style="justify-content:space-between;">
                <div class="d-flex align-items-center" style="gap:14px;">
                    <span class="ti-list" style="color:var(--kit-warm);font-size:1.2rem;"></span>
                    <h3>@lang('kitchen.utilization_list') <span style="color:#a8a29e;font-weight:400;font-size:0.9rem;">({{ $utilizations->total() }})</span></h3>
                </div>
                <a href="{{ route('inv-kitchen-utilization-create') }}" class="primary-btn fix-gr-bg" style="font-size:0.875rem;">
                    <span class="ti-plus mr-1"></span>@lang('kitchen.new_utilization')
                </a>
            </div>
            <div class="inv-kit-card__body p-0">
                @if($utilizations->isEmpty())
                    <div class="inv-kit-empty">
                        <span class="ti-calendar"></span>
                        <p>@lang('kitchen.no_activity')</p>
                    </div>
                @else
                <div class="table-responsive">
                    <table class="table inv-kit-table mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>@lang('inventory.reference_no')</th>
                                <th>@lang('kitchen.dish_name')</th>
                                @if($unitsEnabled)<th>@lang('kitchen.unit')</th>@endif
                                <th>@lang('kitchen.meal_service')</th>
                                <th>@lang('kitchen.for_how_many')</th>
                                <th>@lang('kitchen.approved_by')</th>
                                <th>@lang('kitchen.date_time')</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($utilizations as $row)
                            <tr>
                                <td style="color:#a8a29e;font-size:0.8rem;">{{ $loop->iteration + ($utilizations->currentPage() - 1) * $utilizations->perPage() }}</td>
                                <td><strong>{{ $row->reference_no }}</strong></td>
                                <td>{{ $row->displayTitle() }}</td>
                                @if($unitsEnabled)<td>{{ $row->unit?->name ?? '—' }}</td>@endif
                                <td><span class="inv-kit-badge-meal">{{ $mealServices[$row->meal_service] ?? $row->meal_service }}</span></td>
                                <td>{{ $row->headcount }}</td>
                                <td>{{ $row->approvedByStaff?->full_name ?? '—' }}</td>
                                <td style="white-space:nowrap;">{{ $row->utilization_at?->format('d M Y H:i') }}</td>
                                <td>
                                    <a href="{{ route('inv-kitchen-utilization-show', $row->id) }}" class="primary-btn small fix-gr-bg">@lang('common.view')</a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-20">
                    {{ $utilizations->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>
</section>
@endsection
