<?php

use App\Services\FeeInvoiceLineLabelService;
use App\Services\FeeManualAllocationService;
use App\Services\LeaveSetupAccessService;

if (! function_exists('feeInvoiceLineLabel')) {
    function feeInvoiceLineLabel(mixed $child, mixed $invoice = null): string
    {
        return app(FeeInvoiceLineLabelService::class)->labelFor($child, $invoice);
    }
}

if (! function_exists('canManageFeePaymentAllocation')) {
    function canManageFeePaymentAllocation(): bool
    {
        return app(FeeManualAllocationService::class)->canManageAllocation();
    }
}

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

if (! function_exists('gv')) {
    /** Safe array getter used across ERP services (e.g. CalendarEventService). */
    function gv(mixed $array, string|int|null $key, mixed $default = null): mixed
    {
        return data_get($array, $key, $default);
    }
}
