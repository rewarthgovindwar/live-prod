<?php

namespace App\Http\Middleware;

use App\Services\LeaveSetupAccessService;
use Closure;
use Illuminate\Http\Request;

class EnsureLeaveSetupAccess
{
    public function __construct(private LeaveSetupAccessService $leaveSetupAccess) {}

    public function handle(Request $request, Closure $next)
    {
        if ($this->leaveSetupAccess->canManage()) {
            return $next($request);
        }

        abort(403, 'You do not have permission to manage leave setup.');
    }
}
