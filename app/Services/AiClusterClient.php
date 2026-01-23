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
        $response = Http::timeout(30)->post($endpoint, $payload);

        if (!$response->successful()) {
            throw new \RuntimeException('AI service training failed.');
        }

        $data = $response->json();
        if (!is_array($data) || empty($data['labels'])) {
            throw new \RuntimeException('AI service returned invalid response.');
        }

        return $data;
    }
}
