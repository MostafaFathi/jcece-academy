<?php

namespace App\Services\Reporting;

use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\Response;

class ReportExportService
{
    private const COLUMNS = [
        'sales' => ['order_number', 'paid_at', 'currency', 'total', 'discount_total', 'coupon_code_snapshot'],
        'payments' => ['order_number', 'method', 'status', 'currency', 'amount', 'created_at'],
        'refunds' => ['order_number', 'status', 'amount', 'currency', 'created_at', 'processed_at'],
        'courses' => ['id', 'title', 'instructor_name', 'status', 'enrollments', 'learners', 'completions', 'completion_percentage', 'package_access_learners', 'direct_sales', 'rating_count', 'rating_average'],
        'learners' => ['course_title', 'learner_name', 'status', 'enrolled_at', 'completed_at', 'access_source', 'access_active', 'access_expires_at', 'completed_lessons', 'total_lessons', 'progress_percentage'],
        'instructors' => ['name', 'courses', 'learners', 'enrollments'],
        'reviews' => ['course_title', 'rating_count', 'rating_average', 'five_star_count'],
        'quizzes' => ['course_title', 'quiz_title', 'attempts', 'passed', 'pass_rate_percentage', 'average_percentage'],
        'packages' => ['package_title', 'currency', 'purchases', 'learners', 'gross_sales', 'discounts'],
        'coupons' => ['coupon_code', 'currency', 'redemptions', 'coupon_discount'],
    ];

    private const SUMMARY_COLUMNS = [
        'sales' => ['currency', 'gross_sales', 'discounts', 'refunds', 'net_sales', 'order_count', 'zero_total_orders', 'average_order_value'],
        'payments' => ['currency', 'status', 'count', 'amount'],
        'refunds' => ['currency', 'status', 'count', 'amount'],
        'learners' => ['enrollment_count'],
    ];

    /** @param array<string, mixed> $report */
    public function download(string $type, string $format, array $report, ReportFilters $filters): Response
    {
        $columns = self::COLUMNS[$type];
        $rows = collect($report['rows']->items())->map(fn (array|object $row): array => array_map(
            fn (string $column): string => $this->safeCell($this->scalar(data_get($row, $column))),
            $columns,
        ))->all();
        $summaryColumns = self::SUMMARY_COLUMNS[$type] ?? [];
        $rawSummary = $type === 'learners' ? [$report['summary']] : $report['summary'];
        $summaryRows = collect($rawSummary)->map(fn (array|object $row): array => array_map(
            fn (string $column): string => $this->safeCell($this->scalar(data_get($row, $column))),
            $summaryColumns,
        ))->all();
        $trendColumns = $type === 'sales' ? ['period', 'currency', 'gross_sales', 'discounts', 'refunds', 'net_sales'] : [];
        $trendRows = collect($report['trend'] ?? [])->map(fn (array $row): array => array_map(
            fn (string $column): string => $this->safeCell($this->scalar($row[$column] ?? null)),
            $trendColumns,
        ))->all();
        $filename = "jcec-{$type}-{$filters->from}-{$filters->to}.{$format}";

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($columns, $rows, $summaryColumns, $summaryRows, $trendColumns, $trendRows): void {
                $stream = fopen('php://output', 'wb');
                fwrite($stream, "\xEF\xBB\xBF");
                fputcsv($stream, $columns);
                foreach ($rows as $row) {
                    fputcsv($stream, $row);
                }
                if ($summaryColumns !== []) {
                    fputcsv($stream, []);
                    fputcsv($stream, $summaryColumns);
                    foreach ($summaryRows as $row) {
                        fputcsv($stream, $row);
                    }
                }
                if ($trendColumns !== []) {
                    fputcsv($stream, []);
                    fputcsv($stream, $trendColumns);
                    foreach ($trendRows as $row) {
                        fputcsv($stream, $row);
                    }
                }
                fclose($stream);
            }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private']);
        }

        if ($format === 'xlsx') {
            $path = tempnam(sys_get_temp_dir(), 'jcec_report_');
            $writer = new Writer;
            try {
                $writer->openToFile($path);
                $writer->addRow(Row::fromValues($columns));
                foreach ($rows as $row) {
                    $writer->addRow(Row::fromValues($row));
                }
                if ($summaryColumns !== []) {
                    $writer->addRow(Row::fromValues(['']));
                    $writer->addRow(Row::fromValues($summaryColumns));
                    foreach ($summaryRows as $row) {
                        $writer->addRow(Row::fromValues($row));
                    }
                }
                if ($trendColumns !== []) {
                    $writer->addRow(Row::fromValues(['']));
                    $writer->addRow(Row::fromValues($trendColumns));
                    foreach ($trendRows as $row) {
                        $writer->addRow(Row::fromValues($row));
                    }
                }
                $writer->close();
            } catch (\Throwable $exception) {
                @unlink($path);
                throw $exception;
            }

            return response()->download($path, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'no-store, private'])->deleteFileAfterSend(true);
        }

        $locale = request()->header('X-Locale') === 'en' ? 'en' : 'ar';
        $directory = storage_path('app/mpdf');
        File::ensureDirectoryExists($directory);
        $pdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4-L', 'tempDir' => $directory, 'default_font' => 'dejavusans']);
        $pdf->autoScriptToLang = true;
        $pdf->autoLangToFont = true;
        $pdf->SetHTMLFooter('<div style="text-align: center; font-size: 8pt; color: #666">JCEC Academy · {PAGENO}/{nbpg}</div>');
        $pdf->WriteHTML(view('reports.pdf', compact('type', 'columns', 'rows', 'summaryColumns', 'summaryRows', 'trendColumns', 'trendRows', 'filters', 'locale'))->render());

        return response($pdf->Output('', Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$filename.'"', 'Cache-Control' => 'no-store, private',
        ]);
    }

    public function safeCell(string $value): string
    {
        return preg_match('/^[\s\x00-\x1F]*[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }

    private function scalar(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return is_array($value) || is_object($value) ? (json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '') : (string) $value;
    }
}
