<?php

namespace App\Http\Controllers\Admin\SystemSettings;

use App\Http\Controllers\Controller;
use App\Services\SiteCacheBustService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteCacheBustController extends Controller
{
    public function __construct(private SiteCacheBustService $cacheBustService) {}

    public function bust(Request $request): JsonResponse
    {
        if (! $this->cacheBustService->canBust()) {
            abort(403, 'Only super admin can hard refresh the site.');
        }

        $result = $this->cacheBustService->bustForEveryone();

        return response()->json([
            'success' => true,
            'message' => 'Site cache cleared for everyone. New asset version: '.$result['version'],
            'version' => $result['version'],
            'cleared' => $result['cleared'],
        ]);
    }
}
