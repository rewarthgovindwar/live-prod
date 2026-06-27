<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\InvUom;
use App\Models\SmItem;
use App\Models\SmItemStore;
use App\Services\Inventory\InvKitchenSetupService;
use App\Services\Inventory\InventorySetupHealthService;
use App\Services\Inventory\InventorySettingsService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InvKitchenSetupController extends Controller
{
    public function __construct(
        protected InventorySetupHealthService $healthService,
        protected InvKitchenSetupService $setupService,
        protected InventorySettingsService $settingsService
    ) {}

    public function index()
    {
        $user = Auth::user();
        $schoolId = (int) $user->school_id;
        $kitchenCategoryId = $this->healthService->kitchenCategoryId($schoolId);

        return view('backEnd.inventory.kitchen.setup.wizard', [
            'health' => $this->healthService->report($schoolId, $kitchenCategoryId),
            'items' => $this->healthService->kitchenItemsQuery($schoolId, $kitchenCategoryId)->get(),
            'stores' => SmItemStore::where('school_id', $schoolId)->orderBy('store_name')->get(),
            'uoms' => InvUom::where('school_id', $schoolId)->where('is_active', true)->orderBy('name')->get(),
            'unitsEnabled' => (bool) config('units.enabled', true),
            'units' => config('units.enabled', true) ? accessibleUnits() : collect(),
            'behaviorProfiles' => config('kitchen.behavior_profiles', []),
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $schoolId = (int) $user->school_id;
        $action = $request->input('action');

        try {
            if ($action === 'bulk_opening') {
                $request->validate([
                    'store_id' => 'nullable|integer',
                    'item_id' => 'required|array',
                    'quantity' => 'required|array',
                ]);

                $lines = [];
                foreach ($request->item_id as $i => $itemId) {
                    $lines[] = [
                        'item_id' => $itemId,
                        'quantity' => $request->quantity[$i] ?? 0,
                    ];
                }

                $count = $this->setupService->applyBulkOpening(
                    $lines,
                    $user,
                    $request->filled('store_id') ? (int) $request->store_id : null,
                    $request->filled('location_id') ? (int) $request->location_id : null
                );

                Toastr::success(__('kitchen.opening_stock_applied', ['count' => $count]), __('common.success'));
            } elseif ($action === 'bulk_items') {
                $rows = [];
                foreach ((array) $request->input('item_id', []) as $i => $itemId) {
                    $rows[] = [
                        'item_id' => $itemId,
                        'default_uom_id' => $request->input("default_uom_id.{$i}"),
                        'reorder_level' => $request->input("reorder_level.{$i}"),
                        'reorder_qty' => $request->input("reorder_qty.{$i}"),
                        'behavior_profile' => $request->input("behavior_profile.{$i}"),
                    ];
                }

                $count = $this->setupService->updateBulkItems($schoolId, $rows);
                Toastr::success(__('kitchen.items_updated', ['count' => $count]), __('common.success'));
            } elseif ($action === 'sample_recipes') {
                $count = $this->setupService->importSampleRecipes(
                    $schoolId,
                    (int) $user->id,
                    $request->filled('location_id') ? (int) $request->location_id : null
                );
                Toastr::success(__('kitchen.sample_recipes_imported', ['count' => $count]), __('common.success'));
            } elseif ($action === 'mark_complete') {
                $this->settingsService->set($schoolId, 'kitchen_setup_complete', '1');
                Toastr::success(__('kitchen.setup_marked_complete'), __('common.success'));
            }
        } catch (\Throwable $e) {
            Toastr::error($e->getMessage(), __('common.failed'));
        }

        return redirect()->route('inv-kitchen-setup');
    }

    public function exportItems()
    {
        $user = Auth::user();
        $schoolId = (int) $user->school_id;
        $kitchenCategoryId = $this->healthService->kitchenCategoryId($schoolId);

        $items = $this->healthService->kitchenItemsQuery($schoolId, $kitchenCategoryId)->get();

        $filename = 'kitchen-items-'.date('Ymd').'.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($items) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'item_name', 'item_no', 'total_in_stock', 'reorder_level', 'reorder_qty', 'default_uom_id']);
            foreach ($items as $item) {
                fputcsv($out, [
                    $item->id,
                    $item->item_name,
                    $item->item_no,
                    $item->total_in_stock,
                    $item->reorder_level ?? '',
                    $item->reorder_qty ?? '',
                    $item->default_uom_id ?? '',
                ]);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }
}
