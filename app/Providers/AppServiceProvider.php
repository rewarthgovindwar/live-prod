<?php

namespace App\Providers;

use App\Contracts\FileUploadServiceInterface;
use App\Services\DashboardHeaderTickerService;
use App\Services\FileUploadService;
use App\Services\SitePromotion\SitePromotionService;
use App\Support\ErrorPageContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (class_exists(\Laravel\Passport\Passport::class)) {
            \App\Services\Mobile\MobileV2PassportService::bootstrapKeysFromStorage();
        }

        $this->app->singleton(FileUploadServiceInterface::class, FileUploadService::class);
        $this->app->singleton(\App\Services\Notifications\WaCrmClient::class);
        $this->app->singleton(\App\Services\Notifications\PersonalizationEngine::class);
        $this->app->singleton(\App\Services\Notifications\Channels\PortalChannel::class);
        $this->app->singleton(\App\Services\Notifications\NotificationPreferenceService::class);
        $this->app->singleton(\App\Services\Notifications\NotificationRuleService::class);
        $this->app->singleton(\App\Services\Notifications\NotificationDigestService::class);
        $this->app->singleton(\App\Services\Notifications\Channels\PushChannel::class);
        $this->app->singleton(\App\Services\Notifications\NotificationInboxService::class);
        $this->app->singleton(\App\Services\Notifications\NotificationEngine::class);
        $this->app->singleton(\App\Services\Notifications\ErpWhatsAppRouter::class);
        $this->app->singleton(\Illuminate\Database\Eloquent\Factory::class, static fn () => new \Illuminate\Database\Eloquent\Factory());
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        View::composer('errors.*', function ($view): void {
            $data = $view->getData();
            if (! isset($data['dashboardUrl'])) {
                $view->with(ErrorPageContext::resolve($data['exception'] ?? null));
            }
        });

        View::composer('backEnd.partials.menu', function ($view): void {
            if (! Auth::check()) {
                return;
            }

            $view->with(
                'headerTickers',
                app(DashboardHeaderTickerService::class)->visibleForUser(Auth::user())
            );

            $view->with(
                'sitePromotionAlert',
                app(SitePromotionService::class)->dashboardAlertData()
            );
        });

        View::composer(
            [
                'backEnd.dashboard._shell-modern',
                'backEnd.dashboard._shell-legacy',
                'backEnd.systemSettings.site_promotion',
                'backEnd.partials._site_promotion_global_strip',
                'backEnd.systemSettings.utilityView',
            ],
            function ($view): void {
                $view->with(
                    'sitePromotionAlert',
                    app(SitePromotionService::class)->dashboardAlertData()
                );
            }
        );

       /* $host       = request()->getHost();
        $mainDomain = parse_url(config('app.url') ?? '', PHP_URL_HOST);

        $subdomain = $this->extractSubdomain($host, $mainDomain);

        if ($subdomain) {
            URL::defaults(['subdomain' => $subdomain]);
        }*/
    }

    /**
     * Extract the subdomain prefix from the current host.
     *
     * Returns null when the host IS the main domain (no real subdomain prefix),
     * so URL::defaults is never set in that case.
     *
     * ─── BUG THIS FIXES ───────────────────────────────────────────────────────
     *
     * The original code used str_ends_with() then substr() to strip the domain:
     *
     *   str_ends_with('aoraschool.com', 'aoraschool.com') → true   ← matches root!
     *   substr('aoraschool.com', 0, 14 - 15)              → substr(..., 0, -1)
     *   PHP substr with negative 3rd arg strips from end  → 'aoraschool.co'
     *
     * Then: URL::defaults(['subdomain' => 'aoraschool.co'])
     *
     * Laravel's URL generator injects all defaults into route() calls.
     * The 'login' route (defined inside the {subdomain} domain group) picks it up
     * and generates: http://aoraschool.co.aoraschool.com/login
     *
     * ─── FIX ─────────────────────────────────────────────────────────────────
     *
     * Require host to be STRICTLY longer than mainDomain before extracting prefix.
     * If host == mainDomain → length would be 0 or negative → return null.
     */
    private function extractSubdomain(string $host, ?string $mainDomain): ?string
    {

        if (! $mainDomain) {
            // No APP_URL configured (local dev). Handle sub.localhost style.
            $parts = explode('.', $host);
            if (count($parts) === 2 && in_array($parts[1], ['localhost', 'local', 'test'], true)) {
                return $parts[0];
            }
            return count($parts) > 2 ? $parts[0] : null;
        }

        $suffix = '.' . $mainDomain;  // e.g. '.aoraschool.com'

        // Guard: host must be LONGER than the suffix.
        // If host == mainDomain → len(host)=14, len(suffix)=15 → 14 > 15 is FALSE → return null.
        // This prevents the negative substr that was extracting 'aoraschool.co' from 'aoraschool.com'.
        if (strlen($host) > strlen($suffix) && str_ends_with($host, $suffix)) {
            $prefix = substr($host, 0, strlen($host) - strlen($suffix));

            // Reject double subdomains (a.b.example.com) — these are unusual
            // and should not be treated as a single subdomain token.
            if (strlen($prefix) > 0 && ! str_contains($prefix, '.')) {
                return $prefix;
            }
        }

        // Custom domain or root domain — no subdomain to inject
        return null;
    }
}