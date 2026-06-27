@extends('backEnd.master')
@section('title')
@lang('kitchen.kitchen_inventory')
@endsection
@section('mainContent')
@include('backEnd.inventory.kitchen.partials.kitchen_theme')
<section class="admin-visitor-area up_admin_visitor inv-kitchen-page">
    <div class="container-fluid p-0">
        <div class="inv-kitchen-hero">
            <span class="inv-kitchen-hero__icon"><span class="ti-cut"></span></span>
            <h1>@lang('kitchen.today_kitchen')</h1>
            <p>@lang('kitchen.today_kitchen_hint')</p>
        </div>

        @include('backEnd.inventory.kitchen.partials.kitchen_nav', ['activeTab' => 'inventory'])

        @if(($health['score'] ?? 100) < 80)
        <div class="inv-kit-alert inv-kit-alert--warn mb-20" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
            <div>
                <strong>@lang('kitchen.setup_needed')</strong>
                <span style="font-size:0.875rem;"> — @lang('kitchen.setup_needed_hint', ['score' => $health['score'], 'completed' => $health['completed'], 'total' => $health['total']])</span>
            </div>
            <a href="{{ route('inv-kitchen-setup') }}" class="primary-btn fix-gr-bg small">@lang('kitchen.open_setup_wizard')</a>
        </div>
        @endif

        <div class="inv-kit-toolbar">
            <div class="d-flex flex-wrap" style="gap:8px;">
                <a href="{{ route('inv-kitchen-quick-issue') }}" class="primary-btn fix-gr-bg">
                    <span class="ti-bolt mr-1"></span>@lang('kitchen.quick_issue')
                </a>
                <a href="{{ route('inv-kitchen-utilization-create') }}" class="primary-btn tr-bg">
                    <span class="ti-plus mr-1"></span>@lang('kitchen.log_utilization')
                </a>
                <form method="post" action="{{ route('inv-kitchen-repeat-last') }}" class="d-inline">
                    @csrf
                    @if($unitsEnabled && $selectedUnitId)
                        <input type="hidden" name="unit_id" value="{{ $selectedUnitId }}">
                    @endif
                    <button type="submit" class="primary-btn tr-bg" @if(!$today['last_utilization']) disabled @endif>
                        <span class="ti-reload mr-1"></span>@lang('kitchen.repeat_last')
                    </button>
                </form>
                <a href="{{ route('inv-kitchen-fast-purchase') }}" class="primary-btn tr-bg">
                    <span class="ti-shopping-cart mr-1"></span>@lang('kitchen.fast_purchase')
                </a>
                <a href="{{ route('inv-kitchen-waste') }}" class="primary-btn tr-bg">
                    <span class="ti-trash mr-1"></span>@lang('kitchen.log_waste')
                </a>
            </div>
            @if($unitsEnabled && $units->isNotEmpty())
            <form method="get" class="d-flex align-items-center" style="gap:8px;">
                <label class="mb-0 text-muted" style="font-size:13px;">@lang('kitchen.filter_by_location')</label>
                <select name="unit_id" class="primary_select form-control" style="min-width:200px;" onchange="this.form.submit()">
                    <option value="">@lang('kitchen.all_locations')</option>
                    @foreach($units as $unit)
                        <option value="{{ $unit->id }}" @selected($selectedUnitId == $unit->id)>{{ $unit->name }}</option>
                    @endforeach
                </select>
            </form>
            @endif
        </div>

        {{-- Today stats --}}
        <div class="row mb-20">
            <div class="col-lg-2 col-md-4 col-6 mb-15">
                <div class="inv-kit-stat">
                    <div class="inv-kit-stat__icon inv-kit-stat__icon--gold"><span class="ti-time"></span></div>
                    <div class="inv-kit-stat__content">
                        <div class="inv-kit-stat__value">{{ $today['today_services'] }}</div>
                        <div class="inv-kit-stat__label">@lang('kitchen.services_today')</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6 mb-15">
                <div class="inv-kit-stat">
                    <div class="inv-kit-stat__icon inv-kit-stat__icon--green"><span class="ti-user"></span></div>
                    <div class="inv-kit-stat__content">
                        <div class="inv-kit-stat__value">{{ number_format($today['today_headcount']) }}</div>
                        <div class="inv-kit-stat__label">@lang('kitchen.served_today')</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6 mb-15">
                <div class="inv-kit-stat">
                    <div class="inv-kit-stat__icon inv-kit-stat__icon--red"><span class="ti-alert"></span></div>
                    <div class="inv-kit-stat__content">
                        <div class="inv-kit-stat__value inv-kit-stat__value--danger">{{ $today['low_stock_count'] }}</div>
                        <div class="inv-kit-stat__label">@lang('kitchen.low_stock')</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6 mb-15">
                <div class="inv-kit-stat">
                    <div class="inv-kit-stat__icon inv-kit-stat__icon--red"><span class="ti-na"></span></div>
                    <div class="inv-kit-stat__content">
                        <div class="inv-kit-stat__value inv-kit-stat__value--danger">{{ $today['critical_stock_count'] }}</div>
                        <div class="inv-kit-stat__label">@lang('kitchen.out_of_stock')</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6 mb-15">
                <div class="inv-kit-stat">
                    <div class="inv-kit-stat__icon inv-kit-stat__icon--blue"><span class="ti-shopping-cart"></span></div>
                    <div class="inv-kit-stat__content">
                        <div class="inv-kit-stat__value">{{ $today['pending_purchases'] }}</div>
                        <div class="inv-kit-stat__label">@lang('kitchen.pending_purchases')</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6 mb-15">
                <div class="inv-kit-stat">
                    <div class="inv-kit-stat__icon inv-kit-stat__icon--blue"><span class="ti-calendar"></span></div>
                    <div class="inv-kit-stat__content">
                        <div class="inv-kit-stat__value" style="font-size:1rem;">{{ $today['next_meal'] ?? '—' }}</div>
                        <div class="inv-kit-stat__label">@lang('kitchen.next_meal')</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8 mb-20">
                <div class="inv-kit-card">
                    <div class="inv-kit-card__head">
                        <span class="ti-time" style="color:var(--kit-warm);font-size:1.2rem;"></span>
                        <h3>@lang('kitchen.recent_activity')</h3>
                    </div>
                    <div class="inv-kit-card__body p-0">
                        @if($recent->isEmpty())
                            <div class="inv-kit-empty">
                                <span class="ti-clipboard"></span>
                                <p class="mb-15">@lang('kitchen.no_activity')</p>
                                <a href="{{ route('inv-kitchen-quick-issue') }}" class="primary-btn fix-gr-bg mr-2"><span class="ti-bolt mr-1"></span>@lang('kitchen.quick_issue')</a>
                                <a href="{{ route('inv-kitchen-setup') }}" class="primary-btn tr-bg">@lang('kitchen.setup_wizard')</a>
                            </div>
                        @else
                        <div class="table-responsive">
                            <table class="table inv-kit-table mb-0">
                                <thead>
                                    <tr>
                                        <th>@lang('inventory.reference_no')</th>
                                        <th>@lang('kitchen.dish_name')</th>
                                        <th>@lang('kitchen.meal_service')</th>
                                        <th>@lang('kitchen.for_how_many')</th>
                                        <th>@lang('kitchen.date_time')</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recent as $row)
                                    <tr>
                                        <td><strong>{{ $row->reference_no }}</strong></td>
                                        <td>{{ $row->displayTitle() }}</td>
                                        <td><span class="inv-kit-badge-meal">{{ $mealServices[$row->meal_service] ?? $row->meal_service }}</span></td>
                                        <td>{{ $row->headcount }}</td>
                                        <td>{{ $row->utilization_at?->format('d M Y H:i') }}</td>
                                        <td><a href="{{ route('inv-kitchen-utilization-show', $row->id) }}" class="primary-btn small fix-gr-bg">@lang('common.view')</a></td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                    </div>
                </div>

                @if($today['recent_movements']->isNotEmpty())
                <div class="inv-kit-card mt-20">
                    <div class="inv-kit-card__head"><span class="ti-exchange-vertical" style="color:var(--kit-warm);"></span><h3>@lang('kitchen.stock_movements')</h3></div>
                    <div class="inv-kit-card__body p-0">
                        <table class="table inv-kit-table mb-0">
                            <thead><tr><th>@lang('inventory.item_name')</th><th>@lang('kitchen.type')</th><th>@lang('kitchen.qty')</th><th>@lang('kitchen.date_time')</th></tr></thead>
                            <tbody>
                            @foreach($today['recent_movements'] as $mv)
                                <tr>
                                    <td>{{ $mv->item_name }}</td>
                                    <td><span style="color:{{ $mv->direction === 'in' ? 'var(--kit-success)' : 'var(--kit-danger)' }};">{{ $mv->movement_type }}</span></td>
                                    <td>{{ number_format($mv->quantity, 2) }}</td>
                                    <td>{{ \Carbon\Carbon::parse($mv->created_at)->format('d M H:i') }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif
            </div>

            <div class="col-lg-4 mb-20">
                @if($today['low_stock_items']->isNotEmpty())
                <div class="inv-kit-card mb-20">
                    <div class="inv-kit-card__head">
                        <span class="ti-alert" style="color:var(--kit-danger);"></span>
                        <h3>@lang('kitchen.low_stock_now')</h3>
                    </div>
                    <div class="inv-kit-card__body p-0">
                        <ul class="list-unstyled mb-0">
                            @foreach($today['low_stock_items'] as $item)
                            <li style="padding:10px 22px;border-bottom:1px solid rgba(201,162,39,0.08);display:flex;justify-content:space-between;">
                                <span>{{ $item->item_name }}</span>
                                <strong style="color:var(--kit-danger);">{{ number_format($item->total_in_stock, 2) }}</strong>
                            </li>
                            @endforeach
                        </ul>
                        <div class="p-15">
                            <a href="{{ route('inv-kitchen-report-low-stock') }}" class="primary-btn tr-bg w-100 small">@lang('kitchen.view_low_stock_report')</a>
                        </div>
                    </div>
                </div>
                @endif

                <div class="inv-kit-card mb-20">
                    <div class="inv-kit-card__head"><span class="ti-pie-chart" style="color:var(--kit-warm);"></span><h3>@lang('kitchen.meal_breakdown')</h3></div>
                    <div class="inv-kit-card__body">
                        @php $mealCounts = $stats['by_meal'] ?? []; $maxMeal = max(1, $mealCounts ? max($mealCounts) : 0); @endphp
                        @foreach($mealServices as $key => $label)
                            @php $count = $mealCounts[$key] ?? 0; @endphp
                            <div class="inv-kit-meal-bar">
                                <div class="inv-kit-meal-bar__label"><span>{{ $label }}</span><strong style="color:var(--kit-warm);">{{ $count }}</strong></div>
                                <div class="inv-kit-meal-bar__track"><div class="inv-kit-meal-bar__fill" style="width:{{ round(($count / $maxMeal) * 100) }}%;"></div></div>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if($lowStockAlerts->isNotEmpty())
                <div class="inv-kit-card">
                    <div class="inv-kit-card__head"><span class="ti-alert" style="color:var(--kit-danger);"></span><h3>@lang('kitchen.recent_low_stock')</h3></div>
                    <div class="inv-kit-card__body p-0">
                        <ul class="list-unstyled mb-0">
                            @foreach($lowStockAlerts as $alert)
                            <li style="padding:12px 22px;border-bottom:1px solid rgba(201,162,39,0.08);">
                                <strong>{{ $alert->item?->item_name ?? '—' }}</strong>
                                <div class="text-muted" style="font-size:12px;">{{ $alert->utilization?->reference_no }} · {{ $alert->stock_after }}</div>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</section>
@endsection
