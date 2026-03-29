<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ShopifyWebhookLog;
use App\Services\ShopifyWebhookMonitorService;
use Illuminate\Contracts\View\View;

// This controller renders the Shopify webhook monitor under the admin settings area.
class ShopifyWebhookMonitorController extends Controller
{
    public function index(ShopifyWebhookMonitorService $monitor): View
    {
        $definitions = collect($monitor->definitions());
        $expectedTopics = $definitions->pluck('topic')->all();

        $logsByTopic = ShopifyWebhookLog::query()
            ->whereIn('topic', $expectedTopics)
            ->latest('id')
            ->take(300)
            ->get()
            ->groupBy('topic');

        $webhooks = $definitions->map(function (array $definition) use ($logsByTopic): array {
            /** @var \Illuminate\Support\Collection<int, \App\Models\ShopifyWebhookLog> $topicLogs */
            $topicLogs = $logsByTopic->get($definition['topic'], collect());
            $latestLog = $topicLogs->first();

            $status = 'waiting';
            $statusLabel = 'Waiting';

            if ($latestLog) {
                if ($latestLog->delivery_state === 'processed') {
                    $status = 'connected';
                    $statusLabel = 'Connected';
                } elseif ($latestLog->delivery_state === 'ignored') {
                    $status = 'connected';
                    $statusLabel = 'Connected';
                } else {
                    $status = 'issue';
                    $statusLabel = 'Issue';
                }
            }

            return [
                ...$definition,
                'status' => $status,
                'status_label' => $statusLabel,
                'latest_log' => $latestLog ? $this->serializeLog($latestLog) : null,
                'logs' => $topicLogs
                    ->take(12)
                    ->map(fn (ShopifyWebhookLog $log) => $this->serializeLog($log))
                    ->values()
                    ->all(),
            ];
        });

        return view('settings.shopify-webhooks', [
            'webhooks' => $webhooks,
        ]);
    }

    public function showLog(ShopifyWebhookLog $log, ShopifyWebhookMonitorService $monitor): View
    {
        $definition = collect($monitor->definitions())
            ->first(fn (array $item) => $item['topic'] === $log->topic || $item['webhook_key'] === $log->webhook_key);

        $decodedPayload = json_decode((string) $log->payload, true);
        $payload = is_array($decodedPayload)
            ? json_encode($decodedPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            : (string) $log->payload;

        $headers = json_encode($log->request_headers ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return view('settings.shopify-webhook-log', [
            'definition' => $definition,
            'log' => $log,
            'payload' => $payload ?: '{}',
            'headers' => $headers ?: '{}',
        ]);
    }

    private function serializeLog(ShopifyWebhookLog $log): array
    {
        $decodedPayload = json_decode((string) $log->payload, true);

        return [
            'id' => $log->id,
            'topic' => $log->topic,
            'request_path' => $log->request_path,
            'request_url' => $log->request_url,
            'delivery_state' => $log->delivery_state,
            'response_status' => $log->response_status,
            'hmac_valid' => $log->hmac_valid,
            'shop_domain' => $log->shop_domain,
            'shopify_webhook_id' => $log->shopify_webhook_id,
            'shopify_event_id' => $log->shopify_event_id,
            'error_message' => $log->error_message,
            'created_at' => $log->created_at?->toIso8601String(),
            'created_at_label' => $log->created_at?->format('M d, Y H:i:s'),
            'processed_at_label' => $log->processed_at?->format('M d, Y H:i:s'),
            'headers' => json_encode($log->request_headers ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}',
            'payload' => is_array($decodedPayload)
                ? json_encode($decodedPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                : ((string) $log->payload ?: '{}'),
            'reference_url' => route('shopify-webhooks.logs.show', $log),
        ];
    }
}
