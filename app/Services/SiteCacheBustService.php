<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SiteCacheBustService
{
    public function currentVersion(): string
    {
        $path = $this->versionFilePath();

        if (is_file($path)) {
            $stored = trim((string) file_get_contents($path));
            if ($stored !== '') {
                return $stored;
            }
        }

        return (string) config('asset_bust.default_version', '1');
    }

    public function canBust(?int $roleId = null): bool
    {
        $roleId = $roleId ?? (int) optional(auth()->user())->role_id;

        return isTrueSuperAdminRole($roleId);
    }

    /** @return array{version: string, cleared: string[]} */
    public function bustForEveryone(): array
    {
        $version = date('YmdHis');
        $path = $this->versionFilePath();
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $version);

        $cleared = ['asset_version' => $version];

        foreach (['optimize:clear', 'config:cache', 'route:cache'] as $command) {
            try {
                Artisan::call($command);
                $cleared[] = $command;
            } catch (\Throwable $e) {
                $cleared[] = $command.':skipped';
            }
        }

        $this->clearSidebarCaches();
        Cache::forget('permission');
        Cache::put('site_asset_version', $version, now()->addYear());

        return [
            'version' => $version,
            'cleared' => $cleared,
        ];
    }

    protected function versionFilePath(): string
    {
        return storage_path('app/site_asset_version.txt');
    }

    protected function clearSidebarCaches(): void
    {
        if (! Schema::hasTable('sm_schools')) {
            Cache::forget('sidebar_staff_school_1');

            return;
        }

        foreach (DB::table('sm_schools')->pluck('id') as $schoolId) {
            Cache::forget('sidebar_staff_school_'.$schoolId);
            Cache::forget('sidebar_menus_'.$schoolId);
        }

        Cache::forget('sidebar_menus');
        Cache::forget('sidebar_staff');
    }
}
