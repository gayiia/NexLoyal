<?php

// This client wraps HTTP calls to the external AI clustering service.
namespace App\Services;

use Illuminate\Support\Facades\Http;

// This class encapsulates AI training and prediction requests with consistent error handling.
class AiClusterClient
{
    // This checks whether the AI service is reachable before queueing or training.
    public function health(): array
    {
        $baseUrl = trim((string) config('services.ai_service_url'));
        if ($baseUrl === '') {
            throw new \RuntimeException('AI service URL is not configured.');
        }

        $endpoint = rtrim($baseUrl, '/').'/health';
        $response = Http::timeout(5)
            ->get($endpoint);

        if (!$response->successful()) {
            $status = $response->status();
            $body = $response->body();
            throw new \RuntimeException("AI service health check failed ({$status}): {$body}");
        }

        $data = $response->json();
        if (!is_array($data) || ($data['status'] ?? null) !== 'ok') {
            throw new \RuntimeException('AI service health endpoint returned an invalid response.');
        }

        return $data;
    }

    // This sends a training request to the AI service and returns the parsed response.
    public function train(array $payload): array
    {
        // This validates the base URL early so the error is clear before any HTTP call.
        $baseUrl = trim((string) config('services.ai_service_url'));
        if ($baseUrl === '') {
            throw new \RuntimeException('AI service URL is not configured.');
        }

        // This builds the training endpoint path for the clustering API.
        $endpoint = rtrim($baseUrl, '/').'/ai/cluster/train';
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        // This sends the request with timeouts and optional authentication.
        $response = Http::timeout((int) config('ai.ai_timeout_seconds', 30))
            ->withHeaders($this->authHeaders())
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->withBody($body, 'application/json')
            ->post($endpoint);

        if (!$response->successful()) {
            // This includes status and body to help debug remote errors.
            $status = $response->status();
            $body = $response->body();
            throw new \RuntimeException("AI service training failed ({$status}): {$body}");
        }

        // This validates the expected shape before returning data to callers.
        $data = $response->json();
        if (!is_array($data) || empty($data['labels'])) {
            throw new \RuntimeException('AI service returned invalid response.');
        }

        return $data;
    }

    // This streams a prebuilt JSON training payload from disk to avoid large in-memory request bodies.
    public function trainFromJsonFile(string $path): array
    {
        $baseUrl = trim((string) config('services.ai_service_url'));
        if ($baseUrl === '') {
            throw new \RuntimeException('AI service URL is not configured.');
        }
        if (!is_file($path)) {
            throw new \RuntimeException('AI training payload file was not found.');
        }

        $endpoint = rtrim($baseUrl, '/').'/ai/cluster/train';
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('AI training payload file could not be opened.');
        }

        try {
            $response = Http::timeout((int) config('ai.ai_timeout_seconds', 30))
                ->withHeaders(array_merge($this->authHeaders(), [
                    'Content-Type' => 'application/json',
                ]))
                ->withOptions(['body' => $handle])
                ->send('POST', $endpoint);
        } finally {
            fclose($handle);
        }

        if (!$response->successful()) {
            $status = $response->status();
            $body = $response->body();
            throw new \RuntimeException("AI service training failed ({$status}): {$body}");
        }

        $data = $response->json();
        if (!is_array($data) || empty($data['labels'])) {
            throw new \RuntimeException('AI service returned invalid response.');
        }

        return $data;
    }

    // This requests model metadata so Laravel can decide whether retraining is needed.
    public function modelMetadata(): array
    {
        $baseUrl = trim((string) config('services.ai_service_url'));
        if ($baseUrl === '') {
            throw new \RuntimeException('AI service URL is not configured.');
        }

        $endpoint = rtrim($baseUrl, '/').'/ai/model/metadata';
        $response = Http::timeout((int) config('ai.ai_timeout_seconds', 30))
            ->withHeaders($this->authHeaders())
            ->get($endpoint);

        if (!$response->successful()) {
            $status = $response->status();
            $body = $response->body();
            throw new \RuntimeException("AI model metadata request failed ({$status}): {$body}");
        }

        $data = $response->json();
        if (!is_array($data)) {
            throw new \RuntimeException('AI service returned invalid metadata response.');
        }

        return $data;
    }

    // This sends a batch prediction payload from disk to reuse a trained model without retraining.
    public function predictBatchFromJsonFile(string $path): array
    {
        $baseUrl = trim((string) config('services.ai_service_url'));
        if ($baseUrl === '') {
            throw new \RuntimeException('AI service URL is not configured.');
        }
        if (!is_file($path)) {
            throw new \RuntimeException('AI prediction payload file was not found.');
        }

        $endpoint = rtrim($baseUrl, '/').'/ai/cluster/predict-batch';
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('AI prediction payload file could not be opened.');
        }

        try {
            $response = Http::timeout((int) config('ai.ai_timeout_seconds', 30))
                ->withHeaders(array_merge($this->authHeaders(), [
                    'Content-Type' => 'application/json',
                ]))
                ->withOptions(['body' => $handle])
                ->send('POST', $endpoint);
        } finally {
            fclose($handle);
        }

        if (!$response->successful()) {
            $status = $response->status();
            $body = $response->body();
            throw new \RuntimeException("AI batch prediction failed ({$status}): {$body}");
        }

        $data = $response->json();
        if (!is_array($data) || !is_array($data['labels'] ?? null)) {
            throw new \RuntimeException('AI service returned invalid batch prediction response.');
        }

        return $data;
    }

    // This sends a prediction request for a single feature vector.
    public function predict(array $payload): array
    {
        // This validates the base URL early so the error is clear before any HTTP call.
        $baseUrl = trim((string) config('services.ai_service_url'));
        if ($baseUrl === '') {
            throw new \RuntimeException('AI service URL is not configured.');
        }

        // This builds the prediction endpoint path for the clustering API.
        $endpoint = rtrim($baseUrl, '/').'/ai/cluster/predict';
        // This sends the request with timeouts and optional authentication.
        $response = Http::timeout((int) config('ai.ai_timeout_seconds', 30))
            ->withHeaders($this->authHeaders())
            ->post($endpoint, $payload);

        if (!$response->successful()) {
            // This includes status and body to help debug remote errors.
            $status = $response->status();
            $body = $response->body();
            throw new \RuntimeException("AI service prediction failed ({$status}): {$body}");
        }

        // This validates the expected shape before returning data to callers.
        $data = $response->json();
        if (!is_array($data) || !array_key_exists('cluster_id', $data)) {
            throw new \RuntimeException('AI service returned invalid prediction response.');
        }

        return $data;
    }

    // This builds authentication headers if an API key is configured.
    private function authHeaders(): array
    {
        $apiKey = trim((string) config('ai.api_key'));
        if ($apiKey === '') {
            return [];
        }

        return ['X-AI-KEY' => $apiKey];
    }
}
