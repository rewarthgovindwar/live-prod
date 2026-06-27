<?php

namespace App\Services;

use App\Models\SmBankAccount;
use App\Models\SmBankStatement;
use App\Models\SmPaymentMethhod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Fees\Entities\FmFeesInvoice;
use Modules\Fees\Entities\FmFeesTransaction;
use Modules\Fees\Entities\FmFeesTransactionChield;

class FeeMultiMonthCollectionService
{
    public function __construct(
        protected FeeInvoiceQueryService $queryService,
        protected FeeMonthlyPlanService $planService,
        protected FeeInvoiceBalanceService $balanceService,
        protected FeeReceiptNumberService $receiptNumbers
    ) {}

    /** @return \Illuminate\Support\Collection<int, FmFeesInvoice> */
    public function collectableInvoices(int $recordId, ?int $unitId = null)
    {
        $filters = ['record_id' => $recordId, 'outstanding_only' => true];
        if ($unitId) {
            $filters['unit_id'] = $unitId;
        }

        return $this->queryService->filtered($filters)
            ->with(['invoiceDetails.feesType'])
            ->get()
            ->filter(function (FmFeesInvoice $invoice) {
                return $this->invoiceBalance($invoice) > 0;
            })
            ->values();
    }

    /**
     * @param array<int, int> $invoiceIds
     * @return array{transaction_id: int, payment_batch_uuid: string, total: float, invoice_ids: array<int, int>, receipt_number: string}
     */
    public function collect(Request $request, array $invoiceIds): array
    {
        $invoiceIds = array_values(array_unique(array_map('intval', $invoiceIds)));
        if ($invoiceIds === []) {
            throw new \InvalidArgumentException('Select at least one month to collect.');
        }

        $idempotencyKey = trim((string) $request->input('idempotency_key', ''));
        if ($idempotencyKey !== '' && FeeSchemaCache::hasColumn('fm_fees_transactions', 'idempotency_key')) {
            $existing = FmFeesTransaction::query()
                ->where('school_id', Auth::user()->school_id)
                ->where('academic_id', getAcademicId())
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return [
                    'transaction_id' => (int) $existing->id,
                    'payment_batch_uuid' => (string) ($existing->payment_batch_uuid ?? ''),
                    'total' => round((float) ($existing->total_paid_amount ?: $existing->paid_amount), 2),
                    'invoice_ids' => $invoiceIds,
                    'receipt_number' => (string) ($existing->invoice_number ?: $existing->id),
                    'duplicate' => true,
                ];
            }
        }

        $paymentMethod = SmPaymentMethhod::where('method', $request->payment_method)
            ->where('school_id', Auth::user()->school_id)
            ->first();

        if (! $paymentMethod) {
            throw new \InvalidArgumentException('Invalid payment method.');
        }

        if ($request->payment_method === 'Bank' && ! $request->bank) {
            throw new \InvalidArgumentException('Select a bank account for bank payment.');
        }

        $batchUuid = (string) Str::uuid();
        $totalPaid = 0.0;
        $coveredMonths = [];

        return DB::transaction(function () use (
            $request, $invoiceIds, $paymentMethod, $batchUuid, $idempotencyKey, &$totalPaid, &$coveredMonths
        ) {
            $invoices = FmFeesInvoice::with('invoiceDetails')
                ->whereIn('id', $invoiceIds)
                ->where('school_id', Auth::user()->school_id)
                ->where('academic_id', getAcademicId())
                ->lockForUpdate()
                ->orderBy('due_date')
                ->get();

            if ($invoices->count() !== count($invoiceIds)) {
                throw new \InvalidArgumentException('One or more invoices could not be found.');
            }

            $studentId = (int) $invoices->first()->student_id;
            $recordId = (int) $invoices->first()->record_id;
            foreach ($invoices as $invoice) {
                if ((int) $invoice->student_id !== $studentId || (int) $invoice->record_id !== $recordId) {
                    throw new \InvalidArgumentException('All selected months must belong to the same student.');
                }
            }

            $amountOverrides = $this->normalizeAmountOverrides($request, $invoices);
            $lineOverrides = $this->normalizeLineAllocations($request, $invoices, $amountOverrides);
            $primaryInvoice = $invoices->first();

            $transaction = new FmFeesTransaction();
            $transaction->fees_invoice_id = $primaryInvoice->id;
            $transaction->payment_note = $request->payment_note ?? '';
            $transaction->payment_method = $request->payment_method;
            $transaction->bank_id = $request->bank;
            $transaction->student_id = $primaryInvoice->student_id;
            $transaction->record_id = $primaryInvoice->record_id;
            $transaction->user_id = Auth::id();
            $transaction->paid_status = 'approve';
            $transaction->school_id = Auth::user()->school_id;
            $transaction->academic_id = getAcademicId();

            if (FeeSchemaCache::hasColumn('fm_fees_transactions', 'payment_batch_uuid')) {
                $transaction->payment_batch_uuid = $batchUuid;
            }
            if ($idempotencyKey !== '' && FeeSchemaCache::hasColumn('fm_fees_transactions', 'idempotency_key')) {
                $transaction->idempotency_key = $idempotencyKey;
            }

            $manualReceipt = feeManualReceiptNumberFromRequest($request);
            $this->receiptNumbers->assign($transaction, $manualReceipt);
            $transaction->save();

            foreach ($invoices as $invoice) {
                $invoiceBalance = $this->invoiceBalance($invoice);
                if ($invoiceBalance <= 0) {
                    continue;
                }

                $collectForInvoice = array_key_exists($invoice->id, $amountOverrides)
                    ? min($amountOverrides[$invoice->id], $invoiceBalance)
                    : $invoiceBalance;

                if ($collectForInvoice <= 0) {
                    continue;
                }

                $invoiceTotal = 0.0;
                $remaining = round($collectForInvoice, 2);
                $dueChildren = $invoice->invoiceDetails
                    ->filter(fn ($c) => $this->collectibleChildDue($invoice, $c) > 0)
                    ->values();
                $lastIdx = $dueChildren->count() - 1;
                $invoiceLineOverrides = $lineOverrides[$invoice->id] ?? [];

                foreach ($dueChildren as $idx => $child) {
                    $childDue = $this->collectibleChildDue($invoice, $child);
                    $feesType = (int) $child->fees_type;

                    if (isset($invoiceLineOverrides[$feesType])) {
                        $applied = min(
                            round((float) $invoiceLineOverrides[$feesType], 2),
                            $childDue,
                            $remaining
                        );
                    } elseif ($idx === $lastIdx) {
                        $applied = min($remaining, $childDue);
                    } else {
                        $share = $invoiceBalance > 0
                            ? round(($childDue / $invoiceBalance) * $collectForInvoice, 2)
                            : 0.0;
                        $applied = min($share, $childDue, $remaining);
                    }

                    if ($applied <= 0) {
                        continue;
                    }

                    $child->paid_amount = (float) $child->paid_amount + $applied;
                    $child->due_amount = max(0, $childDue - $applied);
                    $child->save();

                    $txnChild = new FmFeesTransactionChield();
                    $txnChild->fees_transaction_id = $transaction->id;
                    if (FeeSchemaCache::hasColumn('fm_fees_transaction_chields', 'fees_invoice_id')) {
                        $txnChild->fees_invoice_id = $invoice->id;
                    }
                    $txnChild->fees_type = $child->fees_type;
                    $txnChild->weaver = (float) $child->weaver;
                    $txnChild->fine = (float) $child->fine;
                    $txnChild->paid_amount = $applied;
                    $txnChild->note = '';
                    $txnChild->school_id = Auth::user()->school_id;
                    $txnChild->academic_id = getAcademicId();
                    $txnChild->save();

                    $invoiceTotal += $applied;
                    $remaining = round($remaining - $applied, 2);
                }

                $invoice->refresh();
                $balance = $this->balanceService->sync($invoice);
                $fullyPaid = $balance <= 0.009;
                $invoice->payment_status = $fullyPaid ? 'paid' : 'partial';
                $invoice->payment_method = $request->payment_method;
                $invoice->save();

                $totalPaid += $invoiceTotal;

                $coveredMonths[] = [
                    'invoice_id' => (int) $invoice->id,
                    'invoice_number' => (string) $invoice->invoice_id,
                    'month_index' => $invoice->month_index,
                    'month_label' => $invoice->month_label,
                    'billing_period_key' => $invoice->billing_period_key,
                    'paid_amount' => round($invoiceTotal, 2),
                ];

                if ($invoice->unit_id && FeeSchemaCache::hasTable('fee_monthly_installments')) {
                    $plan = $this->planService->resolvePlanForInvoice($invoice);
                    if ($plan) {
                        $installment = $plan->installments()
                            ->where('fees_invoice_id', $invoice->id)
                            ->first();
                        if ($installment) {
                            $installment->update([
                                'status' => $fullyPaid ? 'paid' : 'pending',
                                'paid_amount' => (float) $installment->paid_amount + $invoiceTotal,
                                'transaction_id' => $transaction->id,
                            ]);
                        }
                    }
                }
            }

            if ($totalPaid <= 0) {
                throw new \InvalidArgumentException('Collection amount must be greater than zero.');
            }

            $transaction->total_paid_amount = round($totalPaid, getDecimalDigit());

            if (FeeSchemaCache::hasColumn('fm_fees_transactions', 'covered_months_json')) {
                $monthPayloads = array_map(fn (array $m) => [
                    'month_index' => $m['month_index'],
                    'month_label' => $m['month_label'],
                    'invoice_id' => $m['invoice_id'],
                    'paid_amount' => $m['paid_amount'],
                ], $coveredMonths);
                $transaction->covered_months_json = json_encode($monthPayloads);
            }

            if (FeeSchemaCache::hasColumn('fm_fees_transactions', 'months_from') && $coveredMonths !== []) {
                $first = $invoices->first();
                $last = $invoices->last();
                $transaction->months_from = $first->due_date;
                $transaction->months_to = $last->due_date;
            }

            $transaction->save();

            addIncome($request->payment_method, 'Fees Collect', $totalPaid, $transaction->id, Auth::id(), null);

            if ($request->payment_method === 'Bank') {
                $bank = SmBankAccount::where('id', $request->bank)
                    ->where('school_id', Auth::user()->school_id)
                    ->first();
                if ($bank) {
                    $after = $bank->current_balance + $totalPaid;
                    $stmt = new SmBankStatement();
                    $stmt->amount = $totalPaid;
                    $stmt->after_balance = $after;
                    $stmt->type = 1;
                    $stmt->details = 'Fees Payment';
                    $stmt->item_sell_id = $transaction->id;
                    $stmt->payment_date = date('Y-m-d');
                    $stmt->bank_id = $request->bank;
                    $stmt->school_id = Auth::user()->school_id;
                    $stmt->payment_method = $paymentMethod->id;
                    $stmt->save();
                    $bank->current_balance = $after;
                    $bank->save();
                }
            }

            if (function_exists('auditLog')) {
                auditLog('fees_collected', [
                    'user_id' => Auth::id(),
                    'transaction_id' => $transaction->id,
                    'receipt_number' => $transaction->invoice_number,
                    'total' => $totalPaid,
                    'invoice_ids' => $invoices->pluck('id')->all(),
                ]);
            }

            return [
                'transaction_id' => (int) $transaction->id,
                'payment_batch_uuid' => $batchUuid,
                'total' => round($totalPaid, 2),
                'invoice_ids' => $invoices->pluck('id')->map(fn ($id) => (int) $id)->all(),
                'receipt_number' => (string) ($transaction->invoice_number ?: $transaction->id),
            ];
        });
    }

    /**
     * @param array<int, float> $amountOverrides
     * @return array<int, array<int, float>>
     */
    protected function normalizeLineAllocations(Request $request, $invoices, array $amountOverrides): array
    {
        if ($request->input('payment_allocation_mode') !== 'manual') {
            return [];
        }

        $raw = $request->input('line_paid', []);
        if (! is_array($raw) || $raw === []) {
            return [];
        }

        $manualMode = $request->input('payment_allocation_mode') === 'manual';
        if ($manualMode && ! app(FeeManualAllocationService::class)->canManageAllocation()) {
            throw new \InvalidArgumentException('Only accounts staff can customize payment allocation.');
        }

        $overrides = [];
        foreach ($invoices as $invoice) {
            if (! array_key_exists($invoice->id, $raw) || ! is_array($raw[$invoice->id])) {
                continue;
            }

            $collectForInvoice = array_key_exists($invoice->id, $amountOverrides)
                ? $amountOverrides[$invoice->id]
                : $this->invoiceBalance($invoice);

            $invoiceLines = [];
            $lineTotal = 0.0;

            foreach ($raw[$invoice->id] as $feesType => $amount) {
                $paid = round((float) $amount, getDecimalDigit());
                if ($paid <= 0) {
                    continue;
                }
                $invoiceLines[(int) $feesType] = $paid;
                $lineTotal += $paid;
            }

            if ($invoiceLines === []) {
                continue;
            }

            if (abs($lineTotal - $collectForInvoice) > 0.05) {
                $label = $invoice->month_label ?: ('invoice '.$invoice->invoice_id);
                throw new \InvalidArgumentException(
                    'Allocation for '.$label.' ('.currency_format($lineTotal).') must equal the collection amount ('.currency_format($collectForInvoice).').'
                );
            }

            $overrides[$invoice->id] = $invoiceLines;
        }

        return $overrides;
    }

    /**
     * @return array<int, float>
     */
    protected function normalizeAmountOverrides(Request $request, $invoices): array
    {
        $raw = $request->input('amounts', []);
        if (! is_array($raw) || $raw === []) {
            return [];
        }

        $overrides = [];
        foreach ($invoices as $invoice) {
            if (! array_key_exists($invoice->id, $raw)) {
                continue;
            }

            $value = $raw[$invoice->id];
            if ($value === null || $value === '') {
                continue;
            }

            $amount = round((float) $value, getDecimalDigit());
            if ($amount < 0) {
                throw new \InvalidArgumentException('Collection amount cannot be negative.');
            }

            $balance = $this->invoiceBalance($invoice);
            if ($amount > $balance + 0.01) {
                $label = $invoice->month_label ?: ('invoice '.$invoice->invoice_id);
                throw new \InvalidArgumentException(
                    'Amount for '.$label.' ('.currency_format($amount).') exceeds the balance due ('.currency_format($balance).').'
                );
            }

            $overrides[$invoice->id] = $amount;
        }

        return $overrides;
    }

    public function invoiceBalance(FmFeesInvoice $invoice): float
    {
        return $this->queryService->balance($invoice);
    }

    protected function collectibleChildDue(FmFeesInvoice $invoice, $child): float
    {
        return $this->balanceService->collectibleLineDue($invoice, $child);
    }
}
