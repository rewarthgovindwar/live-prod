<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\InvKitchenUtilization;
use App\Models\Inventory\InvKitchenUtilizationLine;
use App\Models\SmItem;
use App\Services\Inventory\InventorySettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InvKitchenReportController extends Controller
{
    public function __construct(
        protected InventorySettingsService $settingsService
    ) {}

    /**
     * Paginated list of all kitchen utilizations.
     */
    public function utilizationList(Request $request)
    {
        $user = Auth::user();
        $unitsEnabled = (bool) config('units.enabled', true);
        $units = $unitsEnabled ? accessibleUnits() : collect();

        $query = InvKitchenUtilization::with(['unit', 'recipe', 'approvedByStaff', 'store'])
            ->where('school_id', $user->school_id)
            ->orderByDesc('utilization_at');

        if ($request->filled('unit_id')) {
            $query->where('unit_id', (int) $request->unit_id);
        }
        if ($request->filled('meal_service')) {
            $query->where('meal_service', $request->meal_service);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('utilization_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('utilization_at', '<=', $request->date_to);
        }

        $utilizations = $query->paginate(25)->withQueryString();

        return view('backEnd.inventory.kitchen.utilization.index', [
            'utilizations' => $utilizations,
            'unitsEnabled' => $unitsEnabled,
            'units' => $units,
            'mealServices' => config('kitchen.meal_services', []),
            'filters' => $request->only(['unit_id', 'meal_service', 'date_from', 'date_to']),
        ]);
    }

    /**
     * Daily report: all utilizations + per-item consumption summary for a single date.
     */
    public function daily(Request $request)
    {
        $user = Auth::user();
        $unitsEnabled = (bool) config('units.enabled', true);
        $units = $unitsEnabled ? accessibleUnits() : collect();

        $date = $request->input('date', today()->toDateString());
        $unitId = $request->filled('unit_id') ? (int) $request->unit_id : null;

        $utilizations = InvKitchenUtilization::with(['lines.item', 'unit', 'store', 'recipe', 'approvedByStaff'])
            ->where('school_id', $user->school_id)
            ->whereDate('utilization_at', $date)
            ->when($unitId, fn ($q) => $q->where('unit_id', $unitId))
            ->orderBy('utilization_at')
            ->get();

        $itemSummary = InvKitchenUtilizationLine::query()
            ->join('inv_kitchen_utilizations', 'inv_kitchen_utilization_lines.utilization_id', '=', 'inv_kitchen_utilizations.id')
            ->join('sm_items', 'inv_kitchen_utilization_lines.item_id', '=', 'sm_items.id')
            ->where('inv_kitchen_utilizations.school_id', $user->school_id)
            ->whereDate('inv_kitchen_utilizations.utilization_at', $date)
            ->when($unitId, fn ($q) => $q->where('inv_kitchen_utilizations.unit_id', $unitId))
            ->selectRaw('
                inv_kitchen_utilization_lines.item_id,
                sm_items.item_name,
                MAX(inv_kitchen_utilization_lines.uom) as uom,
                SUM(inv_kitchen_utilization_lines.quantity_used) as total_used,
                MIN(inv_kitchen_utilization_lines.stock_after) as stock_after,
                SUM(CASE WHEN inv_kitchen_utilization_lines.low_stock_alert = 1 THEN 1 ELSE 0 END) as alert_count
            ')
            ->groupBy('inv_kitchen_utilization_lines.item_id', 'sm_items.item_name')
            ->orderBy('sm_items.item_name')
            ->get();

        $totalHeadcount = $utilizations->sum('headcount');

        return view('backEnd.inventory.kitchen.reports.daily', [
            'date' => $date,
            'utilizations' => $utilizations,
            'itemSummary' => $itemSummary,
            'totalHeadcount' => $totalHeadcount,
            'unitsEnabled' => $unitsEnabled,
            'units' => $units,
            'selectedUnitId' => $unitId,
            'mealServices' => config('kitchen.meal_services', []),
        ]);
    }

    /**
     * Monthly report: consumption per item aggregated by month + daily headcount.
     */
    public function monthly(Request $request)
    {
        $user = Auth::user();
        $unitsEnabled = (bool) config('units.enabled', true);
        $units = $unitsEnabled ? accessibleUnits() : collect();

        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $unitId = $request->filled('unit_id') ? (int) $request->unit_id : null;

        $itemSummary = InvKitchenUtilizationLine::query()
            ->join('inv_kitchen_utilizations', 'inv_kitchen_utilization_lines.utilization_id', '=', 'inv_kitchen_utilizations.id')
            ->join('sm_items', 'inv_kitchen_utilization_lines.item_id', '=', 'sm_items.id')
            ->where('inv_kitchen_utilizations.school_id', $user->school_id)
            ->whereYear('inv_kitchen_utilizations.utilization_at', $year)
            ->whereMonth('inv_kitchen_utilizations.utilization_at', $month)
            ->when($unitId, fn ($q) => $q->where('inv_kitchen_utilizations.unit_id', $unitId))
            ->selectRaw('
                inv_kitchen_utilization_lines.item_id,
                sm_items.item_name,
                MAX(inv_kitchen_utilization_lines.uom) as uom,
                SUM(inv_kitchen_utilization_lines.quantity_used) as total_used,
                SUM(CASE WHEN inv_kitchen_utilization_lines.low_stock_alert = 1 THEN 1 ELSE 0 END) as alert_count
            ')
            ->groupBy('inv_kitchen_utilization_lines.item_id', 'sm_items.item_name')
            ->orderByDesc('total_used')
            ->get();

        $dailyHeadcount = InvKitchenUtilization::query()
            ->where('school_id', $user->school_id)
            ->whereYear('utilization_at', $year)
            ->whereMonth('utilization_at', $month)
            ->when($unitId, fn ($q) => $q->where('unit_id', $unitId))
            ->selectRaw('DATE(utilization_at) as date, SUM(headcount) as total_headcount, COUNT(*) as service_count')
            ->groupByRaw('DATE(utilization_at)')
            ->orderByRaw('DATE(utilization_at)')
            ->get();

        $mealBreakdown = InvKitchenUtilization::query()
            ->where('school_id', $user->school_id)
            ->whereYear('utilization_at', $year)
            ->whereMonth('utilization_at', $month)
            ->when($unitId, fn ($q) => $q->where('unit_id', $unitId))
            ->selectRaw('meal_service, COUNT(*) as cnt, SUM(headcount) as headcount')
            ->groupBy('meal_service')
            ->get()
            ->keyBy('meal_service');

        $monthlyTotals = [
            'services' => $dailyHeadcount->sum('service_count'),
            'headcount' => $dailyHeadcount->sum('total_headcount'),
            'items_used' => $itemSummary->count(),
        ];

        return view('backEnd.inventory.kitchen.reports.monthly', [
            'year' => $year,
            'month' => $month,
            'itemSummary' => $itemSummary,
            'dailyHeadcount' => $dailyHeadcount,
            'mealBreakdown' => $mealBreakdown,
            'monthlyTotals' => $monthlyTotals,
            'unitsEnabled' => $unitsEnabled,
            'units' => $units,
            'selectedUnitId' => $unitId,
            'mealServices' => config('kitchen.meal_services', []),
        ]);
    }

    /**
     * Low stock report: items currently below the configured threshold.
     */
    public function lowStock(Request $request)
    {
        $user = Auth::user();
        $unitsEnabled = (bool) config('units.enabled', true);

        $threshold = (float) $this->settingsService->get(
            $user->school_id,
            'kitchen_low_stock_threshold',
            null,
            config('kitchen.low_stock_threshold', 10)
        );

        $kitchenCategoryName = config('inventory.item_classification_rules.kitchen.category', 'Kitchen & Food');
        $kitchenCategoryId = DB::table('sm_item_categories')
            ->where('school_id', $user->school_id)
            ->where('category_name', $kitchenCategoryName)
            ->value('id');

        $itemsQuery = SmItem::where('school_id', $user->school_id)
            ->where('total_in_stock', '<', $threshold)
            ->orderBy('total_in_stock');

        if ($kitchenCategoryId) {
            $itemsQuery->where('item_category_id', $kitchenCategoryId);
        }

        $items = $itemsQuery->get(['id', 'item_name', 'item_no', 'total_in_stock']);

        $lastInbound = DB::table('inv_stock_movements')
            ->where('school_id', $user->school_id)
            ->where('direction', 'in')
            ->whereIn('item_id', $items->pluck('id'))
            ->selectRaw('item_id, MAX(created_at) as last_in_date, SUM(quantity) as total_in')
            ->groupBy('item_id')
            ->get()
            ->keyBy('item_id');

        $lastUsage = DB::table('inv_stock_movements')
            ->where('school_id', $user->school_id)
            ->where('direction', 'out')
            ->whereIn('item_id', $items->pluck('id'))
            ->selectRaw('item_id, MAX(created_at) as last_out_date')
            ->groupBy('item_id')
            ->get()
            ->keyBy('item_id');

        return view('backEnd.inventory.kitchen.reports.low_stock', [
            'items' => $items,
            'lastInbound' => $lastInbound,
            'lastUsage' => $lastUsage,
            'threshold' => $threshold,
            'unitsEnabled' => $unitsEnabled,
        ]);
    }
}
