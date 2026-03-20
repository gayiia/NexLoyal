<?php

// This provider configures application-wide services and startup checks.
namespace App\Providers;

use Illuminate\Support\ServiceProvider;

// This class registers and boots core application services.
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
        // This skips runtime env validation during automated tests and build-time codegen.
        if (! $this->shouldValidateRequiredConfiguration()) {
            return;
        }

        // These settings are required for AI, Shopify, and queue processing.
        $required = [
            'AI_SERVICE_URL' => config('services.ai_service_url'),
            'AI_API_KEY' => config('ai.api_key'),
            'SHOPIFY_SHOP_DOMAIN' => config('services.shopify.shop_domain'),
            'SHOPIFY_ADMIN_TOKEN' => config('services.shopify.admin_token'),
            'SHOPIFY_WEBHOOK_SECRET' => config('services.shopify.webhook_secret'),
            'QUEUE_CONNECTION' => config('queue.default'),
        ];

        // This collects missing env values to show a single clear error.
        $missing = [];
        foreach ($required as $key => $value) {
            if ($value === null || $value === '') {
                $missing[] = $key;
            }
        }

        if ($missing) {
            // This fails fast when required configuration is not present.
            throw new \RuntimeException(
                'Missing required environment variables: '.implode(', ', $missing)
            );
        }
    }

    protected function shouldValidateRequiredConfiguration(): bool
    {
        if ($this->app->environment('testing')) {
            return false;
        }

        if (! $this->app->runningInConsole()) {
            return true;
        }

        $command = $_SERVER['argv'][1] ?? null;

        return ! in_array($command, $this->configurationValidationExemptCommands(), true);
    }

    protected function configurationValidationExemptCommands(): array
    {
        return [
            'about',
            'config:cache',
            'config:clear',
            'event:cache',
            'event:clear',
            'optimize',
            'optimize:clear',
            'package:discover',
            'route:cache',
            'route:clear',
            'view:cache',
            'view:clear',
            'wayfinder:generate',
        ];
    }
}
