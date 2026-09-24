<?php

namespace Tests\Feature\Policies;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\PermissionName;
use App\Policies\QuizAttemptPolicy;
use App\Policies\QuizPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class QuizPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_assessment_permissions_control_administrative_abilities(): void
    {
        foreach (PermissionName::cases() as $permission) {
            Permission::findOrCreate($permission->value);
        }
        $manager = User::factory()->create();
        $manager->givePermissionTo([
            PermissionName::AssessmentsView->value,
            PermissionName::AssessmentsCreate->value,
            PermissionName::AssessmentsUpdate->value,
            PermissionName::AssessmentsDelete->value,
            PermissionName::AssessmentsPublish->value,
            PermissionName::AssessmentResultsView->value,
        ]);
        $student = User::factory()->create();
        $quiz = Quiz::factory()->create();
        $policy = new QuizPolicy;

        $this->assertTrue($policy->viewAny($manager));
        $this->assertTrue($policy->view($manager, $quiz));
        $this->assertTrue($policy->create($manager));
        $this->assertTrue($policy->update($manager, $quiz));
        $this->assertTrue($policy->delete($manager, $quiz));
        $this->assertTrue($policy->publish($manager, $quiz));
        $this->assertTrue($policy->viewResults($manager, $quiz));
        $this->assertFalse($policy->forceDelete($manager, $quiz));
        $this->assertFalse($policy->view($student, $quiz));
    }

    public function test_attempt_owner_can_view_and_update_only_own_attempt(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $attempt = QuizAttempt::factory()->create(['user_id' => $owner->id]);
        $policy = new QuizAttemptPolicy;

        $this->assertTrue($policy->view($owner, $attempt));
        $this->assertTrue($policy->update($owner, $attempt));
        $this->assertFalse($policy->view($other, $attempt));
        $this->assertFalse($policy->update($other, $attempt));
    }
}
