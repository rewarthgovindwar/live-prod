<?php

namespace App\Services\Inventory;

use App\Models\Inventory\InvKitchenRecipe;
use App\Models\Inventory\InvKitchenUtilization;
use App\Models\Inventory\InvPurchaseRequisition;
use App\Models\SmItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InventorySetupHealthService
{
    public function report(int $schoolId, ?int $kitchenCategoryId = null): array
    {
        $kitchenCategoryId ??= $this->kitchenCategoryId($schoolId);
        $base = SmItem::where('school_id', $schoolId);

        if ($kitchenCategoryId) {
            $kitchenItems = (clone $base)->where('item_category_id', $kitchenCategoryId);
        } else {
            $kitchenItems = clone $base;
        }

        $totalKitchen = (clone $kitchenItems)->count();
        $withStock = (clone $kitchenItems)->where('total_in_stock', '>', 0)->count();
        $withReorder = Schema::hasColumn('sm_items', 'reorder_level')
            ? (clone $kitchenItems)->whereNotNull('reorder_level')->where('reorder_level', '>', 0)->count()
            : 0;
        $withUom = Schema::hasColumn('sm_items', 'default_uom_id')
            ? (clone $kitchenItems)->whereNotNull('default_uom_id')->count()
            : 0;
        $uncategorized = (clone $base)->whereNull('item_category_id')->count();

        $checks = [
            ['key' => 'items_categorized', 'label' => 'Items categorized', 'done' => $uncategorized === 0, 'detail' => $uncategorized === 0 ? 'All items have categories' : "{$uncategorized} uncategorized"],
            ['key' => 'opening_stock', 'label' => 'Opening stock entered', 'done' => $totalKitchen > 0 && $withStock >= max(1, (int) ($totalKitchen * 0.5)), 'detail' => "{$withStock}/{$totalKitchen} kitchen items have stock"],
            ['key' => 'uom_assigned', 'label' => 'Units of measure assigned', 'done' => $totalKitchen === 0 || $withUom >= max(1, (int) ($totalKitchen * 0.5)), 'detail' => "{$withUom}/{$totalKitchen} kitchen items have UOM"],
            ['key' => 'reorder_levels', 'label' => 'Reorder levels set', 'done' => $totalKitchen === 0 || $withReorder >= max(1, (int) ($totalKitchen * 0.3)), 'detail' => "{$withReorder}/{$totalKitchen} items have reorder level"],
            ['key' => 'recipes', 'label' => 'Recipes created', 'done' => InvKitchenRecipe::where('school_id', $schoolId)->where('is_active', true)->exists(), 'detail' => (string) InvKitchenRecipe::where('school_id', $schoolId)->count().' recipes'],
            ['key' => 'first_utilization', 'label' => 'First meal logged', 'done' => InvKitchenUtilization::where('school_id', $schoolId)->exists(), 'detail' => (string) InvKitchenUtilization::where('school_id', $schoolId)->count().' utilizations'],
            ['key' => 'vendors', 'label' => 'Vendors configured', 'done' => DB::table('sm_suppliers')->where('school_id', $schoolId)->exists(), 'detail' => (string) DB::table('sm_suppliers')->where('school_id', $schoolId)->count().' vendors'],
            ['key' => 'stores', 'label' => 'Stores configured', 'done' => DB::table('sm_item_stores')->where('school_id', $schoolId)->exists(), 'detail' => (string) DB::table('sm_item_stores')->where('school_id', $schoolId)->count().' stores'],
        ];

        $completed = collect($checks)->where('done', true)->count();
        $score = count($checks) > 0 ? (int) round(($completed / count($checks)) * 100) : 0;

        return [
            'score' => $score,
            'completed' => $completed,
            'total' => count($checks),
            'checks' => $checks,
            'stats' => [
                'kitchen_items' => $totalKitchen,
                'with_stock' => $withStock,
                'zero_stock' => max(0, $totalKitchen - $withStock),
                'recipes' => InvKitchenRecipe::where('school_id', $schoolId)->count(),
                'utilizations' => InvKitchenUtilization::where('school_id', $schoolId)->count(),
                'pending_purchases' => InvPurchaseRequisition::where('school_id', $schoolId)->whereIn('status', ['draft', 'pending_approval'])->count(),
            ],
        ];
    }

    public function kitchenCategoryId(int $schoolId): ?int
    {
        $name = config('inventory.item_classification_rules.kitchen.category', 'Kitchen & Food');

        $id = DB::table('sm_item_categories')
            ->where('school_id', $schoolId)
            ->where('category_name', $name)
            ->value('id');

        return $id ? (int) $id : null;
    }

    public function kitchenItemsQuery(int $schoolId, ?int $kitchenCategoryId = null)
    {
        $kitchenCategoryId ??= $this->kitchenCategoryId($schoolId);
        $q = SmItem::where('school_id', $schoolId)->orderBy('item_name');

        if ($kitchenCategoryId) {
            $q->where('item_category_id', $kitchenCategoryId);
        }

        return $q;
    }
}
