<?php

namespace App\Services\Reporting;

use App\CourseReviewStatus;
use App\Models\Enrollment;
use App\Models\User;
use App\OrderStatus;
use App\PurchasableType;
use App\QuizAttemptStatus;
use App\Services\CourseProgressService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportQueryService
{
    public const TYPES = ['sales', 'payments', 'refunds', 'courses', 'learners', 'instructors', 'reviews', 'quizzes', 'packages', 'coupons'];

    public function salesExportWithinLimit(ReportFilters $filters, int $limit): bool
    {
        $orders = $this->paidOrders($filters)->limit($limit + 1)->count();
        $refunds = DB::table('refunds')->where('status', 'completed')
            ->where('processed_at', '>=', $filters->startUtc)->where('processed_at', '<', $filters->endUtc)
            ->when($filters->currency, fn (Builder $query, string $currency) => $query->where('currency', $currency))
            ->limit($limit + 1)->count();

        return $orders <= $limit && $refunds <= $limit;
    }

    /** @return array<string, mixed> */
    public function dashboard(ReportFilters $filters): array
    {
        $cohort = DB::table('enrollments')->where('enrolled_at', '>=', $filters->startUtc)->where('enrolled_at', '<', $filters->endUtc)
            ->selectRaw('COUNT(*) AS enrollments, SUM(CASE WHEN completed_at IS NOT NULL THEN 1 ELSE 0 END) AS completions')->first();
        $activeLearners = DB::table('enrollments')->join('enrollment_access_grants', 'enrollment_access_grants.enrollment_id', '=', 'enrollments.id')
            ->where('enrollments.status', '!=', 'suspended')->whereNull('enrollment_access_grants.revoked_at')
            ->where('enrollment_access_grants.access_starts_at', '<=', now())
            ->where(fn (Builder $query) => $query->whereNull('enrollment_access_grants.access_expires_at')->orWhere('enrollment_access_grants.access_expires_at', '>', now()))
            ->distinct()->count('enrollments.user_id');
        $topCourses = DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.purchasable_type', PurchasableType::Course->value)
            ->whereIn('orders.status', [OrderStatus::Paid->value, OrderStatus::Completed->value, OrderStatus::Refunded->value])
            ->whereNotNull('orders.paid_at')->where('orders.paid_at', '>=', $filters->startUtc)->where('orders.paid_at', '<', $filters->endUtc)
            ->selectRaw('order_items.purchasable_id AS course_id, MAX(order_items.title) AS title, COUNT(*) AS purchases')
            ->groupBy('order_items.purchasable_id')->orderByDesc('purchases')->limit(5)->get();

        return [
            'active_learners' => $activeLearners,
            'new_students' => User::query()->role('student')->where('created_at', '>=', $filters->startUtc)->where('created_at', '<', $filters->endUtc)->count(),
            'cohort_enrollments' => (int) ($cohort->enrollments ?? 0),
            'cohort_completions' => (int) ($cohort->completions ?? 0),
            'top_direct_courses' => $topCourses,
        ];
    }

    /** @return array<string, mixed> */
    public function run(string $type, ReportFilters $filters, ?int $ownerId = null, int $perPage = 25): array
    {
        return match ($type) {
            'sales' => $this->sales($filters, $perPage),
            'payments' => $this->payments($filters, $perPage),
            'refunds' => $this->refunds($filters, $perPage),
            'courses' => $this->courses($filters, $ownerId, $perPage),
            'learners' => $this->learners($filters, $ownerId, $perPage),
            'instructors' => $this->instructors($filters, $perPage),
            'reviews' => $this->reviews($filters, $ownerId, $perPage),
            'quizzes' => $this->quizzes($filters, $ownerId, $perPage),
            'packages' => $this->packages($filters, $perPage),
            'coupons' => $this->coupons($filters, $perPage),
        };
    }

    private function paidOrders(ReportFilters $filters): Builder
    {
        return DB::table('orders')
            ->whereIn('orders.status', [OrderStatus::Paid->value, OrderStatus::Completed->value, OrderStatus::Refunded->value])
            ->whereNotNull('orders.paid_at')
            ->where('orders.paid_at', '>=', $filters->startUtc)
            ->where('orders.paid_at', '<', $filters->endUtc)
            ->when($filters->currency, fn (Builder $query, string $currency) => $query->where('orders.currency', $currency));
    }

    /** @return array<string, mixed> */
    private function sales(ReportFilters $filters, int $perPage): array
    {
        $totals = $this->paidOrders($filters)
            ->selectRaw('currency, COUNT(*) AS order_count, SUM(total) AS gross_sales, SUM(discount_total) AS discounts, SUM(CASE WHEN total = 0 THEN 1 ELSE 0 END) AS zero_total_orders')
            ->groupBy('currency')->get()->keyBy('currency');
        $refunds = DB::table('refunds')->where('status', 'completed')
            ->where('processed_at', '>=', $filters->startUtc)->where('processed_at', '<', $filters->endUtc)
            ->when($filters->currency, fn (Builder $query, string $currency) => $query->where('currency', $currency))
            ->selectRaw('currency, SUM(amount) AS amount')->groupBy('currency')->get()->keyBy('currency');
        $currencies = $totals->keys()->merge($refunds->keys())->unique()->sort()->values();
        $summary = $currencies->map(function (string $currency) use ($totals, $refunds): array {
            $sales = $totals->get($currency);
            $gross = BigDecimal::of((string) ($sales->gross_sales ?? '0'));
            $refunded = BigDecimal::of((string) ($refunds->get($currency)->amount ?? '0'));

            return [
                'currency' => $currency,
                'gross_sales' => $this->money($gross),
                'discounts' => $this->money($sales->discounts ?? '0'),
                'refunds' => $this->money($refunded),
                'net_sales' => $this->money($gross->minus($refunded)),
                'order_count' => (int) ($sales->order_count ?? 0),
                'zero_total_orders' => (int) ($sales->zero_total_orders ?? 0),
                'average_order_value' => $sales?->order_count ? $this->money($gross->dividedBy($sales->order_count, 2, RoundingMode::HalfUp)) : '0.00',
            ];
        })->all();
        $query = $this->paidOrders($filters)->select(['orders.id', 'orders.order_number', 'orders.paid_at', 'orders.currency', 'orders.total', 'orders.discount_total', 'orders.coupon_code_snapshot']);

        $result = $this->result($query, $filters, $perPage, $summary);
        $result['trend'] = $this->salesTrend($filters);

        return $result;
    }

    /** @return array<int, array<string, string>> */
    private function salesTrend(ReportFilters $filters): array
    {
        $buckets = [];
        $periodFormat = $filters->period === 'day' ? 'Y-m-d' : 'Y-m';
        foreach ($this->paidOrders($filters)->select(['orders.id', 'orders.paid_at', 'orders.currency', 'orders.total', 'orders.discount_total'])->orderBy('orders.id')->cursor() as $order) {
            $period = CarbonImmutable::parse($order->paid_at, 'UTC')->setTimezone('Asia/Hebron')->format($periodFormat);
            $key = $period.'|'.$order->currency;
            $buckets[$key] ??= ['period' => $period, 'currency' => $order->currency, 'gross_sales' => '0.00', 'discounts' => '0.00', 'refunds' => '0.00'];
            $buckets[$key]['gross_sales'] = $this->money(BigDecimal::of($buckets[$key]['gross_sales'])->plus((string) $order->total));
            $buckets[$key]['discounts'] = $this->money(BigDecimal::of($buckets[$key]['discounts'])->plus((string) $order->discount_total));
        }
        $refunds = DB::table('refunds')->where('status', 'completed')->where('processed_at', '>=', $filters->startUtc)
            ->where('processed_at', '<', $filters->endUtc)
            ->when($filters->currency, fn (Builder $query, string $currency) => $query->where('currency', $currency))
            ->select(['id', 'processed_at', 'currency', 'amount'])->orderBy('id')->cursor();
        foreach ($refunds as $refund) {
            $period = CarbonImmutable::parse($refund->processed_at, 'UTC')->setTimezone('Asia/Hebron')->format($periodFormat);
            $key = $period.'|'.$refund->currency;
            $buckets[$key] ??= ['period' => $period, 'currency' => $refund->currency, 'gross_sales' => '0.00', 'discounts' => '0.00', 'refunds' => '0.00'];
            $buckets[$key]['refunds'] = $this->money(BigDecimal::of($buckets[$key]['refunds'])->plus((string) $refund->amount));
        }
        ksort($buckets);

        return array_values(array_map(function (array $bucket): array {
            $bucket['net_sales'] = $this->money(BigDecimal::of($bucket['gross_sales'])->minus($bucket['refunds']));

            return $bucket;
        }, $buckets));
    }

    /** @return array<string, mixed> */
    private function payments(ReportFilters $filters, int $perPage): array
    {
        $query = DB::table('payments')->join('orders', 'orders.id', '=', 'payments.order_id')
            ->where('payments.created_at', '>=', $filters->startUtc)->where('payments.created_at', '<', $filters->endUtc)
            ->when($filters->status, fn (Builder $builder, string $status) => $builder->where('payments.status', $status))
            ->when($filters->method, fn (Builder $builder, string $method) => $builder->where('payments.method', $method))
            ->when($filters->currency, fn (Builder $builder, string $currency) => $builder->where('payments.currency', $currency));
        $summary = (clone $query)->selectRaw('payments.currency, payments.status, COUNT(*) AS count, SUM(payments.amount) AS amount')
            ->groupBy('payments.currency', 'payments.status')->get();
        $rows = $query->select(['payments.id', 'orders.order_number', 'payments.method', 'payments.status', 'payments.currency', 'payments.amount', 'payments.created_at']);

        return $this->result($rows, $filters, $perPage, $summary);
    }

    /** @return array<string, mixed> */
    private function refunds(ReportFilters $filters, int $perPage): array
    {
        $query = DB::table('refunds')->join('orders', 'orders.id', '=', 'refunds.order_id')
            ->where('refunds.created_at', '>=', $filters->startUtc)->where('refunds.created_at', '<', $filters->endUtc)
            ->when($filters->status, fn (Builder $builder, string $status) => $builder->where('refunds.status', $status))
            ->when($filters->currency, fn (Builder $builder, string $currency) => $builder->where('refunds.currency', $currency));
        $summary = (clone $query)->selectRaw('refunds.currency, refunds.status, COUNT(*) AS count, SUM(refunds.amount) AS amount')
            ->groupBy('refunds.currency', 'refunds.status')->get();
        $rows = $query->select(['refunds.id', 'orders.order_number', 'refunds.status', 'refunds.amount', 'refunds.currency', 'refunds.created_at', 'refunds.processed_at']);

        return $this->result($rows, $filters, $perPage, $summary);
    }

    /** @return array<string, mixed> */
    private function courses(ReportFilters $filters, ?int $ownerId, int $perPage): array
    {
        $query = DB::table('courses')->leftJoin('users as instructors', 'instructors.id', '=', 'courses.instructor_id')
            ->whereNull('courses.deleted_at')
            ->when($ownerId ?? $filters->instructorId, fn (Builder $builder, int $id) => $builder->where('courses.instructor_id', $id))
            ->when($filters->courseId, fn (Builder $builder, int $id) => $builder->where('courses.id', $id))
            ->select(['courses.id', 'courses.title', 'courses.instructor_id', 'instructors.name as instructor_name', 'courses.status']);
        $page = $query->orderBy('courses.id')->paginate($perPage, ['*'], 'page', $filters->page);
        $ids = collect($page->items())->pluck('id')->all();
        $enrollments = DB::table('enrollments')->whereIn('course_id', $ids)
            ->where('enrolled_at', '>=', $filters->startUtc)->where('enrolled_at', '<', $filters->endUtc)
            ->selectRaw('course_id, COUNT(*) AS enrollments, COUNT(DISTINCT user_id) AS learners, SUM(CASE WHEN completed_at IS NOT NULL THEN 1 ELSE 0 END) AS completions')
            ->groupBy('course_id')->get()->keyBy('course_id');
        $direct = DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('order_items.purchasable_id', $ids)->where('order_items.purchasable_type', PurchasableType::Course->value)
            ->whereIn('orders.status', [OrderStatus::Paid->value, OrderStatus::Completed->value, OrderStatus::Refunded->value])
            ->whereNotNull('orders.paid_at')->where('orders.paid_at', '>=', $filters->startUtc)->where('orders.paid_at', '<', $filters->endUtc)
            ->when($filters->currency, fn (Builder $builder, string $currency) => $builder->where('orders.currency', $currency))
            ->selectRaw('order_items.purchasable_id AS course_id, orders.currency, COUNT(*) AS direct_purchases, SUM(order_items.total) AS direct_gross_sales')
            ->groupBy('order_items.purchasable_id', 'orders.currency')->get()->groupBy('course_id');
        $ratings = DB::table('course_reviews')->whereIn('course_id', $ids)->whereNull('deleted_at')
            ->where('status', CourseReviewStatus::Published->value)
            ->selectRaw('course_id, COUNT(*) AS rating_count, AVG(rating) AS rating_average')->groupBy('course_id')->get()->keyBy('course_id');
        $packageAccess = DB::table('enrollment_access_grants')->join('enrollments', 'enrollments.id', '=', 'enrollment_access_grants.enrollment_id')
            ->whereIn('enrollments.course_id', $ids)->where('enrollment_access_grants.source_type', 'package_purchase')
            ->where('enrollments.enrolled_at', '>=', $filters->startUtc)->where('enrollments.enrolled_at', '<', $filters->endUtc)
            ->selectRaw('enrollments.course_id, COUNT(DISTINCT enrollments.user_id) AS learners')->groupBy('enrollments.course_id')->get()->keyBy('course_id');
        $page->through(function (object $row) use ($enrollments, $direct, $ratings, $packageAccess): array {
            $access = $enrollments->get($row->id);
            $rating = $ratings->get($row->id);

            return [
                'id' => $row->id, 'title' => $row->title, 'instructor_id' => $row->instructor_id, 'instructor_name' => $row->instructor_name,
                'status' => $row->status, 'enrollments' => (int) ($access->enrollments ?? 0), 'learners' => (int) ($access->learners ?? 0),
                'completions' => (int) ($access->completions ?? 0), 'direct_sales' => $direct->get($row->id)?->all() ?? [],
                'completion_percentage' => $access?->enrollments ? round(((int) $access->completions / (int) $access->enrollments) * 100, 2) : 0,
                'package_access_learners' => (int) ($packageAccess->get($row->id)->learners ?? 0),
                'rating_count' => (int) ($rating->rating_count ?? 0), 'rating_average' => $rating?->rating_average === null ? null : round((float) $rating->rating_average, 2),
            ];
        });

        return ['summary' => [], 'rows' => $page, 'timezone' => 'Asia/Hebron'];
    }

    /** @return array<string, mixed> */
    private function learners(ReportFilters $filters, ?int $ownerId, int $perPage): array
    {
        $query = DB::table('enrollments')->join('courses', 'courses.id', '=', 'enrollments.course_id')
            ->join('users', 'users.id', '=', 'enrollments.user_id')
            ->where('enrollments.enrolled_at', '>=', $filters->startUtc)->where('enrollments.enrolled_at', '<', $filters->endUtc)
            ->when($ownerId ?? $filters->instructorId, fn (Builder $builder, int $id) => $builder->where('courses.instructor_id', $id))
            ->when($filters->courseId, fn (Builder $builder, int $id) => $builder->where('courses.id', $id))
            ->select(['enrollments.id', 'enrollments.course_id', 'courses.title as course_title', 'users.name as learner_name', 'enrollments.status', 'enrollments.enrolled_at', 'enrollments.completed_at']);
        $page = $query->orderBy('enrollments.id')->paginate($perPage, ['*'], 'page', $filters->page);
        $ids = collect($page->items())->pluck('id')->all();
        $grants = DB::table('enrollment_access_grants')->whereIn('enrollment_id', $ids)
            ->select(['enrollment_id', 'source_type', 'access_starts_at', 'access_expires_at', 'revoked_at'])
            ->orderByDesc('id')->get()->groupBy('enrollment_id');
        $progress = app(CourseProgressService::class)->summaries(Enrollment::query()->whereIn('id', $ids)->get());
        $page->through(function (object $row) use ($grants, $progress): array {
            $grant = $grants->get($row->id)?->first();
            $valid = $row->status !== 'suspended' && ($grants->get($row->id)?->contains(fn (object $item): bool => $item->revoked_at === null && $item->access_starts_at <= now()->toDateTimeString() && ($item->access_expires_at === null || $item->access_expires_at > now()->toDateTimeString())) ?? false);

            return [
                'id' => $row->id, 'course_id' => $row->course_id, 'course_title' => $row->course_title,
                'learner_name' => $row->learner_name, 'status' => $row->status, 'enrolled_at' => $row->enrolled_at,
                'completed_at' => $row->completed_at, 'access_source' => $grant?->source_type, 'access_active' => $valid,
                'access_expires_at' => $grant?->access_expires_at, 'completed_lessons' => $progress[$row->id]['completed_lessons'] ?? 0,
                'total_lessons' => $progress[$row->id]['total_lessons'] ?? 0, 'progress_percentage' => $progress[$row->id]['progress_percentage'] ?? 0,
            ];
        });

        return ['summary' => ['enrollment_count' => $page->total()], 'rows' => $page, 'timezone' => 'Asia/Hebron'];
    }

    /** @return array<string, mixed> */
    private function instructors(ReportFilters $filters, int $perPage): array
    {
        $query = DB::table('courses')->join('users', 'users.id', '=', 'courses.instructor_id')
            ->whereNull('courses.deleted_at')
            ->when($filters->instructorId, fn (Builder $builder, int $id) => $builder->where('users.id', $id))
            ->leftJoin('enrollments', function ($join) use ($filters): void {
                $join->on('enrollments.course_id', '=', 'courses.id')->where('enrollments.enrolled_at', '>=', $filters->startUtc)->where('enrollments.enrolled_at', '<', $filters->endUtc);
            })
            ->selectRaw('users.id, users.name, COUNT(DISTINCT courses.id) AS courses, COUNT(DISTINCT enrollments.user_id) AS learners, COUNT(DISTINCT enrollments.id) AS enrollments')
            ->groupBy('users.id', 'users.name');

        return $this->result($query, $filters, $perPage);
    }

    /** @return array<string, mixed> */
    private function reviews(ReportFilters $filters, ?int $ownerId, int $perPage): array
    {
        $query = DB::table('course_reviews')->join('courses', 'courses.id', '=', 'course_reviews.course_id')
            ->whereNull('course_reviews.deleted_at')->where('course_reviews.status', CourseReviewStatus::Published->value)
            ->where('course_reviews.published_at', '>=', $filters->startUtc)->where('course_reviews.published_at', '<', $filters->endUtc)
            ->when($ownerId ?? $filters->instructorId, fn (Builder $builder, int $id) => $builder->where('courses.instructor_id', $id))
            ->when($filters->courseId, fn (Builder $builder, int $id) => $builder->where('courses.id', $id))
            ->selectRaw('courses.id AS course_id, courses.title AS course_title, COUNT(*) AS rating_count, AVG(course_reviews.rating) AS rating_average, SUM(CASE WHEN course_reviews.rating = 5 THEN 1 ELSE 0 END) AS five_star_count')
            ->groupBy('courses.id', 'courses.title');

        return $this->result($query, $filters, $perPage);
    }

    /** @return array<string, mixed> */
    private function quizzes(ReportFilters $filters, ?int $ownerId, int $perPage): array
    {
        $query = DB::table('quiz_attempts')->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
            ->join('courses', 'courses.id', '=', 'quizzes.course_id')
            ->where('quiz_attempts.submitted_at', '>=', $filters->startUtc)->where('quiz_attempts.submitted_at', '<', $filters->endUtc)
            ->where('quiz_attempts.status', QuizAttemptStatus::Submitted->value)
            ->when($ownerId ?? $filters->instructorId, fn (Builder $builder, int $id) => $builder->where('courses.instructor_id', $id))
            ->when($filters->courseId, fn (Builder $builder, int $id) => $builder->where('courses.id', $id))
            ->selectRaw('quizzes.id AS quiz_id, quizzes.title AS quiz_title, courses.id AS course_id, courses.title AS course_title, COUNT(*) AS attempts, SUM(CASE WHEN quiz_attempts.passed = 1 THEN 1 ELSE 0 END) AS passed, AVG(quiz_attempts.percentage) AS average_percentage')
            ->groupBy('quizzes.id', 'quizzes.title', 'courses.id', 'courses.title');

        $result = $this->result($query, $filters, $perPage);
        $result['rows']->through(function (object $row): array {
            $values = (array) $row;
            $values['pass_rate_percentage'] = $row->attempts > 0 ? round(((int) $row->passed / (int) $row->attempts) * 100, 2) : 0;

            return $values;
        });

        return $result;
    }

    /** @return array<string, mixed> */
    private function packages(ReportFilters $filters, int $perPage): array
    {
        $query = DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.purchasable_type', PurchasableType::Package->value)
            ->whereIn('orders.status', [OrderStatus::Paid->value, OrderStatus::Completed->value, OrderStatus::Refunded->value])
            ->whereNotNull('orders.paid_at')->where('orders.paid_at', '>=', $filters->startUtc)->where('orders.paid_at', '<', $filters->endUtc)
            ->when($filters->packageId, fn (Builder $builder, int $id) => $builder->where('order_items.purchasable_id', $id))
            ->when($filters->currency, fn (Builder $builder, string $currency) => $builder->where('orders.currency', $currency))
            ->selectRaw('order_items.purchasable_id AS package_id, order_items.title AS package_title, orders.currency, COUNT(*) AS purchases, COUNT(DISTINCT orders.user_id) AS learners, SUM(order_items.total) AS gross_sales, SUM(order_items.discount_amount) AS discounts')
            ->groupBy('order_items.purchasable_id', 'order_items.title', 'orders.currency');

        return $this->result($query, $filters, $perPage);
    }

    /** @return array<string, mixed> */
    private function coupons(ReportFilters $filters, int $perPage): array
    {
        $query = $this->paidOrders($filters)->whereNotNull('orders.coupon_code_snapshot')
            ->join('coupon_redemptions', function ($join): void {
                $join->on('coupon_redemptions.order_id', '=', 'orders.id')->where('coupon_redemptions.status', 'consumed');
            })
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->selectRaw('orders.coupon_code_snapshot AS coupon_code, orders.currency, COUNT(DISTINCT orders.id) AS redemptions, SUM(order_items.coupon_discount_amount) AS coupon_discount')
            ->groupBy('orders.coupon_code_snapshot', 'orders.currency');

        return $this->result($query, $filters, $perPage);
    }

    /** @param array<mixed> | Collection<int, mixed> $summary
     * @return array<string, mixed>
     */
    private function result(Builder $query, ReportFilters $filters, int $perPage, array|Collection $summary = []): array
    {
        return ['summary' => $summary, 'rows' => $query->orderByRaw('1')->paginate($perPage, ['*'], 'page', $filters->page), 'timezone' => 'Asia/Hebron'];
    }

    private function money(BigDecimal|string|int $value): string
    {
        return (string) BigDecimal::of($value)->toScale(2, RoundingMode::HalfUp);
    }
}
