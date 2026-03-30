<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ShopifyWebhookLog;
use App\Services\ShopifyWebhookMonitorService;
use App\Services\ShopifyWebhookRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\View;

// This controller renders the Shopify webhook monitor under the admin settings area.
class ShopifyWebhookMonitorController extends Controller
{
    public function index(
        ShopifyWebhookMonitorService $monitor,
        ShopifyWebhookRegistrationService $registration
    ): View
    {
        $definitions = collect($monitor->definitions());
        $expectedTopics = $definitions->pluck('topic')->all();

        $logsByTopic = ShopifyWebhookLog::query()
            ->whereIn('topic', $expectedTopics)
            ->latest('id')
            ->take(300)
            ->get()
            ->groupBy('topic');

        $verificationError = null;
        $connectionStatusBySignature = collect();

        try {
            $connectionStatusBySignature = collect($registration->inspect($definitions->all())['results'] ?? [])
                ->keyBy(fn (array $item) => $this->definitionSignature($item));
        } catch (\Throwable $exception) {
            report($exception);
            $verificationError = $exception->getMessage();
        }

        $webhooks = $definitions->map(function (array $definition) use ($logsByTopic, $connectionStatusBySignature, $verificationError): array {
            /** @var \Illuminate\Support\Collection<int, \App\Models\ShopifyWebhookLog> $topicLogs */
            $topicLogs = $logsByTopic->get($definition['topic'], collect());
            $latestLog = $topicLogs->first();
            $connection = $connectionStatusBySignature->get($this->definitionSignature($definition));

            $status = 'waiting';
            $statusLabel = 'Not connected';
            $connectionMessage = 'Webhook is not registered in Shopify.';
            $checkedAtLabel = null;
            $shopifyWebhookId = null;

            if (is_array($connection)) {
                $status = (string) ($connection['status'] ?? $status);
                $statusLabel = (string) ($connection['status_label'] ?? $statusLabel);
                $connectionMessage = (string) ($connection['connection_message'] ?? $connectionMessage);
                $checkedAtLabel = $connection['checked_at_label'] ?? null;
                $shopifyWebhookId = $connection['shopify_webhook_id'] ?? null;
            } elseif ($verificationError !== null) {
                $status = 'issue';
                $statusLabel = 'Issue';
                $connectionMessage = 'Unable to verify webhook registration with Shopify.';
            }

            return [
                ...$definition,
                'status' => $status,
                'status_label' => $statusLabel,
                'connection_message' => $connectionMessage,
                'checked_at_label' => $checkedAtLabel,
                'shopify_webhook_id' => $shopifyWebhookId,
                'verification_error' => $verificationError,
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
            'shopifyVerificationError' => $verificationError,
        ]);
    }

    public function register(
        ShopifyWebhookMonitorService $monitor,
        ShopifyWebhookRegistrationService $registration
    ): RedirectResponse {
        return $this->runWebhookAction(
            fn () => $registration->register($monitor->definitions()),
            'create'
        );
    }

    public function destroy(
        ShopifyWebhookMonitorService $monitor,
        ShopifyWebhookRegistrationService $registration
    ): RedirectResponse {
        return $this->runWebhookAction(
            fn () => $registration->delete($monitor->definitions()),
            'delete'
        );
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

    private function runWebhookAction(callable $action, string $operation): RedirectResponse
    {
        try {
            $summary = $action();
        } catch (\Throwable $exception) {
            report($exception);

            return to_route('shopify-webhooks')->with('shopify_webhook_feedback', [
                'title' => $this->feedbackTitle($operation),
                'level' => 'error',
                'message' => $exception->getMessage(),
                'created_count' => 0,
                'existing_count' => 0,
                'deleted_count' => 0,
                'missing_count' => 0,
                'failed_count' => 0,
                'stats' => $this->feedbackStats($operation, []),
                'results' => [],
            ]);
        }

        return to_route('shopify-webhooks')->with('shopify_webhook_feedback', [
            ...$summary,
            'title' => $this->feedbackTitle($operation),
            'level' => $this->feedbackLevel($operation, $summary),
            'message' => $this->feedbackMessage($operation, $summary),
            'stats' => $this->feedbackStats($operation, $summary),
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

    private function feedbackTitle(string $operation): string
    {
        return $operation === 'delete'
            ? 'Webhook deletion result'
            : 'Webhook connection result';
    }

    private function feedbackMessage(string $operation, array $summary): string
    {
        $failed = (int) ($summary['failed_count'] ?? 0);

        if ($operation === 'delete') {
            $deleted = (int) ($summary['deleted_count'] ?? 0);
            $missing = (int) ($summary['missing_count'] ?? 0);

            if ($deleted === 0 && $missing > 0 && $failed === 0) {
                return 'No matching webhooks were found in Shopify.';
            }

            if ($failed === 0) {
                return "Deleted {$deleted} webhook(s). {$missing} not found.";
            }

            return "Deleted {$deleted} webhook(s). {$missing} not found. {$failed} failed.";
        }

        $created = (int) ($summary['created_count'] ?? 0);
        $existing = (int) ($summary['existing_count'] ?? 0);

        if ($created === 0 && $existing > 0 && $failed === 0) {
            return 'All listed webhooks are already connected in Shopify.';
        }

        if ($failed === 0) {
            return "Connected {$created} webhook(s). {$existing} already connected.";
        }

        return "Connected {$created} webhook(s). {$existing} already connected. {$failed} failed.";
    }

    private function feedbackLevel(string $operation, array $summary): string
    {
        if ($operation === 'delete') {
            return $this->summaryLevel(
                (int) ($summary['deleted_count'] ?? 0),
                (int) ($summary['missing_count'] ?? 0),
                (int) ($summary['failed_count'] ?? 0),
            );
        }

        return $this->summaryLevel(
            (int) ($summary['created_count'] ?? 0),
            (int) ($summary['existing_count'] ?? 0),
            (int) ($summary['failed_count'] ?? 0),
        );
    }

    private function feedbackStats(string $operation, array $summary): array
    {
        if ($operation === 'delete') {
            return [
                ['label' => 'Deleted', 'value' => (int) ($summary['deleted_count'] ?? 0)],
                ['label' => 'Missing', 'value' => (int) ($summary['missing_count'] ?? 0)],
                ['label' => 'Failed', 'value' => (int) ($summary['failed_count'] ?? 0)],
            ];
        }

        return [
            ['label' => 'Connected', 'value' => (int) ($summary['created_count'] ?? 0)],
            ['label' => 'Existing', 'value' => (int) ($summary['existing_count'] ?? 0)],
            ['label' => 'Failed', 'value' => (int) ($summary['failed_count'] ?? 0)],
        ];
    }

    private function summaryLevel(int $successfulCount, int $neutralCount, int $failedCount): string
    {
        if ($failedCount > 0 && $successfulCount === 0 && $neutralCount === 0) {
            return 'error';
        }

        if ($failedCount > 0) {
            return 'warning';
        }

        return 'success';
    }

    private function definitionSignature(array $definition): string
    {
        return (string) ($definition['topic'] ?? '').'|'.rtrim((string) ($definition['address'] ?? ''), '/');
    }
}
