<?php

namespace App\Exports;

use Illuminate\Support\Arr;

class GenericReportExport
{
    public function __construct(private readonly array $payload)
    {
    }

    public function title(): string
    {
        return 'Report';
    }

    public function rows(): array
    {
        $rows = [];
        $rows[] = [$this->payload['title'] ?? 'Report'];
        $rows[] = ['Generated at', Arr::get($this->payload, 'meta.generated_at', now()->toDateTimeString())];

        $filters = Arr::get($this->payload, 'meta.filters', []);
        $rows[] = ['Filters', $this->formatFilters($filters)];
        $rows[] = [];

        $summary = Arr::get($this->payload, 'sections.summary');
        if ($summary) {
            $rows[] = ['Executive Summary'];
            $rows[] = [$summary];
            $rows[] = [];
        }

        $insights = Arr::get($this->payload, 'sections.insights', []);
        if (!empty($insights)) {
            $rows[] = ['Key Insights'];
            foreach ($insights as $insight) {
                $rows[] = [$insight];
            }
            $rows[] = [];
        }

        $kpis = $this->payload['kpis'] ?? [];
        if (!empty($kpis)) {
            $rows[] = ['KPIs'];
            foreach ($kpis as $kpi) {
                $rows[] = [$kpi['label'] ?? '', $kpi['value'] ?? ''];
            }
            $rows[] = [];
        }

        $methodology = Arr::get($this->payload, 'sections.methodology', []);
        if (!empty($methodology)) {
            $rows[] = ['Methodology'];
            foreach ($methodology as $item) {
                $rows[] = [$item];
            }
            $rows[] = [];
        }

        $table = $this->payload['table'] ?? [];
        $columns = $table['columns'] ?? [];
        if (!empty($columns)) {
            $rows[] = $columns;
        }

        $tableRows = $table['rows'] ?? [];
        foreach ($tableRows as $row) {
            $rows[] = $row;
        }

        return $rows;
    }

    private function formatFilters(array $filters): string
    {
        return collect($filters)
            ->reject(fn ($value, $key) => str_starts_with((string) $key, '_'))
            ->map(function ($value, $key) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            if ($value === null || $value === '') {
                $value = 'n/a';
            }
            return $key . ': ' . $value;
        })->implode(' | ');
    }
}
