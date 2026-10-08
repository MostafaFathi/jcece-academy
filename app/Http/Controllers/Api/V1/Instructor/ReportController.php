<?php

namespace App\Http\Controllers\Api\V1\Instructor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ReportRequest;
use App\Services\Reporting\ReportFilters;
use App\Services\Reporting\ReportQueryService;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function show(string $type, ReportRequest $request, ReportQueryService $reports): JsonResponse
    {
        abort_unless(in_array($type, ['courses', 'learners', 'reviews', 'quizzes'], true), 404);
        $filters = ReportFilters::fromValidated($request->validated());

        return response()->json(['data' => $reports->run($type, $filters, $request->user()->id), 'filters' => ['from' => $filters->from, 'to' => $filters->to, 'timezone' => 'Asia/Hebron']]);
    }
}
