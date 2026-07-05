<?php

namespace App\Support;

use App\Models\MaintenanceSetting;
use App\Models\Unit;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ErrorPageContext
{
    public static function resolve(?Throwable $exception = null): array
    {
        $defaults = [
            'gs' => null,
            'institutionName' => config('institutional.display_name', 'DNYANDA ERP'),
            'logo' => \institutionalDefaultLogoPath(),
            'favicon' => \institutionalDefaultFaviconPath(),
            'copyright' => null,
            'unit' => null,
            'unitName' => null,
            'dashboardUrl' => url('/admin-dashboard'),
            'loginUrl' => url('/login'),
            'homeUrl' => url('/'),
            'supportEmail' => null,
            'supportPhone' => null,
            'maintenance' => null,
            'referenceId' => session('error_reference_id'),
        ];

        try {
            $gs = generalSetting();
            if ($gs) {
                $defaults['gs'] = $gs;
                $defaults['institutionName'] = config('institutional.display_name', $gs->school_name ?? $defaults['institutionName']);
                $defaults['logo'] = self::safeBrandAssetPath($gs->logo ?? null, $defaults['logo']);
                $defaults['favicon'] = self::safeBrandAssetPath($gs->favicon ?? null, $defaults['favicon'], 'favicon');
                $defaults['copyright'] = $gs->copyright_text ?? null;
                $defaults['supportEmail'] = $gs->email ?? null;
                $defaults['supportPhone'] = $gs->phone ?? null;
            }
        } catch (Throwable) {
            // DB or helpers unavailable during error rendering
        }

        try {
            if (app()->runningInConsole() === false && app()->bound('session')) {
                $unitId = session('unit_id') ?? session('active_unit_id');
                if ($unitId) {
                    $unit = Unit::find($unitId);
                    if ($unit) {
                        $defaults['unit'] = $unit;
                        $defaults['unitName'] = $unit->name;
                        if ($unit->logo) {
                            $defaults['logo'] = self::safeBrandAssetPath($unit->logo, $defaults['logo']);
                        }
                        $defaults['supportEmail'] = $defaults['supportEmail'] ?? $unit->email;
                        $defaults['supportPhone'] = $defaults['supportPhone'] ?? $unit->phone;
                    }
                }
            }
        } catch (Throwable) {
            //
        }

        try {
            $defaults['dashboardUrl'] = Auth::check() ? url('/admin-dashboard') : url('/login');
        } catch (Throwable) {
            //
        }

        try {
            $defaults['maintenance'] = MaintenanceSetting::first();
        } catch (Throwable) {
            //
        }

        $defaults['logo'] = self::safeBrandAssetPath($defaults['logo'], \institutionalDefaultLogoPath());
        $defaults['favicon'] = self::safeBrandAssetPath($defaults['favicon'], \institutionalDefaultFaviconPath(), 'favicon');

        return $defaults;
    }

    private static function safeBrandAssetPath(?string $candidate, string $fallback, string $type = 'logo'): string
    {
        $candidate = trim((string) $candidate);
        $fallback = trim($fallback) !== '' ? $fallback : ($type === 'favicon'
            ? \institutionalDefaultFaviconPath()
            : \institutionalDefaultLogoPath());

        if ($candidate === '') {
            return $fallback;
        }

        $lower = strtolower($candidate);
        $blockedFragments = [
            'backEnd/img/logo',
            'backEnd/login/img/logo',
            'backEnd/login2/img/logo',
            'vendor/spondonit',
            'infix_',
        ];

        foreach ($blockedFragments as $fragment) {
            if (str_contains($lower, strtolower($fragment))) {
                return $fallback;
            }
        }

        $fullPath = base_path($candidate);
        if (! is_file($fullPath)) {
            return $fallback;
        }

        if ($type === 'logo' && is_file(base_path(\institutionalDefaultLogoPath()))) {
            $legacyInfixHash = @md5_file(base_path('public/backEnd/img/logo.png'));
            $candidateHash = @md5_file($fullPath);
            if ($legacyInfixHash && $candidateHash && hash_equals($legacyInfixHash, $candidateHash)) {
                return \institutionalDefaultLogoPath();
            }
        }

        return $candidate;
    }
}
