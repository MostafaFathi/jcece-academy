<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseReview;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Refund;
use App\Models\User;
use App\RoleName;
use App\Services\Reporting\ReportExportService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class ReportingApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sales_uses_paid_order_snapshots_and_only_completed_refunds(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        $this->actingAs($admin);
        $paid = Order::factory()->paid()->create(['subtotal' => '120.00', 'discount_total' => '20.00', 'total' => '100.00', 'coupon_code_snapshot' => 'OLD']);
        Order::factory()->create(['total' => '999.00']);
        Refund::query()->create(['order_id' => $paid->id, 'amount' => '25.00', 'currency' => 'JOD', 'reason' => 'other', 'status' => 'completed', 'access_effect' => 'none', 'initiated_by' => $admin->id, 'processed_at' => now()]);
        Refund::query()->create(['order_id' => $paid->id, 'amount' => '10.00', 'currency' => 'JOD', 'reason' => 'other', 'status' => 'pending', 'access_effect' => 'none', 'initiated_by' => $admin->id]);
        Refund::query()->create(['order_id' => $paid->id, 'amount' => '5.00', 'currency' => 'JOD', 'reason' => 'other', 'status' => 'rejected', 'access_effect' => 'none', 'initiated_by' => $admin->id]);
        Order::factory()->paid()->create(['subtotal' => '0.00', 'total' => '0.00']);

        $this->getJson('/api/v1/admin/reports/sales')->assertOk()
            ->assertJsonPath('data.summary.0.gross_sales', '100.00')
            ->assertJsonPath('data.summary.0.discounts', '20.00')
            ->assertJsonPath('data.summary.0.refunds', '25.00')
            ->assertJsonPath('data.summary.0.net_sales', '75.00')
            ->assertJsonPath('data.summary.0.order_count', 2)
            ->assertJsonPath('data.summary.0.zero_total_orders', 1)
            ->assertJsonPath('data.summary.0.average_order_value', '50.00');
    }

    public function test_reports_and_exports_require_explicit_permission_and_ranges_are_bounded(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);
        $this->actingAs($student)->getJson('/api/v1/admin/reports/sales')->assertForbidden();
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        $this->actingAs($admin)->getJson('/api/v1/admin/reports/sales?from=2020-01-01&to=2025-01-01')->assertUnprocessable();
        $this->get('/api/v1/admin/reports/sales/export/csv')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertDatabaseHas('audit_events', ['actor_id' => $admin->id, 'event_type' => 'report.export_requested']);
    }

    public function test_export_cells_never_start_with_spreadsheet_formulas(): void
    {
        $exporter = new ReportExportService;
        foreach (['=HYPERLINK(1)', '+cmd', '-cmd', '@SUM(1)'] as $unsafe) {
            $this->assertSame("'".$unsafe, $exporter->safeCell($unsafe));
        }
        $this->assertSame("' \t=2+2", $exporter->safeCell(" \t=2+2"));
    }

    public function test_every_report_family_loads_and_bounded_formats_download(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        $this->actingAs($admin);
        foreach (['sales', 'payments', 'refunds', 'courses', 'learners', 'instructors', 'reviews', 'quizzes', 'packages', 'coupons'] as $type) {
            $this->getJson("/api/v1/admin/reports/{$type}")->assertOk()->assertJsonStructure(['data' => ['rows' => ['data']]]);
        }
        $this->get('/api/v1/admin/reports/sales/export/xlsx')->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->get('/api/v1/admin/reports/sales/export/pdf', ['X-Locale' => 'ar'])->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_sales_separates_currencies_and_historical_package_purchase_from_direct_course_sale(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        $this->actingAs($admin);
        $course = Course::factory()->create(['title' => '=دورة تدريبية']);
        $package = Package::factory()->create();
        $order = Order::factory()->paid()->create(['currency' => 'JOD', 'total' => '50.00']);
        OrderItem::factory()->create(['order_id' => $order->id, 'purchasable_type' => 'package', 'purchasable_id' => $package->id, 'title' => 'Package snapshot', 'total' => '50.00']);
        $directOrder = Order::factory()->paid()->create(['currency' => 'JOD', 'total' => '30.00']);
        OrderItem::factory()->create(['order_id' => $directOrder->id, 'purchasable_type' => 'course', 'purchasable_id' => $course->id, 'title' => 'Direct snapshot', 'total' => '30.00']);
        Order::factory()->paid()->create(['currency' => 'USD', 'total' => '20.00']);
        $this->getJson('/api/v1/admin/reports/sales')->assertOk()->assertJsonCount(2, 'data.summary');
        $this->getJson('/api/v1/admin/reports/courses')->assertOk()->assertJsonPath('data.rows.data.0.direct_sales.0.direct_gross_sales', 30);
        $this->getJson('/api/v1/admin/reports/packages')->assertOk()->assertJsonPath('data.rows.data.0.gross_sales', 50);
        $csv = $this->get('/api/v1/admin/reports/courses/export/csv')->assertOk()->streamedContent();
        $this->assertStringContainsString("'=دورة تدريبية", $csv);
        $download = $this->get('/api/v1/admin/reports/courses/export/xlsx')->assertOk();
        $path = $download->baseResponse->getFile()->getPathname();
        $archive = new ZipArchive;
        try {
            $this->assertTrue($archive->open($path) === true);
            $this->assertStringContainsString("'=دورة تدريبية", $archive->getFromName('xl/worksheets/sheet1.xml'));
        } finally {
            $archive->close();
            @unlink($path);
        }
    }

    public function test_instructor_cannot_expand_report_scope_to_another_instructor(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $owner->assignRole(RoleName::Instructor->value);
        $other->assignRole(RoleName::Instructor->value);
        $ownCourse = Course::factory()->create(['instructor_id' => $owner->id]);
        Course::factory()->create(['instructor_id' => $other->id]);
        Enrollment::factory()->create(['course_id' => $ownCourse->id]);

        $this->actingAs($owner)->getJson("/api/v1/instructor/reports/courses?instructor_id={$other->id}")
            ->assertUnprocessable()->assertJsonValidationErrors('instructor_id');
        $this->getJson('/api/v1/instructor/reports/courses')->assertOk()
            ->assertJsonCount(1, 'data.rows.data')->assertJsonPath('data.rows.data.0.id', $ownCourse->id);
        $this->getJson('/api/v1/instructor/reports/sales')->assertNotFound();
        $this->getJson('/api/v1/admin/reports/sales')->assertForbidden();
    }

    public function test_quiz_attempt_snapshot_and_published_rating_are_reported_without_answer_keys(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        $course = Course::factory()->create();
        $quiz = Quiz::factory()->create(['course_id' => $course->id]);
        $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);
        QuizAttempt::factory()->submitted()->create(['quiz_id' => $quiz->id, 'enrollment_id' => $enrollment->id, 'user_id' => $enrollment->user_id, 'percentage' => '70.00', 'passed' => true]);
        $quiz->update(['passing_score' => '90.00']);
        CourseReview::factory()->published()->create(['course_id' => $course->id, 'rating' => 5]);
        CourseReview::factory()->rejected()->create(['course_id' => $course->id, 'rating' => 1]);

        $this->actingAs($admin)->getJson('/api/v1/admin/reports/quizzes')->assertOk()
            ->assertJsonPath('data.rows.data.0.passed', 1)->assertJsonMissingPath('data.rows.data.0.answer_key');
        $this->getJson('/api/v1/admin/reports/reviews')->assertOk()
            ->assertJsonPath('data.rows.data.0.rating_count', 1);
    }

    public function test_local_date_range_includes_start_and_excludes_next_day_start(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        $start = CarbonImmutable::parse('2026-10-05 00:00:00', 'Asia/Hebron')->utc();
        $end = CarbonImmutable::parse('2026-10-06 00:00:00', 'Asia/Hebron')->utc();
        Order::factory()->paid()->create(['paid_at' => $start, 'total' => '12.00']);
        Order::factory()->paid()->create(['paid_at' => $end, 'total' => '88.00']);

        $this->actingAs($admin)->getJson('/api/v1/admin/reports/sales?from=2026-10-05&to=2026-10-05')
            ->assertOk()->assertJsonPath('data.summary.0.gross_sales', '12.00')->assertJsonPath('data.summary.0.order_count', 1)
            ->assertJsonPath('data.trend.0.period', '2026-10');
        $this->getJson('/api/v1/admin/reports/sales?from=2026-10-05&to=2026-10-05&period=day')
            ->assertOk()->assertJsonPath('data.trend.0.period', '2026-10-05');
    }

    public function test_sales_support_cannot_open_financial_reports_and_large_export_is_rejected(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $support = User::factory()->create();
        $support->assignRole(RoleName::SalesSupport->value);
        $this->actingAs($support)->getJson('/api/v1/admin/reports/sales')->assertForbidden();
        $this->get('/api/v1/admin/reports/sales/export/csv')->assertForbidden();

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        $buyer = User::factory()->create();
        Order::factory()->count(501)->for($buyer)->paid()->create();
        $this->actingAs($admin)->getJson('/api/v1/admin/reports/sales/export/csv')->assertUnprocessable();
        $this->assertDatabaseMissing('audit_events', ['actor_id' => $admin->id, 'event_type' => 'report.export_requested']);
    }
}
