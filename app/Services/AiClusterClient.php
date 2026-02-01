<?php

// This client wraps HTTP calls to the external AI clustering service.
namespace App\Services;

use Illuminate\Support\Facades\Http;

// This class encapsulates AI training and prediction requests with consistent error handling.
class AiClusterClient
{
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
        // This sends the request with timeouts and optional authentication.
        $response = Http::timeout((int) config('ai.ai_timeout_seconds', 30))
            ->withHeaders($this->authHeaders())
            ->post($endpoint, $payload);

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
