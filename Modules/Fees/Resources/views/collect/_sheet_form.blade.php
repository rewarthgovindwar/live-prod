<form method="POST" action="{{ route('fees.collect-store') }}" id="collect_form" class="fa-collect-form">
    @csrf
    <input type="hidden" name="idempotency_key" id="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
    <input type="hidden" name="payment_allocation_mode" id="collectAllocationMode" value="auto">

    @php
        $bifurcationByInvoice = collect($bifurcationData ?? [])->keyBy('invoice_id');
    @endphp

    <div class="fa-collect-section">
        <div class="fa-collect-section__head">
            <div>
                <span class="fa-collect-section__title">Select months</span>
                <span class="fa-collect-section__hint">Tick a month, enter how much to collect — the fee split updates inside that month.</span>
            </div>
            <div class="fa-collect-section__actions">
                @if(!empty($canManageFeeAllocation))
                    <label class="fa-collect-custom-toggle">
                        <input type="checkbox" id="faCustomAllocation" value="1">
                        <span>Custom split</span>
                    </label>
                @endif
                <button type="button" class="fa-collect-selectall" id="collect_select_all">Select all</button>
            </div>
        </div>

        <div class="fa-collect-months">
        @foreach($invoices as $invoice)
            @php
                $bal = app(\App\Services\FeeMultiMonthCollectionService::class)->invoiceBalance($invoice);
                $lineMeta = $bifurcationByInvoice->get($invoice->id, ['lines' => []]);
                $feeLines = $lineMeta['lines'] ?? [];
            @endphp
            <div class="fa-collect-month {{ $loop->first ? 'is-selected' : '' }}" data-invoice-id="{{ $invoice->id }}" data-balance="{{ $bal }}">
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

                @if(count($feeLines) > 0)
                    <div class="fa-month-split" data-invoice-id="{{ $invoice->id }}" @if(! $loop->first) hidden @endif>
                        <div class="fa-month-split__head">
                            <span class="fa-month-split__title">Fee split for this month</span>
                            <span class="fa-month-split__meta" data-split-meta>—</span>
                        </div>
                        <table class="fa-month-split__table">
                            <thead>
                                <tr>
                                    <th>Fee type</th>
                                    <th class="text-right">Due</th>
                                    <th class="text-right">Paying</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($feeLines as $line)
                                    <tr data-fees-type="{{ $line['fees_type'] }}" data-due="{{ $line['due'] }}">
                                        <td>{{ $line['label'] }}</td>
                                        <td class="text-right">{{ currency_format($line['due']) }}</td>
                                        <td class="text-right fa-month-split__paid-cell">
                                            <span class="fa-month-split__paid-text" data-paid-text>—</span>
                                            @if(!empty($canManageFeeAllocation))
                                                <input type="number" min="0" step="0.01" max="{{ $line['due'] }}"
                                                    class="primary_input_field form-control fa-month-line-paid"
                                                    data-invoice-id="{{ $invoice->id }}"
                                                    data-fees-type="{{ $line['fees_type'] }}"
                                                    hidden disabled>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="2" class="text-right"><strong>Month allocation</strong></td>
                                    <td class="text-right"><strong class="fa-month-split__total" data-split-total>—</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                        <div class="fa-month-split__fields" data-line-fields></div>
                    </div>
                @endif
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

<script type="application/json" id="collectCanManageAllocation">@json(!empty($canManageFeeAllocation))</script>

<style>
    .fa-collect-section__actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .fa-collect-custom-toggle {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 12px; font-weight: 600; color: var(--fa-muted, #64748b);
        cursor: pointer; user-select: none; margin: 0;
    }
    .fa-month-split {
        border-top: 1px dashed var(--fa-border-strong, #e2e8f0);
        background: rgba(255, 255, 255, 0.72);
        padding: 10px 16px 14px;
    }
    .fa-month-split__head {
        display: flex; justify-content: space-between; align-items: center; gap: 8px;
        margin-bottom: 8px;
    }
    .fa-month-split__title { font-size: 12px; font-weight: 700; color: var(--fa-text, #1e293b); }
    .fa-month-split__meta { font-size: 11px; color: var(--fa-muted, #64748b); }
    .fa-month-split__table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
    .fa-month-split__table th,
    .fa-month-split__table td { padding: 6px 4px; border-bottom: 1px solid #eef2f7; vertical-align: middle; }
    .fa-month-split__table th { font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.03em; color: var(--fa-muted, #64748b); font-weight: 600; }
    .fa-month-split__table tfoot td { border-bottom: none; padding-top: 8px; }
    .fa-month-split__paid-text { font-weight: 700; color: var(--fa-text, #1e293b); }
    .fa-month-line-paid { max-width: 110px; margin-left: auto; text-align: right; font-weight: 700; }
    .fa-collect-month.is-selected .fa-month-split[hidden] { display: none !important; }
</style>
