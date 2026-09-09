<?php

namespace App\Http\Controllers;

use App\Platform\ReportCatalog;
use App\Services\ReportingService;
use App\Support\ReportExporter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ReportsController extends Controller
{
    public function __construct(private readonly ReportingService $reports) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('reports.view'), 403);

        return view('reports.index', $this->reports->catalog($request->user(), $request->query()));
    }

    public function show(Request $request, string $report): View
    {
        $payload = $this->reports->run($request->user(), $report, $request->query());

        return view('reports.show', [
            'report' => $payload,
            'query' => $request->query(),
        ]);
    }

    public function export(Request $request, string $report): Response
    {
        abort_unless($request->user()?->can('reports.export'), 403);
        $format = strtolower((string) $request->query('format', 'csv'));
        abort_unless(in_array($format, ReportCatalog::FORMATS, true), 422, 'Export format must be csv, xlsx, or pdf');
        $payload = $this->reports->run($request->user(), $report, $request->query());

        return ReportExporter::download(
            $format,
            $report.'-report',
            (string) $payload['label'],
            $payload['rows'],
        );
    }
}
