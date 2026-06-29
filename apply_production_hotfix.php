<?php
/**
 * One-shot production hotfix runner (delete after successful run).
 * Upload to public_html root, visit once, then delete.
 */
declare(strict_types=1);

$root = __DIR__;
$changes = [];

function hotfix_write(string $path, string $content, array &$changes): void
{
    global $root;
    $full = $root.'/'.ltrim($path, '/');
    $dir = dirname($full);
    if (! is_dir($dir)) {
        throw new RuntimeException("Directory missing: {$dir}");
    }
    $backup = $full.'.bak.'.date('YmdHis');
    if (is_file($full)) {
        copy($full, $backup);
    }
    file_put_contents($full, $content);
    $changes[] = "WROTE {$path} (backup: ".basename($backup).')';
}

function hotfix_patch(string $path, string $search, string $replace, array &$changes): void
{
    global $root;
    $full = $root.'/'.ltrim($path, '/');
    if (! is_file($full)) {
        $changes[] = "SKIP missing {$path}";

        return;
    }
    $content = file_get_contents($full);
    if (! str_contains($content, $search)) {
        $changes[] = "UNCHANGED {$path} (pattern not found)";

        return;
    }
    hotfix_write($path, str_replace($search, $replace, $content, $count), $changes);
    $changes[] = "PATCHED {$path} ({$count} replacement(s))";
}

function hotfix_regex(string $path, string $pattern, string $replacement, array &$changes): void
{
    global $root;
    $full = $root.'/'.ltrim($path, '/');
    if (! is_file($full)) {
        $changes[] = "SKIP missing {$path}";

        return;
    }
    $content = file_get_contents($full);
    $updated = preg_replace($pattern, $replacement, $content, -1, $count);
    if ($count === 0 || $updated === null) {
        $changes[] = "UNCHANGED {$path} (regex no match)";

        return;
    }
    hotfix_write($path, $updated, $changes);
    $changes[] = "REGEX-PATCHED {$path} ({$count} replacement(s))";
}

function hotfix_append_if_missing(string $path, string $needle, string $append, array &$changes): void
{
    global $root;
    $full = $root.'/'.ltrim($path, '/');
    if (! is_file($full)) {
        $changes[] = "SKIP missing {$path}";

        return;
    }
    $content = file_get_contents($full);
    if (str_contains($content, $needle)) {
        $changes[] = "UNCHANGED {$path} (already has {$needle})";

        return;
    }
    hotfix_write($path, rtrim($content)."\n".$append."\n", $changes);
    $changes[] = "APPENDED {$path}";
}

header('Content-Type: text/plain; charset=utf-8');

try {
    // ── 1) Fee collect 500: feeInvoiceLineLabel() undefined ─────────────────
    hotfix_patch(
        'Modules/Fees/Resources/views/collect/_sheet_form.blade.php',
        "'label' => feeInvoiceLineLabel(\$child, \$invoice),",
        "'label' => app(\\App\\Services\\FeeInvoiceLineLabelService::class)->labelFor(\$child, \$invoice),",
        $changes
    );

    // ── 2) Webroot security (production .env was HTTP 200) ────────────────────
    $htaccessFull = $root.'/.htaccess';
    $htaccess = is_file($htaccessFull) ? file_get_contents($htaccessFull) : '';
    $securityBlock = <<<'HTACCESS'

# --- Production security (hotfix) ---
<IfModule mod_rewrite.c>
RewriteRule ^\.env$ - [F,L]
RewriteRule ^\.env\. - [F,L]
RewriteRule ^composer\.(json|lock)$ - [F,L]
RewriteRule ^artisan$ - [F,L]
RewriteRule ^storage/logs/ - [F,L]
RewriteRule ^scripts/ - [F,L]
RewriteRule ^apply_.*\.php$ - [F,L]
</IfModule>

<FilesMatch "^(\.env|\.env\..*|composer\.(json|lock)|artisan|phpunit\.xml|\.gitignore|\.rr\.yaml|apply_.*\.php)$">
    Require all denied
</FilesMatch>
HTACCESS;
    if (! str_contains($htaccess, 'Production security (hotfix)')) {
        hotfix_write('.htaccess', rtrim($htaccess).$securityBlock."\n", $changes);
    } else {
        $changes[] = 'UNCHANGED .htaccess security block already present';
    }

    // ── 3) Helpers: gv() + ensure institutional helpers file is complete ────
    $helpersPath = 'app/helpers_institutional.php';
    $helpersFull = $root.'/'.$helpersPath;
    $gvHelper = <<<'PHP'

if (! function_exists('gv')) {
  function gv(mixed $array, string|int|null $key, mixed $default = null): mixed
  {
    return data_get($array, $key, $default);
  }
}
PHP;
    if (is_file($helpersFull)) {
        $helpers = file_get_contents($helpersFull);
        if (! str_contains($helpers, 'function gv(')) {
            hotfix_write($helpersPath, rtrim($helpers).$gvHelper."\n", $changes);
        } else {
            $changes[] = 'UNCHANGED helpers_institutional.php already defines gv()';
        }
    } else {
        hotfix_write($helpersPath, "<?php\n".$gvHelper."\n", $changes);
    }

    $appProvider = $root.'/app/Providers/AppServiceProvider.php';
    if (is_file($appProvider)) {
        $provider = file_get_contents($appProvider);
        if (! str_contains($provider, "helpers_institutional.php")) {
            hotfix_patch(
                'app/Providers/AppServiceProvider.php',
                "    public function register(): void\n    {",
                "    public function register(): void\n    {\n        if (is_file(app_path('helpers_institutional.php'))) {\n            require_once app_path('helpers_institutional.php');\n        }",
                $changes
            );
        }
        if (! str_contains($provider, "singleton('school_general_settings'")) {
            hotfix_patch(
                'app/Providers/AppServiceProvider.php',
                "    public function register(): void\n    {",
                "    public function register(): void\n    {\n        \$this->app->singleton('school_general_settings', fn () => app('school_info'));",
                $changes
            );
        }
    }

    // ── 4) Student list 500: $currentSession undefined ────────────────────────
    hotfix_patch(
        'app/Http/Controllers/Admin/StudentInfo/SmStudentAdmissionController.php',
        "return view('backEnd.studentInformation.student_details', ['classes' => \$classes, 'sessions' => \$sessions]);",
        "return view('backEnd.studentInformation.student_details', [\n                'classes' => \$classes,\n                'sessions' => \$sessions,\n                'currentSession' => \\App\\SmAcademicYear::find(getAcademicId()),\n            ]);",
        $changes
    );
    hotfix_regex(
        'app/Http/Controllers/DatatableQueryController.php',
        "/return view\\('backEnd\\.studentInformation\\.student_details', \\[(.*?)\\]\\);/s",
        "return view('backEnd.studentInformation.student_details', [$1, 'currentSession' => \\App\\SmAcademicYear::find(getAcademicId())]);",
        $changes
    );

    // ── 5) Student view 500: school_general_settings binding ──────────────────
    hotfix_patch(
        'resources/views/backEnd/studentInformation/student_view.blade.php',
        "app('school_general_settings')",
        "app('school_info')",
        $changes
    );
    hotfix_patch(
        'resources/views/backEnd/studentInformation/student_view.blade.php',
        'app("school_general_settings")',
        "app('school_info')",
        $changes
    );

    // ── 6) Parent dashboard 500: gv() resolved in wrong namespace ───────────
    hotfix_regex(
        'app/Services/Calendar/CalendarEventService.php',
        '/(?<!\\\\)\\bgv\\(/',
        '\\gv(',
        $changes
    );

    // ── 7) Inventory approval 500: $suppliers undefined ──────────────────────
    $inboxController = $root.'/app/Http/Controllers/Admin/Inventory/InvApprovalInboxController.php';
    if (is_file($inboxController)) {
        $content = file_get_contents($inboxController);
        if (! str_contains($content, "'suppliers'") && ! str_contains($content, '"suppliers"')) {
            hotfix_regex(
                'app/Http/Controllers/Admin/Inventory/InvApprovalInboxController.php',
                '/(public function show\\([^)]*\\)[^{]*\\{)(.*?)(return view\\()/s',
                '$1$2$suppliers = \\App\\SmSupplier::select([\'id\', \'company_name\'])->where(\'school_id\', auth()->user()->school_id)->get();'."\n        ".'$3',
                $changes
            );
            // Fallback: inject into compact() calls
            hotfix_regex(
                'app/Http/Controllers/Admin/Inventory/InvApprovalInboxController.php',
                "/compact\\(([^)]*)\\)/",
                "compact($1, 'suppliers')",
                $changes
            );
        } else {
            $changes[] = 'UNCHANGED InvApprovalInboxController already passes suppliers';
        }
    }

    // ── 8) Scheduler 500: TskReminder missing task() relationship ─────────────
    $reminderModel = $root.'/app/Models/Tasks/TskReminder.php';
    if (is_file($reminderModel)) {
        $content = file_get_contents($reminderModel);
        if (! str_contains($content, 'function task(')) {
            hotfix_regex(
                'app/Models/Tasks/TskReminder.php',
                '/\\}\\s*$/',
                <<<'PHP'

    public function task(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TskTask::class, 'task_id');
    }
}
PHP,
                $changes
            );
        }
    }

    // ── 9) Fix CRLF in post-promote-aethon.sh ─────────────────────────────────
    $aethonScript = $root.'/scripts/site-promotion/post-promote-aethon.sh';
    if (is_file($aethonScript)) {
        $normalized = str_replace("\r\n", "\n", (string) file_get_contents($aethonScript));
        if ($normalized !== file_get_contents($aethonScript)) {
            hotfix_write('scripts/site-promotion/post-promote-aethon.sh', $normalized, $changes);
        }
    }

    echo "Production hotfix complete\n";
    echo str_repeat('-', 40)."\n";
    foreach ($changes as $line) {
        echo $line."\n";
    }
    echo str_repeat('-', 40)."\n";
    echo "Next: cd /home/dnyanda.vss.ac/public_html && ./scripts/aethon refresh\n";
    echo "Then DELETE this file and verify .env returns 403.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'HOTFIX FAILED: '.$e->getMessage()."\n";
}
