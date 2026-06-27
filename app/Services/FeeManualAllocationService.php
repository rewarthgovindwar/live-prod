<?php

namespace App\Services;

use Illuminate\Http\Request;
use InvalidArgumentException;

class FeeManualAllocationService
{
    public function isManualMode(Request $request): bool
    {
        return $request->input('payment_allocation_mode') === 'manual';
    }

    public function hasManualGroupPayments(Request $request): bool
    {
        foreach ((array) $request->input('groups', []) as $group) {
            if ((float) ($group['paid_amount'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    /** @return array{line_payments: array<int, float>, payment_total: float} */
    public function extractFromGroups(Request $request): array
    {
        $linePayments = [];
        $total = 0.0;

        foreach ((array) $request->input('groups', []) as $group) {
            $paid = round((float) ($group['paid_amount'] ?? 0), 2);
            if ($paid <= 0) {
                continue;
            }

            $feesType = (int) ($group['feesType'] ?? 0);
            $linePayments[$feesType] = round(($linePayments[$feesType] ?? 0) + $paid, 2);
            $total += $paid;
        }

        return [
            'line_payments' => $linePayments,
            'payment_total' => round($total, 2),
        ];
    }

    public function resolveCollectTotal(Request $request): float
    {
        $collect = (float) $request->input('fiw_collect_amount', 0);
        if ($collect <= 0) {
            $collect = (float) $request->input('total_paid_amount', 0);
        }

        return round(max(0, $collect), 2);
    }

    /** @return array{line_payments: array<int, float>, payment_total: float} */
    public function validateAndExtract(Request $request): array
    {
        $extracted = $this->extractFromGroups($request);

        if ($extracted['payment_total'] <= 0) {
            throw new InvalidArgumentException('Enter how much goes to each fee type.');
        }

        $collectTotal = $this->resolveCollectTotal($request);
        if ($collectTotal > 0 && abs($extracted['payment_total'] - $collectTotal) > 0.02) {
            throw new InvalidArgumentException(
                'Allocated amounts must equal the collection amount (₹'.number_format($collectTotal, 2).').'
            );
        }

        foreach ($extracted['line_payments'] as $feesType => $paid) {
            if ($paid < 0) {
                throw new InvalidArgumentException('Payment amounts cannot be negative.');
            }
            if ($feesType < 0) {
                throw new InvalidArgumentException('Invalid fee type in payment allocation.');
            }
        }

        return $extracted;
    }

    /** @param array<int, float> $linePayments */
    public function mergePaidAmountsIntoRequest(Request $request, array $linePayments, float $paymentTotal): void
    {
        $paidAmounts = [];

        foreach ((array) $request->input('groups', []) as $key => $group) {
            $feesType = (int) ($group['feesType'] ?? 0);
            $paidAmounts[$key] = $linePayments[$feesType] ?? round((float) ($group['paid_amount'] ?? 0), 2);
        }

        if ($paidAmounts === []) {
            foreach ($request->input('fees_type', []) as $key => $type) {
                if ($type === 'preset') {
                    continue;
                }
                $paidAmounts[$key] = $linePayments[(int) $type] ?? 0;
            }
        }

        $request->merge([
            'paid_amount' => $paidAmounts,
            'total_paid_amount' => $paymentTotal,
            'add_wallet' => 0,
        ]);
    }
}
