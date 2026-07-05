<?php

namespace App\Services\ShortUrl;

use App\Models\ShortDomain;
use App\Models\ShortUrlSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShortDomainService
{
    public const CACHE_KEY = 'short_url:verified_hosts';

    /** @param  array<string, mixed>  $data */
    public function create(int $schoolId, array $data, ?int $userId = null): ShortDomain
    {
        $host = $this->normalizeHost((string) ($data['host'] ?? ''));
        $method = strtolower((string) ($data['verification_method'] ?? 'cname'));

        if (! in_array($method, ['cname', 'a', 'txt'], true)) {
            throw ValidationException::withMessages(['verification_method' => 'Use cname, a, or txt.']);
        }

        if (ShortDomain::where('host', $host)->exists()) {
            throw ValidationException::withMessages(['host' => 'This domain is already registered.']);
        }

        $domain = ShortDomain::create([
            'school_id' => $schoolId,
            'host' => $host,
            'verification_method' => $method,
            'verification_token' => Str::random(32),
            'status' => 'pending',
            'created_by' => $userId,
        ]);

        return $domain;
    }

    public function verify(ShortDomain $domain): array
    {
        $result = match ($domain->verification_method) {
            'a' => $this->verifyARecord($domain->host),
            'txt' => $this->verifyTxtRecord($domain->host, $domain->verification_token),
            default => $this->verifyCname($domain->host),
        };

        if ($result['verified']) {
            $domain->forceFill([
                'status' => 'verified',
                'verified_at' => now(),
            ])->save();

            $this->flushHostCache();
        } else {
            $domain->forceFill(['status' => 'failed'])->save();
        }

        return $result;
    }

    public function delete(ShortDomain $domain): void
    {
        if ($domain->links()->exists()) {
            throw ValidationException::withMessages(['host' => 'Remove all links on this domain before deleting it.']);
        }

        $domain->delete();
        $this->flushHostCache();
    }

    public function defaultForSchool(int $schoolId): ?ShortDomain
    {
        $settings = ShortUrlSetting::resolveFor($schoolId);

        if ($settings->default_domain_id) {
            $selected = ShortDomain::where('school_id', $schoolId)
                ->where('id', $settings->default_domain_id)
                ->where('status', 'verified')
                ->first();

            if ($selected) {
                return $selected;
            }
        }

        $platform = $this->platformDomainRecord($schoolId);
        if ($platform) {
            return $platform;
        }

        return ShortDomain::where('school_id', $schoolId)
            ->where('status', 'verified')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    public function platformDomainRecord(int $schoolId): ?ShortDomain
    {
        $host = strtolower((string) config('short_url.default_domain'));
        if ($host === '') {
            return null;
        }

        return ShortDomain::firstOrCreate(
            [
                'host' => $host,
            ],
            [
                'school_id' => $schoolId,
                'verification_method' => 'a',
                'verification_token' => Str::random(32),
                'status' => 'verified',
                'verified_at' => now(),
                'is_default' => true,
            ]
        );
    }

    public function findVerifiedHost(string $host): ?ShortDomain
    {
        $host = strtolower($host);
        $hosts = $this->verifiedHosts();

        if (! isset($hosts[$host])) {
            return null;
        }

        return ShortDomain::find($hosts[$host]);
    }

    /** @return array<string, int> host => domain_id */
    public function verifiedHosts(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('short_domains')) {
            return [];
        }

        try {
            return Cache::remember(self::CACHE_KEY, (int) config('short_url.hosts_cache_ttl', 300), function (): array {
                return $this->loadVerifiedHostsFromDatabase();
            });
        } catch (\Throwable) {
            try {
                return $this->loadVerifiedHostsFromDatabase();
            } catch (\Throwable) {
                return [];
            }
        }
    }

    /** @return array<string, int> */
    private function loadVerifiedHostsFromDatabase(): array
    {
        return ShortDomain::query()
            ->where('status', 'verified')
            ->pluck('id', 'host')
            ->mapWithKeys(fn ($id, $host) => [strtolower((string) $host) => (int) $id])
            ->all();
    }

    public function flushHostCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, mixed> */
    public function dnsInstructions(ShortDomain $domain): array
    {
        $cnameTarget = (string) config('short_url.cname_target');
        $serverIp = (string) config('short_url.server_ip');

        return match ($domain->verification_method) {
            'a' => [
                'method' => 'A',
                'records' => [
                    ['type' => 'A', 'host' => '@', 'value' => $serverIp],
                    ['type' => 'A', 'host' => 'www', 'value' => $serverIp],
                ],
                'note' => 'Point your apex/root domain (or subdomain) to our server IP.',
            ],
            'txt' => [
                'method' => 'TXT',
                'records' => [
                    ['type' => 'TXT', 'host' => '_shorturl.'.$domain->host, 'value' => 'shorturl-verify='.$domain->verification_token],
                ],
                'note' => 'Add this TXT record, verify ownership, then switch to CNAME for production use.',
            ],
            default => [
                'method' => 'CNAME',
                'records' => [
                    ['type' => 'CNAME', 'host' => $domain->host, 'value' => $cnameTarget],
                ],
                'note' => 'Point your subdomain CNAME to our branded target. Apex domains should use an A record instead.',
            ],
        };
    }

    /** @return array<string, mixed> */
    public function serialize(ShortDomain $domain): array
    {
        return [
            'id' => $domain->id,
            'host' => $domain->host,
            'status' => $domain->status,
            'verification_method' => $domain->verification_method,
            'verified_at' => optional($domain->verified_at)?->toIso8601String(),
            'is_default' => $domain->is_default,
            'dns' => $this->dnsInstructions($domain),
        ];
    }

    /** @return array<string, mixed> */
    private function verifyCname(string $host): array
    {
        $target = strtolower(rtrim((string) config('short_url.cname_target'), '.'));
        $records = @dns_get_record($host, DNS_CNAME) ?: [];

        foreach ($records as $record) {
            $value = strtolower(rtrim((string) ($record['target'] ?? ''), '.'));
            if ($value === $target || str_ends_with($value, '.'.$target)) {
                return ['verified' => true, 'message' => 'CNAME verified.', 'records_found' => $records];
            }
        }

        return [
            'verified' => false,
            'message' => 'CNAME not found. Point '.$host.' to '.$target.'.',
            'records_found' => $records,
        ];
    }

    /** @return array<string, mixed> */
    private function verifyARecord(string $host): array
    {
        $expected = (string) config('short_url.server_ip');
        $records = @dns_get_record($host, DNS_A) ?: [];

        foreach ($records as $record) {
            if (($record['ip'] ?? null) === $expected) {
                return ['verified' => true, 'message' => 'A record verified.', 'records_found' => $records];
            }
        }

        return [
            'verified' => false,
            'message' => 'A record not found. Point '.$host.' to '.$expected.'.',
            'records_found' => $records,
        ];
    }

    /** @return array<string, mixed> */
    private function verifyTxtRecord(string $host, string $token): array
    {
        $name = '_shorturl.'.$host;
        $records = @dns_get_record($name, DNS_TXT) ?: [];
        $needle = 'shorturl-verify='.$token;

        foreach ($records as $record) {
            $txt = (string) ($record['txt'] ?? '');
            if (str_contains($txt, $needle)) {
                return ['verified' => true, 'message' => 'TXT record verified.', 'records_found' => $records];
            }
        }

        return [
            'verified' => false,
            'message' => 'TXT record not found at '.$name.'.',
            'records_found' => $records,
        ];
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('#^https?://#', '', $host) ?? $host;
        $host = rtrim($host, '/');
        $host = explode('/', $host)[0];

        if ($host === '' || ! preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $host)) {
            throw ValidationException::withMessages(['host' => 'Enter a valid domain name.']);
        }

        return $host;
    }
}
