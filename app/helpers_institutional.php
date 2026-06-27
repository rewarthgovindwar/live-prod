<?php

use App\Services\LeaveSetupAccessService;

if (! function_exists('canManageLeaveSetup')) {
    function canManageLeaveSetup(): bool
    {
        return app(LeaveSetupAccessService::class)->canManage();
    }
}

if (! function_exists('userCanAccessLeaveSetupRoute')) {
    function userCanAccessLeaveSetupRoute(?string $route = null): bool
    {
        if (! canManageLeaveSetup()) {
            return false;
        }

        if ($route === null) {
            return true;
        }

        return userPermission($route);
    }
}
