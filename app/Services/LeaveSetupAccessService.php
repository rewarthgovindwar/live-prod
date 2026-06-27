<?php

namespace App\Services;

use App\User;
use Illuminate\Support\Facades\DB;

class LeaveSetupAccessService
{
    /** @var string[] */
    private const SETUP_ROUTES = [
        'leave-type',
        'leave-type-store',
        'leave-type-edit',
        'leave-type-delete',
        'leave-define',
        'leave-define-store',
        'leave-define-edit',
        'leave-define-delete',
        'leave-define-ajax',
        'leave-define-updateLeave',
    ];

    public function canManage(?User $user = null): bool
    {
        $user = $user ?? auth()->user();
        if (! $user) {
            return false;
        }

        $roleId = (int) $user->role_id;

        if (isTrueSuperAdminRole($roleId) || isSuperDuperAdminRole($roleId)) {
            return true;
        }

        if (function_exists('isInstitutionalPrincipalRole') && isInstitutionalPrincipalRole($roleId)) {
            return true;
        }

        if (function_exists('userHasInstitutionalPrincipalRouteAccess')
            && userHasInstitutionalPrincipalRouteAccess($user)) {
            return true;
        }

        return in_array($roleId, $this->principalRoleIds(), true);
    }

    /** @return string[] */
    public function setupRoutes(): array
    {
        return self::SETUP_ROUTES;
    }

    /** @return int[] */
    public function allowedRoleIds(): array
    {
        $roles = collect([1]);

        $superDuper = DB::table('infix_roles')
            ->where('is_saas', 0)
            ->whereRaw('LOWER(name) = ?', [
                strtolower((string) config('institutional.super_duper_admin_role_name', 'Super Duper Admin')),
            ])
            ->value('id');
        if ($superDuper) {
            $roles->push((int) $superDuper);
        }

        return $roles->merge($this->principalRoleIds())->unique()->filter()->values()->all();
    }

    /** @return int[] */
    public function principalRoleIds(): array
    {
        if (function_exists('institutionalPrincipalRoleIds')) {
            return collect(institutionalPrincipalRoleIds())
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values()
                ->all();
        }

        $names = array_values(array_unique(array_merge(
            ['Principal', 'principal'],
            (array) config('institutional.principal_role_names', [])
        )));

        if ($names === []) {
            return [];
        }

        return DB::table('infix_roles')
            ->where('is_saas', 0)
            ->where(function ($query) use ($names) {
                foreach ($names as $name) {
                    $query->orWhereRaw('LOWER(name) = ?', [strtolower((string) $name)]);
                }
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
