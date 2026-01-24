<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AiClusterClient
{
    public function train(array $payload): array
    {
        $baseUrl = trim((string) config('services.ai_service_url'));
        if ($baseUrl === '') {
            throw new \RuntimeException('AI service URL is not configured.');
        }

        $endpoint = rtrim($baseUrl, '/').'/ai/cluster/train';
        $response = Http::timeout((int) config('ai.ai_timeout_seconds', 30))
            ->withHeaders($this->authHeaders())
            ->post($endpoint, $payload);

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

    public function predict(array $payload): array
    {
        $baseUrl = trim((string) config('services.ai_service_url'));
        if ($baseUrl === '') {
            throw new \RuntimeException('AI service URL is not configured.');
        }

        $endpoint = rtrim($baseUrl, '/').'/ai/cluster/predict';
        $response = Http::timeout((int) config('ai.ai_timeout_seconds', 30))
            ->withHeaders($this->authHeaders())
            ->post($endpoint, $payload);

        if (!$response->successful()) {
            $status = $response->status();
            $body = $response->body();
            throw new \RuntimeException("AI service prediction failed ({$status}): {$body}");
        }

        $data = $response->json();
        if (!is_array($data) || !array_key_exists('cluster_id', $data)) {
            throw new \RuntimeException('AI service returned invalid prediction response.');
        }

        return $data;
    }

    private function authHeaders(): array
    {
        $apiKey = trim((string) config('ai.api_key'));
        if ($apiKey === '') {
            return [];
        }

        return ['X-AI-KEY' => $apiKey];
    }
}
