<?php

namespace App\Services\SitePromotion;

class SitePromotionChangelogParser
{
    /** @var array<string, array{label: string, icon: string}> */
    private const AREA_RULES = [
        'database/migrations' => ['label' => 'Database schema', 'icon' => '🗄️'],
        'app/' => ['label' => 'Application core', 'icon' => '⚙️'],
        'Modules/' => ['label' => 'ERP modules', 'icon' => '🧩'],
        'resources/views/' => ['label' => 'Screens & layouts', 'icon' => '🖥️'],
        'resources/' => ['label' => 'Frontend assets', 'icon' => '🎨'],
        'routes/' => ['label' => 'Routes & APIs', 'icon' => '🔌'],
        'config/' => ['label' => 'Configuration', 'icon' => '🔧'],
        'scripts/' => ['label' => 'Server scripts', 'icon' => '📜'],
        'docs/' => ['label' => 'Documentation', 'icon' => '📋'],
        'public/' => ['label' => 'Public assets', 'icon' => '🌐'],
        'wacrm/' => ['label' => 'WhatsApp CRM', 'icon' => '💬'],
    ];

    /** @var array<string, string> */
    private const HIGHLIGHT_KEYWORDS = [
        'site-promotion' => 'Site update pipeline',
        'backup' => 'Automated backups',
        'fee' => 'Fee collection',
        'biometric' => 'Biometric attendance',
        'api/v3' => 'Mobile Super App API',
        'mobile-app' => 'Mobile app',
        'notification' => 'Notifications',
        'whatsapp' => 'WhatsApp messaging',
        'medical' => 'Medical / infirmary',
        'hostel' => 'Hostel operations',
        'inventory' => 'Inventory & procurement',
        'exam' => 'Examinations & results',
        'library' => 'Library',
        'payroll' => 'HR & payroll',
        'dashboard' => 'Dashboard',
        'permission' => 'Permissions & security',
    ];

    /**
     * @return array{
     *   file_count: int,
     *   new_count: int,
     *   modified_count: int,
     *   deleted_count: int,
     *   files: array<int, array{path: string, type: string, type_label: string, area: string, area_icon: string}>,
     *   areas: array<int, array{key: string, label: string, icon: string, count: int, samples: array<int, string>}>,
     *   highlights: array<int, string>
     * }
     */
    public function parse(string $log, int $maxFiles = 80): array
    {
        $files = [];

        foreach (explode("\n", $log) as $line) {
            $parsed = $this->parseLine($line);
            if ($parsed !== null) {
                $files[] = $parsed;
            }
        }

        $newCount = count(array_filter($files, fn ($f) => $f['type'] === 'new'));
        $deletedCount = count(array_filter($files, fn ($f) => $f['type'] === 'deleted'));
        $modifiedCount = count($files) - $newCount - $deletedCount;

        $areas = $this->groupByArea($files);
        $highlights = $this->detectHighlights($files);

        return [
            'file_count' => count($files),
            'new_count' => $newCount,
            'modified_count' => $modifiedCount,
            'deleted_count' => $deletedCount,
            'files' => array_slice($files, 0, $maxFiles),
            'areas' => $areas,
            'highlights' => $highlights,
        ];
    }

    /** @return array{path: string, type: string, type_label: string, area: string, area_icon: string}|null */
    private function parseLine(string $line): ?array
    {
        $line = ltrim($line);
        if ($line === '' || $line[0] !== '>') {
            return null;
        }

        if (! preg_match('/^>([a-z+\.\s\*]+?)\s+(\S.*)$/i', $line, $m)) {
            return null;
        }

        $flags = $m[1];
        $path = trim($m[2]);

        if (! str_contains($flags, 'f') || str_ends_with($path, '/')) {
            return null;
        }

        if ($path === '' || str_starts_with($path, 'deleting ')) {
            $path = str_replace('deleting ', '', $path);
        }

        $type = 'modified';
        $typeLabel = 'Updated';
        if (str_contains($flags, '++++') || preg_match('/\+{4,}/', $flags)) {
            $type = 'new';
            $typeLabel = 'New';
        } elseif (str_contains($line, 'deleting') || str_contains($flags, '*deleting')) {
            $type = 'deleted';
            $typeLabel = 'Removed';
        }

        [$area, $icon] = $this->resolveArea($path);

        return [
            'path' => $path,
            'type' => $type,
            'type_label' => $typeLabel,
            'area' => $area,
            'area_icon' => $icon,
        ];
    }

    /** @return array{0: string, 1: string} */
    private function resolveArea(string $path): array
    {
        if (str_starts_with($path, 'Modules/')) {
            $parts = explode('/', $path);
            $module = $parts[1] ?? 'Module';

            return ["Module: {$module}", '🧩'];
        }

        foreach (self::AREA_RULES as $prefix => $meta) {
            if (str_starts_with($path, $prefix)) {
                return [$meta['label'], $meta['icon']];
            }
        }

        $top = explode('/', $path)[0] ?? 'Other';

        return [ucfirst($top), '📁'];
    }

    /**
     * @param array<int, array{path: string, type: string, type_label: string, area: string, area_icon: string}> $files
     * @return array<int, array{key: string, label: string, icon: string, count: int, samples: array<int, string}>}
     */
    private function groupByArea(array $files): array
    {
        $groups = [];
        foreach ($files as $file) {
            $key = $file['area'];
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'key' => $key,
                    'label' => $file['area'],
                    'icon' => $file['area_icon'],
                    'count' => 0,
                    'samples' => [],
                ];
            }
            $groups[$key]['count']++;
            if (count($groups[$key]['samples']) < 4) {
                $groups[$key]['samples'][] = basename($file['path']);
            }
        }

        usort($groups, fn ($a, $b) => $b['count'] <=> $a['count']);

        return array_values($groups);
    }

    /**
     * @param array<int, array{path: string}> $files
     * @return array<int, string>
     */
    private function detectHighlights(array $files): array
    {
        $found = [];
        $blob = strtolower(implode(' ', array_column($files, 'path')));

        foreach (self::HIGHLIGHT_KEYWORDS as $needle => $label) {
            if (str_contains($blob, strtolower($needle))) {
                $found[$label] = true;
            }
        }

        return array_keys($found);
    }
}
