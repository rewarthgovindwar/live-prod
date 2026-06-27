<?php

namespace Modules\Fees\Http\Controllers;

use App\Models\SmBankAccount;
use App\Models\SmPaymentMethhod;
use App\Services\FeeManualAllocationService;
use App\Services\FeeMultiMonthCollectionService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class FeeCollectController extends Controller
{
    public function show(int $recordId, FeeMultiMonthCollectionService $collection)
    {
        $unitId = request()->query('unit_id') ? (int) request()->query('unit_id') : null;
        $invoices = $collection->collectableInvoices($recordId, $unitId);

        if ($invoices->isEmpty()) {
            if (request()->boolean('modal') || request()->ajax()) {
                return response()->json(['message' => 'No due invoices for this student.'], 404);
            }

            Toastr::info('No due invoices for this student.', 'All clear');

            return redirect()->route('fees.fees-invoice-list');
        }

        $first = $invoices->first();
        $paymentMethods = SmPaymentMethhod::whereIn('method', ['Cash', 'Cheque', 'Bank', 'UPI'])
            ->where('school_id', Auth::user()->school_id)
            ->get();
        $banks = SmBankAccount::where('school_id', Auth::user()->school_id)->get();

        $data = [
            'invoices' => $invoices,
            'record' => $first->recordDetail,
            'student' => $first->studentInfo,
            'studentName' => $first->studentInfo->full_name ?? '',
            'unit' => $first->unit,
            'paymentMethods' => $paymentMethods,
            'banks' => $banks,
            'canOverrideReceipt' => feeCanEditReceiptNumber(),
            'canManageFeeAllocation' => app(FeeManualAllocationService::class)->canManageAllocation(),
        ];

        if (request()->boolean('modal') || request()->ajax()) {
            return view('fees::collect._modal', $data);
        }

        return view('fees::collect.sheet', $data);
    }

    public function store(Request $request, FeeMultiMonthCollectionService $collection)
    {
        $request->validate([
            'invoice_ids' => 'required|array|min:1',
            'invoice_ids.*' => 'integer',
            'amounts' => 'nullable|array',
            'amounts.*' => 'nullable|numeric|min:0',
            'line_paid' => 'nullable|array',
            'line_paid.*' => 'nullable|array',
            'line_paid.*.*' => 'nullable|numeric|min:0',
            'payment_allocation_mode' => 'nullable|in:auto,manual',
            'payment_method' => 'required|string',
            'bank' => 'required_if:payment_method,Bank',
            'manual_receipt_number' => 'nullable|string|max:120',
            'idempotency_key' => 'nullable|string|max:64',
        ]);

        $wantsJson = $request->ajax() || $request->wantsJson();

        try {
            $result = $collection->collect($request, $request->invoice_ids);

            $message = ! empty($result['duplicate'])
                ? 'This payment was already recorded.'
                : 'Collected '.currency_format($result['total']).' for '.count($result['invoice_ids']).' month(s). Receipt '.$result['receipt_number'].'.';

            if ($wantsJson) {
                return response()->json([
                    'success' => true,
                    'duplicate' => ! empty($result['duplicate']),
                    'message' => $message,
                    'transaction_id' => $result['transaction_id'],
                    'receipt_url' => route('fees.download.receipt', [
                        'transaction' => $result['transaction_id'],
                        'format' => 'combined',
                        'inline' => 1,
                    ]),
                ]);
            }

            if (! empty($result['duplicate'])) {
                Toastr::info('This payment was already recorded (duplicate submit prevented).', 'Already collected');
            } else {
                Toastr::success($message, 'Payment recorded');
            }

            return redirect()->route('fees.fees-invoice-list');
        } catch (\InvalidArgumentException $e) {
            if ($wantsJson) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            Toastr::warning($e->getMessage(), 'Could not collect');

            return redirect()->back()->withInput();
        }
    }
}
