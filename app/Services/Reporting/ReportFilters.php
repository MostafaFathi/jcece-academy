<?php

namespace App\Services\Reporting;

use Carbon\CarbonImmutable;

class ReportFilters
{
    public function __construct(
        public readonly CarbonImmutable $startUtc,
        public readonly CarbonImmutable $endUtc,
        public readonly string $from,
        public readonly string $to,
        public readonly ?int $courseId,
        public readonly ?int $packageId,
        public readonly ?int $instructorId,
        public readonly ?string $currency,
        public readonly ?string $status,
        public readonly ?string $method,
        public readonly string $period,
        public readonly int $page,
    ) {}

    /** @param array<string, mixed> $values */
    public static function fromValidated(array $values): self
    {
        $timezone = 'Asia/Hebron';
        $from = $values['from'] ?? now($timezone)->subDays(29)->toDateString();
        $to = $values['to'] ?? now($timezone)->toDateString();

        return new self(
            CarbonImmutable::parse($from, $timezone)->startOfDay()->utc(),
            CarbonImmutable::parse($to, $timezone)->addDay()->startOfDay()->utc(),
            $from,
            $to,
            $values['course_id'] ?? null,
            $values['package_id'] ?? null,
            $values['instructor_id'] ?? null,
            $values['currency'] ?? null,
            $values['status'] ?? null,
            $values['method'] ?? null,
            $values['period'] ?? 'month',
            $values['page'] ?? 1,
        );
    }
}
