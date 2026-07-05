<?php

namespace App\Providers;

use App\Services\ShortUrl\ShortDomainService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ShortUrlServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        if (! config('short_url.enabled', true)) {
            return;
        }

        $this->registerRedirectRoutes();
    }

    private function registerRedirectRoutes(): void
    {
        try {
            $domains = array_keys(app(ShortDomainService::class)->verifiedHosts());
        } catch (\Throwable) {
            // Cache or DB unavailable during boot — skip short-url routes; main app must still load.
            return;
        }
        $default = strtolower((string) config('short_url.default_domain'));

        if ($default !== '' && ! in_array($default, $domains, true)) {
            $domains[] = $default;
        }

        $domains = array_values(array_unique(array_filter($domains)));

        foreach ($domains as $domain) {
            Route::domain($domain)
                ->middleware('web')
                ->group(base_path('routes/short_url_redirect.php'));
        }
    }
}
