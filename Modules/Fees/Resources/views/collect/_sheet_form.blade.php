<form method="POST" action="{{ route('fees.collect-store') }}" id="collect_form" class="fa-collect-form">
    @csrf
    <input type="hidden" name="idempotency_key" id="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
    <input type="hidden" name="payment_allocation_mode" id="collectAllocationMode" value="auto">

    <div class="fa-collect-section">
        <div class="fa-collect-section__head">
            <div>
                <span class="fa-collect-section__title">Select months</span>
                <span class="fa-collect-section__hint">Choose months to collect. Amount and fee split update live inside each card.</span>
            </div>
            <div class="fa-collect-section__actions">
                @if(!empty($canManageFeeAllocation))
                    <label class="fa-alloc-mode" id="faCustomAllocationWrap" title="Accounts: enter exact amounts per fee type">
                        <input type="checkbox" id="faCustomAllocation" value="1">
                        <span class="fa-alloc-mode__track" aria-hidden="true"><span class="fa-alloc-mode__thumb"></span></span>
                        <span class="fa-alloc-mode__label">Custom split</span>
                    </label>
                @endif
                <button type="button" class="fa-collect-selectall" id="collect_select_all">Select all</button>
            </div>
        </div>

        <div class="fa-collect-months">
        @foreach($invoices as $invoice)
            @php
                $bal = app(\App\Services\FeeMultiMonthCollectionService::class)->invoiceBalance($invoice);
                $balanceService = app(\App\Services\FeeInvoiceBalanceService::class);
                $labelService = app(\App\Services\FeeInvoiceLineLabelService::class);
                $invoiceLines = $invoice->invoiceDetails
                    ->sortBy('id')
                    ->values()
                    ->map(function ($child) use ($invoice, $balanceService, $labelService) {
                        $due = round($balanceService->collectibleLineDue($invoice, $child), 2);

                        return [
                            'child' => $child,
                            'due' => $due,
                            'label' => $labelService->labelFor($child, $invoice),
                        ];
                    });
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
                    <div class="fa-collect-month__pay-head">
                        <label for="amt_{{ $invoice->id }}" class="fa-collect-month__pay-label">Paying now</label>
                        <button type="button" class="fa-pay-fill" data-fill-invoice="{{ $invoice->id }}" title="Fill full balance">Pay full</button>
                    </div>
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

                @if($invoiceLines->isNotEmpty())
                    <div class="fa-month-split" data-invoice-id="{{ $invoice->id }}" @if(! $loop->first) hidden @endif>
                        <div class="fa-month-split__head">
                            <div class="fa-month-split__head-left">
                                <span class="fa-month-split__icon" aria-hidden="true"><i class="ti-layout-list-thumb"></i></span>
                                <div>
                                    <span class="fa-month-split__title">Payment breakdown</span>
                                    <span class="fa-month-split__sub">How this month's collection is allocated</span>
                                </div>
                                <span class="fa-split-badge" data-split-badge>Auto</span>
                            </div>
                            <div class="fa-month-split__progress">
                                <div class="fa-month-split__progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100">
                                    <div class="fa-month-split__progress-fill" data-split-progress style="width: 0%"></div>
                                </div>
                                <span class="fa-month-split__meta" data-split-meta>—</span>
                            </div>
                        </div>

                        <div class="fa-month-split__lines">
                                @foreach($invoiceLines as $line)
                                    <div class="fa-split-line {{ $line['due'] <= 0 ? 'is-zero-due' : '' }}"
                                         data-fees-type="{{ $line['child']->fees_type }}"
                                         data-due="{{ $line['due'] }}"
                                         data-label="{{ $line['label'] }}">
                                        <div class="fa-split-line__main">
                                            <span class="fa-split-line__dot" aria-hidden="true"></span>
                                            <div class="fa-split-line__info">
                                                <span class="fa-split-line__name">{{ $line['label'] }}</span>
                                                <span class="fa-split-line__due">
                                                    @if($line['due'] > 0)
                                                        Due {{ currency_format($line['due']) }}
                                                    @else
                                                        No balance due
                                                    @endif
                                                </span>
                                            </div>
                                        </div>
                                        <div class="fa-split-line__pay">
                                            <div class="fa-split-line__bar" aria-hidden="true">
                                                <div class="fa-split-line__bar-fill" data-line-bar style="width: 0%"></div>
                                            </div>
                                            <div class="fa-split-line__amount">
                                                <span class="fa-month-split__paid-text" data-paid-text>—</span>
                                                @if(!empty($canManageFeeAllocation) && $line['due'] > 0)
                                                    <input type="number" min="0" step="0.01" max="{{ $line['due'] }}"
                                                        class="primary_input_field form-control fa-month-line-paid"
                                                        data-invoice-id="{{ $invoice->id }}"
                                                        data-fees-type="{{ $line['child']->fees_type }}"
                                                        placeholder="0.00"
                                                        hidden disabled>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                        </div>

                        <div class="fa-month-split__footer">
                            <span class="fa-month-split__footer-label">Month allocation</span>
                            <strong class="fa-month-split__total" data-split-total>—</strong>
                        </div>
                        <p class="fa-month-split__hint" data-split-hint>Split follows each fee type's share of the balance.</p>
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
    .fa-collect-section__actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .fa-alloc-mode {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        cursor: pointer;
        user-select: none;
        padding: 4px 10px 4px 4px;
        border-radius: 999px;
        border: 1px solid var(--fa-border-strong, #e2e8f0);
        background: #fff;
        transition: border-color 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
    }

    .fa-alloc-mode:hover {
        border-color: #d9def0;
    }

    .fa-alloc-mode input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .fa-alloc-mode__track {
        width: 34px;
        height: 20px;
        border-radius: 999px;
        background: #e2e8f0;
        position: relative;
        transition: background 0.2s ease;
        flex: 0 0 34px;
    }

    .fa-alloc-mode__thumb {
        position: absolute;
        top: 2px;
        left: 2px;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: #fff;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.18);
        transition: transform 0.2s ease;
    }

    .fa-alloc-mode input:checked + .fa-alloc-mode__track {
        background: var(--fa-primary, #7c32ff);
    }

    .fa-alloc-mode input:checked + .fa-alloc-mode__track .fa-alloc-mode__thumb {
        transform: translateX(14px);
    }

    .fa-alloc-mode input:checked ~ .fa-alloc-mode__label {
        color: var(--fa-primary, #7c32ff);
    }

    .fa-alloc-mode__label {
        font-size: 12px;
        font-weight: 700;
        color: var(--fa-muted, #64748b);
    }

    .fa-collect-month__pay-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        flex: 1 1 auto;
        min-width: 0;
    }

    .fa-pay-fill {
        border: 0;
        background: #fff;
        color: var(--fa-primary, #7c32ff);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.02em;
        text-transform: uppercase;
        padding: 4px 10px;
        border-radius: 999px;
        cursor: pointer;
        box-shadow: inset 0 0 0 1px rgba(124, 50, 255, 0.25);
        transition: background 0.15s ease, color 0.15s ease;
    }

    .fa-pay-fill:hover {
        background: var(--fa-primary-soft, rgba(124, 50, 255, 0.08));
    }

    .fa-collect-month.is-selected .fa-collect-month__pay {
        flex-wrap: wrap;
        align-items: flex-end;
    }

    .fa-collect-month.is-mismatch {
        border-color: #f87171 !important;
        box-shadow: 0 0 0 3px rgba(248, 113, 113, 0.15) !important;
    }

    .fa-month-split {
        border-top: 1px solid rgba(124, 50, 255, 0.12);
        background: linear-gradient(180deg, rgba(124, 50, 255, 0.04), rgba(255, 255, 255, 0.9));
        padding: 14px 16px 16px;
        animation: faSplitIn 0.22s ease;
    }

    @keyframes faSplitIn {
        from { opacity: 0; transform: translateY(-4px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .fa-month-split__head {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-bottom: 12px;
    }

    .fa-month-split__head-left {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
    }

    .fa-month-split__icon {
        width: 32px;
        height: 32px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #fff;
        color: var(--fa-primary, #7c32ff);
        box-shadow: inset 0 0 0 1px rgba(124, 50, 255, 0.12);
        flex: 0 0 32px;
    }

    .fa-month-split__title {
        display: block;
        font-size: 13px;
        font-weight: 700;
        color: var(--fa-text, #1e293b);
        line-height: 1.2;
    }

    .fa-month-split__sub {
        display: block;
        font-size: 11px;
        color: var(--fa-muted, #64748b);
        margin-top: 1px;
    }

    .fa-split-badge {
        margin-left: auto;
        flex: 0 0 auto;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        padding: 3px 8px;
        border-radius: 999px;
        background: #fff;
        color: var(--fa-muted, #64748b);
        box-shadow: inset 0 0 0 1px #e2e8f0;
    }

    .fa-split-badge.is-custom {
        color: var(--fa-primary, #7c32ff);
        background: var(--fa-primary-soft, rgba(124, 50, 255, 0.08));
        box-shadow: inset 0 0 0 1px rgba(124, 50, 255, 0.2);
    }

    .fa-split-badge.is-full {
        color: #15803d;
        background: #ecfdf3;
        box-shadow: inset 0 0 0 1px #bbf7d0;
    }

    .fa-month-split__progress {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .fa-month-split__progress-track {
        height: 6px;
        border-radius: 999px;
        background: rgba(15, 23, 42, 0.08);
        overflow: hidden;
    }

    .fa-month-split__progress-fill {
        height: 100%;
        width: 0;
        border-radius: inherit;
        background: linear-gradient(90deg, var(--fa-primary, #7c32ff), #a855f7);
        transition: width 0.25s ease;
    }

    .fa-month-split__meta {
        font-size: 11.5px;
        font-weight: 600;
        color: var(--fa-muted, #64748b);
    }

    .fa-month-split__lines {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .fa-split-line {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 10px 12px;
        border-radius: 12px;
        background: #fff;
        box-shadow: inset 0 0 0 1px #eef2f7;
        transition: box-shadow 0.15s ease, background 0.15s ease;
    }

    .fa-split-line.is-active {
        box-shadow: inset 0 0 0 1.5px rgba(124, 50, 255, 0.35);
        background: rgba(124, 50, 255, 0.03);
    }

    .fa-split-line.is-zero-due {
        opacity: 0.62;
        background: #f8fafc;
    }

    .fa-split-line.is-zero-due .fa-split-line__dot {
        background: #cbd5e1;
    }

    .fa-split-line.is-zero-due .fa-split-line__name {
        color: var(--fa-muted, #64748b);
        font-weight: 600;
    }

    .fa-split-line__main {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        min-width: 0;
        flex: 1 1 auto;
    }

    .fa-split-line__dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--fa-primary, #7c32ff);
        margin-top: 5px;
        flex: 0 0 8px;
        opacity: 0.75;
    }

    .fa-split-line__info {
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .fa-split-line__name {
        font-size: 13px;
        font-weight: 700;
        color: var(--fa-text, #1e293b);
        line-height: 1.25;
        word-break: break-word;
    }

    .fa-split-line__due {
        font-size: 11px;
        color: var(--fa-muted, #64748b);
    }

    .fa-split-line__pay {
        flex: 0 0 auto;
        min-width: 108px;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 4px;
    }

    .fa-split-line__bar {
        width: 100%;
        max-width: 120px;
        height: 4px;
        border-radius: 999px;
        background: #edf2f7;
        overflow: hidden;
    }

    .fa-split-line__bar-fill {
        height: 100%;
        width: 0;
        border-radius: inherit;
        background: linear-gradient(90deg, #7c32ff, #c084fc);
        transition: width 0.25s ease;
    }

    .fa-split-line__amount {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        min-height: 28px;
    }

    .fa-month-split__paid-text {
        font-size: 13px;
        font-weight: 800;
        color: var(--fa-text, #1e293b);
        letter-spacing: -0.01em;
    }

    .fa-month-line-paid {
        max-width: 110px;
        margin: 0;
        text-align: right;
        font-weight: 700;
        font-size: 13px;
        border-radius: 8px !important;
        padding: 4px 8px !important;
        min-height: 32px;
    }

    .fa-month-split__footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-top: 12px;
        padding-top: 10px;
        border-top: 1px dashed #e2e8f0;
    }

    .fa-month-split__footer-label {
        font-size: 12px;
        font-weight: 600;
        color: var(--fa-muted, #64748b);
    }

    .fa-month-split__total {
        font-size: 15px;
        font-weight: 800;
        color: var(--fa-primary, #7c32ff);
        letter-spacing: -0.01em;
    }

    .fa-month-split__total.is-mismatch {
        color: #dc2626;
    }

    .fa-month-split__hint {
        margin: 8px 0 0;
        font-size: 11px;
        color: var(--fa-muted, #64748b);
    }

    .fa-collect-month.is-selected .fa-month-split[hidden] {
        display: none !important;
    }

    @media (max-width: 575px) {
        .fa-split-line {
            flex-direction: column;
            align-items: stretch;
        }

        .fa-split-line__pay {
            width: 100%;
            align-items: stretch;
        }

        .fa-split-line__bar {
            max-width: none;
        }

        .fa-split-line__amount {
            justify-content: space-between;
        }

        .fa-month-split__head-left {
            flex-wrap: wrap;
        }

        .fa-split-badge {
            margin-left: 0;
        }
    }
</style>
