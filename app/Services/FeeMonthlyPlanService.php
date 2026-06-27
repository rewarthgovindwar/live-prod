<?php

namespace App\Services;

use App\Models\FeeMonthlyInstallment;
use App\Models\FeeStudentPlan;
use App\Models\SmAcademicYear;
use App\Models\SmStudent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Modules\Fees\Entities\FmFeesInvoice;
use Modules\Fees\Entities\FmFeesInvoiceChield;
use Modules\Fees\Entities\FmFeesTransaction;

class FeeMonthlyPlanService
{
    public function ensurePlan(
        int $studentId,
        int $recordId,
        int $unitId,
        string $serviceLine,
        float $monthlyAmount,
        ?int $schoolId = null,
        ?int $academicId = null,
        ?array $schedule = null
    ): FeeStudentPlan {
        $schoolId = $schoolId ?? (int) auth()->user()->school_id;
        $academicId = $academicId ?? (int) getAcademicId();
        $schedule = $this->normalizeSchedule($schedule, $academicId);

        $plan = FeeStudentPlan::firstOrCreate(
            [
                'record_id' => $recordId,
                'unit_id' => $unitId,
                'service_line' => $serviceLine,
                'academic_id' => $academicId,
            ],
            [
                'student_id' => $studentId,
                'monthly_amount' => $monthlyAmount,
                'school_id' => $schoolId,
                'year_start' => $this->yearStart($academicId),
                'year_end' => $this->yearEnd($academicId),
                'schedule_from_index' => $schedule['from_index'],
                'schedule_to_index' => $schedule['to_index'],
                'due_day' => $schedule['due_day'],
            ]
        );

        if ((float) $plan->monthly_amount !== $monthlyAmount && $monthlyAmount > 0) {
            $plan->monthly_amount = $monthlyAmount;
            $plan->save();
        }

        $scheduleChanged = (int) $plan->schedule_from_index !== $schedule['from_index']
            || (int) $plan->schedule_to_index !== $schedule['to_index']
            || (int) $plan->due_day !== $schedule['due_day'];

        if ($scheduleChanged) {
            $plan->schedule_from_index = $schedule['from_index'];
            $plan->schedule_to_index = $schedule['to_index'];
            $plan->due_day = $schedule['due_day'];
            $plan->save();
        }

        if ($plan->installments()->count() === 0 || ($scheduleChanged && ! $plan->installments()->where('status', 'paid')->exists())) {
            if ($scheduleChanged) {
                $plan->installments()->delete();
            }
            $this->seedInstallments($plan);
        }

        return $plan->fresh(['installments']);
    }

    public function applyPayment(FeeStudentPlan $plan, int $invoiceId, int $monthsPaid, float $paidTotal, ?int $transactionId = null): array
    {
        if ($monthsPaid < 1) {
            return [];
        }

        if ($transactionId) {
            return $this->allocateForTransaction($transactionId, $plan, $monthsPaid, $paidTotal, $invoiceId);
        }

        if (config('fee_presets.enforce_whole_months_only', false)) {
            $covered = [];
            for ($i = 0; $i < $monthsPaid; $i++) {
                $perMonth = $monthsPaid > 0 ? round($paidTotal / $monthsPaid, 2) : $paidTotal;
                $inst = $this->linkInstallment($plan, $invoiceId, true, $perMonth);
                if ($inst) {
                    $covered[] = $this->installmentPayload($inst, $perMonth);
                }
            }

            return $covered;
        }

        $pending = $plan->installments()
            ->where('status', 'pending')
            ->orderBy('month_index')
            ->limit($monthsPaid)
            ->get();

        $perMonth = $monthsPaid > 0 ? round($paidTotal / $monthsPaid, 2) : 0;
        $remaining = $paidTotal;
        $covered = [];

        foreach ($pending as $index => $installment) {
            $pay = ($index === $pending->count() - 1)
                ? round($remaining, 2)
                : $perMonth;
            $remaining -= $pay;

            $installment->update([
                'status' => 'paid',
                'paid_amount' => $pay,
                'fees_invoice_id' => $invoiceId,
                'reminder_sent_at' => now(),
            ]);
            $covered[] = $this->installmentPayload($installment->fresh(), $pay);
        }

        return $covered;
    }

    /** @return array<int, array<string, mixed>> */
    public function allocateForTransaction(
        int $transactionId,
        FeeStudentPlan $plan,
        int $monthsPaid,
        float $paidTotal,
        ?int $invoiceId = null
    ): array {
        $monthsPaid = max(1, $monthsPaid);
        $invoiceId = $invoiceId ?? (int) (FmFeesTransaction::find($transactionId)?->fees_invoice_id ?? 0);

        $pending = $plan->installments()
            ->where('status', 'pending')
            ->orderBy('month_index')
            ->limit($monthsPaid)
            ->get();

        if ($pending->isEmpty()) {
            return [];
        }

        $count = $pending->count();
        $perMonth = $count > 0 ? round($paidTotal / $count, 2) : 0;
        $remaining = $paidTotal;
        $covered = [];

        foreach ($pending as $index => $installment) {
            $pay = ($index === $count - 1) ? round($remaining, 2) : $perMonth;
            $remaining -= $pay;

            $update = [
                'status' => 'paid',
                'paid_amount' => $pay,
                'fees_invoice_id' => $invoiceId ?: $installment->fees_invoice_id,
                'reminder_sent_at' => now(),
            ];
            if (Schema::hasColumn('fee_monthly_installments', 'transaction_id')) {
                $update['transaction_id'] = $transactionId;
            }

            $installment->update($update);

            $covered[] = $this->installmentPayload($installment->fresh(), $pay);
        }

        $this->syncTransactionMetadata($transactionId, $plan, $covered);

        return $covered;
    }

    public function syncTransactionMetadata(int $transactionId, FeeStudentPlan $plan, array $covered): void
    {
        if (! Schema::hasTable('fm_fees_transactions') || empty($covered)) {
            return;
        }

        $transaction = FmFeesTransaction::withoutGlobalScopes()->find($transactionId);
        if (! $transaction) {
            return;
        }

        $first = $covered[0];
        $last = $covered[count($covered) - 1];

        $nextPending = $plan->installments()
            ->where('status', 'pending')
            ->orderBy('month_index')
            ->first();

        $outstanding = (float) $plan->installments()
            ->where('status', 'pending')
            ->sum('amount');

        $updates = [];
        if (Schema::hasColumn('fm_fees_transactions', 'covered_months_json')) {
            $updates['covered_months_json'] = $covered;
        }
        if (Schema::hasColumn('fm_fees_transactions', 'months_from')) {
            $updates['months_from'] = $first['due_date'] ?? null;
        }
        if (Schema::hasColumn('fm_fees_transactions', 'months_to')) {
            $updates['months_to'] = $last['due_date'] ?? null;
        }
        if (Schema::hasColumn('fm_fees_transactions', 'next_due_date')) {
            $updates['next_due_date'] = $nextPending?->due_date?->format('Y-m-d');
        }
        if (Schema::hasColumn('fm_fees_transactions', 'outstanding_after')) {
            $updates['outstanding_after'] = $outstanding;
        }

        if ($updates !== []) {
            $transaction->forceFill($updates)->save();
        }
    }

    /** @return Collection<int, FeeMonthlyInstallment> */
    public function installmentsForTransaction(int $transactionId): Collection
    {
        if (! Schema::hasTable('fee_monthly_installments')
            || ! Schema::hasColumn('fee_monthly_installments', 'transaction_id')) {
            return collect();
        }

        return FeeMonthlyInstallment::where('transaction_id', $transactionId)
            ->orderBy('month_index')
            ->get();
    }

    /** @return array<int, array<string, mixed>> */
    public function coveredMonthsForTransaction(FmFeesTransaction $transaction): array
    {
        if (Schema::hasColumn('fm_fees_transactions', 'covered_months_json')
            && ! empty($transaction->covered_months_json)) {
            $decoded = is_string($transaction->covered_months_json)
                ? json_decode($transaction->covered_months_json, true)
                : $transaction->covered_months_json;

            if (is_array($decoded) && $decoded !== []) {
                return $decoded;
            }
        }

        $fromDb = $this->installmentsForTransaction((int) $transaction->id);
        if ($fromDb->isNotEmpty()) {
            return $fromDb->map(fn (FeeMonthlyInstallment $i) => $this->installmentPayload($i, (float) $i->paid_amount))->all();
        }

        if (Schema::hasTable('fee_monthly_installments') && $transaction->fees_invoice_id) {
            $linked = FeeMonthlyInstallment::where('fees_invoice_id', $transaction->fees_invoice_id)
                ->orderBy('month_index')
                ->get();
            if ($linked->isNotEmpty()) {
                return $linked->map(fn (FeeMonthlyInstallment $i) => $this->installmentPayload($i, (float) $i->paid_amount))->all();
            }
        }

        return [];
    }

    public function formatPeriodLabel(array $coveredMonths, ?int $totalPlanMonths = null): string
    {
        if ($coveredMonths === []) {
            return '';
        }

        if ($totalPlanMonths && count($coveredMonths) >= $totalPlanMonths) {
            return 'संपूर्ण शैक्षणिक वर्ष';
        }

        if (count($coveredMonths) === 1) {
            return $coveredMonths[0]['month_label'] ?? '';
        }

        $first = $coveredMonths[0]['month_label'] ?? '';
        $last = $coveredMonths[count($coveredMonths) - 1]['month_label'] ?? '';

        return $first.' ते '.$last;
    }

    public function monthWiseEnabled(): bool
    {
        return Schema::hasTable('fee_student_plans')
            && (bool) config('fee_presets.month_wise_payment_only', true);
    }

    public function resolvePlanForInvoice(FmFeesInvoice $invoice): ?FeeStudentPlan
    {
        if (! Schema::hasTable('fee_student_plans')) {
            return null;
        }

        $unitId = (int) ($invoice->unit_id ?? 0);
        $serviceLine = (string) ($invoice->service_line ?? 'school');

        if ($unitId <= 0) {
            $student = SmStudent::find($invoice->student_id);
            $unitId = (int) ($student->unit_id ?? $student->hostel_unit_id ?? 0);
        }

        if ($unitId <= 0) {
            return null;
        }

        $plan = FeeStudentPlan::where('record_id', $invoice->record_id)
            ->where('unit_id', $unitId)
            ->where('service_line', $serviceLine)
            ->where('academic_id', $invoice->academic_id)
            ->first();

        if ($plan) {
            if ($plan->installments()->count() === 0) {
                $this->seedInstallments($plan);
            }

            return $plan->fresh(['installments']);
        }

        $lines = FmFeesInvoiceChield::where('fees_invoice_id', $invoice->id)->get();
        $totalDue = (float) $lines->sum('due_amount');
        $months = $this->academicMonthCount($invoice->academic_id);
        $monthly = $totalDue > 0 ? round($totalDue / max(1, $months), 2) : 0;

        if ($monthly <= 0) {
            return null;
        }

        return $this->ensurePlan(
            (int) $invoice->student_id,
            (int) $invoice->record_id,
            $unitId,
            $serviceLine,
            $monthly,
            (int) $invoice->school_id,
            (int) $invoice->academic_id
        );
    }

    /** @return array<string, mixed>|null */
    public function buildPaymentViewData(FmFeesInvoice $invoice): ?array
    {
        if (! $this->monthWiseEnabled()) {
            return null;
        }

        $plan = $this->resolvePlanForInvoice($invoice);
        if (! $plan) {
            return null;
        }

        $pending = $plan->installments()->where('status', 'pending')->orderBy('month_index')->get();
        $paid = $plan->installments()->where('status', 'paid')->orderBy('month_index')->get();

        return [
            'plan' => $plan,
            'monthly_amount' => max(
                (float) $plan->monthly_amount,
                $this->invoiceMonthlyLineTotal($invoice, FmFeesInvoiceChield::where('fees_invoice_id', $invoice->id)->get())
            ),
            'pending_count' => $pending->count(),
            'pending_months' => $pending,
            'paid_months' => $paid,
            'paid_through' => $paid->last()?->month_label,
            'next_due' => $pending->first()?->due_date?->format('d M Y'),
        ];
    }

    /** Monthly preset total from invoice line amounts (authoritative for collections). */
    public function invoiceMonthlyLineTotal(FmFeesInvoice $invoice, ?iterable $lines = null, array $presetFeeTypeIds = []): float
    {
        $lines = $lines instanceof Collection
            ? $lines
            : collect($lines ?? FmFeesInvoiceChield::where('fees_invoice_id', $invoice->id)->get());

        if ($presetFeeTypeIds === [] && $invoice->unit_id) {
            $presetFeeTypeIds = collect(
                app(UnitFeePresetService::class)->linesForUnit(
                    (int) $invoice->unit_id,
                    (int) $invoice->class_id,
                    (int) $invoice->student_id,
                    true
                )
            )->pluck('fees_type_id')->map(fn ($id) => (int) $id)->all();
        }

        if ($presetFeeTypeIds !== []) {
            $presetTotal = (float) $lines
                ->filter(fn ($line) => in_array((int) $line->fees_type, $presetFeeTypeIds, true))
                ->sum('amount');

            if ($presetTotal > 0) {
                return $presetTotal;
            }
        }

        return (float) $lines->sum('amount');
    }

    /** Remaining due attributable to the next monthly collection (not full-year balance). */
    public function currentMonthBalanceDue(FmFeesInvoice $invoice, iterable $details, array $presetFeeTypeIds): float
    {
        $lines = $details instanceof Collection ? $details : collect($details);

        $presetRemaining = (float) $lines
            ->filter(fn ($line) => (int) $line->fees_type !== 0
                && ($presetFeeTypeIds === [] || in_array((int) $line->fees_type, $presetFeeTypeIds, true)))
            ->sum(function ($line) {
                $due = max(0, (float) $line->due_amount);
                $monthly = max(0, (float) $line->amount);

                if ($due <= 0) {
                    return 0.0;
                }

                return $monthly > 0 ? min($monthly, $due) : $due;
            });

        $extraRemaining = (float) $lines
            ->filter(fn ($line) => (int) $line->fees_type === 0
                || ($presetFeeTypeIds !== [] && ! in_array((int) $line->fees_type, $presetFeeTypeIds, true)))
            ->sum(fn ($line) => max(0, (float) $line->due_amount));

        return round($presetRemaining + $extraRemaining, 2);
    }

    /** @return array<string, mixed> */
    public function prepareMonthWisePayment(FmFeesInvoice $invoice, int $monthsPaid): array
    {
        $plan = $this->resolvePlanForInvoice($invoice);
        if (! $plan) {
            throw new \InvalidArgumentException('Monthly fee plan not found for this invoice.');
        }

        $pending = $plan->installments()->where('status', 'pending')->orderBy('month_index')->get();
        $pendingCount = $pending->count();

        if ($pendingCount === 0) {
            throw new \InvalidArgumentException('All months are already paid for this academic year.');
        }

        $monthsPaid = max(1, min($monthsPaid, $pendingCount));

        $lines = FmFeesInvoiceChield::where('fees_invoice_id', $invoice->id)->get();
        $presetFeeTypeIds = collect(
            $invoice->unit_id
                ? app(UnitFeePresetService::class)->linesForUnit(
                    (int) $invoice->unit_id,
                    (int) $invoice->class_id,
                    (int) $invoice->student_id,
                    true
                )
                : []
        )->pluck('fees_type_id')->map(fn ($id) => (int) $id)->all();

        $monthlyLineTotal = $this->invoiceMonthlyLineTotal($invoice, $lines, $presetFeeTypeIds);
        if ($monthlyLineTotal > 0 && abs($monthlyLineTotal - (float) $plan->monthly_amount) > 0.009) {
            $plan->monthly_amount = $monthlyLineTotal;
            $plan->save();
        }

        $paymentTotal = round($monthlyLineTotal * $monthsPaid, 2);
        if ($paymentTotal <= 0) {
            $totalDue = (float) $lines->sum('due_amount');
            $paymentTotal = round($totalDue / max(1, $pendingCount) * $monthsPaid, 2);
        }

        $extraDue = (float) $lines
            ->filter(fn ($line) => (int) $line->fees_type === 0
                || ($presetFeeTypeIds !== [] && ! in_array((int) $line->fees_type, $presetFeeTypeIds, true)))
            ->sum(fn ($line) => max(0, (float) $line->due_amount));

        if ($extraDue > 0) {
            $paymentTotal = round($paymentTotal + $extraDue, 2);
        }

        $allocWeights = [];
        foreach ($lines as $line) {
            $weight = (float) $line->amount;
            if ($weight <= 0) {
                $weight = max(0, (float) $line->due_amount);
            }
            $allocWeights[(int) $line->fees_type] = $weight;
        }
        $weightTotal = array_sum($allocWeights) ?: max(0.01, (float) $lines->sum('due_amount'));

        $linePayments = [];
        $allocated = 0.0;
        $lineKeys = array_keys($allocWeights);

        foreach ($lineKeys as $index => $feesType) {
            if ($index === count($lineKeys) - 1) {
                $linePayments[$feesType] = round($paymentTotal - $allocated, 2);
            } else {
                $amount = round($paymentTotal * ($allocWeights[$feesType] / $weightTotal), 2);
                $linePayments[$feesType] = $amount;
                $allocated += $amount;
            }
        }

        $perMonthPaid = $monthsPaid > 0 ? round($paymentTotal / $monthsPaid, 2) : $paymentTotal;
        $selected = $pending->take($monthsPaid)->values();
        $coveredPayload = $selected->map(fn (FeeMonthlyInstallment $i) => $this->installmentPayload($i, $perMonthPaid))->all();

        return [
            'plan' => $plan,
            'months_paid' => $monthsPaid,
            'payment_total' => $paymentTotal,
            'line_payments' => $linePayments,
            'period_label' => $this->formatPeriodLabel($coveredPayload, $this->academicMonthCount((int) $invoice->academic_id)),
            'pending_count' => $pendingCount,
        ];
    }

    /** @return array<string, mixed>|null */
    public function applyMonthWiseToRequest(Request $request, FmFeesInvoice $invoice): ?array
    {
        $collectPrep = $this->applyCollectAllocationsToRequest($request, $invoice);
        if ($collectPrep !== null) {
            return $collectPrep;
        }

        if (! $this->monthWiseEnabled()) {
            return null;
        }

        $monthsPaid = (int) $request->input('months_to_pay', $request->input('pay_months', 0));
        if ($monthsPaid < 1) {
            throw new \InvalidArgumentException('Select how many months to pay.');
        }

        $prep = $this->prepareMonthWisePayment($invoice, $monthsPaid);

        $paidAmounts = [];
        foreach ($request->input('fees_type', []) as $key => $type) {
            $paidAmounts[$key] = $prep['line_payments'][(int) $type] ?? 0;
        }

        $request->merge([
            'paid_amount' => $paidAmounts,
            'total_paid_amount' => $prep['payment_total'],
            'add_wallet' => 0,
        ]);

        foreach ($request->input('fees_type', []) as $key => $type) {
            $extra = $request->input('extraAmount', []);
            $extra[$key] = 0;
            $request->merge(['extraAmount' => $extra]);
        }

        return $prep;
    }

    /** @return array<string, mixed>|null */
    public function applyCollectAllocationsToRequest(Request $request, ?FmFeesInvoice $invoice = null): ?array
    {
        $manualAllocator = app(FeeManualAllocationService::class);
        $collectTotal = $manualAllocator->resolveCollectTotal($request);

        if ($collectTotal <= 0) {
            return null;
        }

        if ($manualAllocator->isManualMode($request)) {
            if (! $manualAllocator->canManageAllocation()) {
                throw new \InvalidArgumentException('Only accounts staff can customize payment allocation.');
            }
            $extracted = $manualAllocator->validateAndExtract($request);
        } else {
            $extracted = $manualAllocator->computeProportionalFromGroups($request, $collectTotal);
        }

        $manualAllocator->mergePaidAmountsIntoRequest(
            $request,
            $extracted['line_payments'],
            $extracted['payment_total']
        );

        $monthsPaid = max(1, (int) $request->input('months_to_pay', $request->input('pay_months', 1)));
        $prep = null;

        if ($invoice) {
            $plan = $this->resolvePlanForInvoice($invoice);
            if ($plan) {
                $pending = $plan->installments()->where('status', 'pending')->orderBy('month_index')->get();
                $monthsPaid = min($monthsPaid, max(1, $pending->count()));
                $perMonthPaid = $monthsPaid > 0
                    ? round($extracted['payment_total'] / $monthsPaid, 2)
                    : $extracted['payment_total'];
                $selected = $pending->take($monthsPaid)->values();
                $coveredPayload = $selected
                    ->map(fn (FeeMonthlyInstallment $i) => $this->installmentPayload($i, $perMonthPaid))
                    ->all();

                $prep = [
                    'plan' => $plan,
                    'months_paid' => $monthsPaid,
                    'payment_total' => $extracted['payment_total'],
                    'line_payments' => $extracted['line_payments'],
                    'period_label' => $this->formatPeriodLabel(
                        $coveredPayload,
                        $this->academicMonthCount((int) $invoice->academic_id)
                    ),
                    'pending_count' => $pending->count(),
                ];
            }
        }

        foreach ($request->input('fees_type', []) as $key => $type) {
            $extra = $request->input('extraAmount', []);
            $extra[$key] = 0;
            $request->merge(['extraAmount' => $extra]);
        }

        return $prep;
    }

    /** @return array<string, mixed>|null */
    public function applyManualAllocationsToRequest(Request $request, ?FmFeesInvoice $invoice = null): ?array
    {
        return $this->applyCollectAllocationsToRequest($request, $invoice);
    }

    public function finalizeTransactionPayment(int $transactionId, FmFeesInvoice $invoice, ?array $prep): void
    {
        if (! $prep || ! Schema::hasTable('fee_monthly_installments')) {
            return;
        }

        $this->allocateForTransaction(
            $transactionId,
            $prep['plan'],
            (int) $prep['months_paid'],
            (float) $prep['payment_total'],
            (int) $invoice->id
        );
    }

    protected function installmentPayload(FeeMonthlyInstallment $installment, float $paidAmount): array
    {
        return [
            'installment_id' => $installment->id,
            'month_index' => $installment->month_index,
            'month_label' => $installment->month_label,
            'due_date' => $installment->due_date?->format('Y-m-d'),
            'amount' => (float) $installment->amount,
            'paid_amount' => $paidAmount,
        ];
    }

    public function linkInstallment(
        FeeStudentPlan $plan,
        int $invoiceId,
        bool $markPaid,
        float $paidAmount = 0
    ): ?FeeMonthlyInstallment {
        $installment = $plan->installments()
            ->where('status', 'pending')
            ->whereNull('fees_invoice_id')
            ->orderBy('month_index')
            ->first();

        if (! $installment) {
            return null;
        }

        $update = ['fees_invoice_id' => $invoiceId];
        if ($markPaid && $paidAmount > 0) {
            $update['status'] = 'paid';
            $update['paid_amount'] = $paidAmount;
            $update['reminder_sent_at'] = now();
        }

        $installment->update($update);

        return $installment->fresh();
    }

    public function monthLabelForInvoice(int $invoiceId): ?string
    {
        if (! Schema::hasTable('fee_monthly_installments')) {
            return null;
        }

        return FeeMonthlyInstallment::where('fees_invoice_id', $invoiceId)->value('month_label');
    }

    /** @return \Illuminate\Support\Collection<int, FeeMonthlyInstallment> */
    public function dueForReminder(?int $schoolId = null)
    {
        if (! Schema::hasTable('fee_monthly_installments')) {
            return collect();
        }

        $daysBefore = (int) config('fee_presets.reminder_days_before', 3);
        $today = Carbon::today();
        $windowEnd = $today->copy()->addDays($daysBefore);

        $q = FeeMonthlyInstallment::with(['plan.unit', 'plan'])
            ->where('status', 'pending')
            ->whereNull('reminder_sent_at')
            ->whereBetween('due_date', [$today, $windowEnd]);

        if ($schoolId) {
            $q->where('school_id', $schoolId);
        }

        return $q->get();
    }

    public function sendDueReminder(FeeMonthlyInstallment $installment): bool
    {
        $student = SmStudent::with('parents')->find($installment->plan->student_id);
        if (! $student) {
            return false;
        }

        $unitLabel = $installment->plan->unit
            ? app(HostelPlacementService::class)->unitLabel($installment->plan->unit)
            : 'Fees';

        $data = [
            'student_name' => $student->full_name,
            'fees_name' => $unitLabel.' — '.$installment->month_label,
            'due_amount' => $installment->amount,
            'date' => $installment->due_date->format('d M Y'),
            'due_date' => $installment->due_date->format('d M Y'),
        ];

        $sent = false;
        if ($student->parents?->guardians_mobile) {
            @send_sms($student->parents->guardians_mobile, 'student_dues_fees_for_parent', $data);
            $sent = true;
        }
        if ($student->mobile) {
            @send_sms($student->mobile, 'student_fees_due', $data);
            $sent = true;
        }

        $installment->update(['reminder_sent_at' => now()]);

        return $sent;
    }

    protected function seedInstallments(FeeStudentPlan $plan): void
    {
        $schedule = $this->buildScheduleForPlan($plan);

        foreach ($schedule as $month) {
            FeeMonthlyInstallment::create([
                'plan_id' => $plan->id,
                'month_index' => $month['month_index'],
                'month_label' => $month['month_label'],
                'due_date' => $month['due_date'],
                'amount' => $plan->monthly_amount,
                'paid_amount' => 0,
                'status' => 'pending',
                'school_id' => $plan->school_id,
            ]);
        }
    }

    /** @return array<int, array<string, mixed>> */
    protected function buildScheduleForPlan(FeeStudentPlan $plan): array
    {
        $maxMonths = $this->academicMonthCount((int) $plan->academic_id);

        return $this->defaultAcademicSchedule(
            (int) $plan->academic_id,
            (int) ($plan->schedule_from_index ?: 1),
            (int) ($plan->schedule_to_index ?: $maxMonths),
            (int) ($plan->due_day ?: config('fee_presets.monthly_due_day', 25))
        );
    }

    /** Use academic session start date (e.g. June). Falls back to configured start month. */
    protected function yearStart(int $academicId): string
    {
        $year = SmAcademicYear::find($academicId);
        if ($year?->starting_date) {
            return Carbon::parse($year->starting_date)->toDateString();
        }

        $startMonth = max(1, min(12, (int) config('fee_presets.academic_start_month', 6)));
        $now = Carbon::now();
        $sessionYear = $now->month >= $startMonth ? $now->year : $now->year - 1;

        return Carbon::create($sessionYear, $startMonth, 1)->toDateString();
    }

    /** Use academic session end date (e.g. March). Falls back to session length from config. */
    protected function yearEnd(int $academicId): string
    {
        $year = SmAcademicYear::find($academicId);
        if ($year?->ending_date) {
            return Carbon::parse($year->ending_date)->toDateString();
        }

        $months = max(1, (int) config('fee_presets.academic_months', 10));
        $start = Carbon::parse($this->yearStart($academicId));

        return $start->copy()->addMonths($months - 1)->endOfMonth()->toDateString();
    }

    public function academicMonthCount(?int $academicId = null): int
    {
        $academicId = $academicId ?? (int) getAcademicId();

        return max(1, count($this->academicMonthLabels($academicId)));
    }

    /** 1-based index of the current calendar month within the active academic session. */
    public function currentAcademicMonthIndex(?int $academicId = null): int
    {
        $academicId = $academicId ?? (int) getAcademicId();
        $start = Carbon::parse($this->yearStart($academicId))->startOfMonth();
        $end = Carbon::parse($this->yearEnd($academicId))->startOfMonth();
        $now = Carbon::now()->startOfMonth();

        if ($now->lt($start)) {
            return 1;
        }

        if ($now->gt($end)) {
            return $this->academicMonthCount($academicId);
        }

        $index = 1;
        $current = $start->copy();
        while ($current->lte($end)) {
            if ($current->equalTo($now)) {
                return $index;
            }
            $current->addMonth();
            $index++;
        }

        return 1;
    }

    /** @return array<int, array{index: int, label: string}> */
    protected function academicMonthLabels(int $academicId): array
    {
        $start = Carbon::parse($this->yearStart($academicId))->startOfMonth();
        $end = Carbon::parse($this->yearEnd($academicId))->startOfMonth();
        $months = [];
        $current = $start->copy();
        $index = 1;

        while ($current <= $end) {
            $months[] = [
                'index' => $index,
                'label' => $current->format('F Y'),
            ];
            $current->addMonth();
            $index++;
        }

        return $months;
    }

    /**
     * Academic-year month schedule for invoice generation UI.
     *
     * @return array<int, array{month_index: int, month_label: string, due_date: string, due_display: string, status: string}>
     */
    public function invoiceMonthSchedule(
        ?int $recordId = null,
        ?int $unitId = null,
        string $serviceLine = 'school',
        ?int $academicId = null,
        ?array $schedule = null
    ): array {
        $academicId = $academicId ?? (int) getAcademicId();
        $schedule = $this->normalizeSchedule($schedule, $academicId);

        if ($recordId && $unitId) {
            $plan = FeeStudentPlan::where('record_id', $recordId)
                ->where('unit_id', $unitId)
                ->where('service_line', $serviceLine)
                ->where('academic_id', $academicId)
                ->first();

            if ($plan && $plan->installments()->count() > 0) {
                return $plan->installments()->orderBy('month_index')->get()->map(fn (FeeMonthlyInstallment $i) => [
                    'month_index' => (int) $i->month_index,
                    'month_label' => (string) $i->month_label,
                    'due_date' => $i->due_date?->format('Y-m-d') ?? '',
                    'due_display' => $i->due_date?->format('d M Y') ?? '',
                    'status' => (string) $i->status,
                ])->all();
            }
        }

        return $this->defaultAcademicSchedule(
            $academicId,
            $schedule['from_index'],
            $schedule['to_index'],
            $schedule['due_day']
        );
    }

    /** @param array<int, array<string, mixed>> $schedule */
    public function invoiceScheduleStartIndex(array $schedule): int
    {
        foreach ($schedule as $index => $month) {
            if (($month['status'] ?? 'pending') === 'pending') {
                return (int) $index;
            }
        }

        return 0;
    }

    /** @param array<int, array<string, mixed>> $schedule */
    public function formatInvoiceDueRange(array $schedule, int $months, int $startIndex = 0): string
    {
        if ($months < 1 || $schedule === []) {
            return '—';
        }

        $slice = array_slice($schedule, $startIndex, $months);
        if ($slice === []) {
            return '—';
        }

        $first = $slice[0]['due_display'] ?? '';
        $last = $slice[count($slice) - 1]['due_display'] ?? $first;

        return $months === 1 ? $first : ($first.' – '.$last);
    }

    /** @return array<int, array{month_index: int, month_label: string, due_date: string, due_display: string, status: string}> */
    public function defaultAcademicSchedule(
        int $academicId,
        int $fromIndex = 1,
        ?int $toIndex = null,
        ?int $dueDay = null
    ): array {
        $maxMonths = $this->academicMonthCount($academicId);
        $toIndex = $toIndex ?? $maxMonths;
        $fromIndex = max(1, min($maxMonths, $fromIndex));
        $toIndex = max($fromIndex, min($maxMonths, $toIndex));
        $dueDay = max(1, min(28, $dueDay ?? (int) config('fee_presets.monthly_due_day', 25)));
        $start = Carbon::parse($this->yearStart($academicId));
        $schedule = [];

        for ($i = $fromIndex - 1; $i < $toIndex; $i++) {
            $period = $start->copy()->addMonths($i);
            $dueDate = $period->copy()->day(min($dueDay, $period->daysInMonth));
            $schedule[] = [
                'month_index' => $i + 1,
                'month_label' => $period->format('F Y'),
                'due_date' => $dueDate->format('Y-m-d'),
                'due_display' => $dueDate->format('d M Y'),
                'status' => 'pending',
            ];
        }

        return $schedule;
    }

    /** @return array{title: string, year_suffix: string, start_date: string, end_date: string, month_count: int, months: array<int, array{index: int, label: string}>, default_due_day: int} */
    public function academicYearMeta(?int $academicId = null): array
    {
        $academicId = $academicId ?? (int) getAcademicId();
        $year = SmAcademicYear::find($academicId);
        $start = Carbon::parse($this->yearStart($academicId));
        $end = Carbon::parse($this->yearEnd($academicId));
        $months = $this->academicMonthLabels($academicId);

        $title = $year?->title
            ?: ($start->format('Y').'-'.$end->format('y'));

        $yearSuffix = $year?->ending_date
            ? Carbon::parse($year->ending_date)->format('y')
            : $end->format('y');

        return [
            'title' => $title,
            'year_suffix' => $yearSuffix,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'month_count' => count($months),
            'months' => $months,
            'default_due_day' => (int) config('fee_presets.monthly_due_day', 25),
        ];
    }

    /** @return array{from_index: int, to_index: int, due_day: int} */
    public function normalizeSchedule(?array $schedule, ?int $academicId = null): array
    {
        $academicId = $academicId ?? (int) getAcademicId();
        $maxMonths = $this->academicMonthCount($academicId);
        $from = max(1, min($maxMonths, (int) ($schedule['from_index'] ?? $schedule['schedule_from_index'] ?? 1)));
        $to = max($from, min($maxMonths, (int) ($schedule['to_index'] ?? $schedule['schedule_to_index'] ?? $maxMonths)));
        $dueDay = max(1, min(28, (int) ($schedule['due_day'] ?? $schedule['fee_due_day'] ?? config('fee_presets.monthly_due_day', 25))));

        return [
            'from_index' => $from,
            'to_index' => $to,
            'due_day' => $dueDay,
        ];
    }

    public function scheduleFromRequest(\Illuminate\Http\Request $request): array
    {
        return $this->normalizeSchedule([
            'from_index' => $request->input('schedule_from_index'),
            'to_index' => $request->input('schedule_to_index'),
            'due_day' => $request->input('fee_due_day'),
        ]);
    }

    /**
     * Print/view metadata: monthly receipt when unpaid, schedule due date, billed months.
     *
     * @return array{
     *     schedule_due_date: ?string,
     *     schedule_due_display: string,
     *     payment_months_label: string,
     *     contract_period_label: string,
     *     preset_fee_type_ids: array<int, int>,
     *     display_mode: string,
     *     monthly_due_amount: float,
     *     display_balance_due: float,
     *     total_outstanding: float
     * }
     */
    public function invoicePrintContext(FmFeesInvoice $invoice): array
    {
        $academicId = (int) ($invoice->academic_id ?? getAcademicId());
        $schedule = $this->invoiceMonthSchedule(
            $invoice->record_id ? (int) $invoice->record_id : null,
            $invoice->unit_id ? (int) $invoice->unit_id : null,
            (string) ($invoice->service_line ?? 'school'),
            $academicId,
            $this->normalizeSchedule([
                'from_index' => $invoice->schedule_from_index,
                'to_index' => $invoice->schedule_to_index,
                'due_day' => $invoice->fee_due_day,
            ], $academicId)
        );

        $payMonths = max(1, (int) ($invoice->pay_months ?? 1));
        $startIndex = max(0, (int) ($invoice->schedule_from_index ?? 1) - 1);
        $slice = array_slice($schedule, $startIndex, $payMonths);

        $firstDue = $slice[0]['due_date'] ?? null;
        $contractPeriodLabel = $this->formatPaymentMonthsLabel($slice);

        $presetFeeTypeIds = [];
        if ($invoice->unit_id) {
            $presetFeeTypeIds = collect(
                app(UnitFeePresetService::class)->linesForUnit(
                    (int) $invoice->unit_id,
                    (int) $invoice->class_id,
                    (int) $invoice->student_id,
                    true
                )
            )->pluck('fees_type_id')->map(fn ($id) => (int) $id)->all();
        }

        $details = FmFeesInvoiceChield::where('fees_invoice_id', $invoice->id)->get();
        $monthlySum = (float) $details->sum('amount');
        $grandTotal = (float) $details->sum('sub_total');
        if ($grandTotal <= 0) {
            $grandTotal = max(0, ($monthlySum * $payMonths) - (float) $details->sum('weaver'));
        }
        $totalPaid = (float) $details->sum('paid_amount');
        $totalOutstanding = max(0, $grandTotal - $totalPaid);

        $paymentMonthsLabel = $contractPeriodLabel;
        $dueDisplay = $firstDue ? dateConvert($firstDue) : '';
        $displayMode = 'period';
        $monthlyDueAmount = $monthlySum;
        $displayBalanceDue = $totalOutstanding;

        if ($totalOutstanding > 0 && ($payMonths > 1 || $invoice->unit_id)) {
            $nextInstallment = $this->nextPendingInstallmentForInvoice($invoice);
            $plan = $this->resolvePlanForInvoice($invoice);

            if ($nextInstallment || $slice !== []) {
                $displayMode = 'monthly';
                $paymentMonthsLabel = $nextInstallment
                    ? $this->abbreviateMonthLabel($this->installmentMonthSource($nextInstallment))
                    : $this->abbreviateMonthFromScheduleItem($slice[0] ?? []);

                $dueDateRaw = $nextInstallment?->due_date ?? ($slice[0]['due_date'] ?? null);
                if ($dueDateRaw) {
                    $dueDisplay = dateConvert($dueDateRaw instanceof Carbon ? $dueDateRaw->format('Y-m-d') : (string) $dueDateRaw);
                }

                if ($plan && (float) $plan->monthly_amount > 0) {
                    $monthlyDueAmount = (float) $plan->monthly_amount;
                } elseif ($monthlySum > 0) {
                    $monthlyDueAmount = $monthlySum;
                }

                $lineMonthlyTotal = $this->invoiceMonthlyLineTotal($invoice, $details, $presetFeeTypeIds);
                if ($lineMonthlyTotal > $monthlyDueAmount) {
                    $monthlyDueAmount = $lineMonthlyTotal;
                }

                $displayBalanceDue = $this->currentMonthBalanceDue($invoice, $details, $presetFeeTypeIds);
            }
        }

        return [
            'schedule_due_date' => $firstDue,
            'schedule_due_display' => $dueDisplay,
            'payment_months_label' => $paymentMonthsLabel,
            'contract_period_label' => $contractPeriodLabel,
            'preset_fee_type_ids' => $presetFeeTypeIds,
            'display_mode' => $displayMode,
            'monthly_due_amount' => round($monthlyDueAmount, 2),
            'display_balance_due' => round($displayBalanceDue, 2),
            'total_outstanding' => round($totalOutstanding, 2),
        ];
    }

    protected function nextPendingInstallmentForInvoice(FmFeesInvoice $invoice): ?FeeMonthlyInstallment
    {
        if (! Schema::hasTable('fee_monthly_installments')) {
            return null;
        }

        $linked = FeeMonthlyInstallment::where('fees_invoice_id', $invoice->id)
            ->where('status', 'pending')
            ->orderBy('month_index')
            ->first();

        if ($linked) {
            return $linked;
        }

        $plan = $this->resolvePlanForInvoice($invoice);
        if (! $plan) {
            return null;
        }

        return $plan->installments()
            ->where('status', 'pending')
            ->orderBy('month_index')
            ->first();
    }

    public function invoiceLinePaymentMonth(FmFeesInvoice $invoice, FmFeesInvoiceChield $line, array $presetFeeTypeIds, string $paymentMonthsLabel): string
    {
        if ((int) $line->fees_type === 0) {
            return 'One-time';
        }

        if ($invoice->unit_id
            && ($invoice->pay_months ?? 0) > 0
            && in_array((int) $line->fees_type, $presetFeeTypeIds, true)) {
            return $paymentMonthsLabel !== '—' ? $paymentMonthsLabel : 'Monthly';
        }

        return 'One-time';
    }

    /** @param array<int, array<string, mixed>> $slice */
    protected function formatPaymentMonthsLabel(array $slice): string
    {
        if ($slice === []) {
            return '—';
        }

        if (count($slice) === 1) {
            return $this->abbreviateMonthFromScheduleItem($slice[0]);
        }

        $first = $this->abbreviateMonthFromScheduleItem($slice[0]);
        $last = $this->abbreviateMonthFromScheduleItem($slice[count($slice) - 1]);

        return ($first !== '' && $first !== '—') ? ($first.' – '.$last) : '—';
    }

    /** @param array<string, mixed> $item */
    public function abbreviateMonthFromScheduleItem(array $item): string
    {
        if (! empty($item['due_date'])) {
            try {
                return Carbon::parse((string) $item['due_date'])->format('M');
            } catch (\Throwable) {
                // Fall back to month_label below.
            }
        }

        return $this->abbreviateMonthLabel((string) ($item['month_label'] ?? ''));
    }

    protected function installmentMonthSource(FeeMonthlyInstallment $installment): string
    {
        if ($installment->due_date) {
            try {
                return $installment->due_date instanceof Carbon
                    ? $installment->due_date->format('Y-m-d')
                    : (string) $installment->due_date;
            } catch (\Throwable) {
                // Fall back to month_label below.
            }
        }

        return (string) ($installment->month_label ?? '');
    }

    public function abbreviateMonthLabel(string $label): string
    {
        $label = trim($label);
        if ($label === '' || $label === '—') {
            return '—';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $label)) {
            try {
                return Carbon::parse($label)->format('M');
            } catch (\Throwable) {
                // Continue with broader parsing below.
            }
        }

        try {
            return Carbon::parse($label)->format('M');
        } catch (\Throwable) {
            $monthWord = preg_split('/\s+/', $label)[0] ?? $label;

            try {
                return Carbon::parse($monthWord)->format('M');
            } catch (\Throwable) {
                return $monthWord;
            }
        }
    }
}
