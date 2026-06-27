<form method="POST" action="{{ route('fees.collect-store') }}" id="collect_form" class="fa-collect-form">
    @csrf
    <input type="hidden" name="idempotency_key" id="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
    <input type="hidden" name="payment_allocation_mode" id="collectAllocationMode" value="auto">

    <div class="fa-collect-section">
        <div class="fa-collect-section__head">
            <div>
                <span class="fa-collect-section__title">Select months</span>
                <span class="fa-collect-section__hint">Tick a month to pay it. Edit the amount for a part payment.</span>
            </div>
            <button type="button" class="fa-collect-selectall" id="collect_select_all">Select all</button>
        </div>

        <div class="fa-collect-months">
        @foreach($invoices as $invoice)
            @php $bal = app(\App\Services\FeeMultiMonthCollectionService::class)->invoiceBalance($invoice); @endphp
            <div class="fa-collect-month {{ $loop->first ? 'is-selected' : '' }}">
                <label class="fa-collect-month__main">
                    <input type="checkbox" name="invoice_ids[]" value="{{ $invoice->id }}" class="month-check"
                           data-amount="{{ $bal }}" data-id="{{ $invoice->id }}" @checked($loop->first)>
                    <span class="fa-collect-month__info">
                        <span class="fa-collect-month__month">{{ $invoice->month_label ?? dateConvert($invoice->due_date) }}</span>
                        <span class="fa-collect-month__sub">{{ $invoice->invoice_id }}@if($invoice->due_date) · due {{ dateConvert($invoice->due_date) }}@endif</span>
                    </span>
                    <span class="fa-collect-month__bal">
                        <span class="fa-collect-month__bal-label">Balance</span>
                        <span class="fa-collect-month__bal-amt">{{ currency_format($bal) }}</span>
                    </span>
                </label>
                <div class="fa-collect-month__pay">
                    <label for="amt_{{ $invoice->id }}" class="fa-collect-month__pay-label">Paying now</label>
                    <div class="fa-collect-amt">
                        <span class="fa-collect-amt__cur">₹</span>
                        <input type="number" step="0.01" min="0" max="{{ $bal }}"
                               id="amt_{{ $invoice->id }}"
                               name="amounts[{{ $invoice->id }}]"
                               class="primary_input_field form-control amount-input"
                               data-id="{{ $invoice->id }}"
                               value="{{ $bal }}" @disabled(! $loop->first)>
                    </div>
                </div>
            </div>
        @endforeach
        </div>
    </div>

    <div class="fa-collect-total">
        <div class="fa-collect-total__text">
            <span class="fa-collect-total__label">Total to collect</span>
            <span class="fa-collect-total__count" id="collect_count">0 months selected</span>
        </div>
        <strong id="collect_total">₹0.00</strong>
    </div>

    <details class="fa-bifurcation" id="faBifurcation">
        <summary class="fa-bifurcation__summary">
            Payment bifurcation
            <span class="fa-bifurcation__preview text-muted" id="faBifurcationPreview"></span>
        </summary>
        <div class="fa-bifurcation__body mt-10">
            @if(!empty($canManageFeeAllocation))
                <label class="fa-bifurcation__custom mb-10">
                    <input type="checkbox" id="faCustomAllocation" value="1">
                    <span>Custom allocation (accounts only)</span>
                </label>
            @endif
            <small class="text-muted d-block mb-10" id="faBifurcationHint">
                Split follows each fee type's share of the invoice balance (same percentage on every line).
            </small>
            <div class="table-responsive">
                <table class="table table-sm fa-bifurcation-table mb-0">
                    <thead>
                        <tr>
                            <th>Fee type</th>
                            <th class="text-right">Balance due</th>
                            <th class="text-right">Share</th>
                            <th class="text-right">Paying now</th>
                        </tr>
                    </thead>
                    <tbody id="faBifurcationBody">
                        <tr>
                            <td colspan="4" class="text-muted">Select months and enter amounts to see the split.</td>
                        </tr>
                    </tbody>
                    <tfoot id="faBifurcationFoot" style="display:none">
                        <tr>
                            <th colspan="3" class="text-right">Total allocated</th>
                            <th class="text-right" id="faBifurcationTotal">₹0.00</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div id="faBifurcationHiddenFields"></div>
        </div>
    </details>

    <div class="fa-collect-fields">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="primary_input_label">Payment method</label>
                <select name="payment_method" id="payment_method" class="form-control fa-collect-select" required>
                    @foreach($paymentMethods as $method)
                        <option value="{{ $method->method }}">{{ $method->method }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 mb-3 d-none" id="bank_wrap">
                <label class="primary_input_label">Bank account</label>
                <select name="bank" class="form-control fa-collect-select">
                    <option value="">Select bank</option>
                    @foreach($banks as $bank)
                        <option value="{{ $bank->id }}">{{ $bank->bank_name }} — {{ $bank->account_name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if($canOverrideReceipt)
            <div class="mb-3">
                <label class="primary_input_label">Receipt number override (optional)</label>
                <input type="text" name="manual_receipt_number" class="primary_input_field form-control" maxlength="120">
            </div>
        @endif

        <div class="mb-3">
            <label class="primary_input_label">Note (optional)</label>
            <input type="text" name="payment_note" class="primary_input_field form-control" placeholder="Reference, cheque no, remark…">
        </div>
    </div>

    <div class="fa-collect-actions">
        @if(!empty($inModal))
            <button type="button" class="fa-btn fa-btn--ghost" data-dismiss="modal">Cancel</button>
        @else
            <a href="{{ route('fees.fees-invoice-list') }}" class="fa-btn fa-btn--ghost">Cancel</a>
        @endif
        <button type="submit" class="fa-btn fa-btn--primary" id="collect_submit_btn">
            <i class="ti-check"></i> Collect &amp; receipt
        </button>
    </div>
</form>

<script type="application/json" id="collectBifurcationData">@json($bifurcationData ?? [])</script>
<script type="application/json" id="collectCanManageAllocation">@json(!empty($canManageFeeAllocation))</script>

<style>
    .fa-bifurcation { border: 1px solid #e9ecef; border-radius: 8px; padding: 10px 12px; margin: 16px 0; background: #fafbfc; }
    .fa-bifurcation__summary { cursor: pointer; font-weight: 600; list-style: none; }
    .fa-bifurcation__summary::-webkit-details-marker { display: none; }
    .fa-bifurcation__preview { font-weight: 400; font-size: 12px; margin-left: 8px; }
    .fa-bifurcation__custom { display: flex; align-items: center; gap: 8px; cursor: pointer; user-select: none; }
    .fa-bifurc-paid-input { max-width: 120px; margin-left: auto; text-align: right; }
</style>
