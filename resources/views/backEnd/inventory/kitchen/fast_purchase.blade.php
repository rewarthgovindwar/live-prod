@extends('backEnd.master')
@section('title')
@lang('kitchen.fast_purchase')
@endsection
@section('mainContent')
@include('backEnd.inventory.kitchen.partials.kitchen_theme')
<section class="admin-visitor-area up_admin_visitor inv-kitchen-page">
    <div class="container-fluid p-0">
        <div class="inv-kitchen-hero">
            <span class="inv-kitchen-hero__icon"><span class="ti-shopping-cart"></span></span>
            <h1>@lang('kitchen.fast_purchase')</h1>
            <p>@lang('kitchen.fast_purchase_hint', ['max' => number_format($maxAmount)])</p>
        </div>
        @include('backEnd.inventory.kitchen.partials.kitchen_nav', ['activeTab' => 'fast_purchase'])

        {{ html()->form('POST', route('inv-kitchen-fast-purchase-store'))->open() }}
        <div class="row mb-15">
            <div class="col-lg-6">
                <label>@lang('kitchen.purchase_title') *</label>
                <input type="text" name="title" class="form-control" value="{{ old('title', 'Kitchen restock — '.date('d M Y')) }}" required>
            </div>
            @if($unitsEnabled)
            <div class="col-lg-3">
                <label>@lang('kitchen.location')</label>
                <select name="unit_id" class="primary_select form-control">
                    @foreach($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-lg-3">
                <label>@lang('kitchen.store')</label>
                <select name="store_id" class="primary_select form-control">
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="inv-kit-card mb-20">
            <div class="inv-kit-card__head"><h3>@lang('kitchen.items_to_order')</h3></div>
            <div class="inv-kit-card__body p-0">
                <table class="table inv-kit-table mb-0">
                    <thead>
                        <tr>
                            <th></th>
                            <th>@lang('inventory.item_name')</th>
                            <th>@lang('kitchen.current_stock')</th>
                            <th>@lang('kitchen.order_qty')</th>
                            <th>@lang('kitchen.uom')</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($items as $i => $item)
                        @php $suggest = max((float)($item->reorder_qty ?? 0), max(0, (float)($item->reorder_level ?? 0) - (float)$item->total_in_stock)); @endphp
                        <tr>
                            <td><input type="checkbox" class="fp-check" data-row="{{ $i }}"></td>
                            <td>{{ $item->item_name }}</td>
                            <td>{{ number_format($item->total_in_stock, 2) }}</td>
                            <td>
                                <input type="hidden" name="item_id[]" value="{{ $item->id }}" class="fp-item" data-row="{{ $i }}" disabled>
                                <input type="number" step="0.001" min="0" name="quantity[]" class="form-control form-control-sm fp-qty" data-row="{{ $i }}" value="{{ $suggest > 0 ? $suggest : '' }}" disabled style="max-width:120px;">
                            </td>
                            <td><input type="text" name="uom[]" class="form-control form-control-sm fp-uom" data-row="{{ $i }}" value="kg" disabled style="max-width:80px;"></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <button type="submit" class="primary-btn fix-gr-bg"><span class="ti-check mr-1"></span>@lang('kitchen.submit_fast_purchase')</button>
        {{ html()->form()->close() }}
    </div>
</section>
@endsection
@push('script')
<script>
document.querySelectorAll('.fp-check').forEach(function(cb) {
    cb.addEventListener('change', function() {
        var row = this.dataset.row, on = this.checked;
        ['fp-item','fp-qty','fp-uom'].forEach(function(cls) {
            var el = document.querySelector('.'+cls+'[data-row="'+row+'"]');
            if (el) el.disabled = !on;
        });
    });
});
</script>
@endpush
