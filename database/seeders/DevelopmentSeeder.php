<?php

namespace Database\Seeders;

use App\CourseLevel;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseLearningOutcome;
use App\Models\CourseRequiredTool;
use App\Models\CourseRequirement;
use App\Models\CourseTargetAudience;
use App\Models\User;
use App\RoleName;
use Illuminate\Database\Seeder;

class DevelopmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->isLocal()) {
            return;
        }

        $admin = User::updateOrCreate(
            ['email' => config('jcec.development_admin.email')],
            [
                'name' => config('jcec.development_admin.name'),
                'password' => config('jcec.development_admin.password'),
                'email_verified_at' => now(),
            ],
        );
        $admin->syncRoles(RoleName::Admin->value);

        $categories = collect([
            ['name' => 'Business & Management', 'slug' => 'business-management'],
            ['name' => 'Technology', 'slug' => 'technology'],
            ['name' => 'Professional Skills', 'slug' => 'professional-skills'],
        ])->map(fn (array $attributes): Category => Category::firstOrCreate(
            ['slug' => $attributes['slug']],
            $attributes,
        ));

        $instructors = collect([
            ['name' => 'Ahmad Khalil', 'email' => 'ahmad.instructor@jcec.test', 'specialization' => 'Project Management'],
            ['name' => 'Lina Hassan', 'email' => 'lina.instructor@jcec.test', 'specialization' => 'Software Development'],
        ])->map(function (array $attributes): User {
            $instructor = User::firstOrCreate(
                ['email' => $attributes['email']],
                User::factory()->raw($attributes),
            );
            $instructor->assignRole(RoleName::Instructor->value);
            $instructor->instructorProfile()->firstOrCreate([], [
                'job_title' => $attributes['specialization'].' Trainer',
                'short_bio' => 'Professional trainer at JCEC Academy.',
                'years_experience' => 8,
                'specialties' => [$attributes['specialization']],
            ]);

            return $instructor;
        });

        if (! Course::where('slug', 'project-management-foundations')->exists()) {
            $course = Course::factory()
                ->published()
                ->for($categories->first())
                ->for($instructors->first(), 'instructor')
                ->create([
                    'title' => 'Project Management Foundations',
                    'slug' => 'project-management-foundations',
                    'level' => CourseLevel::Beginner,
                    'language' => 'ar',
                    'price' => 120,
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ]);

            CourseLearningOutcome::factory()->for($course)->count(3)->sequence(
                ['outcome' => 'Build a practical project plan.', 'sort_order' => 0],
                ['outcome' => 'Manage project risks.', 'sort_order' => 1],
                ['outcome' => 'Track progress effectively.', 'sort_order' => 2],
            )->create();
            CourseRequirement::factory()->for($course)->create(['requirement' => 'No prior experience is required.']);
            CourseTargetAudience::factory()->for($course)->create(['audience' => 'New project managers.']);
            CourseRequiredTool::factory()->for($course)->create(['tool' => 'A spreadsheet application.']);
        }
    }
}
