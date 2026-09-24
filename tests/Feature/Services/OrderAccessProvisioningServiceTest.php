<?php

namespace Tests\Feature\Services;

use App\AccessGrantSource;
use App\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\EnrollmentAccessGrant;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemPackageCourse;
use App\Models\Package;
use App\Models\PackageCourse;
use App\Models\Payment;
use App\Models\User;
use App\OrderStatus;
use App\PaymentStatus;
use App\Services\CourseAccessService;
use App\Services\OrderAccessProvisioningService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderAccessProvisioningServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_paid_course_creates_enrollment_and_grant_from_historical_duration(): void
    {
        $this->travelTo('2026-10-15 12:00:00');
        $paidAt = now()->subDays(5);
        [$order, $course, $item] = $this->createDirectOrder(30, $paidAt);
        $course->update(['access_duration_days' => 365]);

        $result = app(OrderAccessProvisioningService::class)->provision($order);

        $enrollment = Enrollment::query()->whereBelongsTo($order->user)->whereBelongsTo($course)->sole();
        $grant = $enrollment->accessGrants()->sole();
        $this->assertSame(1, $result['grants_created']);
        $this->assertSame(0, $result['grants_existing']);
        $this->assertSame(OrderStatus::Completed, $order->fresh()->status);
        $this->assertSame(AccessGrantSource::DirectPurchase, $grant->source_type);
        $this->assertSame($item->id, $grant->source_id);
        $this->assertSame("course-order-item:{$item->id}:course:{$course->id}", $grant->purchase_entitlement_key);
        $this->assertSame('2026-10-10 12:00:00', $grant->access_starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-09 12:00:00', $grant->access_expires_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-10 12:00:00', $order->fresh()->paid_at?->format('Y-m-d H:i:s'));
    }

    public function test_lifetime_course_creates_lifetime_grant(): void
    {
        [$order] = $this->createDirectOrder(null, now()->subHour());

        app(OrderAccessProvisioningService::class)->provision($order);

        $this->assertNull(EnrollmentAccessGrant::query()->sole()->access_expires_at);
    }

    public function test_package_uses_course_snapshots_and_package_duration_after_catalog_changes(): void
    {
        $this->travelTo('2026-10-01 08:00:00');
        $user = User::factory()->create();
        $order = Order::factory()->paid()->for($user)->create(['paid_at' => now()]);
        $package = Package::factory()->create(['access_duration_days' => 45]);
        $retainedCourse = Course::factory()->create(['access_duration_days' => 5]);
        $removedCourse = Course::factory()->create(['access_duration_days' => 10]);
        $newCourse = Course::factory()->create(['access_duration_days' => 20]);
        $item = OrderItem::factory()->for($order)->forPackage($package)->create(['access_duration_days' => 45]);
        $retainedSnapshot = OrderItemPackageCourse::factory()->for($item)->for($retainedCourse)->create(['sort_order' => 1]);
        $removedSnapshot = OrderItemPackageCourse::factory()->for($item)->for($removedCourse)->create(['sort_order' => 2]);
        PackageCourse::factory()->for($package)->for($retainedCourse)->create();
        PackageCourse::factory()->for($package)->for($newCourse)->create();
        $removedCourse->delete();

        app(OrderAccessProvisioningService::class)->provision($order);

        $this->assertDatabaseHas('enrollments', ['user_id' => $user->id, 'course_id' => $retainedCourse->id]);
        $this->assertDatabaseHas('enrollments', ['user_id' => $user->id, 'course_id' => $removedCourse->id]);
        $this->assertDatabaseMissing('enrollments', ['user_id' => $user->id, 'course_id' => $newCourse->id]);
        $this->assertDatabaseHas('enrollment_access_grants', [
            'source_type' => AccessGrantSource::PackagePurchase->value,
            'source_id' => $item->id,
            'purchase_entitlement_key' => "package-order-item:{$item->id}:snapshot:{$retainedSnapshot->id}",
            'access_expires_at' => '2026-11-15 08:00:00',
        ]);
        $this->assertDatabaseHas('enrollment_access_grants', [
            'source_type' => AccessGrantSource::PackagePurchase->value,
            'source_id' => $item->id,
            'purchase_entitlement_key' => "package-order-item:{$item->id}:snapshot:{$removedSnapshot->id}",
            'access_expires_at' => '2026-11-15 08:00:00',
        ]);
        $this->assertDatabaseCount('enrollment_access_grants', 2);
    }

    public function test_lifetime_package_creates_lifetime_grants(): void
    {
        [$order, , $snapshots] = $this->createPackageOrder(null, 2);

        app(OrderAccessProvisioningService::class)->provision($order);

        $this->assertCount(2, $snapshots);
        $this->assertSame(2, EnrollmentAccessGrant::query()->whereNull('access_expires_at')->count());
    }

    public function test_existing_suspended_enrollment_is_reused_without_unsuspending_or_altering_old_grants(): void
    {
        $this->freezeTime();
        [$order, $course] = $this->createDirectOrder(30, now());
        $enrollment = Enrollment::factory()->suspended()->for($order->user)->for($course)->create();
        $oldGrant = EnrollmentAccessGrant::factory()->for($enrollment)->create([
            'access_expires_at' => now()->addDays(5),
        ]);

        app(OrderAccessProvisioningService::class)->provision($order);

        $this->assertSame(EnrollmentStatus::Suspended, $enrollment->fresh()->status);
        $this->assertSame(2, $enrollment->accessGrants()->count());
        $this->assertSame(now()->addDays(5)->format('Y-m-d H:i:s'), $oldGrant->fresh()->access_expires_at?->format('Y-m-d H:i:s'));
    }

    public function test_separate_course_purchases_create_distinct_grants_on_one_enrollment(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $firstOrder = Order::factory()->paid()->for($user)->create();
        $firstItem = OrderItem::factory()->for($firstOrder)->create(['purchasable_id' => $course->id]);
        $secondOrder = Order::factory()->paid()->for($user)->create();
        $secondItem = OrderItem::factory()->for($secondOrder)->create(['purchasable_id' => $course->id]);

        app(OrderAccessProvisioningService::class)->provision($firstOrder);
        app(OrderAccessProvisioningService::class)->provision($secondOrder);

        $enrollment = Enrollment::query()->whereBelongsTo($user)->whereBelongsTo($course)->sole();
        $sourceIds = $enrollment->accessGrants()->pluck('source_id')->sort()->values()->all();
        $expectedSourceIds = collect([$firstItem->id, $secondItem->id])->sort()->values()->all();
        $this->assertSame(2, $enrollment->accessGrants()->count());
        $this->assertSame($expectedSourceIds, $sourceIds);
    }

    public function test_two_package_purchases_remain_distinguishable_and_one_revoked_grant_does_not_revoke_another(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        [$firstOrder, , $firstSnapshots] = $this->createPackageOrder(30, 1, $user, [$course]);
        [$secondOrder, , $secondSnapshots] = $this->createPackageOrder(30, 1, $user, [$course]);

        app(OrderAccessProvisioningService::class)->provision($firstOrder);
        app(OrderAccessProvisioningService::class)->provision($secondOrder);
        $firstGrant = EnrollmentAccessGrant::query()
            ->where('purchase_entitlement_key', "package-order-item:{$firstSnapshots->sole()->order_item_id}:snapshot:{$firstSnapshots->sole()->id}")
            ->sole();
        $firstGrant->update(['revoked_at' => now()]);

        $enrollment = Enrollment::query()->whereBelongsTo($user)->whereBelongsTo($course)->sole();
        $this->assertSame(2, $enrollment->accessGrants()->count());
        $this->assertNotSame($firstGrant->source_id, EnrollmentAccessGrant::query()
            ->where('purchase_entitlement_key', "package-order-item:{$secondSnapshots->sole()->order_item_id}:snapshot:{$secondSnapshots->sole()->id}")
            ->value('source_id'));
        $this->assertTrue(app(CourseAccessService::class)->enrollmentHasAccess($enrollment));
    }

    public function test_repeated_provisioning_is_idempotent_and_reports_existing_grants(): void
    {
        [$order] = $this->createDirectOrder(30, now());

        $first = app(OrderAccessProvisioningService::class)->provision($order);
        $second = app(OrderAccessProvisioningService::class)->provision($order);

        $this->assertSame(1, $first['grants_created']);
        $this->assertSame(0, $first['grants_existing']);
        $this->assertSame(0, $second['grants_created']);
        $this->assertSame(1, $second['grants_existing']);
        $this->assertDatabaseCount('enrollments', 1);
        $this->assertDatabaseCount('enrollment_access_grants', 1);
    }

    public function test_database_constraint_rejects_duplicate_entitlement_identity(): void
    {
        $firstEnrollment = Enrollment::factory()->create();
        $secondEnrollment = Enrollment::factory()->create();
        EnrollmentAccessGrant::factory()->for($firstEnrollment)->create([
            'source_type' => AccessGrantSource::DirectPurchase,
            'source_id' => 123,
            'purchase_entitlement_key' => 'course-order-item:123:course:456',
        ]);

        $this->expectException(QueryException::class);

        EnrollmentAccessGrant::factory()->for($secondEnrollment)->create([
            'source_type' => AccessGrantSource::DirectPurchase,
            'source_id' => 456,
            'purchase_entitlement_key' => 'course-order-item:123:course:456',
        ]);
    }

    public function test_purchase_constraint_does_not_restrict_non_purchase_sources(): void
    {
        EnrollmentAccessGrant::factory()->for(Enrollment::factory())->create([
            'source_type' => AccessGrantSource::Promotion,
            'source_id' => 77,
        ]);
        EnrollmentAccessGrant::factory()->for(Enrollment::factory())->create([
            'source_type' => AccessGrantSource::Promotion,
            'source_id' => 77,
        ]);

        $this->assertDatabaseCount('enrollment_access_grants', 2);
    }

    public function test_unpaid_rejected_and_cancelled_orders_are_not_provisioned(): void
    {
        foreach ([OrderStatus::Pending, OrderStatus::AwaitingPayment, OrderStatus::Cancelled] as $status) {
            $order = Order::factory()->create(['status' => $status, 'paid_at' => null]);
            OrderItem::factory()->for($order)->create();
            Payment::factory()->for($order)->rejected()->create(['status' => PaymentStatus::Rejected]);

            try {
                app(OrderAccessProvisioningService::class)->provision($order);
                $this->fail("An {$status->value} order was provisioned.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('order', $exception->errors());
            }
        }

        $this->assertDatabaseCount('enrollments', 0);
        $this->assertDatabaseCount('enrollment_access_grants', 0);
    }

    public function test_package_provisioning_is_atomic_when_one_snapshot_is_invalid(): void
    {
        [$order, $item] = $this->createPackageOrder(30, 1);
        OrderItemPackageCourse::factory()->for($item)->create(['course_id' => null, 'sort_order' => 99]);

        try {
            app(OrderAccessProvisioningService::class)->provision($order);
            $this->fail('An invalid package snapshot was provisioned.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('order', $exception->errors());
        }

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertDatabaseCount('enrollments', 0);
        $this->assertDatabaseCount('enrollment_access_grants', 0);
    }

    /** @return array{Order, Course, OrderItem} */
    private function createDirectOrder(?int $durationDays, \DateTimeInterface $paidAt): array
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['access_duration_days' => $durationDays]);
        $order = Order::factory()->paid()->for($user)->create(['paid_at' => $paidAt]);
        $item = OrderItem::factory()->for($order)->create([
            'purchasable_id' => $course->id,
            'access_duration_days' => $durationDays,
        ]);

        return [$order, $course, $item];
    }

    /**
     * @param  array<int, Course>|null  $courses
     * @return array{Order, OrderItem, Collection<int, OrderItemPackageCourse>}
     */
    private function createPackageOrder(
        ?int $durationDays,
        int $courseCount,
        ?User $user = null,
        ?array $courses = null,
    ): array {
        $user ??= User::factory()->create();
        $package = Package::factory()->create(['access_duration_days' => $durationDays]);
        $order = Order::factory()->paid()->for($user)->create();
        $item = OrderItem::factory()->for($order)->forPackage($package)->create([
            'access_duration_days' => $durationDays,
        ]);
        $courses ??= Course::factory()->count($courseCount)->create()->all();
        $snapshots = new Collection(collect($courses)->map(
            fn (Course $course, int $index): OrderItemPackageCourse => OrderItemPackageCourse::factory()
                ->for($item)
                ->for($course)
                ->create(['sort_order' => $index]),
        )->all());

        return [$order, $item, $snapshots];
    }
}
