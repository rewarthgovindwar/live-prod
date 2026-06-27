@extends('backEnd.master')
@section('title')
@lang('kitchen.report_daily')
@endsection
@section('mainContent')
@include('backEnd.inventory.kitchen.partials.kitchen_theme')
<section class="admin-visitor-area up_admin_visitor inv-kitchen-page">
    <div class="container-fluid p-0">
        <div class="inv-kitchen-hero">
            <span class="inv-kitchen-hero__icon"><span class="ti-bar-chart"></span></span>
            <h1>@lang('kitchen.report_daily')</h1>
            <p>@lang('kitchen.report_daily_hint')</p>
        </div>

        @include('backEnd.inventory.kitchen.partials.kitchen_nav', ['activeTab' => 'report_daily'])

        {{-- Filters --}}
        <form method="get" class="inv-kit-card mb-20">
            <div class="inv-kit-card__body">
                <div class="row align-items-end">
                    <div class="col-lg-3 col-md-4 mb-10">
                        <label class="form-control-label" style="font-size:0.8rem;font-weight:600;color:#78716c;">@lang('kitchen.select_date')</label>
                        <input type="date" name="date" class="form-control" value="{{ $date }}">
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
                    <div class="col-lg-2 col-md-4 mb-10">
                        <button type="submit" class="primary-btn fix-gr-bg w-100" style="padding:10px 18px;">
                            <span class="ti-search mr-1"></span>@lang('common.view')
                        </button>
                    </div>
                    @if($utilizations->isNotEmpty())
                    <div class="col-lg-2 col-md-4 mb-10">
                        <button type="button" onclick="window.print()" class="primary-btn tr-bg w-100" style="padding:10px 18px;">
                            <span class="ti-printer mr-1"></span>@lang('common.print')
                        </button>
                    </div>
                    @endif
                </div>
            </div>
        </form>

        @if($utilizations->isEmpty())
            <div class="inv-kit-card">
                <div class="inv-kit-empty">
                    <span class="ti-bar-chart"></span>
                    <p>@lang('kitchen.no_data_for_date', ['date' => \Carbon\Carbon::parse($date)->format('d M Y')])</p>
                </div>
            </div>
        @else

        {{-- Day summary stats --}}
        <div class="row mb-20">
            <div class="col-lg-3 col-md-6 mb-15">
                <div class="inv-kit-stat">
                    <div class="inv-kit-stat__icon inv-kit-stat__icon--gold"><span class="ti-calendar"></span></div>
                    <div class="inv-kit-stat__content">
                        <div class="inv-kit-stat__value">{{ $utilizations->count() }}</div>
                        <div class="inv-kit-stat__label">@lang('kitchen.services_today')</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-15">
                <div class="inv-kit-stat">
                    <div class="inv-kit-stat__icon inv-kit-stat__icon--blue"><span class="ti-user"></span></div>
                    <div class="inv-kit-stat__content">
                        <div class="inv-kit-stat__value">{{ number_format($totalHeadcount) }}</div>
                        <div class="inv-kit-stat__label">@lang('kitchen.total_served')</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-15">
                <div class="inv-kit-stat">
                    <div class="inv-kit-stat__icon inv-kit-stat__icon--green"><span class="ti-package"></span></div>
                    <div class="inv-kit-stat__content">
                        <div class="inv-kit-stat__value">{{ $itemSummary->count() }}</div>
                        <div class="inv-kit-stat__label">@lang('kitchen.items_consumed')</div>
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

        {{-- Service-by-service breakdown --}}
        <div class="inv-kit-card mb-20">
            <div class="inv-kit-card__head">
                <span class="ti-list" style="color:var(--kit-warm);font-size:1.2rem;"></span>
                <h3>@lang('kitchen.daily_services') — {{ \Carbon\Carbon::parse($date)->format('l, d M Y') }}</h3>
            </div>
            <div class="inv-kit-card__body p-0">
                <div class="table-responsive">
                    <table class="table inv-kit-table mb-0">
                        <thead>
                            <tr>
                                <th>@lang('inventory.reference_no')</th>
                                <th>@lang('kitchen.meal_service')</th>
                                <th>@lang('kitchen.dish_name')</th>
                                @if($unitsEnabled)<th>@lang('kitchen.unit')</th>@endif
                                <th>@lang('kitchen.store')</th>
                                <th>@lang('kitchen.for_how_many')</th>
                                <th>@lang('kitchen.served_to')</th>
                                <th>@lang('kitchen.time')</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($utilizations as $u)
                            <tr>
                                <td><strong>{{ $u->reference_no }}</strong></td>
                                <td><span class="inv-kit-badge-meal">{{ $mealServices[$u->meal_service] ?? $u->meal_service }}</span></td>
                                <td>{{ $u->displayTitle() }}</td>
                                @if($unitsEnabled)<td>{{ $u->unit?->name ?? '—' }}</td>@endif
                                <td>{{ $u->store?->store_name ?? '—' }}</td>
                                <td>{{ $u->headcount }}</td>
                                <td>{{ ucfirst($u->served_to) }}</td>
                                <td style="white-space:nowrap;">{{ $u->utilization_at?->format('H:i') }}</td>
                                <td>
                                    <a href="{{ route('inv-kitchen-utilization-show', $u->id) }}" class="primary-btn small fix-gr-bg">@lang('common.view')</a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Item consumption summary --}}
        <div class="inv-kit-card">
            <div class="inv-kit-card__head">
                <span class="ti-package" style="color:var(--kit-warm);font-size:1.2rem;"></span>
                <h3>@lang('kitchen.item_consumption_summary')</h3>
            </div>
            <div class="inv-kit-card__body p-0">
                <div class="table-responsive">
                    <table class="table inv-kit-table mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>@lang('inventory.item_name')</th>
                                <th>@lang('kitchen.total_used')</th>
                                <th>@lang('kitchen.uom')</th>
                                <th>@lang('kitchen.stock_after')</th>
                                <th>@lang('kitchen.status')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($itemSummary as $i => $item)
                            <tr>
                                <td style="color:#a8a29e;font-size:0.8rem;">{{ $i + 1 }}</td>
                                <td><strong>{{ $item->item_name }}</strong></td>
                                <td>{{ number_format($item->total_used, 2) }}</td>
                                <td>{{ $item->uom ?? '—' }}</td>
                                <td class="{{ $item->stock_after <= 0 ? 'inv-kit-stock-warn' : 'inv-kit-stock-ok' }}">
                                    {{ number_format($item->stock_after, 2) }}
                                </td>
                                <td>
                                    @if($item->alert_count > 0)
                                        <span class="inv-kit-low-pill">
                                            <span class="ti-alert"></span>@lang('kitchen.low_stock')
                                        </span>
                                    @else
                                        <span style="color:var(--kit-success);font-size:0.8rem;">✓ @lang('kitchen.ok')</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @endif
    </div>
</section>
@endsection
