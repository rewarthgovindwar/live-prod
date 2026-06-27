<?php

use App\Services\LeaveSetupAccessService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hide Leave Type and Leave Define from everyone except super admin,
 * super duper admin, and principal roles.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        if (! Schema::hasTable('permissions') || ! Schema::hasTable('assign_permissions')) {
            return;
        }

        $service = app(LeaveSetupAccessService::class);
        $allowedRoleIds = $service->allowedRoleIds();
        $permissionIds = DB::table('permissions')
            ->whereIn('route', $service->setupRoutes())
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        $schoolIds = Schema::hasTable('sm_schools')
            ? DB::table('sm_schools')->pluck('id')
            : collect([1]);

        foreach ($schoolIds as $schoolId) {
            DB::table('assign_permissions')
                ->where('school_id', (int) $schoolId)
                ->whereIn('permission_id', $permissionIds)
                ->whereNotIn('role_id', $allowedRoleIds)
                ->update([
                    'status' => 0,
                    'menu_status' => 0,
                    'updated_at' => now(),
                ]);

            foreach ($allowedRoleIds as $roleId) {
                foreach ($permissionIds as $permissionId) {
                    DB::table('assign_permissions')->updateOrInsert(
                        [
                            'role_id' => (int) $roleId,
                            'permission_id' => (int) $permissionId,
                            'school_id' => (int) $schoolId,
                        ],
                        [
                            'status' => 1,
                            'menu_status' => 1,
                            'created_by' => 1,
                            'updated_by' => 1,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }
            }

            Cache::forget('sidebar_staff_school_'.$schoolId);
        }

        Cache::forget('permission');
    }

    public function down(): void
    {
        // Permissions are managed via Admin UI.
    }
};
