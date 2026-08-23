@extends('backEnd.master')
@section('title')
@lang('kitchen.report_low_stock')
@endsection
@section('mainContent')
@include('backEnd.inventory.kitchen.partials.kitchen_theme')
<section class="admin-visitor-area up_admin_visitor inv-kitchen-page">
    <div class="container-fluid p-0">
        <div class="inv-kitchen-hero" style="background: linear-gradient(135deg, #2d0a0a 0%, #5a1a1a 45%, #7c2c2c 100%);">
            <span class="inv-kitchen-hero__icon" style="background:rgba(185,28,28,0.2);border-color:rgba(185,28,28,0.4);color:#fca5a5;">
                <span class="ti-alert"></span>
            </span>
            <h1>@lang('kitchen.report_low_stock')</h1>
            <p>@lang('kitchen.report_low_stock_hint', ['threshold' => $threshold])</p>
        </div>

        @include('backEnd.inventory.kitchen.partials.kitchen_nav', ['activeTab' => 'report_low_stock'])

        @if($items->isEmpty())
            <div class="inv-kit-card">
                <div class="inv-kit-empty">
                    <span class="ti-check" style="color:var(--kit-success);opacity:1;"></span>
                    <p style="color:var(--kit-success);font-weight:600;">@lang('kitchen.all_stock_ok', ['threshold' => $threshold])</p>
                </div>
            </div>
        @else

        <div class="d-flex justify-content-between align-items-center mb-15" style="flex-wrap:wrap;gap:8px;">
            <p class="mb-0" style="font-size:0.9rem;color:#b91c1c;font-weight:600;">
                <span class="ti-alert mr-1"></span>
                @lang('kitchen.low_stock_count', ['count' => $items->count(), 'threshold' => $threshold])
            </p>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="{{ route('inv-purchase-create') }}" class="primary-btn fix-gr-bg" style="font-size:0.875rem;">
                    <span class="ti-shopping-cart mr-1"></span>@lang('kitchen.create_purchase')
                </a>
                <button type="button" onclick="window.print()" class="primary-btn tr-bg" style="font-size:0.875rem;padding:10px 18px;">
                    <span class="ti-printer mr-1"></span>@lang('common.print')
                </button>
            </div>
        </div>

        <div class="inv-kit-card">
            <div class="inv-kit-card__head">
                <span class="ti-alert" style="color:var(--kit-danger);font-size:1.2rem;"></span>
                <h3>@lang('kitchen.items_below_threshold')</h3>
            </div>
            <div class="inv-kit-card__body p-0">
                <div class="table-responsive">
                    <table class="table inv-kit-table mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>@lang('inventory.item_name')</th>
                                <th>@lang('inventory.item_no')</th>
                                <th>@lang('kitchen.current_stock')</th>
                                <th>@lang('kitchen.threshold')</th>
                                <th>@lang('kitchen.deficit')</th>
                                <th>@lang('kitchen.last_received')</th>
                                <th>@lang('kitchen.last_used')</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $i => $item)
                            @php
                                $inbound = $lastInbound->get($item->id);
                                $usage   = $lastUsage->get($item->id);
                                $deficit = max(0, $threshold - (float)$item->total_in_stock);
                            @endphp
                            <tr>
                                <td style="color:#a8a29e;font-size:0.8rem;">{{ $i + 1 }}</td>
                                <td><strong>{{ $item->item_name }}</strong></td>
                                <td style="color:#78716c;">{{ $item->item_no ?? '—' }}</td>
                                <td>
                                    <span class="{{ $item->total_in_stock <= 0 ? 'inv-kit-stock-warn' : '' }}"
                                          style="{{ $item->total_in_stock > 0 ? 'color:var(--kit-danger);font-weight:600;' : '' }}">
                                        {{ number_format($item->total_in_stock, 2) }}
                                    </span>
                                </td>
                                <td>{{ number_format($threshold, 2) }}</td>
                                <td style="color:var(--kit-danger);font-weight:600;">
                                    {{ number_format($deficit, 2) }}
                                </td>
                                <td style="font-size:0.82rem;color:#78716c;white-space:nowrap;">
                                    @if($inbound)
                                        {{ \Carbon\Carbon::parse($inbound->last_in_date)->format('d M Y') }}
                                    @else
                                        <span style="color:#d1cdc7;">@lang('kitchen.never')</span>
                                    @endif
                                </td>
                                <td style="font-size:0.82rem;color:#78716c;white-space:nowrap;">
                                    @if($usage)
                                        {{ \Carbon\Carbon::parse($usage->last_out_date)->format('d M Y') }}
                                    @else
                                        <span style="color:#d1cdc7;">—</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('inv-purchase-create') }}?item_id={{ $item->id }}" class="primary-btn small fix-gr-bg">
                                        @lang('kitchen.reorder')
                                    </a>
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
