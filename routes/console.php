<?php

use App\Models\Customer;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule;
use App\Models\PointRule;
use Illuminate\Support\Facades\Mail;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('shopify:backfill-customers {--since_id=0}', function () {
    $shopDomain = config('services.shopify.shop_domain');
    $token = config('services.shopify.admin_token');
    $apiVersion = config('services.shopify.api_version');

    if (!$shopDomain || !$token) {
        $this->error('Missing Shopify credentials. Set SHOPIFY_SHOP_DOMAIN and SHOPIFY_ADMIN_TOKEN.');
        return 1;
    }

    $sinceId = (int) $this->option('since_id');
    $baseUrl = "https://{$shopDomain}/admin/api/{$apiVersion}/customers.json";
    $fields = 'id,first_name,last_name,email,phone,state,orders_count,total_spent,currency,created_at';
    $nextUrl = "{$baseUrl}?limit=250&fields={$fields}&since_id={$sinceId}";
    $imported = 0;
    $page = 0;

    $this->info('Starting Shopify customer backfill...');

    while ($nextUrl) {
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
        ])->get($nextUrl);

        if (!$response->ok()) {
            $this->error("Shopify request failed ({$response->status()}): ".$response->body());
            return 1;
        }

        $customers = $response->json('customers', []);
        foreach ($customers as $customer) {
            if (empty($customer['id'])) {
                continue;
            }

            Customer::updateOrCreate(
                ['shopify_id' => (string) $customer['id']],
                [
                    'first_name' => $customer['first_name'] ?? null,
                    'last_name' => $customer['last_name'] ?? null,
                    'email' => $customer['email'] ?? null,
                    'phone' => $customer['phone'] ?? null,
                    'status' => $customer['state'] ?? null,
                    'orders_count' => (int) ($customer['orders_count'] ?? 0),
                    'total_spent' => (float) ($customer['total_spent'] ?? 0),
                    'currency' => $customer['currency'] ?? null,
                    'shopify_created_at' => $customer['created_at'] ?? null,
                ]
            );
        }

        $page++;
        $imported += count($customers);
        $this->info("Imported {$imported} customers (page {$page}).");

        $nextUrl = null;
        $linkHeader = $response->header('Link');
        if ($linkHeader && preg_match('/<([^>]+)>;\\s*rel=\"next\"/', $linkHeader, $matches)) {
            $nextUrl = $matches[1];
        }
    }

    $this->info("Backfill complete. Total imported: {$imported}.");
    return 0;
})->purpose('Backfill customers from Shopify Admin API');

Artisan::command('shopify:sync-customers {--prune}', function () {
    $shopDomain = config('services.shopify.shop_domain');
    $token = config('services.shopify.admin_token');
    $apiVersion = config('services.shopify.api_version');

    if (!$shopDomain || !$token) {
        $this->error('Missing Shopify credentials. Set SHOPIFY_SHOP_DOMAIN and SHOPIFY_ADMIN_TOKEN.');
        return 1;
    }

    $baseUrl = "https://{$shopDomain}/admin/api/{$apiVersion}/customers.json";
    $fields = 'id,first_name,last_name,email,phone,state,orders_count,total_spent,currency,created_at';
    $nextUrl = "{$baseUrl}?limit=250&fields={$fields}";
    $imported = 0;
    $page = 0;
    $shopifyIds = [];

    $this->info('Starting Shopify customer sync...');

    while ($nextUrl) {
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
        ])->get($nextUrl);

        if (!$response->ok()) {
            $this->error("Shopify request failed ({$response->status()}): ".$response->body());
            return 1;
        }

        $customers = $response->json('customers', []);
        foreach ($customers as $customer) {
            if (empty($customer['id'])) {
                continue;
            }

            $shopifyIds[] = (string) $customer['id'];

            Customer::updateOrCreate(
                ['shopify_id' => (string) $customer['id']],
                [
                    'first_name' => $customer['first_name'] ?? null,
                    'last_name' => $customer['last_name'] ?? null,
                    'email' => $customer['email'] ?? null,
                    'phone' => $customer['phone'] ?? null,
                    'status' => $customer['state'] ?? null,
                    'orders_count' => (int) ($customer['orders_count'] ?? 0),
                    'total_spent' => (float) ($customer['total_spent'] ?? 0),
                    'currency' => $customer['currency'] ?? null,
                    'shopify_created_at' => $customer['created_at'] ?? null,
                ]
            );
        }

        $page++;
        $imported += count($customers);
        $this->info("Synced {$imported} customers (page {$page}).");

        $nextUrl = null;
        $linkHeader = $response->header('Link');
        if ($linkHeader && preg_match('/<([^>]+)>;\\s*rel=\"next\"/', $linkHeader, $matches)) {
            $nextUrl = $matches[1];
        }
    }

    if ($this->option('prune')) {
        if (count($shopifyIds) === 0) {
            $this->warn('Skipping prune because no Shopify customers were fetched.');
        } else {
            $deleted = Customer::whereNotNull('shopify_id')
                ->where('shopify_id', '!=', '')
                ->whereNotIn('shopify_id', $shopifyIds)
                ->delete();
            $this->info("Pruned {$deleted} local customers not found in Shopify.");
        }
    }

    $this->info("Sync complete. Total synced: {$imported}.");
    return 0;
})->purpose('Sync customers from Shopify and optionally prune locals');

Artisan::command('shopify:register-webhooks', function () {
    $shopDomain = config('services.shopify.shop_domain');
    $token = config('services.shopify.admin_token');
    $apiVersion = config('services.shopify.api_version');
    $address = config('services.shopify.webhook_address') ?: rtrim(config('app.url'), '/').'/webhooks/shopify/customers';

    if (!$shopDomain || !$token) {
        $this->error('Missing Shopify credentials. Set SHOPIFY_SHOP_DOMAIN and SHOPIFY_ADMIN_TOKEN.');
        return 1;
    }

    if (!$address) {
        $this->error('Missing webhook address. Set SHOPIFY_WEBHOOK_ADDRESS or APP_URL.');
        return 1;
    }

    $topics = ['customers/create', 'customers/update', 'customers/delete'];
    $endpoint = "https://{$shopDomain}/admin/api/{$apiVersion}/webhooks.json";

    foreach ($topics as $topic) {
        $payload = [
            'webhook' => [
                'topic' => $topic,
                'address' => $address,
                'format' => 'json',
            ],
        ];

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
        ])->post($endpoint, $payload);

        if (!$response->ok()) {
            $this->error("Failed to register {$topic} ({$response->status()}): ".$response->body());
            continue;
        }

        $id = data_get($response->json(), 'webhook.id');
        $this->info("Registered {$topic} webhook (id {$id}).");
    }

    return 0;
})->purpose('Register Shopify customer webhooks');

Artisan::command('loyalty:award-birthday', function () {
    $rule = PointRule::query()->first();
    $points = (int) ($rule?->birthday_points ?? 0);
    if ($points <= 0) {
        $this->info('Birthday points are not configured.');
        return 0;
    }

    $today = now();
    $monthDay = $today->format('m-d');

    $customers = Customer::query()
        ->whereNotNull('birthday')
        ->whereRaw("DATE_FORMAT(birthday, '%m-%d') = ?", [$monthDay])
        ->get();

    $awarded = 0;
    foreach ($customers as $customer) {
        $lastYear = $customer->birthday_rewarded_at?->format('Y');
        if ($lastYear === $today->format('Y')) {
            continue;
        }

        $customer->loyalty_points += $points;
        $customer->birthday_rewarded_at = $today->toDateString();
        $customer->save();

        if ($customer->email) {
            Mail::send('emails.birthday', [
                'customer' => $customer,
                'points' => $points,
            ], function ($message) use ($customer): void {
                $message->to($customer->email, $customer->full_name ?: $customer->email)
                    ->subject('Happy Birthday from NexLoyal');
            });
        }

        $awarded++;
    }

    $this->info("Awarded birthday points to {$awarded} customers.");
    return 0;
})->purpose('Award birthday points and send birthday emails');

Schedule::command('loyalty:award-birthday')->dailyAt('00:10');
