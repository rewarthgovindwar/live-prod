<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\RolePermission\Entities\AssignPermission;

return new class extends Migration
{
    private int $schoolId = 1;

    /** @var list<string> */
    private array $assignRoles = [
        'Admin', 'Super Admin', 'Super Duper Admin', 'Principal', 'Support Staff', 'Kitchen & Food',
    ];

    public function up(): void
    {
        $this->createUomTables();
        $this->extendSmItems();
        $this->createWasteAndMenuTables();
        $this->seedDefaultUoms();
        $this->seedPermissions();
        $this->seedSettings();
        $this->assignPermissions();
        Cache::flush();
    }

    private function createUomTables(): void
    {
        if (! Schema::hasTable('inv_uoms')) {
            Schema::create('inv_uoms', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('school_id');
                $table->string('code', 16);
                $table->string('name', 64);
                $table->string('symbol', 16)->nullable();
                $table->string('type', 32)->default('count');
                $table->unsignedTinyInteger('decimal_places')->default(2);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['school_id', 'code'], 'inv_uoms_school_code');
            });
        }

        if (! Schema::hasTable('inv_uom_conversions')) {
            Schema::create('inv_uom_conversions', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('school_id');
                $table->unsignedBigInteger('from_uom_id');
                $table->unsignedBigInteger('to_uom_id');
                $table->decimal('factor', 20, 6);
                $table->timestamps();
                $table->unique(['school_id', 'from_uom_id', 'to_uom_id'], 'inv_uom_conv_unique');
            });
        }
    }

    private function extendSmItems(): void
    {
        Schema::table('sm_items', function (Blueprint $table) {
            if (! Schema::hasColumn('sm_items', 'default_uom_id')) {
                $table->unsignedBigInteger('default_uom_id')->nullable()->after('unit_id');
            }
            if (! Schema::hasColumn('sm_items', 'purchase_uom_id')) {
                $table->unsignedBigInteger('purchase_uom_id')->nullable()->after('default_uom_id');
            }
            if (! Schema::hasColumn('sm_items', 'issue_uom_id')) {
                $table->unsignedBigInteger('issue_uom_id')->nullable()->after('purchase_uom_id');
            }
            if (! Schema::hasColumn('sm_items', 'reorder_level')) {
                $table->decimal('reorder_level', 20, 3)->nullable()->after('total_in_stock');
            }
            if (! Schema::hasColumn('sm_items', 'reorder_qty')) {
                $table->decimal('reorder_qty', 20, 3)->nullable()->after('reorder_level');
            }
            if (! Schema::hasColumn('sm_items', 'preferred_supplier_id')) {
                $table->unsignedInteger('preferred_supplier_id')->nullable()->after('reorder_qty');
            }
            if (! Schema::hasColumn('sm_items', 'behavior_profile')) {
                $table->string('behavior_profile', 32)->nullable()->after('preferred_supplier_id');
            }
            if (! Schema::hasColumn('sm_items', 'last_unit_cost')) {
                $table->decimal('last_unit_cost', 20, 4)->nullable()->after('behavior_profile');
            }
        });
    }

    private function createWasteAndMenuTables(): void
    {
        if (! Schema::hasTable('inv_waste_logs')) {
            Schema::create('inv_waste_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('school_id');
                $table->unsignedBigInteger('unit_id')->nullable();
                $table->unsignedInteger('item_id');
                $table->decimal('quantity', 20, 3);
                $table->string('uom', 32)->nullable();
                $table->string('reason', 64);
                $table->decimal('estimated_cost', 20, 2)->nullable();
                $table->unsignedInteger('approved_by_staff_id')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();
                $table->index(['school_id', 'created_at'], 'inv_waste_logs_idx');
            });
        }

        if (! Schema::hasTable('inv_menu_plans')) {
            Schema::create('inv_menu_plans', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('school_id');
                $table->unsignedBigInteger('unit_id')->nullable();
                $table->date('plan_date');
                $table->string('meal_service', 32);
                $table->unsignedBigInteger('recipe_id')->nullable();
                $table->string('dish_name', 200)->nullable();
                $table->unsignedInteger('planned_headcount')->default(0);
                $table->text('notes')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();
                $table->index(['school_id', 'plan_date', 'meal_service'], 'inv_menu_plans_idx');
            });
        }
    }

    private function seedDefaultUoms(): void
    {
        if (! Schema::hasTable('inv_uoms')) {
            return;
        }

        $schools = DB::table('sm_schools')->pluck('id');
        $defaults = [
            ['code' => 'kg', 'name' => 'Kilogram', 'symbol' => 'kg', 'type' => 'weight', 'decimal_places' => 3],
            ['code' => 'g', 'name' => 'Gram', 'symbol' => 'g', 'type' => 'weight', 'decimal_places' => 0],
            ['code' => 'l', 'name' => 'Litre', 'symbol' => 'L', 'type' => 'volume', 'decimal_places' => 2],
            ['code' => 'ml', 'name' => 'Millilitre', 'symbol' => 'ml', 'type' => 'volume', 'decimal_places' => 0],
            ['code' => 'nos', 'name' => 'Piece', 'symbol' => 'Nos', 'type' => 'count', 'decimal_places' => 0],
            ['code' => 'sack', 'name' => 'Sack', 'symbol' => 'Sack', 'type' => 'pack', 'decimal_places' => 0],
            ['code' => 'pkt', 'name' => 'Packet', 'symbol' => 'Pkt', 'type' => 'pack', 'decimal_places' => 0],
            ['code' => 'bag', 'name' => 'Bag', 'symbol' => 'Bag', 'type' => 'pack', 'decimal_places' => 0],
            ['code' => 'box', 'name' => 'Box', 'symbol' => 'Box', 'type' => 'pack', 'decimal_places' => 0],
            ['code' => 'doz', 'name' => 'Dozen', 'symbol' => 'Doz', 'type' => 'count', 'decimal_places' => 0],
            ['code' => 'cyl', 'name' => 'Cylinder', 'symbol' => 'Cyl', 'type' => 'count', 'decimal_places' => 0],
        ];

        foreach ($schools as $schoolId) {
            $ids = [];
            foreach ($defaults as $row) {
                $existing = DB::table('inv_uoms')
                    ->where('school_id', $schoolId)
                    ->where('code', $row['code'])
                    ->value('id');

                if ($existing) {
                    $ids[$row['code']] = (int) $existing;

                    continue;
                }

                $ids[$row['code']] = (int) DB::table('inv_uoms')->insertGetId([
                    'school_id' => $schoolId,
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'symbol' => $row['symbol'],
                    'type' => $row['type'],
                    'decimal_places' => $row['decimal_places'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->seedConversion($schoolId, $ids['sack'] ?? null, $ids['kg'] ?? null, 50);
            $this->seedConversion($schoolId, $ids['kg'] ?? null, $ids['g'] ?? null, 1000);
            $this->seedConversion($schoolId, $ids['l'] ?? null, $ids['ml'] ?? null, 1000);
            $this->seedConversion($schoolId, $ids['doz'] ?? null, $ids['nos'] ?? null, 12);
        }
    }

    private function seedConversion(int $schoolId, ?int $fromId, ?int $toId, float $factor): void
    {
        if (! $fromId || ! $toId || ! Schema::hasTable('inv_uom_conversions')) {
            return;
        }

        $exists = DB::table('inv_uom_conversions')
            ->where('school_id', $schoolId)
            ->where('from_uom_id', $fromId)
            ->where('to_uom_id', $toId)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('inv_uom_conversions')->insert([
            'school_id' => $schoolId,
            'from_uom_id' => $fromId,
            'to_uom_id' => $toId,
            'factor' => $factor,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedPermissions(): void
    {
        if (! function_exists('storePermissionData')) {
            return;
        }

        $routes = [
            ['route' => 'inv-kitchen-setup', 'name' => 'Kitchen Setup Wizard', 'lang_name' => 'kitchen.setup_wizard', 'position' => 8],
            ['route' => 'inv-kitchen-quick-issue', 'name' => 'Quick Kitchen Issue', 'lang_name' => 'kitchen.quick_issue', 'position' => 9],
            ['route' => 'inv-kitchen-repeat-last', 'name' => 'Repeat Last Meal', 'lang_name' => 'kitchen.repeat_last', 'position' => 10],
            ['route' => 'inv-kitchen-fast-purchase', 'name' => 'Kitchen Fast Purchase', 'lang_name' => 'kitchen.fast_purchase', 'position' => 11],
            ['route' => 'inv-kitchen-waste', 'name' => 'Log Kitchen Waste', 'lang_name' => 'kitchen.log_waste', 'position' => 12],
        ];

        foreach ($routes as $def) {
            storePermissionData([
                'module' => 'Inventory', 'sidebar_menu' => 'inventory', 'name' => $def['name'],
                'lang_name' => $def['lang_name'], 'icon' => null, 'svg' => null,
                'route' => $def['route'], 'parent_route' => 'inv-kitchen',
                'is_admin' => 1, 'is_teacher' => 0, 'is_student' => 0, 'is_parent' => 0,
                'position' => $def['position'], 'is_saas' => 0, 'is_menu' => 0, 'status' => 1, 'menu_status' => 1,
                'relate_to_child' => 0, 'alternate_module' => null, 'permission_section' => 0,
                'user_id' => null, 'type' => 3, 'old_id' => null,
                'child' => [
                    $def['route'].'-store' => [
                        'module' => 'Inventory', 'sidebar_menu' => 'inventory', 'name' => $def['name'].' Save',
                        'lang_name' => null, 'icon' => null, 'svg' => null,
                        'route' => $def['route'].'-store', 'parent_route' => $def['route'],
                        'is_admin' => 1, 'is_teacher' => 0, 'is_student' => 0, 'is_parent' => 0,
                        'position' => 1, 'is_saas' => 0, 'is_menu' => 0, 'status' => 1, 'menu_status' => 1,
                        'relate_to_child' => 0, 'alternate_module' => null, 'permission_section' => 0,
                        'user_id' => null, 'type' => 3, 'old_id' => null,
                    ],
                ],
            ]);
        }
    }

    private function seedSettings(): void
    {
        if (! Schema::hasTable('inv_settings')) {
            return;
        }

        $keys = [
            'kitchen_fast_purchase_max_amount' => '5000',
            'kitchen_setup_complete' => '0',
        ];

        foreach (DB::table('sm_schools')->pluck('id') as $schoolId) {
            foreach ($keys as $key => $value) {
                $exists = DB::table('inv_settings')
                    ->where('school_id', $schoolId)
                    ->whereNull('unit_id')
                    ->where('setting_key', $key)
                    ->exists();

                if (! $exists) {
                    DB::table('inv_settings')->insert([
                        'school_id' => $schoolId,
                        'unit_id' => null,
                        'setting_key' => $key,
                        'setting_value' => $value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    private function assignPermissions(): void
    {
        if (! Schema::hasTable('permissions') || ! class_exists(AssignPermission::class)) {
            return;
        }

        $routes = [
            'inv-kitchen-setup', 'inv-kitchen-setup-store',
            'inv-kitchen-quick-issue', 'inv-kitchen-quick-issue-store',
            'inv-kitchen-repeat-last',
            'inv-kitchen-fast-purchase', 'inv-kitchen-fast-purchase-store',
            'inv-kitchen-waste', 'inv-kitchen-waste-store',
        ];

        $permissionIds = DB::table('permissions')->whereIn('route', $routes)->pluck('id', 'route');

        foreach ($this->assignRoles as $roleName) {
            $roleId = DB::table('roles')->where('name', $roleName)->value('id');
            if (! $roleId) {
                continue;
            }

            foreach ($permissionIds as $route => $permissionId) {
                AssignPermission::firstOrCreate([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'school_id' => $this->schoolId,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_menu_plans');
        Schema::dropIfExists('inv_waste_logs');
        Schema::dropIfExists('inv_uom_conversions');
        Schema::dropIfExists('inv_uoms');

        Schema::table('sm_items', function (Blueprint $table) {
            foreach (['default_uom_id', 'purchase_uom_id', 'issue_uom_id', 'reorder_level', 'reorder_qty', 'preferred_supplier_id', 'behavior_profile', 'last_unit_cost'] as $col) {
                if (Schema::hasColumn('sm_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
