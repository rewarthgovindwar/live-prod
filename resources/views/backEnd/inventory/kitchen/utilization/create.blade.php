@extends('backEnd.master')
@section('title')
@lang('kitchen.log_utilization')
@endsection
@section('mainContent')
@include('backEnd.inventory.kitchen.partials.kitchen_theme')
<section class="admin-visitor-area up_admin_visitor inv-kitchen-page">
    <div class="container-fluid p-0">
        <div class="inv-kitchen-hero">
            <span class="inv-kitchen-hero__icon"><span class="ti-clipboard"></span></span>
            <h1>@lang('kitchen.log_utilization')</h1>
            <p>@lang('kitchen.utilization_wizard_hint')</p>
        </div>

        @include('backEnd.inventory.kitchen.partials.kitchen_nav', ['activeTab' => 'utilization'])

        <div id="kit-wizard-alert" class="inv-kit-alert inv-kit-alert--error" role="alert"></div>

        <div class="inv-kit-steps" id="kit-steps">
            <div class="inv-kit-step is-active" data-step="1">1. @lang('kitchen.step_location')</div>
            <div class="inv-kit-step" data-step="2">2. @lang('kitchen.step_dish')</div>
            <div class="inv-kit-step" data-step="3">3. @lang('kitchen.step_event')</div>
            <div class="inv-kit-step" data-step="4">4. @lang('kitchen.step_ingredients')</div>
            <div class="inv-kit-step" data-step="5">5. @lang('kitchen.step_review')</div>
        </div>

        {{ html()->form('POST', route('inv-kitchen-utilization-store'))->id('kit-util-form')->attribute('novalidate', 'novalidate')->open() }}
        <input type="hidden" name="mode" id="kit_mode" value="{{ old('mode', 'recipe') }}">

        {{-- Step 1 --}}
        <div class="inv-kit-wizard-panel is-visible" data-panel="1">
            <div class="inv-kit-card">
                <div class="inv-kit-card__head"><h3>@lang('kitchen.choose_unit_store')</h3></div>
                <div class="inv-kit-card__body">
                    <div class="row">
                        @if($unitsEnabled && $units->isNotEmpty())
                        <div class="col-lg-6 mb-15">
                            <label class="primary_input_label">@lang('inventory.organization') *</label>
                            <select name="unit_id" id="kit_unit_id" class="primary_select form-control kit-required" data-step-field="1">
                                <option value="">@lang('common.select')</option>
                                @foreach($units as $unit)
                                    <option value="{{ $unit->id }}" @selected(old('unit_id', activeUnitId()) == $unit->id)>{{ $unit->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                        <div class="col-lg-6 mb-15">
                            <label class="primary_input_label">@lang('inventory.dept') *</label>
                            <select name="store_id" id="kit_store_id" class="primary_select form-control kit-required" data-step-field="1">
                                <option value="">@lang('common.select')</option>
                                @foreach($stores as $store)
                                    <option value="{{ $store->id }}" @selected(old('store_id') == $store->id)>{{ $store->store_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Step 2 --}}
        <div class="inv-kit-wizard-panel" data-panel="2">
            <div class="inv-kit-card">
                <div class="inv-kit-card__head"><h3>@lang('kitchen.choose_mode')</h3></div>
                <div class="inv-kit-card__body">
                    <div class="row mb-20">
                        <div class="col-md-4 mb-15">
                            <div class="inv-kit-mode-btn is-selected" data-mode="recipe" role="button" tabindex="0">
                                <span class="ti-book"></span>
                                <strong>@lang('kitchen.mode_recipe')</strong>
                                <p>@lang('kitchen.mode_recipe_hint')</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-15">
                            <div class="inv-kit-mode-btn" data-mode="manual" role="button" tabindex="0">
                                <span class="ti-pencil-alt"></span>
                                <strong>@lang('kitchen.mode_manual')</strong>
                                <p>@lang('kitchen.mode_manual_hint')</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-15">
                            <div class="inv-kit-mode-btn" data-mode="items" role="button" tabindex="0">
                                <span class="ti-package"></span>
                                <strong>@lang('kitchen.mode_items')</strong>
                                <p>@lang('kitchen.mode_items_hint')</p>
                            </div>
                        </div>
                    </div>
                    <div id="kit-recipe-block">
                        <label class="primary_input_label">@lang('kitchen.recipes') *</label>
                        <select name="recipe_id" id="kit_recipe_id" class="primary_select form-control">
                            <option value="">@lang('common.select')</option>
                            @foreach($recipes as $recipe)
                                <option value="{{ $recipe->id }}" data-servings="{{ $recipe->default_servings }}" @selected(old('recipe_id') == $recipe->id)>{{ $recipe->name }} ({{ $recipe->default_servings }} @lang('kitchen.default_servings'))</option>
                            @endforeach
                        </select>
                        <p class="text-muted mt-10 mb-0" style="font-size:13px;">@lang('kitchen.scale_by_headcount')</p>
                    </div>
                    <div id="kit-dish-block" style="display:none;">
                        <label class="primary_input_label">@lang('kitchen.what_dish_making') *</label>
                        <input type="text" name="dish_name" id="kit_dish_name" class="primary_input_field form-control" value="{{ old('dish_name') }}" maxlength="200">
                    </div>
                </div>
            </div>
        </div>

        {{-- Step 3 --}}
        <div class="inv-kit-wizard-panel" data-panel="3">
            <div class="inv-kit-card">
                <div class="inv-kit-card__head"><h3>@lang('kitchen.step_event')</h3></div>
                <div class="inv-kit-card__body">
                    <div class="row">
                        <div class="col-lg-4 mb-15">
                            <label class="primary_input_label">@lang('kitchen.for_how_many') *</label>
                            <input type="number" name="headcount" id="kit_headcount" class="primary_input_field form-control kit-required" data-step-field="3" min="1" value="{{ old('headcount', 1) }}">
                        </div>
                        <div class="col-lg-4 mb-15">
                            <label class="primary_input_label">@lang('kitchen.served_to') *</label>
                            <select name="served_to" id="kit_served_to" class="primary_select form-control kit-required" data-step-field="3">
                                @foreach($servedToOptions as $key => $label)
                                    <option value="{{ $key }}" @selected(old('served_to', 'mixed') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-4 mb-15">
                            <label class="primary_input_label">@lang('kitchen.meal_service') *</label>
                            <select name="meal_service" id="kit_meal_service" class="primary_select form-control kit-required" data-step-field="3">
                                @foreach($mealServices as $key => $label)
                                    <option value="{{ $key }}" @selected(old('meal_service') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-6 mb-15">
                            <label class="primary_input_label">@lang('kitchen.special_occasion')</label>
                            <input type="text" name="special_occasion" class="primary_input_field form-control" value="{{ old('special_occasion') }}">
                        </div>
                        <div class="col-lg-6 mb-15">
                            <label class="primary_input_label">@lang('kitchen.approved_by') *</label>
                            <select name="approved_by_staff_id" id="kit_approved_by" class="primary_select form-control kit-required" data-step-field="3">
                                <option value="">@lang('common.select')</option>
                                @foreach($staffs as $staff)
                                    <option value="{{ $staff->id }}" @selected(old('approved_by_staff_id') == $staff->id)>{{ $staff->full_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-6 mb-15">
                            <label class="primary_input_label">@lang('kitchen.date_time') *</label>
                            <input type="datetime-local" name="utilization_at" id="kit_utilization_at" class="primary_input_field form-control kit-required" data-step-field="3" value="{{ old('utilization_at', now()->format('Y-m-d\TH:i')) }}">
                        </div>
                        <div class="col-lg-6 mb-15">
                            <label class="primary_input_label d-block">@lang('kitchen.beverages_optional')</label>
                            <label class="checkbox-inline mr-20">
                                <input type="checkbox" name="has_beverages" id="kit_has_beverages" value="1" @checked(old('has_beverages'))> @lang('kitchen.beverages')
                            </label>
                        </div>
                        <div class="col-lg-12 mb-15" id="kit-beverage-notes" style="display:none;">
                            <label class="primary_input_label">@lang('kitchen.beverage_ingredients')</label>
                            <input type="text" name="beverage_notes" class="primary_input_field form-control" value="{{ old('beverage_notes') }}">
                        </div>
                        <div class="col-lg-12 mb-15">
                            <label class="primary_input_label">@lang('common.note')</label>
                            <textarea name="notes" class="primary_input_field form-control" rows="2">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Step 4 --}}
        <div class="inv-kit-wizard-panel" data-panel="4">
            <div class="inv-kit-card">
                <div class="inv-kit-card__head">
                    <h3>@lang('inventory.line_items')</h3>
                    <button type="button" class="primary-btn small fix-gr-bg ml-auto" id="kit-add-ingredient"><span class="ti-plus"></span> @lang('kitchen.add_custom_item')</button>
                </div>
                <div class="inv-kit-card__body p-0">
                    <div class="table-responsive">
                        <table class="table inv-kit-table mb-0">
                            <thead>
                                <tr>
                                    <th>@lang('inventory.item_name')</th>
                                    <th>@lang('kitchen.quantity_used')</th>
                                    <th>@lang('inventory.uom')</th>
                                    <th>@lang('kitchen.stock_before')</th>
                                    <th>@lang('kitchen.remaining_after_use')</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="kit-ingredient-lines">
                                @include('backEnd.inventory.kitchen.partials.ingredient_line_row', ['index' => 0, 'lineType' => 'ingredient', 'prefill' => [], 'items' => $items, 'uomOptions' => $uomOptions])
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="inv-kit-card" id="kit-beverage-section" style="display:none;">
                <div class="inv-kit-card__head">
                    <h3>@lang('kitchen.beverage_ingredients')</h3>
                    <button type="button" class="primary-btn small tr-bg ml-auto" id="kit-add-beverage"><span class="ti-plus"></span> @lang('kitchen.add_beverage_line')</button>
                </div>
                <div class="inv-kit-card__body p-0">
                    <div class="table-responsive">
                        <table class="table inv-kit-table mb-0">
                            <tbody id="kit-beverage-lines"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Step 5 --}}
        <div class="inv-kit-wizard-panel" data-panel="5">
            <div class="inv-kit-card">
                <div class="inv-kit-card__head"><h3>@lang('kitchen.step_review')</h3></div>
                <div class="inv-kit-card__body" id="kit-review-summary"></div>
            </div>
        </div>

        <div class="inv-kit-nav">
            <button type="button" class="primary-btn tr-bg" id="kit-prev" style="display:none;">@lang('common.previous')</button>
            <div class="ml-auto d-flex flex-wrap" style="gap:8px;">
                <a href="{{ route('inv-kitchen') }}" class="primary-btn tr-bg">@lang('common.cancel')</a>
                <button type="button" class="primary-btn fix-gr-bg" id="kit-next">@lang('common.next')</button>
                <button type="submit" class="primary-btn fix-gr-bg" id="kit-submit" style="display:none;">
                    <span class="ti-check mr-1"></span>@lang('kitchen.process_utilization')
                </button>
            </div>
        </div>
        {{ html()->form()->close() }}
    </div>
</section>

<script type="text/template" id="kit-line-template">
@include('backEnd.inventory.kitchen.partials.ingredient_line_row', ['index' => '__IDX__', 'lineType' => '__TYPE__', 'prefill' => [], 'items' => $items, 'uomOptions' => $uomOptions])
</script>

@push('script')
<script>
(function () {
    var currentStep = 1;
    var maxStep = 5;
    var recipeLinesUrl = @json(route('inv-kitchen-recipe-lines'));
    var lowStockThreshold = @json((float) config('kitchen.low_stock_threshold', 10));
    var lineIdx = {{ max(1, count(old('item_id', [0]))) }};
    var recipeLoading = false;
    var labels = {
        org: @json(__('inventory.organization')),
        dept: @json(__('inventory.dept')),
        mealService: @json(__('kitchen.meal_service')),
        headcount: @json(__('kitchen.for_how_many')),
        recipes: @json(__('kitchen.recipes')),
        dishName: @json(__('kitchen.dish_name')),
        itemName: @json(__('inventory.item_name')),
        qtyUsed: @json(__('kitchen.quantity_used')),
        remaining: @json(__('kitchen.remaining_after_use')),
        select: @json(__('common.select')),
        noIngredients: @json(__('kitchen.wizard_no_ingredients'))
    };

    function refreshNice($scope) {
        if (typeof $.fn.niceSelect === 'function') {
            $scope.find('.primary_select').each(function () {
                var $s = $(this);
                if ($s.next('.nice-select').length) {
                    $s.niceSelect('update');
                } else {
                    $s.niceSelect();
                }
            });
        }
    }

    function fieldVal($el) {
        return $.trim(String($el.val() || ''));
    }

    function selectText($el) {
        var val = fieldVal($el);
        if (!val) { return '—'; }
        return $el.find('option:selected').text().trim() || '—';
    }

    function showAlert(msg) {
        $('#kit-wizard-alert').text(msg).addClass('is-visible');
        var $target = $('#kit-wizard-alert');
        if ($target.offset()) {
            $('html, body').animate({ scrollTop: $target.offset().top - 80 }, 200);
        }
    }

    function hideAlert() {
        $('#kit-wizard-alert').removeClass('is-visible').text('');
    }

    function showStep(n) {
        hideAlert();
        currentStep = n;
        $('.inv-kit-wizard-panel').removeClass('is-visible');
        $('.inv-kit-wizard-panel[data-panel="' + n + '"]').addClass('is-visible');
        $('.inv-kit-step').removeClass('is-active is-done');
        $('.inv-kit-step').each(function () {
            var s = parseInt($(this).data('step'), 10);
            if (s < n) { $(this).addClass('is-done'); }
            if (s === n) { $(this).addClass('is-active'); }
        });
        $('#kit-prev').toggle(n > 1);
        $('#kit-next').toggle(n < maxStep);
        $('#kit-submit').toggle(n === maxStep);
        if (n === 5) { buildReview(); }
    }

    function validateStep(step) {
        if (step === 1) {
            @if($unitsEnabled && $units->isNotEmpty())
            if (!fieldVal($('#kit_unit_id')) || !fieldVal($('#kit_store_id'))) {
                showAlert(@json(__('kitchen.wizard_step_error')));
                return false;
            }
            @else
            if (!fieldVal($('#kit_store_id'))) {
                showAlert(@json(__('kitchen.wizard_step_error')));
                return false;
            }
            @endif
        }
        if (step === 2) {
            var mode = $('#kit_mode').val();
            if (mode === 'recipe' && !fieldVal($('#kit_recipe_id'))) {
                showAlert(@json(__('kitchen.select_recipe')));
                return false;
            }
            if (mode === 'manual' && !fieldVal($('#kit_dish_name'))) {
                showAlert(@json(__('kitchen.enter_dish_name')));
                return false;
            }
        }
        if (step === 3) {
            var valid = true;
            $('[data-step-field="3"].kit-required').each(function () {
                if (!fieldVal($(this))) { valid = false; }
            });
            if (!valid || parseInt($('#kit_headcount').val(), 10) < 1) {
                showAlert(@json(__('kitchen.wizard_step_error')));
                return false;
            }
        }
        if (step === 4) {
            if (!hasValidLines()) {
                showAlert(@json(__('kitchen.wizard_no_ingredients')));
                return false;
            }
        }
        return true;
    }

    function hasValidLines() {
        var found = false;
        $('#kit-ingredient-lines .kit-line-row, #kit-beverage-lines .kit-line-row').each(function () {
            var itemId = fieldVal($(this).find('.kit-item-select'));
            var qty = parseFloat($(this).find('.kit-qty-input').val()) || 0;
            if (itemId && qty > 0) { found = true; }
        });
        return found;
    }

    function prepareLinesForSubmit() {
        $('#kit-ingredient-lines .kit-line-row, #kit-beverage-lines .kit-line-row').each(function () {
            var itemId = fieldVal($(this).find('.kit-item-select'));
            var qty = parseFloat($(this).find('.kit-qty-input').val()) || 0;
            var disable = !itemId || qty <= 0;
            $(this).find(':input').prop('disabled', disable);
        });
    }

    function updateRemain($row) {
        var stock = parseFloat($row.find('.kit-item-select option:selected').data('stock')) || 0;
        var qty = parseFloat($row.find('.kit-qty-input').val()) || 0;
        $row.find('.kit-stock-val').text(stock.toFixed(2));
        var remain = Math.max(0, stock - qty);
        var $rem = $row.find('.kit-remain-val');
        $rem.text(remain.toFixed(2));
        var $cell = $rem.closest('.kit-stock-after');
        $cell.removeClass('inv-kit-stock-warn inv-kit-stock-ok');
        if (qty > 0 && itemIdSelected($row)) {
            if (remain < lowStockThreshold || qty > stock) {
                $cell.addClass('inv-kit-stock-warn');
            } else {
                $cell.addClass('inv-kit-stock-ok');
            }
        }
    }

    function itemIdSelected($row) {
        return !!fieldVal($row.find('.kit-item-select'));
    }

    function recalcAllLines() {
        $('#kit-ingredient-lines .kit-line-row, #kit-beverage-lines .kit-line-row').each(function () {
            updateRemain($(this));
        });
    }

    function loadRecipeLines(callback) {
        var recipeId = fieldVal($('#kit_recipe_id'));
        var headcount = $('#kit_headcount').val() || 1;
        if (!recipeId || $('#kit_mode').val() !== 'recipe') {
            if (callback) { callback(true); }
            return;
        }
        recipeLoading = true;
        $('#kit-next').prop('disabled', true);
        $.get(recipeLinesUrl, { recipe_id: recipeId, headcount: headcount })
            .done(function (res) {
                if (!res || res.status !== 'success') {
                    showAlert(@json(__('kitchen.recipe_lines_load_error')));
                    if (callback) { callback(false); }
                    return;
                }
                $('#kit-ingredient-lines').empty();
                (res.lines || []).forEach(function (line) {
                    addLine('ingredient', line, '#kit-ingredient-lines');
                });
                if (!res.lines || !res.lines.length) {
                    addLine('ingredient', {}, '#kit-ingredient-lines');
                }
                recalcAllLines();
                if (callback) { callback(true); }
            })
            .fail(function () {
                showAlert(@json(__('kitchen.recipe_lines_load_error')));
                if (callback) { callback(false); }
            })
            .always(function () {
                recipeLoading = false;
                $('#kit-next').prop('disabled', false);
            });
    }

    function addLine(type, prefill, target) {
        var html = $('#kit-line-template').html();
        if (!html) { return; }
        html = html.replace(/__IDX__/g, String(lineIdx++)).replace(/__TYPE__/g, type);
        var $row = $(html);
        if (prefill && prefill.item_id) {
            $row.find('.kit-item-select').val(String(prefill.item_id));
            $row.find('.kit-qty-input').val(prefill.quantity || 1);
            if (prefill.uom) { $row.find('[name="uom[]"]').val(prefill.uom); }
            if (prefill.stock !== undefined) {
                $row.find('.kit-stock-val').text(parseFloat(prefill.stock).toFixed(2));
            }
        }
        $(target || '#kit-ingredient-lines').append($row);
        refreshNice($row);
        updateRemain($row);
    }

    function applyModeUI(mode) {
        $('.inv-kit-mode-btn').removeClass('is-selected');
        $('.inv-kit-mode-btn[data-mode="' + mode + '"]').addClass('is-selected');
        $('#kit-recipe-block').toggle(mode === 'recipe');
        $('#kit-dish-block').toggle(mode === 'manual');
    }

    function buildReview() {
        recalcAllLines();
        var mode = $('#kit_mode').val();
        var html = '<div class="row">';
        html += '<div class="col-md-6"><p><strong>' + labels.org + ':</strong> ' + selectText($('#kit_unit_id')) + '</p></div>';
        html += '<div class="col-md-6"><p><strong>' + labels.dept + ':</strong> ' + selectText($('#kit_store_id')) + '</p></div>';
        html += '<div class="col-md-6"><p><strong>' + labels.mealService + ':</strong> ' + selectText($('#kit_meal_service')) + '</p></div>';
        html += '<div class="col-md-6"><p><strong>' + labels.headcount + ':</strong> ' + ($('#kit_headcount').val() || '—') + '</p></div>';
        if (mode === 'recipe') {
            html += '<div class="col-md-12"><p><strong>' + labels.recipes + ':</strong> ' + selectText($('#kit_recipe_id')) + '</p></div>';
        } else if (mode === 'manual') {
            html += '<div class="col-md-12"><p><strong>' + labels.dishName + ':</strong> ' + (fieldVal($('#kit_dish_name')) || '—') + '</p></div>';
        }
        html += '<div class="col-md-12"><table class="table inv-kit-table"><thead><tr><th>' + labels.itemName + '</th><th>' + labels.qtyUsed + '</th><th>' + labels.remaining + '</th></tr></thead><tbody>';
        var hasRows = false;
        $('#kit-ingredient-lines .kit-line-row, #kit-beverage-lines .kit-line-row').each(function () {
            var name = $(this).find('.kit-item-select option:selected').text().trim();
            if (!name || name === labels.select) { return; }
            hasRows = true;
            html += '<tr><td>' + name + '</td><td>' + $(this).find('.kit-qty-input').val() + '</td><td>' + $(this).find('.kit-remain-val').text() + '</td></tr>';
        });
        if (!hasRows) {
            html += '<tr><td colspan="3" class="text-muted">' + labels.noIngredients + '</td></tr>';
        }
        html += '</tbody></table></div></div>';
        $('#kit-review-summary').html(html);
    }

    $('#kit-next').on('click', function () {
        if (recipeLoading) { return; }
        if (!validateStep(currentStep)) { return; }
        if (currentStep === 3 && $('#kit_mode').val() === 'recipe') {
            loadRecipeLines(function (ok) {
                if (ok) { showStep(currentStep + 1); }
            });
            return;
        }
        if (currentStep < maxStep) { showStep(currentStep + 1); }
    });

    $('#kit-prev').on('click', function () {
        if (currentStep > 1) { showStep(currentStep - 1); }
    });

    $('.inv-kit-mode-btn').on('click keypress', function (e) {
        if (e.type === 'keypress' && e.which !== 13 && e.which !== 32) { return; }
        e.preventDefault();
        var mode = $(this).data('mode');
        $('#kit_mode').val(mode);
        applyModeUI(mode);
    });

    applyModeUI($('#kit_mode').val() || 'recipe');

    $('#kit_recipe_id, #kit_headcount').on('change', function () {
        if ($('#kit_mode').val() === 'recipe' && currentStep >= 4) {
            loadRecipeLines();
        }
    });

    $('#kit_has_beverages').on('change', function () {
        var on = $(this).is(':checked');
        $('#kit-beverage-notes').toggle(on);
        $('#kit-beverage-section').toggle(on);
        if (on && $('#kit-beverage-lines').children().length === 0) {
            addLine('beverage', {}, '#kit-beverage-lines');
        }
    }).trigger('change');

    $('#kit-add-ingredient').on('click', function () { addLine('ingredient', {}, '#kit-ingredient-lines'); });
    $('#kit-add-beverage').on('click', function () { addLine('beverage', {}, '#kit-beverage-lines'); });

    $(document).on('change', '.kit-item-select', function () { updateRemain($(this).closest('.kit-line-row')); });
    $(document).on('input', '.kit-qty-input', function () { updateRemain($(this).closest('.kit-line-row')); });
    $(document).on('click', '.kit-remove-line', function () {
        var $tbody = $(this).closest('tbody');
        if ($tbody.find('.kit-line-row').length <= 1) { return; }
        $(this).closest('.kit-line-row').remove();
    });

    $('#kit-util-form').on('submit', function (e) {
        if (!validateStep(4) || !hasValidLines()) {
            e.preventDefault();
            showAlert(@json(__('kitchen.wizard_no_ingredients')));
            showStep(4);
            return;
        }
        prepareLinesForSubmit();
    });

    refreshNice($('.inv-kitchen-page'));
    recalcAllLines();
    showStep(1);
})();
</script>
@endpush
@endsection
