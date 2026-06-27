<?php

namespace App\Services\Inventory;

use App\Models\Inventory\InvKitchenRecipe;
use App\Models\Inventory\InvKitchenRecipeLine;
use App\Models\SmItem;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InvKitchenSetupService
{
    public function __construct(
        protected InventoryStockService $stockService,
        protected InventorySetupHealthService $healthService,
        protected KitchenRecipeService $recipeService
    ) {}

    public function applyBulkOpening(array $lines, User $user, ?int $storeId = null, ?int $unitId = null): int
    {
        $applied = 0;
        $schoolId = (int) $user->school_id;

        DB::transaction(function () use ($lines, $user, $storeId, $unitId, $schoolId, &$applied) {
            foreach ($lines as $line) {
                $itemId = (int) ($line['item_id'] ?? 0);
                $qty = (float) ($line['quantity'] ?? 0);
                if ($itemId <= 0 || $qty <= 0) {
                    continue;
                }

                $this->stockService->postInbound(
                    $schoolId,
                    $unitId,
                    $itemId,
                    $storeId,
                    $qty,
                    'setup_opening',
                    'kitchen_setup',
                    0,
                    (int) $user->id,
                    'Kitchen setup wizard — opening stock'
                );
                $applied++;
            }
        });

        return $applied;
    }

    public function updateBulkItems(int $schoolId, array $rows): int
    {
        $updated = 0;

        foreach ($rows as $row) {
            $itemId = (int) ($row['item_id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }

            $item = SmItem::where('school_id', $schoolId)->where('id', $itemId)->first();
            if (! $item) {
                continue;
            }

            $data = [];
            if (Schema::hasColumn('sm_items', 'default_uom_id') && isset($row['default_uom_id']) && $row['default_uom_id'] !== '') {
                $data['default_uom_id'] = (int) $row['default_uom_id'];
            }
            if (Schema::hasColumn('sm_items', 'reorder_level') && isset($row['reorder_level']) && $row['reorder_level'] !== '') {
                $data['reorder_level'] = (float) $row['reorder_level'];
            }
            if (Schema::hasColumn('sm_items', 'reorder_qty') && isset($row['reorder_qty']) && $row['reorder_qty'] !== '') {
                $data['reorder_qty'] = (float) $row['reorder_qty'];
            }
            if (Schema::hasColumn('sm_items', 'behavior_profile') && ! empty($row['behavior_profile'])) {
                $data['behavior_profile'] = $row['behavior_profile'];
            }

            if ($data !== []) {
                $item->update($data);
                $updated++;
            }
        }

        return $updated;
    }

    public function importSampleRecipes(int $schoolId, int $userId, ?int $unitId = null): int
    {
        $templates = config('kitchen.sample_recipes', []);
        if ($templates === []) {
            return 0;
        }

        $kitchenCategoryId = $this->healthService->kitchenCategoryId($schoolId);
        $items = SmItem::where('school_id', $schoolId)
            ->when($kitchenCategoryId, fn ($q) => $q->where('item_category_id', $kitchenCategoryId))
            ->pluck('id', 'item_name');

        $created = 0;

        DB::transaction(function () use ($templates, $schoolId, $userId, $unitId, $items, &$created) {
            foreach ($templates as $tpl) {
                $exists = InvKitchenRecipe::where('school_id', $schoolId)
                    ->where('name', $tpl['name'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                $recipe = InvKitchenRecipe::create([
                    'school_id' => $schoolId,
                    'unit_id' => $unitId,
                    'name' => $tpl['name'],
                    'description' => $tpl['description'] ?? null,
                    'default_servings' => (int) ($tpl['default_servings'] ?? 50),
                    'meal_service' => $tpl['meal_service'] ?? 'lunch',
                    'is_active' => true,
                    'created_by' => $userId,
                ]);

                foreach ($tpl['lines'] as $line) {
                    $itemId = $this->resolveItemId($items, $line['item'] ?? '');
                    if (! $itemId) {
                        continue;
                    }

                    InvKitchenRecipeLine::create([
                        'recipe_id' => $recipe->id,
                        'item_id' => $itemId,
                        'quantity' => (float) ($line['quantity'] ?? 0),
                        'uom' => $line['uom'] ?? 'kg',
                    ]);
                }

                $created++;
            }
        });

        return $created;
    }

    protected function resolveItemId($items, string $needle): ?int
    {
        if ($needle === '') {
            return null;
        }

        if ($items->has($needle)) {
            return (int) $items->get($needle);
        }

        $lower = mb_strtolower($needle);
        foreach ($items as $name => $id) {
            if (mb_strtolower((string) $name) === $lower || str_contains(mb_strtolower((string) $name), $lower)) {
                return (int) $id;
            }
        }

        return null;
    }
}
