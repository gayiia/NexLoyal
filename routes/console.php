<?php

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\CustomerCoupon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule;
use App\Models\PointRule;
use App\Models\AiClusterRun;
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
    $baseAddress = config('services.shopify.webhook_address') ?: rtrim(config('app.url'), '/').'/webhooks/shopify';

    if (!$shopDomain || !$token) {
        $this->error('Missing Shopify credentials. Set SHOPIFY_SHOP_DOMAIN and SHOPIFY_ADMIN_TOKEN.');
        return 1;
    }

    if (!$baseAddress) {
        $this->error('Missing webhook address. Set SHOPIFY_WEBHOOK_ADDRESS or APP_URL.');
        return 1;
    }

    $topics = [
        'customers/create',
        'customers/update',
        'customers/delete',
        'orders/create',
        'orders/paid',
        'orders/fulfilled',
        'orders/refunded',
        'orders/cancelled',
    ];
    $endpoint = "https://{$shopDomain}/admin/api/{$apiVersion}/webhooks.json";

    foreach ($topics as $topic) {
        $address = str_starts_with($topic, 'orders/')
            ? "{$baseAddress}/orders/".str_replace('orders/', '', $topic)
            : "{$baseAddress}/customers";
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

Artisan::command('loyalty:expire-coupons', function () {
    $now = now();
    $expired = CustomerCoupon::query()
        ->whereIn('status', ['active', 'in_progress'])
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', $now)
        ->update([
            'status' => 'expired',
        ]);

    $this->info("Expired {$expired} coupon redemptions.");
    return 0;
})->purpose('Expire coupon redemptions past their validity date');

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
Schedule::command('loyalty:expire-coupons')->hourly();

Artisan::command('ai:seed-coupons {--codes=} {--file=}', function () {
    $codesOption = trim((string) $this->option('codes'));
    $fileOption = trim((string) $this->option('file'));

    $codes = [];
    if ($codesOption !== '') {
        $codes = array_filter(array_map('trim', explode(',', $codesOption)));
    } elseif ($fileOption !== '') {
        $path = $fileOption;
        if (!is_file($path)) {
            $this->error("File not found: {$path}");
            return 1;
        }
        $handle = fopen($path, 'r');
        if (!$handle) {
            $this->error('Unable to read file.');
            return 1;
        }
        $headers = fgetcsv($handle) ?: [];
        $headers = array_map(fn ($header) => strtolower(trim((string) $header)), $headers);
        $codeIndex = array_search('coupon_code', $headers, true);
        if ($codeIndex === false) {
            $codeIndex = array_search('code', $headers, true);
        }
        if ($codeIndex === false) {
            $this->error('CSV missing coupon_code column.');
            fclose($handle);
            return 1;
        }
        while (($row = fgetcsv($handle)) !== false) {
            $value = trim((string) ($row[$codeIndex] ?? ''));
            if ($value !== '') {
                $codes[] = $value;
            }
        }
        fclose($handle);
    } else {
        $this->error('Provide --codes=CODE1,CODE2 or --file=path/to/customer_coupons.csv');
        return 1;
    }

    $codes = array_values(array_unique($codes));
    if (!$codes) {
        $this->warn('No coupon codes found.');
        return 0;
    }

    $now = now();
    $created = 0;
    $skipped = 0;

    foreach ($codes as $code) {
        $exists = Coupon::query()->where('code', $code)->exists();
        if ($exists) {
            $skipped++;
            continue;
        }

        Coupon::query()->create([
            'title' => "AI Seed {$code}",
            'type' => 'amount-order',
            'value_type' => 'fixed',
            'value' => 10,
            'points_value' => 100,
            'start_date' => $now->toDateString(),
            'end_date' => $now->copy()->addYear()->toDateString(),
            'description' => 'Auto-created for AI CSV import.',
            'status' => 'active',
            'code' => $code,
            'is_mystery_box_coupon' => false,
            'is_ai_cluster_coupon' => false,
        ]);
        $created++;
    }

    $this->info("Coupons created: {$created}. Skipped existing: {$skipped}.");
    return 0;
})->purpose('Create missing coupon codes for AI CSV imports');

Artisan::command('ai:reset-clusters {--force}', function () {
    if (!$this->option('force')) {
        if (!$this->confirm('This will delete all AI clustering runs, clusters, and mappings. Continue?')) {
            $this->info('Aborted.');
            return 0;
        }
    }

    $tables = [
        'ai_cluster_customers',
        'ai_clusters',
        'ai_cluster_runs',
    ];

    foreach ($tables as $table) {
        try {
            DB::table($table)->delete();
        } catch (Throwable $exception) {
            $this->error("Failed to clear {$table}: {$exception->getMessage()}");
            return 1;
        }
    }

    $this->info('AI clustering data cleared.');
    return 0;
})->purpose('Delete AI clustering runs, clusters, and cluster customer mappings');

Artisan::command('ai:cleanup-runs {--days=}', function () {
    $days = (int) ($this->option('days') ?: config('ai.cleanup_days', 90));
    if ($days < 1) {
        $this->error('Days must be at least 1.');
        return 1;
    }

    $latestRun = AiClusterRun::query()->orderByDesc('id')->first();
    if (!$latestRun) {
        $this->info('No AI cluster runs found.');
        return 0;
    }

    $cutoff = now()->subDays($days);
    $query = AiClusterRun::query()
        ->where('id', '!=', $latestRun->id)
        ->whereNotNull('completed_at')
        ->where('completed_at', '<', $cutoff);

    $count = $query->count();
    if ($count === 0) {
        $this->info('No AI cluster runs eligible for cleanup.');
        return 0;
    }

    $deleted = $query->delete();
    $this->info("Deleted {$deleted} AI cluster runs older than {$days} days (kept latest run #{$latestRun->id}).");
    return 0;
})->purpose('Delete AI clustering runs older than N days while preserving the latest run');
