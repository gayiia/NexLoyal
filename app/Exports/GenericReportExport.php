<?php

// This export helper formats report payloads into a tabular array for spreadsheets.
namespace App\Exports;

use Illuminate\Support\Arr;

// This class converts a report payload into rows suitable for CSV/Excel exports.
class GenericReportExport
{
    // This stores the report payload so export methods can format it consistently.
    public function __construct(private readonly array $payload)
    {
    }

    // This provides a default worksheet title.
    public function title(): string
    {
        return 'Report';
    }

    // This builds the row list with summary sections and the main data table.
    public function rows(): array
    {
        $rows = [];
        $rows[] = [$this->payload['title'] ?? 'Report'];
        $rows[] = ['Generated at', Arr::get($this->payload, 'meta.generated_at', now()->toDateTimeString())];

        // This renders selected filters near the top of the export.
        $filters = Arr::get($this->payload, 'meta.filters', []);
        $rows[] = ['Filters', $this->formatFilters($filters)];
        $rows[] = [];

        // This includes the narrative summary if provided in the report payload.
        $summary = Arr::get($this->payload, 'sections.summary');
        if ($summary) {
            $rows[] = ['Executive Summary'];
            $rows[] = [$summary];
            $rows[] = [];
        }

        // This includes a bullet-style list of insights if present.
        $insights = Arr::get($this->payload, 'sections.insights', []);
        if (!empty($insights)) {
            $rows[] = ['Key Insights'];
            foreach ($insights as $insight) {
                $rows[] = [$insight];
            }
            $rows[] = [];
        }

        // This outputs KPI label/value pairs.
        $kpis = $this->payload['kpis'] ?? [];
        if (!empty($kpis)) {
            $rows[] = ['KPIs'];
            foreach ($kpis as $kpi) {
                $rows[] = [$kpi['label'] ?? '', $kpi['value'] ?? ''];
            }
            $rows[] = [];
        }

        // This writes the methodology section for transparency in reports.
        $methodology = Arr::get($this->payload, 'sections.methodology', []);
        if (!empty($methodology)) {
            $rows[] = ['Methodology'];
            foreach ($methodology as $item) {
                $rows[] = [$item];
            }
            $rows[] = [];
        }

        // This appends the main data table columns and rows.
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

    // This formats filter arrays into a compact, readable string.
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
