<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\RolePermission\Entities\AssignPermission;

/**
 * Create Sports Teacher role with sports module access plus staff essentials
 * (dashboard, calendar, leave apply, student view, attendance).
 */
return new class extends Migration
{
    private const ROLE_NAME = 'Sports Teacher';

    private int $schoolId = 1;

    /** @var list<string> */
    private const SPORTS_ROUTES = [
        'sports_pe',
        'sports.inventory.index',
        'sports.inventory.create',
        'sports.inventory.issues',
        'sports.audit.index',
        'sports.audit.create',
        'sports.fitness.index',
        'sports.fitness.create',
        'sports.fitness.export-csv',
        'sports.fitness.reports.index',
        'sports.fitness.reports.export-csv',
        'sports.houses.index',
    ];

    /** @var list<string> */
    private const STAFF_ROOT_ROUTES = [
        'dashboard',
        'dashboard_section',
        'to-do-list',
        'calender-section',
        'leave',
        'apply-leave',
        'student_info',
        'student_list',
        'student_view',
        'student_attendance',
    ];

    /** @var list<string> */
    private const EXCLUDED_LEAVE_ADMIN_ROUTES = [
        'leave-define',
        'leave-define-store',
        'leave-define-edit',
        'leave-define-delete',
        'leave-type',
        'leave-type-store',
        'leave-type-edit',
        'leave-type-delete',
        'approve-leave',
        'approve-leave-store',
        'approve-leave-edit',
        'approve-leave-delete',
        'view-leave-details-approve',
    ];

    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        if (! Schema::hasTable('infix_roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        $roleId = $this->ensureRoleExists();
        if (! $roleId) {
            return;
        }

        $routes = $this->routesForSportsTeacher();
        $permissionIds = $this->permissionIdsForRoutes($routes);

        if ($permissionIds === []) {
            return;
        }

        $this->assignPermissionIds($roleId, $permissionIds);
        $this->clearCaches();
    }

    private function ensureRoleExists(): ?int
    {
        $existingId = DB::table('infix_roles')
            ->where('is_saas', 0)
            ->whereRaw('LOWER(name) = ?', [strtolower(self::ROLE_NAME)])
            ->value('id');

        if ($existingId) {
            $this->ensureLegacyRoleRow((int) $existingId);
            $this->ensureRoleProfile((int) $existingId);

            return (int) $existingId;
        }

        $now = now();
        $slug = Str::slug(self::ROLE_NAME);

        $roleId = (int) DB::table('infix_roles')->insertGetId([
            'name' => self::ROLE_NAME,
            'slug' => $slug,
            'type' => 'User Defined',
            'active_status' => 1,
            'created_by' => '1',
            'updated_by' => '1',
            'created_at' => $now,
            'updated_at' => $now,
            'school_id' => $this->schoolId,
            'is_saas' => 0,
        ]);

        $this->ensureLegacyRoleRow($roleId);
        $this->ensureRoleProfile($roleId);

        return $roleId;
    }

    private function ensureLegacyRoleRow(int $roleId): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $exists = DB::table('roles')->where('id', $roleId)->exists();
        if ($exists) {
            DB::table('roles')
                ->where('id', $roleId)
                ->update([
                    'name' => self::ROLE_NAME,
                    'type' => 'custom',
                    'active_status' => 1,
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table('roles')->insert([
            'id' => $roleId,
            'name' => self::ROLE_NAME,
            'type' => 'custom',
            'active_status' => 1,
            'created_by' => '1',
            'updated_by' => '1',
            'created_at' => now(),
            'updated_at' => now(),
            'school_id' => $this->schoolId,
        ]);
    }

    private function ensureRoleProfile(int $roleId): void
    {
        if (! Schema::hasTable('access_role_profiles')) {
            return;
        }

        $exists = DB::table('access_role_profiles')
            ->where('role_id', $roleId)
            ->where('school_id', $this->schoolId)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('access_role_profiles')->insert([
            'role_id' => $roleId,
            'school_id' => $this->schoolId,
            'description' => 'Sports & PE staff — equipment, fitness assessments, audits, and houses.',
            'color' => '#16a34a',
            'icon' => 'ph-barbell',
            'department_id' => null,
            'default_dashboard_profile' => null,
            'default_landing_route' => 'sports.fitness.index',
            'priority' => 100,
            'template_source_role_id' => DB::table('infix_roles')->whereRaw('LOWER(name) = ?', ['teacher'])->value('id'),
            'status' => 'active',
            'created_by' => 1,
            'updated_at' => now(),
            'created_at' => now(),
        ]);
    }

    /** @return list<string> */
    private function routesForSportsTeacher(): array
    {
        $routes = $this->expandRoutes(array_merge(self::STAFF_ROOT_ROUTES, self::SPORTS_ROUTES));

        return array_values(array_diff($routes, self::EXCLUDED_LEAVE_ADMIN_ROUTES));
    }

    /** @param list<string> $rootRoutes @return list<string> */
    private function expandRoutes(array $rootRoutes): array
    {
        $all = $rootRoutes;
        $frontier = $rootRoutes;

        while ($frontier !== []) {
            $children = DB::table('permissions')
                ->whereIn('parent_route', $frontier)
                ->whereNotNull('route')
                ->where('route', '!=', '')
                ->pluck('route')
                ->all();

            $new = array_diff($children, $all);
            if ($new === []) {
                break;
            }

            $all = array_merge($all, $new);
            $frontier = array_values($new);
        }

        return array_values(array_unique($all));
    }

    /** @param list<string> $routes @return list<int> */
    private function permissionIdsForRoutes(array $routes): array
    {
        return DB::table('permissions')
            ->whereIn('route', $routes)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /** @param list<int> $permissionIds */
    private function assignPermissionIds(int $roleId, array $permissionIds): void
    {
        if (! Schema::hasTable('assign_permissions') || ! class_exists(AssignPermission::class)) {
            return;
        }

        foreach ($permissionIds as $permissionId) {
            $existing = AssignPermission::query()
                ->where('role_id', $roleId)
                ->where('permission_id', $permissionId)
                ->where('school_id', $this->schoolId)
                ->first();

            if ($existing) {
                if ((int) $existing->status !== 1 || (int) $existing->menu_status !== 1) {
                    $existing->status = 1;
                    $existing->menu_status = 1;
                    $existing->updated_by = 1;
                    $existing->save();
                }

                continue;
            }

            $assign = new AssignPermission();
            $assign->permission_id = $permissionId;
            $assign->role_id = $roleId;
            $assign->status = 1;
            $assign->menu_status = 1;
            $assign->school_id = $this->schoolId;
            $assign->created_by = 1;
            $assign->updated_by = 1;
            $assign->save();
        }
    }

    private function clearCaches(): void
    {
        if (Schema::hasTable('sm_schools')) {
            foreach (DB::table('sm_schools')->pluck('id') as $schoolId) {
                Cache::forget('sidebar_staff_school_'.$schoolId);
            }
        }

        Cache::forget('permission');
    }

    public function down(): void
    {
        // Role and permissions are managed via Admin UI.
    }
};
