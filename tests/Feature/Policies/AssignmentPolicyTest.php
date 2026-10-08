<?php

namespace Tests\Feature\Policies;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\User;
use App\PermissionName;
use App\Policies\AssignmentPolicy;
use App\Policies\AssignmentSubmissionPolicy;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AssignmentPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_and_content_manager_manage_assignments_but_student_does_not(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $assignment = Assignment::factory()->create();
        $policy = new AssignmentPolicy;

        foreach ([RoleName::Admin, RoleName::ContentManager] as $role) {
            $manager = User::factory()->create();
            $manager->assignRole($role->value);
            $this->assertTrue($policy->view($manager, $assignment));
            $this->assertTrue($policy->create($manager, $assignment->course));
            $this->assertTrue($policy->update($manager, $assignment));
            $this->assertTrue($policy->delete($manager, $assignment));
            $this->assertTrue($policy->publish($manager, $assignment));
            $this->assertFalse($policy->forceDelete($manager, $assignment));
        }

        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);
        $this->assertFalse($policy->view($student, $assignment));
        $this->assertFalse($policy->create($student, $assignment->course));
    }

    public function test_instructor_review_and_grade_permissions_are_limited_to_assigned_course(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $instructor = User::factory()->create();
        $instructor->assignRole(RoleName::Instructor->value);
        $ownCourse = Course::factory()->for($instructor, 'instructor')->create();
        $ownSubmission = AssignmentSubmission::factory()->for(Assignment::factory()->for($ownCourse))->create();
        $otherSubmission = AssignmentSubmission::factory()->create();
        $assignmentPolicy = new AssignmentPolicy;
        $submissionPolicy = new AssignmentSubmissionPolicy;

        $this->assertTrue($assignmentPolicy->reviewSubmissions($instructor, $ownSubmission->assignment));
        $this->assertTrue($submissionPolicy->view($instructor, $ownSubmission));
        $this->assertTrue($submissionPolicy->review($instructor, $ownSubmission));
        $this->assertTrue($submissionPolicy->grade($instructor, $ownSubmission));
        $this->assertFalse($assignmentPolicy->reviewSubmissions($instructor, $otherSubmission->assignment));
        $this->assertFalse($submissionPolicy->view($instructor, $otherSubmission));
        $this->assertFalse($submissionPolicy->review($instructor, $otherSubmission));
        $this->assertFalse($submissionPolicy->grade($instructor, $otherSubmission));

        $owner = $ownSubmission->user;
        $this->assertTrue($submissionPolicy->view($owner, $ownSubmission));
        $this->assertTrue($submissionPolicy->update($owner, $ownSubmission));
        $this->assertFalse($submissionPolicy->review($owner, $ownSubmission));
        $this->assertFalse($submissionPolicy->delete($owner, $ownSubmission));
    }

    public function test_instructor_assignment_definition_permissions_are_limited_to_assigned_course(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $instructor = User::factory()->create();
        $instructor->assignRole(RoleName::Instructor->value);
        $instructor->givePermissionTo([
            PermissionName::AssignmentsView->value,
            PermissionName::AssignmentsCreate->value,
            PermissionName::AssignmentsUpdate->value,
            PermissionName::AssignmentsDelete->value,
        ]);
        $ownCourse = Course::factory()->for($instructor, 'instructor')->create();
        $foreignCourse = Course::factory()->create();
        $ownAssignment = Assignment::factory()->for($ownCourse)->create();
        $foreignAssignment = Assignment::factory()->for($foreignCourse)->create();
        $policy = new AssignmentPolicy;

        $this->assertTrue($policy->create($instructor, $ownCourse));
        $this->assertTrue($policy->update($instructor, $ownAssignment));
        $this->assertTrue($policy->delete($instructor, $ownAssignment));
        $this->assertFalse($policy->create($instructor, $foreignCourse));
        $this->assertFalse($policy->view($instructor, $foreignAssignment));
        $this->assertFalse($policy->update($instructor, $foreignAssignment));
        $this->assertFalse($policy->delete($instructor, $foreignAssignment));
    }
}
