@extends('backEnd.master')
@section('title')
@lang('kitchen.setup_wizard')
@endsection
@section('mainContent')
@include('backEnd.inventory.kitchen.partials.kitchen_theme')
<section class="admin-visitor-area up_admin_visitor inv-kitchen-page">
    <div class="container-fluid p-0">
        <div class="inv-kitchen-hero">
            <span class="inv-kitchen-hero__icon"><span class="ti-settings"></span></span>
            <h1>@lang('kitchen.setup_wizard')</h1>
            <p>@lang('kitchen.setup_wizard_hint')</p>
        </div>

        @include('backEnd.inventory.kitchen.partials.kitchen_nav', ['activeTab' => 'setup'])

        {{-- Health score --}}
        <div class="inv-kit-card mb-20">
            <div class="inv-kit-card__head" style="justify-content:space-between;">
                <div class="d-flex align-items-center" style="gap:14px;">
                    <span class="ti-check-box" style="color:var(--kit-warm);font-size:1.2rem;"></span>
                    <h3>@lang('kitchen.data_health') — {{ $health['score'] }}%</h3>
                </div>
                <span style="font-size:0.9rem;color:#78716c;">{{ $health['completed'] }}/{{ $health['total'] }} @lang('kitchen.steps_complete')</span>
            </div>
            <div class="inv-kit-card__body">
                <div class="row">
                    @foreach($health['checks'] as $check)
                    <div class="col-lg-3 col-md-6 mb-10">
                        <div style="padding:12px 14px;border-radius:10px;background:{{ $check['done'] ? 'rgba(22,163,74,0.08)' : 'rgba(185,28,28,0.06)' }};border:1px solid {{ $check['done'] ? 'rgba(22,163,74,0.2)' : 'rgba(185,28,28,0.15)' }};">
                            <div style="font-weight:600;font-size:0.875rem;color:{{ $check['done'] ? 'var(--kit-success)' : 'var(--kit-danger)' }};">
                                <span class="ti {{ $check['done'] ? 'ti-check' : 'ti-close' }} mr-1"></span>{{ $check['label'] }}
                            </div>
                            <div style="font-size:0.78rem;color:#78716c;margin-top:4px;">{{ $check['detail'] }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="row">
            {{-- Step 1: Bulk opening stock --}}
            <div class="col-lg-6 mb-20">
                <div class="inv-kit-card">
                    <div class="inv-kit-card__head">
                        <span class="ti-package" style="color:var(--kit-warm);"></span>
                        <h3>@lang('kitchen.bulk_opening_stock')</h3>
                    </div>
                    <div class="inv-kit-card__body">
                        <p class="text-muted" style="font-size:0.875rem;">@lang('kitchen.bulk_opening_hint')</p>
                        {{ html()->form('POST', route('inv-kitchen-setup-store'))->open() }}
                        <input type="hidden" name="action" value="bulk_opening">
                        @if($unitsEnabled && $units->isNotEmpty())
                        <div class="mb-10">
                            <label class="form-control-label">@lang('kitchen.location')</label>
                            <select name="location_id" class="primary_select form-control">
                                <option value="">@lang('kitchen.all_locations')</option>
                                @foreach($units as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                        <div class="mb-10">
                            <label class="form-control-label">@lang('kitchen.store')</label>
                            <select name="store_id" class="primary_select form-control">
                                <option value="">—</option>
                                @foreach($stores as $store)
                                    <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="table-responsive" style="max-height:320px;overflow-y:auto;">
                            <table class="table inv-kit-table mb-0">
                                <thead><tr><th>@lang('inventory.item_name')</th><th>@lang('kitchen.current_stock')</th><th>@lang('kitchen.opening_qty')</th></tr></thead>
                                <tbody>
                                @foreach($items as $item)
                                    <tr>
                                        <td>
                                            <input type="hidden" name="item_id[]" value="{{ $item->id }}">
                                            <strong>{{ $item->item_name }}</strong>
                                        </td>
                                        <td>{{ number_format((float)$item->total_in_stock, 2) }}</td>
                                        <td><input type="number" step="0.001" min="0" name="quantity[]" class="form-control form-control-sm" placeholder="0"></td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <button type="submit" class="primary-btn fix-gr-bg mt-15"><span class="ti-check mr-1"></span>@lang('kitchen.apply_opening_stock')</button>
                        {{ html()->form()->close() }}
                    </div>
                </div>
            </div>

            {{-- Step 2: Item master bulk edit --}}
            <div class="col-lg-6 mb-20">
                <div class="inv-kit-card">
                    <div class="inv-kit-card__head" style="justify-content:space-between;">
                        <div class="d-flex align-items-center" style="gap:14px;">
                            <span class="ti-pencil" style="color:var(--kit-warm);"></span>
                            <h3>@lang('kitchen.bulk_item_setup')</h3>
                        </div>
                        <a href="{{ route('inv-kitchen-setup-export') }}" class="primary-btn tr-bg small">@lang('kitchen.export_csv')</a>
                    </div>
                    <div class="inv-kit-card__body">
                        {{ html()->form('POST', route('inv-kitchen-setup-store'))->open() }}
                        <input type="hidden" name="action" value="bulk_items">
                        <div class="table-responsive" style="max-height:360px;overflow-y:auto;">
                            <table class="table inv-kit-table mb-0">
                                <thead>
                                    <tr>
                                        <th>@lang('inventory.item_name')</th>
                                        <th>@lang('kitchen.uom')</th>
                                        <th>@lang('kitchen.reorder_at')</th>
                                        <th>@lang('kitchen.reorder_qty')</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach($items as $i => $item)
                                    <tr>
                                        <td>
                                            <input type="hidden" name="item_id[]" value="{{ $item->id }}">
                                            {{ $item->item_name }}
                                        </td>
                                        <td>
                                            <select name="default_uom_id[]" class="form-control form-control-sm">
                                                <option value="">—</option>
                                                @foreach($uoms as $uom)
                                                    <option value="{{ $uom->id }}" @selected($item->default_uom_id == $uom->id)>{{ $uom->symbol }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td><input type="number" step="0.001" min="0" name="reorder_level[]" class="form-control form-control-sm" value="{{ $item->reorder_level }}"></td>
                                        <td><input type="number" step="0.001" min="0" name="reorder_qty[]" class="form-control form-control-sm" value="{{ $item->reorder_qty }}"></td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <button type="submit" class="primary-btn fix-gr-bg mt-15"><span class="ti-save mr-1"></span>@lang('kitchen.save_item_settings')</button>
                        {{ html()->form()->close() }}
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4 mb-20">
                <div class="inv-kit-card">
                    <div class="inv-kit-card__head"><span class="ti-book" style="color:var(--kit-warm);"></span><h3>@lang('kitchen.import_sample_recipes')</h3></div>
                    <div class="inv-kit-card__body">
                        <p class="text-muted" style="font-size:0.875rem;">@lang('kitchen.sample_recipes_hint')</p>
                        {{ html()->form('POST', route('inv-kitchen-setup-store'))->open() }}
                        <input type="hidden" name="action" value="sample_recipes">
                        <button type="submit" class="primary-btn tr-bg w-100"><span class="ti-download mr-1"></span>@lang('kitchen.import_recipes')</button>
                        {{ html()->form()->close() }}
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mb-20">
                <div class="inv-kit-card">
                    <div class="inv-kit-card__head"><span class="ti-check" style="color:var(--kit-success);"></span><h3>@lang('kitchen.mark_setup_complete')</h3></div>
                    <div class="inv-kit-card__body">
                        <p class="text-muted" style="font-size:0.875rem;">@lang('kitchen.mark_complete_hint')</p>
                        {{ html()->form('POST', route('inv-kitchen-setup-store'))->open() }}
                        <input type="hidden" name="action" value="mark_complete">
                        <button type="submit" class="primary-btn fix-gr-bg w-100">@lang('kitchen.mark_complete')</button>
                        {{ html()->form()->close() }}
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mb-20">
                <div class="inv-kit-card">
                    <div class="inv-kit-card__head"><span class="ti-arrow-left" style="color:var(--kit-warm);"></span><h3>@lang('kitchen.back_to_kitchen')</h3></div>
                    <div class="inv-kit-card__body">
                        <a href="{{ route('inv-kitchen') }}" class="primary-btn tr-bg w-100">@lang('kitchen.kitchen_inventory')</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
