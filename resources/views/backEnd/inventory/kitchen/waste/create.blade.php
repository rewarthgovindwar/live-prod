@extends('backEnd.master')
@section('title')
@lang('kitchen.log_waste')
@endsection
@section('mainContent')
@include('backEnd.inventory.kitchen.partials.kitchen_theme')
<section class="admin-visitor-area up_admin_visitor inv-kitchen-page">
    <div class="container-fluid p-0">
        <div class="inv-kitchen-hero" style="background:linear-gradient(135deg,#2d0a0a,#5a1a1a 45%,#7c2c2c);">
            <span class="inv-kitchen-hero__icon"><span class="ti-trash"></span></span>
            <h1>@lang('kitchen.log_waste')</h1>
            <p>@lang('kitchen.log_waste_hint')</p>
        </div>
        @include('backEnd.inventory.kitchen.partials.kitchen_nav', ['activeTab' => 'waste'])

        <div class="row">
            <div class="col-lg-6">
                {{ html()->form('POST', route('inv-kitchen-waste-store'))->open() }}
                <div class="inv-kit-card">
                    <div class="inv-kit-card__body">
                        <div class="mb-15">
                            <label>@lang('inventory.item_name') *</label>
                            <select name="item_id" class="primary_select form-control" required>
                                @foreach($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->item_name }} ({{ number_format($item->total_in_stock, 2) }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-15">
                            <label>@lang('kitchen.qty') *</label>
                            <input type="number" step="0.001" min="0.001" name="quantity" class="form-control" required>
                        </div>
                        <div class="mb-15">
                            <label>@lang('kitchen.waste_reason') *</label>
                            <select name="reason" class="primary_select form-control" required>
                                @foreach($wasteReasons as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if($unitsEnabled)
                        <div class="mb-15">
                            <label>@lang('kitchen.location')</label>
                            <select name="unit_id" class="primary_select form-control">
                                <option value="">—</option>
                                @foreach($units as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                        <div class="mb-15">
                            <label>@lang('kitchen.approved_by')</label>
                            <select name="approved_by_staff_id" class="primary_select form-control">
                                <option value="">—</option>
                                @foreach($staffs as $staff)
                                    <option value="{{ $staff->id }}">{{ $staff->full_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-15">
                            <label>@lang('kitchen.notes')</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                        <button type="submit" class="primary-btn fix-gr-bg">@lang('kitchen.save_waste')</button>
                        <a href="{{ route('inv-kitchen') }}" class="primary-btn tr-bg ml-2">@lang('common.cancel')</a>
                    </div>
                </div>
                {{ html()->form()->close() }}
            </div>
        </div>
    </div>
</section>
@endsection
