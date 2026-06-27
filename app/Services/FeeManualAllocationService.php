<?php

namespace App\Services;

use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FeeManualAllocationService
{
    public function isManualMode(Request $request): bool
    {
        return $request->input('payment_allocation_mode') === 'manual';
    }

    public function canManageAllocation(?User $user = null): bool
    {
        $user = $user ?? auth()->user();
        if (! $user) {
            return false;
        }

        $roleId = (int) $user->role_id;

        if (isTrueSuperAdminRole($roleId) || isSuperDuperAdminRole($roleId)) {
            return true;
        }

        $accountantRoleId = DB::table('infix_roles')
            ->where('is_saas', 0)
            ->whereRaw('LOWER(name) = ?', ['accountant'])
            ->value('id');

        if ($accountantRoleId && $roleId === (int) $accountantRoleId) {
            return true;
        }

        foreach (['collect_fees', 'fees.fees-invoice', 'fees.fees-invoice-store', 'fees.fees-invoice-update'] as $route) {
            if (userPermission($route)) {
                return true;
            }
        }

        return false;
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

    /**
     * Split collection across fee lines by each line's share of the invoice total.
     *
     * @return array{line_payments: array<int, float>, payment_total: float}
     */
    public function computeProportionalFromGroups(Request $request, float $collectTotal): array
    {
        $collectTotal = round(max(0, $collectTotal), 2);
        if ($collectTotal <= 0) {
            return ['line_payments' => [], 'payment_total' => 0];
        }

        $weights = [];
        foreach ((array) $request->input('groups', []) as $group) {
            $feesType = (int) ($group['feesType'] ?? 0);
            $sub = (float) ($group['sub_total'] ?? 0);
            if ($sub <= 0) {
                $amount = (float) ($group['amount'] ?? 0);
                $weaver = (float) ($group['weaver'] ?? 0);
                $sub = max(0, $amount - $weaver);
            }
            if ($sub > 0) {
                $weights[$feesType] = round(($weights[$feesType] ?? 0) + $sub, 2);
            }
        }

        $weightTotal = array_sum($weights);
        if ($weightTotal <= 0) {
            return ['line_payments' => [], 'payment_total' => $collectTotal];
        }

        $linePayments = [];
        $allocated = 0.0;
        $keys = array_keys($weights);

        foreach ($keys as $index => $feesType) {
            if ($index === count($keys) - 1) {
                $linePayments[$feesType] = round($collectTotal - $allocated, 2);
            } else {
                $amount = round($collectTotal * ($weights[$feesType] / $weightTotal), 2);
                $linePayments[$feesType] = $amount;
                $allocated += $amount;
            }
        }

        return [
            'line_payments' => $linePayments,
            'payment_total' => $collectTotal,
        ];
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
