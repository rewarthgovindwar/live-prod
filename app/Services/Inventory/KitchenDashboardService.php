<?php

namespace App\Services\Inventory;

use App\Models\Inventory\InvKitchenUtilization;
use App\Models\Inventory\InvPurchaseRequisition;
use App\Models\SmItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class KitchenDashboardService
{
    public function __construct(
        protected KitchenUtilizationService $utilizationService,
        protected InventorySetupHealthService $healthService,
        protected InventorySettingsService $settingsService
    ) {}

    public function todaySnapshot(int $schoolId, ?int $unitId = null, ?int $kitchenCategoryId = null): array
    {
        $today = now()->toDateString();
        $kitchenCategoryId ??= $this->healthService->kitchenCategoryId($schoolId);

        $utilBase = InvKitchenUtilization::where('school_id', $schoolId)->whereDate('utilization_at', $today);
        if ($unitId) {
            $utilBase->where('unit_id', $unitId);
        }

        $todayServices = (clone $utilBase)->count();
        $todayHeadcount = (clone $utilBase)->sum('headcount');

        $threshold = (float) $this->settingsService->get(
            $schoolId,
            'kitchen_low_stock_threshold',
            null,
            config('kitchen.low_stock_threshold', 10)
        );

        $lowStockQuery = SmItem::where('school_id', $schoolId)
            ->where('total_in_stock', '<', $threshold)
            ->orderBy('total_in_stock');

        if ($kitchenCategoryId) {
            $lowStockQuery->where('item_category_id', $kitchenCategoryId);
        }

        $lowStockItems = $lowStockQuery->limit(12)->get(['id', 'item_name', 'total_in_stock']);

        $critical = $lowStockItems->filter(fn ($i) => (float) $i->total_in_stock <= 0)->count();

        $pendingPurchases = InvPurchaseRequisition::where('school_id', $schoolId)
            ->whereIn('status', ['draft', 'pending_approval'])
            ->when($unitId, fn ($q) => $q->where('unit_id', $unitId))
            ->count();

        $lastUtilization = InvKitchenUtilization::with(['lines', 'recipe'])
            ->where('school_id', $schoolId)
            ->when($unitId, fn ($q) => $q->where('unit_id', $unitId))
            ->orderByDesc('utilization_at')
            ->first();

        $stockMovements = DB::table('inv_stock_movements')
            ->join('sm_items', 'sm_items.id', '=', 'inv_stock_movements.item_id')
            ->where('inv_stock_movements.school_id', $schoolId)
            ->when($kitchenCategoryId, fn ($q) => $q->where('sm_items.item_category_id', $kitchenCategoryId))
            ->orderByDesc('inv_stock_movements.created_at')
            ->limit(10)
            ->get([
                'inv_stock_movements.id',
                'inv_stock_movements.direction',
                'inv_stock_movements.movement_type',
                'inv_stock_movements.quantity',
                'inv_stock_movements.created_at',
                'sm_items.item_name',
            ]);

        return [
            'today_services' => $todayServices,
            'today_headcount' => (int) $todayHeadcount,
            'low_stock_count' => $lowStockItems->count(),
            'critical_stock_count' => $critical,
            'pending_purchases' => $pendingPurchases,
            'low_stock_items' => $lowStockItems,
            'last_utilization' => $lastUtilization,
            'recent_movements' => $stockMovements,
            'next_meal' => $this->guessNextMeal(),
        ];
    }

    public function quickIssueItems(int $schoolId, ?int $kitchenCategoryId = null, int $limit = 20): Collection
    {
        $kitchenCategoryId ??= $this->healthService->kitchenCategoryId($schoolId);

        $q = SmItem::where('school_id', $schoolId)
            ->where('total_in_stock', '>', 0)
            ->orderByDesc('total_in_stock');

        if ($kitchenCategoryId) {
            $q->where('item_category_id', $kitchenCategoryId);
        }

        return $q->limit($limit)->get(['id', 'item_name', 'total_in_stock', 'default_uom_id']);
    }

    protected function guessNextMeal(): ?string
    {
        $hour = (int) now()->format('H');
        $services = config('kitchen.meal_services', []);

        if ($hour < 10) {
            return $services['breakfast'] ?? 'Breakfast';
        }
        if ($hour < 15) {
            return $services['lunch'] ?? 'Lunch';
        }
        if ($hour < 18) {
            return $services['supper'] ?? 'Supper';
        }

        return $services['dinner'] ?? 'Dinner';
    }
}
