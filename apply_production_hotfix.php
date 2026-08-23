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

function hotfix_patch(string $path, string $search, string $replace, array &$changes, bool $replaceAll = false): void
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
    if ($replaceAll) {
        $updated = str_replace($search, $replace, $content, $count);
    } else {
        $updated = str_replace($search, $replace, $content, $count);
        if ($count > 1) {
            $changes[] = "WARN {$path} ({$count} matches; only first replaced — use replaceAll)";
        }
    }
    hotfix_write($path, $updated, $changes);
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

    // ── 2) Webroot security — LiteSpeed ignores <Files .env>; rules must be inside RewriteEngine
    $htaccessFull = $root.'/.htaccess';
    $htaccess = is_file($htaccessFull) ? file_get_contents($htaccessFull) : '';
    $securityRules = <<<'HTACCESS'
    # Block sensitive paths (survives site promotion; LiteSpeed-compatible)
    RewriteRule ^\.env$ - [F,L]
    RewriteRule ^\.env\. - [F,L]
    RewriteRule ^composer\.(json|lock)$ - [F,L]
    RewriteRule ^artisan$ - [F,L]
    RewriteRule ^package(-lock)?\.json$ - [F,L]
    RewriteRule ^storage/logs/ - [F,L]
    RewriteRule ^scripts/ - [F,L]
    RewriteRule ^vendor/ - [F,L]
    RewriteRule ^apply_.*\.php$ - [F,L]
    RewriteRule ^_deploy_.*\.(ps1|sh)$ - [F,L]
    RewriteRule ^\.git - [F,L]

HTACCESS;
    if (! str_contains($htaccess, 'Block sensitive paths')) {
        if (preg_match('/(RewriteBase \/\s*\n)/', $htaccess, $m)) {
            hotfix_write('.htaccess', str_replace($m[0], $m[0].$securityRules, $htaccess), $changes);
            $changes[] = 'PATCHED .htaccess (security rules inside RewriteEngine)';
        } else {
            $changes[] = 'SKIP .htaccess (RewriteBase anchor not found)';
        }
    } else {
        $changes[] = 'UNCHANGED .htaccess security rules already present';
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
    hotfix_patch(
        'app/Http/Controllers/DatatableQueryController.php',
        "'branch_id' => \$branch_id]);",
        "'branch_id' => \$branch_id, 'currentSession' => \\App\\SmAcademicYear::find(getAcademicId())]);",
        $changes,
        true
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

    // ── 9) TaskSmartTaskService: sm_fees_assigns has no balance/due_date columns ─
    hotfix_patch(
        'app/Services/Tasks/TaskSmartTaskService.php',
        "            if (Schema::hasTable('sm_fees_assigns')) {\n                \$dueFees = DB::table('sm_fees_assigns')\n                    ->where('balance', '>', 0)\n                    ->whereDate('due_date', '<=', now()->addDays(3))\n                    ->limit(20)\n                    ->get();\n                foreach (\$dueFees as \$fee) {\n                    \$this->trigger('fees.due', [\n                        'module' => 'fees',\n                        'entity_type' => 'fees_assign',\n                        'entity_id' => \$fee->id,\n                        'school_id' => \$fee->school_id ?? 1,\n                        'description' => 'Fee balance pending',\n                    ], (int) (\$fee->school_id ?? 1));\n                    \$count++;\n                }\n            }",
        "            if (Schema::hasTable('fee_monthly_installments')) {\n                \$dueFees = DB::table('fee_monthly_installments')\n                    ->where('status', 'pending')\n                    ->whereColumn('paid_amount', '<', 'amount')\n                    ->whereDate('due_date', '<=', now()->addDays(3))\n                    ->limit(20)\n                    ->get();\n                foreach (\$dueFees as \$fee) {\n                    \$this->trigger('fees.due', [\n                        'module' => 'fees',\n                        'entity_type' => 'fee_monthly_installment',\n                        'entity_id' => \$fee->id,\n                        'school_id' => \$fee->school_id ?? 1,\n                        'description' => 'Fee installment due within 3 days',\n                    ], (int) (\$fee->school_id ?? 1));\n                    \$count++;\n                }\n            }",
        $changes
    );

    // ── 10) Email WhatsApp fallback: App\User vs App\Models\User type mismatch ─
    hotfix_patch(
        'app/Services/Email/EmailFailureWhatsAppFallbackService.php',
        'use App\User;',
        'use App\Models\User;',
        $changes
    );

    // ── 11) Fix CRLF in post-promote-aethon.sh ────────────────────────────────
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
