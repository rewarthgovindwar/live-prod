<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Services\Inventory\InventorySetupHealthService;
use App\Services\Inventory\KitchenDashboardService;
use App\Services\Inventory\KitchenUtilizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InvKitchenController extends Controller
{
    public function __construct(
        protected KitchenUtilizationService $utilizationService,
        protected KitchenDashboardService $dashboardService,
        protected InventorySetupHealthService $healthService
    ) {}

    public function index(Request $request)
    {
        $user = Auth::user();
        $unitsEnabled = (bool) config('units.enabled', true);
        $units = $unitsEnabled ? accessibleUnits() : collect();
        $unitId = $request->filled('unit_id') ? (int) $request->unit_id : ($unitsEnabled ? activeUnitId() : null);

        if ($unitId && $units->isNotEmpty() && ! $units->contains('id', $unitId)) {
            $unitId = activeUnitId();
        }

        $schoolId = (int) $user->school_id;
        $kitchenCategoryId = $this->healthService->kitchenCategoryId($schoolId);

        return view('backEnd.inventory.kitchen.dashboard', [
            'unitsEnabled' => $unitsEnabled,
            'units' => $units,
            'selectedUnitId' => $unitId,
            'stats' => $this->utilizationService->dashboardStats($schoolId, $unitId),
            'today' => $this->dashboardService->todaySnapshot($schoolId, $unitId, $kitchenCategoryId),
            'health' => $this->healthService->report($schoolId, $kitchenCategoryId),
            'recent' => $this->utilizationService->recentUtilizations($schoolId, $unitId),
            'lowStockAlerts' => $this->utilizationService->recentLowStockAlerts($schoolId, $unitId),
            'mealServices' => config('kitchen.meal_services', []),
        ]);
    }
}
