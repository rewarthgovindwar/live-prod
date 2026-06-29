<?php
/**
 * One-shot production hotfix runner (delete after successful run).
 * Visit once as super admin from a trusted IP, then remove this file.
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
        $changes[] = "UNCHANGED {$path}";

        return;
    }
    hotfix_write($path, str_replace($search, $replace, $content, $count), $changes);
    $changes[] = "PATCHED {$path} ({$count} replacement(s))";
}

header('Content-Type: text/plain; charset=utf-8');

try {
    // 1) Fee collect: stop depending on helpers_institutional autoload
    hotfix_patch(
        'Modules/Fees/Resources/views/collect/_sheet_form.blade.php',
        "'label' => feeInvoiceLineLabel(\$child, \$invoice),",
        "'label' => app(\\App\\Services\\FeeInvoiceLineLabelService::class)->labelFor(\$child, \$invoice),",
        $changes
    );

    // 2) Block sensitive webroot files on LiteSpeed (production .env was HTTP 200)
    $htaccessPath = '.htaccess';
    $htaccessFull = $root.'/'.$htaccessPath;
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
</IfModule>

<FilesMatch "^(\.env|\.env\..*|composer\.(json|lock)|artisan|phpunit\.xml|\.gitignore|\.rr\.yaml)$">
    Require all denied
</FilesMatch>
HTACCESS;
    if (! str_contains($htaccess, 'Production security (hotfix)')) {
        hotfix_write($htaccessPath, rtrim($htaccess).$securityBlock."\n", $changes);
    } else {
        $changes[] = 'UNCHANGED .htaccess security block already present';
    }

    // 3) Ensure helpers load early if present (skip if already required)
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
        } else {
            $changes[] = 'UNCHANGED AppServiceProvider already loads helpers_institutional.php';
        }
    }

    // 4) Fix CRLF in post-promote-aethon.sh (caused pipefail failure on promote)
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
    echo "Next: run ./scripts/aethon refresh on the server, then DELETE this file.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'HOTFIX FAILED: '.$e->getMessage()."\n";
}
