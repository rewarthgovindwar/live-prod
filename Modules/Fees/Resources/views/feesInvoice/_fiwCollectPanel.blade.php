@if(config('fee_presets.simple_menu', true))
@php
    $canManageFeeAllocation = app(\App\Services\FeeManualAllocationService::class)->canManageAllocation();
@endphp
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

        <input type="hidden" name="payment_allocation_mode" id="paymentAllocationMode" value="auto">

        <details class="fiw-bifurcation mt-15" id="fiwBifurcation">
            <summary class="fiw-bifurcation-summary">
                Payment bifurcation
                <span class="fiw-bifurcation-preview text-muted" id="fiwBifurcationPreview"></span>
            </summary>
            <div class="fiw-bifurcation-body mt-10">
                @if($canManageFeeAllocation)
                    <label class="fiw-custom-alloc-toggle mb-10">
                        <input type="checkbox" id="fiwCustomAllocation" value="1">
                        <span>Custom allocation (accounts only)</span>
                    </label>
                    <small class="text-muted d-block mb-10" id="fiwAllocationHint">
                        By default, payment is split by each fee type's share of the invoice total (same percentage for every line).
                    </small>
                @else
                    <small class="text-muted d-block mb-10" id="fiwAllocationHint">
                        Payment is auto-split by each fee type's share of the invoice total.
                    </small>
                @endif
                <div class="table-responsive">
                    <table class="table table-sm fiw-bifurcation-table mb-0">
                        <thead>
                            <tr>
                                <th>Fee type</th>
                                <th class="text-right">Invoice amount</th>
                                <th class="text-right">Share</th>
                                <th class="text-right">Paying now</th>
                            </tr>
                        </thead>
                        <tbody id="fiwBifurcationBody">
                            <tr class="fiw-bifurcation-empty">
                                <td colspan="4" class="text-muted">Enter a collection amount to see the split.</td>
                            </tr>
                        </tbody>
                        <tfoot id="fiwBifurcationFoot" style="display:none">
                            <tr>
                                <th colspan="3" class="text-right">Total</th>
                                <th class="text-right" id="fiwBifurcationTotal">₹0</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <small class="text-muted d-block mt-5" id="fiwAllocationSummary"></small>
            </div>
        </details>
    </div>
</details>
<style>
    .fiw-bifurcation { border: 1px solid #e9ecef; border-radius: 8px; padding: 10px 12px; background: #fafbfc; }
    .fiw-bifurcation-summary { cursor: pointer; font-weight: 600; list-style: none; }
    .fiw-bifurcation-summary::-webkit-details-marker { display: none; }
    .fiw-bifurcation-preview { font-weight: 400; font-size: 12px; margin-left: 8px; }
    .fiw-bifurcation-table th, .fiw-bifurcation-table td { vertical-align: middle; }
    .fiw-bifurc-paid-input { max-width: 120px; margin-left: auto; text-align: right; }
    .fiw-custom-alloc-toggle { display: flex; align-items: center; gap: 8px; cursor: pointer; user-select: none; }
</style>
@endif
