<?php

// This controller renders reports and handles exports for the admin UI.
namespace App\Http\Controllers\Admin;

use App\Exports\GenericReportExport;
use App\Models\AiCluster;
use App\Models\AiClusterRun;
use App\Models\Tier;
use App\Services\Reports\ReportsService;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

// This class coordinates report generation, filtering, and output formats.
class ReportsController extends Controller
{
    // This shows the report dashboard with any previously generated data.
    public function index(Request $request, ReportsService $reports)
    {
        $filters = $this->defaultFilters();

        // This restores any previously generated report from the session.
        $payload = $request->session()->get('reports.payload');
        $selected = $request->session()->get('reports.report_key');
        $filters = array_merge($filters, $request->session()->get('reports.filters', []));

        $tiers = Tier::query()->orderBy('min_points')->get();
        $clusters = $this->latestClusters();

        return view('reports', [
            'reports' => $reports->availableReports(),
            'filters' => $filters,
            'payload' => $payload,
            'selectedReport' => $selected,
            'tiers' => $tiers,
            'clusters' => $clusters,
        ]);
    }

    // This validates filters, generates the report, and stores it in session.
    public function generate(Request $request, ReportsService $reports)
    {
        $validated = $request->validate($this->rules($reports));
        $filters = $this->normalizeFilters($validated);

        $payload = $reports->generate($validated['report_key'], $filters);

        // This keeps the latest report in session for export actions.
        $request->session()->put('reports.payload', $payload);
        $request->session()->put('reports.report_key', $validated['report_key']);
        $request->session()->put('reports.filters', $filters);

        $tiers = Tier::query()->orderBy('min_points')->get();
        $clusters = $this->latestClusters();

        return view('reports', [
            'reports' => $reports->availableReports(),
            'filters' => $filters,
            'payload' => $payload,
            'selectedReport' => $validated['report_key'],
            'tiers' => $tiers,
            'clusters' => $clusters,
        ]);
    }

    // This exports the report data to an Excel file.
    public function exportExcel(Request $request, ReportsService $reports)
    {
        $payload = $request->session()->get('reports.payload');
        $reportKey = $request->session()->get('reports.report_key');
        $filters = $request->session()->get('reports.filters', []);

        // This allows direct export by query parameters without prior session state.
        if ($request->query('report_key')) {
            $validated = $request->validate($this->rules($reports));
            $filters = $this->normalizeFilters($validated);
            $payload = $reports->generate($validated['report_key'], $filters);
            $reportKey = $validated['report_key'];
        }

        if (!$payload || !$reportKey) {
            return redirect()->route('reports')->with('status', 'Please generate a report before exporting.');
        }

        // This formats the report into a tabular export.
        $export = new GenericReportExport($payload);
        $fileBase = $reportKey . '_' . now()->format('Ymd_His');

        return Excel::create($fileBase, function ($excel) use ($export) {
            $excel->sheet($export->title(), function ($sheet) use ($export) {
                $sheet->fromArray($export->rows(), null, 'A1', false, false);
            });
        })->download('xlsx');
    }

    // This exports the report data to a PDF file.
    public function exportPdf()
    {
        $payload = request()->session()->get('reports.payload');
        $reportKey = request()->session()->get('reports.report_key');

        if (!$payload || !$reportKey) {
            return redirect()->route('reports')->with('status', 'Please generate a report before exporting.');
        }

        // This names the PDF using the report key and timestamp.
        $fileName = $reportKey . '_' . now()->format('Ymd_His') . '.pdf';

        return Pdf::loadView('reports-pdf', [
            'payload' => $payload,
        ])->setPaper('a4', 'portrait')->download($fileName);
    }

    // This defines validation rules for report filters.
    private function rules(ReportsService $reports): array
    {
        $keys = array_keys($reports->availableReports());

        return [
            'report_key' => ['required', Rule::in($keys)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'customer_type' => ['nullable', Rule::in(['all', 'loyalty', 'non_loyalty'])],
            'tier' => ['nullable', 'integer', 'exists:tiers,id'],
            'cluster' => ['nullable', 'integer', 'exists:ai_clusters,id'],
            'min_total_spent' => ['nullable', 'numeric', 'min:0'],
            'min_orders_count' => ['nullable', 'numeric', 'min:0'],
            'group_by' => ['nullable', Rule::in(['day', 'week', 'month'])],
            'top_n' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    // This normalizes optional filters into a consistent shape.
    private function normalizeFilters(array $validated): array
    {
        return [
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'customer_type' => $validated['customer_type'] ?? 'all',
            'tier' => $validated['tier'] ?? null,
            'cluster' => $validated['cluster'] ?? null,
            'min_total_spent' => Arr::get($validated, 'min_total_spent', null),
            'min_orders_count' => Arr::get($validated, 'min_orders_count', null),
            'group_by' => $validated['group_by'] ?? 'day',
            'top_n' => $validated['top_n'] ?? 10,
        ];
    }

    // This provides default filter values for first load.
    private function defaultFilters(): array
    {
        return [
            'start_date' => now()->subDays(29)->toDateString(),
            'end_date' => now()->toDateString(),
            'customer_type' => 'all',
            'tier' => null,
            'cluster' => null,
            'min_total_spent' => null,
            'min_orders_count' => null,
            'group_by' => 'day',
            'top_n' => 10,
        ];
    }

    // This loads clusters from the most recent AI run for report filters.
    private function latestClusters()
    {
        $run = AiClusterRun::query()->orderByDesc('id')->first();
        if (!$run) {
            return collect();
        }

        return AiCluster::query()
            ->where('ai_cluster_run_id', $run->id)
            ->orderBy('cluster_index')
            ->get();
    }
}
