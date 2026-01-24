<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('testing')) {
            return;
        }

        $required = [
            'AI_SERVICE_URL' => config('services.ai_service_url'),
            'AI_API_KEY' => config('ai.api_key'),
            'SHOPIFY_SHOP_DOMAIN' => config('services.shopify.shop_domain'),
            'SHOPIFY_ADMIN_TOKEN' => config('services.shopify.admin_token'),
            'SHOPIFY_WEBHOOK_SECRET' => config('services.shopify.webhook_secret'),
            'QUEUE_CONNECTION' => config('queue.default'),
        ];

        $missing = [];
        foreach ($required as $key => $value) {
            if ($value === null || $value === '') {
                $missing[] = $key;
            }
        }

        if ($missing) {
            throw new \RuntimeException(
                'Missing required environment variables: '.implode(', ', $missing)
            );
        }
    }
}
