@if(config('fee_presets.simple_menu', true))
<details class="fiw-collect-panel" id="fiwCollectNow">
    <summary class="fiw-collect-summary">
        <i class="ti-money"></i> Collect payment now
        <span class="fiw-collect-hint text-muted">Optional — record payment while creating invoice</span>
    </summary>
    <div class="fiw-collect-body mt-15">
        <div class="row">
            <div class="col-md-4 fiw-field">
                <label class="primary_input_label" for="fiwCollectAmount">Amount to collect</label>
                <input type="number" min="0" step="0.01" class="primary_input_field form-control" id="fiwCollectAmount"
                    name="fiw_collect_amount" placeholder="0.00">
                <button type="button" class="primary-btn small fix-gr-bg mt-10" id="fiwFillFullAmount">
                    Fill full invoice amount
                </button>
            </div>
            <div class="col-md-4 fiw-field">
                <label class="primary_input_label" for="fiwCollectMethod">Payment method</label>
                <select class="primary_select form-control" id="fiwCollectMethod" name="payment_method">
                    <option value="">Select method</option>
                    @foreach(($paymentMethods ?? ['Cash', 'Bank', 'Cheque', 'UPI']) as $method)
                        <option value="{{ $method }}">{{ $method }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 fiw-field" id="fiwCollectBankWrap" style="display:none">
                <label class="primary_input_label" for="fiwCollectBank">Bank account</label>
                <select class="primary_select form-control" id="fiwCollectBank" name="bank_id">
                    <option value="">Select bank</option>
                    @foreach(($banks ?? []) as $bank)
                        <option value="{{ $bank->id ?? $bank['id'] ?? '' }}">{{ $bank->bank_name ?? $bank['bank_name'] ?? '' }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="fiw-allocation-bar mt-15">
            <span class="primary_input_label d-block mb-5">Split payment by fee type</span>
            <div class="fiw-allocation-toggle btn-group btn-group-sm" role="group" aria-label="Payment allocation mode">
                <button type="button" class="btn btn-primary fiw-alloc-mode is-active" data-mode="auto">Auto split</button>
                <button type="button" class="btn btn-outline-primary fiw-alloc-mode" data-mode="manual">Manual split</button>
            </div>
            <input type="hidden" name="payment_allocation_mode" id="paymentAllocationMode" value="auto">
            <small class="text-muted d-block mt-8" id="fiwAllocationHint">
                Auto split divides the collection across fee lines by their period totals. Switch to manual to assign amounts yourself (e.g. ₹1000 Building Fund, ₹500 Tuition).
            </small>
            <small class="text-muted d-block mt-5" id="fiwAllocationSummary"></small>
        </div>
    </div>
</details>
@endif
