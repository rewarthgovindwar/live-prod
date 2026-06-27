@extends('backEnd.master')
@section('title')
@lang('kitchen.quick_issue')
@endsection
@section('mainContent')
@include('backEnd.inventory.kitchen.partials.kitchen_theme')
<section class="admin-visitor-area up_admin_visitor inv-kitchen-page">
    <div class="container-fluid p-0">
        <div class="inv-kitchen-hero">
            <span class="inv-kitchen-hero__icon"><span class="ti-bolt"></span></span>
            <h1>@lang('kitchen.quick_issue')</h1>
            <p>@lang('kitchen.quick_issue_hint')</p>
        </div>
        @include('backEnd.inventory.kitchen.partials.kitchen_nav', ['activeTab' => 'quick_issue'])

        {{ html()->form('POST', route('inv-kitchen-quick-issue-store'))->id('kit-quick-form')->open() }}
        <div class="row mb-20">
            <div class="col-lg-3 col-md-6 mb-10">
                <label>@lang('kitchen.store') *</label>
                <select name="store_id" class="primary_select form-control" required>
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                    @endforeach
                </select>
            </div>
            @if($unitsEnabled)
            <div class="col-lg-3 col-md-6 mb-10">
                <label>@lang('kitchen.location') *</label>
                <select name="unit_id" class="primary_select form-control" required>
                    @foreach($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-lg-2 col-md-4 mb-10">
                <label>@lang('kitchen.meal_service') *</label>
                <select name="meal_service" class="primary_select form-control" required>
                    @foreach($mealServices as $key => $label)
                        <option value="{{ $key }}" @selected($defaultMeal === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-4 mb-10">
                <label>@lang('kitchen.for_how_many') *</label>
                <input type="number" name="headcount" class="form-control" value="100" min="1" required>
            </div>
            <div class="col-lg-2 col-md-4 mb-10">
                <label>@lang('kitchen.approved_by') *</label>
                <select name="approved_by_staff_id" class="primary_select form-control" required>
                    @foreach($staffs as $staff)
                        <option value="{{ $staff->id }}">{{ $staff->full_name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="inv-kit-card mb-20">
            <div class="inv-kit-card__head"><h3>@lang('kitchen.select_items')</h3></div>
            <div class="inv-kit-card__body p-0">
                <div class="table-responsive">
                    <table class="table inv-kit-table mb-0">
                        <thead>
                            <tr>
                                <th style="width:40px;"></th>
                                <th>@lang('inventory.item_name')</th>
                                <th>@lang('kitchen.current_stock')</th>
                                <th>@lang('kitchen.qty')</th>
                                <th>@lang('kitchen.uom')</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($items as $i => $item)
                            <tr>
                                <td><input type="checkbox" class="kit-item-check" data-row="{{ $i }}"></td>
                                <td>{{ $item->item_name }}</td>
                                <td class="kit-stock" data-item="{{ $item->id }}">{{ number_format($item->total_in_stock, 2) }}</td>
                                <td>
                                    <input type="hidden" name="item_id[]" value="{{ $item->id }}" class="kit-item-id" data-row="{{ $i }}" disabled>
                                    <input type="number" step="0.001" min="0" name="quantity[]" class="form-control form-control-sm kit-qty" data-row="{{ $i }}" disabled style="max-width:120px;">
                                </td>
                                <td>
                                    <select name="uom[]" class="form-control form-control-sm kit-uom" data-row="{{ $i }}" disabled style="max-width:100px;">
                                        @foreach($uomOptions as $uom)
                                            <option value="{{ $uom }}">{{ $uom }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="d-flex" style="gap:10px;">
            <button type="submit" class="primary-btn fix-gr-bg"><span class="ti-check mr-1"></span>@lang('kitchen.process_issue')</button>
            <a href="{{ route('inv-kitchen') }}" class="primary-btn tr-bg">@lang('common.cancel')</a>
        </div>
        {{ html()->form()->close() }}
    </div>
</section>
@endsection
@push('script')
<script>
document.querySelectorAll('.kit-item-check').forEach(function(cb) {
    cb.addEventListener('change', function() {
        var row = this.dataset.row;
        var on = this.checked;
        document.querySelectorAll('[data-row="'+row+'"]').forEach(function(el) {
            if (el.classList.contains('kit-item-id') || el.classList.contains('kit-qty') || el.classList.contains('kit-uom')) {
                el.disabled = !on;
            }
        });
        if (on) {
            var qty = document.querySelector('.kit-qty[data-row="'+row+'"]');
            if (qty && !qty.value) qty.value = '1';
        }
    });
});
</script>
@endpush
