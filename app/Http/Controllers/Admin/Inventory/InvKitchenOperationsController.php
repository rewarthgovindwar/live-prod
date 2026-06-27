<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\InvKitchenUtilization;
use App\Models\SmItemStore;
use App\Models\SmStaff;
use App\Services\Inventory\KitchenDashboardService;
use App\Services\Inventory\KitchenUtilizationService;
use App\Services\Inventory\PurchaseRequisitionService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InvKitchenOperationsController extends Controller
{
    public function __construct(
        protected KitchenUtilizationService $utilizationService,
        protected KitchenDashboardService $dashboardService,
        protected PurchaseRequisitionService $purchaseService
    ) {}

    public function quickIssueForm()
    {
        $user = Auth::user();
        $schoolId = (int) $user->school_id;
        $unitsEnabled = (bool) config('units.enabled', true);

        return view('backEnd.inventory.kitchen.quick_issue', [
            'unitsEnabled' => $unitsEnabled,
            'units' => $unitsEnabled ? accessibleUnits() : collect(),
            'stores' => SmItemStore::where('school_id', $schoolId)->orderBy('store_name')->get(),
            'items' => $this->dashboardService->quickIssueItems($schoolId),
            'staffs' => SmStaff::where('school_id', $schoolId)->where('active_status', 1)->orderBy('full_name')->get(['id', 'full_name']),
            'mealServices' => config('kitchen.meal_services', []),
            'uomOptions' => config('inventory.uom_options', []),
            'defaultMeal' => $this->defaultMealKey(),
        ]);
    }

    public function quickIssueStore(Request $request)
    {
        $unitsEnabled = (bool) config('units.enabled', true);

        $rules = [
            'store_id' => 'required|integer|exists:sm_item_stores,id',
            'headcount' => 'required|integer|min:1',
            'approved_by_staff_id' => 'required|integer|exists:sm_staffs,id',
            'meal_service' => 'required|in:breakfast,lunch,supper,dinner',
            'item_id' => 'required|array|min:1',
            'quantity' => 'required|array',
        ];

        if ($unitsEnabled) {
            $rules['unit_id'] = 'required|integer|exists:units,id';
        }

        $request->validate($rules);

        $lines = [];
        foreach ((array) $request->item_id as $i => $itemId) {
            if (! $itemId) {
                continue;
            }
            $qty = (float) ($request->quantity[$i] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $lines[] = [
                'item_id' => (int) $itemId,
                'quantity' => $qty,
                'uom' => $request->input("uom.{$i}"),
            ];
        }

        if ($lines === []) {
            Toastr::error(__('kitchen.add_ingredients'), __('common.failed'));

            return back()->withInput();
        }

        try {
            $result = $this->utilizationService->processUtilization([
                'unit_id' => $unitsEnabled ? (int) $request->unit_id : null,
                'store_id' => (int) $request->store_id,
                'dish_name' => $request->input('dish_name') ?: __('kitchen.quick_issue_default_dish'),
                'headcount' => (int) $request->headcount,
                'served_to' => $request->input('served_to', 'mixed'),
                'approved_by_staff_id' => (int) $request->approved_by_staff_id,
                'utilization_at' => now()->toDateTimeString(),
                'meal_service' => $request->meal_service,
                'notes' => $request->input('notes'),
            ], $lines, [], Auth::user());

            Toastr::success(__('kitchen.utilization_recorded'), __('common.success'));

            return redirect()->route('inv-kitchen-utilization-show', $result['utilization']->id);
        } catch (\Throwable $e) {
            Toastr::error($e->getMessage(), __('common.failed'));

            return back()->withInput();
        }
    }

    public function repeatLast(Request $request)
    {
        $user = Auth::user();
        $schoolId = (int) $user->school_id;
        $unitId = $request->filled('unit_id') ? (int) $request->unit_id : null;

        $last = InvKitchenUtilization::with('lines')
            ->where('school_id', $schoolId)
            ->when($unitId, fn ($q) => $q->where('unit_id', $unitId))
            ->orderByDesc('utilization_at')
            ->first();

        if (! $last) {
            Toastr::warning(__('kitchen.no_previous_meal'), __('common.warning'));

            return redirect()->route('inv-kitchen-utilization-create');
        }

        $ingredientLines = $last->lines->map(fn ($line) => [
            'item_id' => $line->item_id,
            'quantity' => (float) $line->quantity_used,
            'uom' => $line->uom,
        ])->all();

        try {
            $result = $this->utilizationService->processUtilization([
                'unit_id' => $last->unit_id,
                'store_id' => $last->store_id,
                'recipe_id' => $last->recipe_id,
                'dish_name' => $last->displayTitle(),
                'headcount' => $last->headcount,
                'served_to' => $last->served_to,
                'approved_by_staff_id' => $last->approved_by_staff_id,
                'utilization_at' => now()->toDateTimeString(),
                'meal_service' => $last->meal_service,
                'notes' => __('kitchen.repeat_last_note', ['ref' => $last->reference_no]),
            ], $ingredientLines, [], $user);

            Toastr::success(__('kitchen.repeat_last_success', ['dish' => $last->displayTitle()]), __('common.success'));

            return redirect()->route('inv-kitchen-utilization-show', $result['utilization']->id);
        } catch (\Throwable $e) {
            Toastr::error($e->getMessage(), __('common.failed'));

            return redirect()->route('inv-kitchen');
        }
    }

    public function wasteForm()
    {
        $user = Auth::user();
        $schoolId = (int) $user->school_id;
        $health = app(\App\Services\Inventory\InventorySetupHealthService::class);

        return view('backEnd.inventory.kitchen.waste.create', [
            'unitsEnabled' => (bool) config('units.enabled', true),
            'units' => config('units.enabled', true) ? accessibleUnits() : collect(),
            'items' => $health->kitchenItemsQuery($schoolId)->get(['id', 'item_name', 'total_in_stock']),
            'staffs' => SmStaff::where('school_id', $schoolId)->where('active_status', 1)->orderBy('full_name')->get(['id', 'full_name']),
            'wasteReasons' => config('kitchen.waste_reasons', []),
        ]);
    }

    public function wasteStore(Request $request)
    {
        $request->validate([
            'item_id' => 'required|integer',
            'quantity' => 'required|numeric|min:0.001',
            'reason' => 'required|string|max:64',
            'approved_by_staff_id' => 'nullable|integer',
            'notes' => 'nullable|string|max:1000',
        ]);

        $user = Auth::user();
        $schoolId = (int) $user->school_id;
        $item = \App\Models\SmItem::where('school_id', $schoolId)->findOrFail((int) $request->item_id);
        $qty = (float) $request->quantity;

        if ((float) $item->total_in_stock < $qty) {
            Toastr::error(__('kitchen.insufficient_stock', ['item' => $item->item_name]), __('common.failed'));

            return back()->withInput();
        }

        DB::transaction(function () use ($user, $schoolId, $item, $qty, $request) {
            $stockBefore = (float) $item->total_in_stock;
            $stockAfter = $stockBefore - $qty;
            $item->total_in_stock = $stockAfter;
            $item->save();

            $wasteId = DB::table('inv_waste_logs')->insertGetId([
                'school_id' => $schoolId,
                'unit_id' => $request->input('unit_id'),
                'item_id' => $item->id,
                'quantity' => $qty,
                'uom' => $request->input('uom'),
                'reason' => $request->reason,
                'estimated_cost' => $request->input('estimated_cost'),
                'approved_by_staff_id' => $request->input('approved_by_staff_id'),
                'notes' => $request->input('notes'),
                'created_by' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('inv_stock_movements')->insert([
                'school_id' => $schoolId,
                'unit_id' => $request->input('unit_id'),
                'item_id' => $item->id,
                'store_id' => null,
                'quantity' => $qty,
                'direction' => 'out',
                'movement_type' => 'waste',
                'reference_type' => 'inv_waste_logs',
                'reference_id' => $wasteId,
                'notes' => $request->reason,
                'created_by' => $user->id,
                'academic_id' => getAcademicId(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Toastr::success(__('kitchen.waste_logged'), __('common.success'));

        return redirect()->route('inv-kitchen');
    }

    public function fastPurchaseForm()
    {
        $user = Auth::user();
        $schoolId = (int) $user->school_id;
        $health = app(\App\Services\Inventory\InventorySetupHealthService::class);

        return view('backEnd.inventory.kitchen.fast_purchase', [
            'unitsEnabled' => (bool) config('units.enabled', true),
            'units' => config('units.enabled', true) ? accessibleUnits() : collect(),
            'stores' => SmItemStore::where('school_id', $schoolId)->orderBy('store_name')->get(),
            'items' => $health->kitchenItemsQuery($schoolId)->get(['id', 'item_name', 'total_in_stock', 'reorder_qty', 'reorder_level']),
            'maxAmount' => app(\App\Services\Inventory\InventorySettingsService::class)->get($schoolId, 'kitchen_fast_purchase_max_amount', null, 5000),
        ]);
    }

    public function fastPurchaseStore(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:200',
            'item_id' => 'required|array|min:1',
            'quantity' => 'required|array',
        ]);

        $lines = [];
        foreach ((array) $request->item_id as $i => $itemId) {
            if (! $itemId) {
                continue;
            }
            $qty = (float) ($request->quantity[$i] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $item = \App\Models\SmItem::find($itemId);
            $lines[] = [
                'item_id' => (int) $itemId,
                'item_name' => $item?->item_name,
                'quantity' => $qty,
                'uom' => $request->input("uom.{$i}"),
                'estimated_unit_price' => (float) ($request->input("price.{$i}") ?? 0),
            ];
        }

        if ($lines === []) {
            Toastr::error(__('kitchen.add_ingredients'), __('common.failed'));

            return back()->withInput();
        }

        try {
            $requisition = $this->purchaseService->create([
                'unit_id' => $request->input('unit_id'),
                'store_id' => $request->input('store_id'),
                'title' => $request->title,
                'purpose' => __('kitchen.fast_purchase_purpose'),
                'urgency' => 'high',
                'required_date' => now()->addDays(2)->toDateString(),
                'department_note' => __('kitchen.fast_purchase_note'),
            ], $lines, Auth::user());

            $this->purchaseService->submitForApproval($requisition, Auth::user());

            Toastr::success(__('kitchen.fast_purchase_created'), __('common.success'));

            return redirect()->route('inv-purchase-show', $requisition->id);
        } catch (\Throwable $e) {
            Toastr::error($e->getMessage(), __('common.failed'));

            return back()->withInput();
        }
    }

    public function itemStock(Request $request)
    {
        $request->validate(['item_id' => 'required|integer']);
        $item = \App\Models\SmItem::where('school_id', Auth::user()->school_id)
            ->find((int) $request->item_id);

        if (! $item) {
            return response()->json(['status' => 'error'], 404);
        }

        return response()->json([
            'status' => 'success',
            'stock' => (float) $item->total_in_stock,
            'item_name' => $item->item_name,
            'reorder_level' => (float) ($item->reorder_level ?? 0),
        ]);
    }

    protected function defaultMealKey(): string
    {
        $hour = (int) now()->format('H');
        if ($hour < 10) {
            return 'breakfast';
        }
        if ($hour < 15) {
            return 'lunch';
        }
        if ($hour < 18) {
            return 'supper';
        }

        return 'dinner';
    }
}
