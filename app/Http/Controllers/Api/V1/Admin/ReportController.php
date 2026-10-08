<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ReportRequest;
use App\PermissionName;
use App\Services\AuditTrail;
use App\Services\Reporting\ReportExportService;
use App\Services\Reporting\ReportFilters;
use App\Services\Reporting\ReportQueryService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function show(string $type, ReportRequest $request, ReportQueryService $reports): JsonResponse
    {
        abort_unless(in_array($type, ReportQueryService::TYPES, true), 404);
        $filters = ReportFilters::fromValidated($request->validated());

        return response()->json(['data' => $reports->run($type, $filters), 'filters' => ['from' => $filters->from, 'to' => $filters->to, 'timezone' => 'Asia/Hebron']]);
    }

    public function export(string $type, string $format, ReportRequest $request, ReportQueryService $reports, ReportExportService $exports, AuditTrail $audit): Response
    {
        abort_unless(in_array($type, ReportQueryService::TYPES, true), 404);
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);
        abort_unless($request->user()->can(PermissionName::ReportsExport->value), 403);
        $values = $request->validated();
        unset($values['page']);
        $filters = ReportFilters::fromValidated($values);
        abort_if($type === 'sales' && ! $reports->salesExportWithinLimit($filters, 500), 422, 'Export is limited to 500 sales or refund transactions. Narrow the filters.');
        $report = $reports->run($type, $filters, null, 501);
        abort_if($report['rows']->total() > 500, 422, 'Export is limited to 500 rows. Narrow the date or entity filters.');
        $audit->record('report.export_requested', $request->user(), $request->user(), [
            'report_type' => $type, 'report_format' => $format, 'report_from' => $filters->from, 'report_to' => $filters->to,
        ]);

        return $exports->download($type, $format, $report, $filters);
    }
}
