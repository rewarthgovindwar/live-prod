<?php

namespace App\Services\SitePromotion;

use App\Models\SmAcademicYear;
use App\Models\SmNotification;
use App\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class SitePromotionService
{
    public function __construct(private SitePromotionChangelogParser $changelogParser) {}

    public function isEnabled(): bool
    {
        return (bool) config('site-promotion.enabled');
    }

    public function siteRole(): string
    {
        return (string) config('site-promotion.site_role', 'production');
    }

    public function canManage(?int $roleId = null): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $roleId = $roleId ?? (int) optional(auth()->user())->role_id;

        return isTrueSuperAdminRole($roleId) || isSuperDuperAdminRole($roleId);
    }

    public function isLocked(): bool
    {
        return is_file((string) config('site-promotion.lock_file'));
    }

    /** @return array<int, string> */
    public function recentLogs(int $limit = 10): array
    {
        $dir = (string) config('site-promotion.log_dir');

        if (! is_dir($dir)) {
            return [];
        }

        $files = collect(File::files($dir))
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->take($limit)
            ->map(fn ($f) => $f->getFilename())
            ->values()
            ->all();

        return $files;
    }

    public function readLog(string $filename): string
    {
        $path = $this->safeLogPath($filename);

        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    public function tailLog(string $filename, int $lines = 80): string
    {
        $path = $this->safeLogPath($filename);

        if (! is_file($path)) {
            return '';
        }

        $content = (string) file_get_contents($path);
        $rows = explode("\n", $content);

        return implode("\n", array_slice($rows, -$lines));
    }

    /**
     * Run promotion on this server (production role).
     *
     * @return array{ok: bool, message: string, log_file: ?string, output?: string}
     */
    public function runLocalPromotion(bool $preview = false): array
    {
        if ($this->siteRole() !== 'production') {
            return ['ok' => false, 'message' => 'Local promotion only runs on production.', 'log_file' => null];
        }

        if ($this->isLocked()) {
            return ['ok' => false, 'message' => 'A promotion is already in progress.', 'log_file' => null];
        }

        $script = $preview
            ? (string) config('site-promotion.preview_script')
            : (string) config('site-promotion.promote_script');

        if (! is_file($script)) {
            return ['ok' => false, 'message' => "Script not found: {$script}", 'log_file' => null];
        }

        $args = [];
        if (! $preview && ! (bool) config('site-promotion.maintenance.enabled')) {
            $args = ['--no-maintenance'];
        }

        try {
            $process = $this->createPromotionProcess($script, $args, $preview);
            $process->run();
        } catch (\Throwable $e) {
            Log::channel('single')->error('site-promotion.local_failed', [
                'preview' => $preview,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'message' => $this->formatProcessStartFailure($e->getMessage()),
                'log_file' => null,
            ];
        }

        $output = trim($process->getOutput()."\n".$process->getErrorOutput());
        Log::channel('single')->info('site-promotion.local', ['preview' => $preview, 'output' => $output]);

        if (! $process->isSuccessful()) {
            return [
                'ok' => false,
                'message' => $this->formatProcessFailureMessage($output),
                'log_file' => $this->extractLogFile($output),
                'output' => $output,
            ];
        }

        if (! $preview) {
            $this->afterSuccessfulPromotion();
        }

        return [
            'ok' => true,
            'message' => $preview ? 'Preview completed — no files changed.' : 'Promotion completed successfully.',
            'log_file' => $this->extractLogFile($output),
            'output' => $output,
        ];
    }

    /** @param array<int, string> $args */
    private function createPromotionProcess(string $script, array $args, bool $preview): Process
    {
        $command = $this->promotionCommand($script, $args, $preview);

        $process = new Process($command);
        $process->setWorkingDirectory(base_path());
        $process->setTimeout(3600);
        $process->setEnv([
            'SITE_PROMOTION_CONFIG' => (string) config('site-promotion.server_config'),
            'SITE_PROMOTION_LOCK' => (string) config('site-promotion.lock_file'),
            'SITE_PROMOTION_LOG_DIR' => (string) config('site-promotion.log_dir'),
            'HOME' => base_path(),
        ]);

        return $process;
    }

    /** @param array<int, string> $args @return array<int, string> */
    private function promotionCommand(string $script, array $args, bool $preview): array
    {
        if ($preview) {
            return array_merge(['bash', $script], $args);
        }

        if ($this->shouldRunPromoteAsRoot()) {
            $wrapper = (string) config('site-promotion.root_wrapper_script');

            return array_merge(['sudo', '-n', $wrapper], $args);
        }

        return array_merge(['bash', $script], $args);
    }

    private function shouldRunPromoteAsRoot(): bool
    {
        if (! (bool) config('site-promotion.sudo_promote', true)) {
            return false;
        }

        if (! function_exists('posix_geteuid')) {
            return false;
        }

        return posix_geteuid() !== 0;
    }

    private function formatProcessStartFailure(string $error): string
    {
        if (str_contains($error, 'Working directory: /root') || str_contains($error, 'posix_spawn')) {
            return 'Could not start the promotion process. The server blocked subprocess execution — contact support if this persists.';
        }

        return 'Could not start the promotion process: '.$error;
    }

    private function formatProcessFailureMessage(string $output): string
    {
        if (str_contains($output, 'sudo: a password is required') || str_contains($output, 'is not allowed to execute')) {
            return 'Apply update needs one-time server setup. Run as root: bash scripts/site-promotion/install-sudoers.sh';
        }

        return 'Promotion failed. Check the log below.';
    }

    private function afterSuccessfulPromotion(): void
    {
        $this->clearPendingUpdate();
    }

    /**
     * Trigger promotion on production from staging via signed API.
     *
     * @return array{ok: bool, message: string}
     */
    public function triggerRemotePromotion(bool $preview = false): array
    {
        if ($this->siteRole() !== 'staging') {
            return ['ok' => false, 'message' => 'Remote trigger only runs from staging.'];
        }

        $secret = (string) config('site-promotion.api_secret');
        if ($secret === '') {
            return ['ok' => false, 'message' => 'SITE_PROMOTION_API_SECRET is not configured.'];
        }

        $url = rtrim((string) config('site-promotion.production.url'), '/').'/api/internal/site-promotion/trigger';
        $timestamp = (string) time();
        $payload = $preview ? 'preview' : 'promote';
        $signature = hash_hmac('sha256', $timestamp.'|'.$payload, $secret);

        try {
            $response = Http::timeout(3600)
                ->withHeaders([
                    'X-Site-Promotion-Timestamp' => $timestamp,
                    'X-Site-Promotion-Signature' => $signature,
                    'Accept' => 'application/json',
                ])
                ->post($url, ['action' => $payload]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Could not reach production: '.$e->getMessage()];
        }

        if (! $response->successful()) {
            return [
                'ok' => false,
                'message' => 'Production rejected request: '.$response->body(),
            ];
        }

        $body = $response->json();

        return [
            'ok' => (bool) ($body['ok'] ?? false),
            'message' => (string) ($body['message'] ?? 'Unknown response'),
        ];
    }

    /**
     * Staging: notify production that a tested update is ready (does not apply it).
     *
     * @return array{ok: bool, message: string, file_count?: int}
     */
    public function notifyProductionUpdateAvailable(?string $title = null, ?string $releaseNotes = null, ?string $note = null): array
    {
        if ($this->siteRole() !== 'staging') {
            return ['ok' => false, 'message' => 'Push update only runs from the staging / backup server.'];
        }

        $secret = (string) config('site-promotion.api_secret');
        if ($secret === '') {
            return ['ok' => false, 'message' => 'SITE_PROMOTION_API_SECRET is not configured on staging.'];
        }

        $url = rtrim((string) config('site-promotion.production.url'), '/').'/api/internal/site-promotion/push-available';
        $timestamp = (string) time();
        $action = 'push-available';
        $signature = hash_hmac('sha256', $timestamp.'|'.$action, $secret);

        try {
            $response = Http::timeout(3600)
                ->withHeaders([
                    'X-Site-Promotion-Timestamp' => $timestamp,
                    'X-Site-Promotion-Signature' => $signature,
                    'Accept' => 'application/json',
                ])
                ->post($url, [
                    'action' => $action,
                    'title' => $title,
                    'release_notes' => $releaseNotes,
                    'note' => $note,
                    'staging_url' => (string) config('site-promotion.staging.url'),
                ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Could not reach production: '.$e->getMessage()];
        }

        if (! $response->successful()) {
            return [
                'ok' => false,
                'message' => 'Production rejected push: '.$response->body(),
            ];
        }

        $body = $response->json();

        return [
            'ok' => (bool) ($body['ok'] ?? false),
            'message' => (string) ($body['message'] ?? 'Update notification sent.'),
            'file_count' => isset($body['file_count']) ? (int) $body['file_count'] : null,
        ];
    }

    /** @return array<string, mixed>|null */
    public function getPendingUpdate(): ?array
    {
        $path = (string) config('site-promotion.pending_file');
        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) && ! empty($data['pushed_at']) ? $data : null;
    }

    /** @param array<string, mixed> $data */
    public function setPendingUpdate(array $data): void
    {
        $path = (string) config('site-promotion.pending_file');
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        @chmod($path, 0600);
        @chmod($dir, 0700);
    }

    public function dismissPendingUpdate(): bool
    {
        if (! $this->hasPendingUpdate()) {
            return false;
        }

        $this->clearPendingUpdate();

        return true;
    }

    public function clearPendingUpdate(): void
    {
        $path = (string) config('site-promotion.pending_file');
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function hasPendingUpdate(): bool
    {
        return $this->getPendingUpdate() !== null;
    }

    /** @return array<string, mixed>|null Dashboard banner data for super admins */
    public function dashboardAlertData(): ?array
    {
        if (! $this->canManage() || $this->siteRole() !== 'production') {
            return null;
        }

        $pending = $this->getPendingUpdate();
        if ($pending === null) {
            return null;
        }

        $pushedAt = isset($pending['pushed_at']) ? \Carbon\Carbon::parse($pending['pushed_at']) : null;

        return [
            'push_id' => (string) ($pending['push_id'] ?? ''),
            'title' => (string) ($pending['title'] ?? 'Production update ready'),
            'pushed_at' => (string) ($pending['pushed_at'] ?? ''),
            'pushed_at_human' => $pushedAt?->diffForHumans() ?? '',
            'pushed_at_formatted' => $pushedAt?->format('d M Y, h:i A') ?? '',
            'file_count' => (int) ($pending['file_count'] ?? 0),
            'new_count' => (int) ($pending['new_count'] ?? 0),
            'modified_count' => (int) ($pending['modified_count'] ?? 0),
            'deleted_count' => (int) ($pending['deleted_count'] ?? 0),
            'staging_url' => (string) ($pending['staging_url'] ?? config('site-promotion.staging.url')),
            'note' => (string) ($pending['note'] ?? ''),
            'release_notes' => (string) ($pending['release_notes'] ?? ''),
            'release_notes_lines' => $this->releaseNoteLines((string) ($pending['release_notes'] ?? '')),
            'areas' => $pending['areas'] ?? [],
            'highlights' => $pending['highlights'] ?? [],
            'files' => $pending['files'] ?? [],
            'detail_url' => route('site-promotion.index'),
            'preview_url' => route('site-promotion.preview'),
            'promote_url' => route('site-promotion.promote'),
            'dismiss_url' => route('site-promotion.dismiss'),
        ];
    }

    /** @return array<int, string> */
    private function releaseNoteLines(string $notes): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($notes)) ?: [];

        return array_values(array_filter(array_map(function ($line) {
            $line = trim(ltrim($line, '-•* '));

            return $line !== '' ? $line : null;
        }, $lines)));
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    public function buildPendingPayload(string $logContent, array $meta = []): array
    {
        $changelog = $this->changelogParser->parse($logContent);

        return array_merge([
            'push_id' => (string) Str::uuid(),
            'pushed_at' => now()->toIso8601String(),
            'title' => 'Production update ready',
            'note' => '',
            'release_notes' => '',
            'staging_url' => (string) config('site-promotion.staging.url'),
        ], $meta, $changelog);
    }

    public function notifySuperAdminsOfPendingUpdate(array $pending): void
    {
        $title = (string) ($pending['title'] ?? 'Production update');
        $count = (int) ($pending['file_count'] ?? 0);
        $highlights = $pending['highlights'] ?? [];
        $highlightText = $highlights !== [] ? ' — '.implode(', ', array_slice($highlights, 0, 3)) : '';
        $message = "New update ready: {$title} ({$count} files){$highlightText}";
        $url = route('site-promotion.index');

        $superDuperId = institutionalSuperDuperAdminRoleId();

        $admins = User::withoutGlobalScopes()
            ->where('active_status', 1)
            ->where(function ($query) use ($superDuperId): void {
                $query->where(function ($inner): void {
                    $inner->where('role_id', 1)->where('is_administrator', 'yes');
                });
                if ($superDuperId) {
                    $query->orWhere('role_id', $superDuperId);
                }
            })
            ->get(['id', 'role_id', 'school_id']);

        foreach ($admins as $admin) {
            try {
                $schoolId = (int) ($admin->school_id ?? 1);
                $academicId = SmAcademicYear::where('school_id', $schoolId)
                    ->where('active_status', 1)
                    ->value('id') ?? 1;

                $notification = new SmNotification;
                $notification->date = date('Y-m-d');
                $notification->message = $message;
                $notification->url = $url;
                $notification->user_id = $admin->id;
                $notification->role_id = $admin->role_id;
                $notification->school_id = $schoolId;
                if (function_exists('moduleStatusCheck') && moduleStatusCheck('University')) {
                    $unYearClass = \Modules\University\Entities\UnAcademicYear::class;
                    if (class_exists($unYearClass)) {
                        $notification->un_academic_id = $unYearClass::where('school_id', $schoolId)
                            ->where('active_status', 1)
                            ->value('id') ?? $academicId;
                    } else {
                        $notification->academic_id = $academicId;
                    }
                } else {
                    $notification->academic_id = $academicId;
                }
                $notification->is_read = 0;
                $notification->save();
            } catch (\Throwable $e) {
                Log::warning('site-promotion.notify_admin_failed', ['user_id' => $admin->id, 'error' => $e->getMessage()]);
            }
        }
    }

    public function countChangesInLog(string $log): int
    {
        return $this->changelogParser->parse($log)['file_count'];
    }

    /** Resolve rsync preview log content (path from output, explicit file, or newest log). */
    public function previewLogContent(?string $logFile, string $processOutput = ''): string
    {
        foreach (array_filter([$logFile, $this->extractLogFile($processOutput)]) as $candidate) {
            $content = $this->readLog($candidate);
            if ($content !== '') {
                return $content;
            }
        }

        $latest = $this->recentLogs(1);

        return $latest !== [] ? $this->readLog($latest[0]) : '';
    }

    /**
     * @return array{ok: bool, message: string, file_count?: int, push_id?: string}
     */
    public function registerStagingPush(?string $title, ?string $releaseNotes, ?string $note, ?string $stagingUrl = null): array
    {
        $preview = $this->runLocalPromotion(true);
        $logContent = $this->previewLogContent($preview['log_file'] ?? null, $preview['output'] ?? '');

        if (! $preview['ok']) {
            return ['ok' => false, 'message' => 'Could not preview changes from staging.'];
        }

        $fileCount = $this->countChangesInLog($logContent);

        if ($fileCount === 0) {
            return [
                'ok' => false,
                'message' => 'No file changes detected — nothing to push.',
                'file_count' => 0,
            ];
        }

        $releaseNotes = trim((string) $releaseNotes);
        $note = trim((string) $note);
        if ($releaseNotes === '' && $note !== '') {
            $releaseNotes = $note;
        }

        $pending = $this->buildPendingPayload($logContent, [
            'title' => trim((string) $title) !== '' ? trim((string) $title) : 'Production update ready',
            'release_notes' => $releaseNotes,
            'note' => $note,
            'staging_url' => $stagingUrl ?: (string) config('site-promotion.staging.url'),
            'preview_log' => $preview['log_file'] ?? null,
        ]);

        $this->setPendingUpdate($pending);
        $this->notifySuperAdminsOfPendingUpdate($pending);

        return [
            'ok' => true,
            'message' => "Update available on production ({$fileCount} files). Super admins will see it on the dashboard.",
            'file_count' => $fileCount,
            'push_id' => $pending['push_id'] ?? null,
        ];
    }

    public function verifyApiSignature(string $timestamp, string $signature, string $action): bool
    {
        $secret = (string) config('site-promotion.api_secret');
        if ($secret === '' || $timestamp === '' || $signature === '') {
            return false;
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        if (! in_array($action, ['promote', 'preview', 'push-available'], true)) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'|'.$action, $secret);

        return hash_equals($expected, $signature);
    }

    private function safeLogPath(string $filename): string
    {
        $safe = basename($filename);
        if (! Str::endsWith($safe, '.log')) {
            $safe .= '.log';
        }

        return rtrim((string) config('site-promotion.log_dir'), '/').'/'.$safe;
    }

    private function extractLogFile(string $output): ?string
    {
        if (preg_match('/Log:\s*(.+\.log)/', $output, $m)) {
            return basename(trim($m[1]));
        }

        return null;
    }
}
