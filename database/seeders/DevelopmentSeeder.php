<?php

namespace Database\Seeders;

use App\AccessGrantSource;
use App\CourseLevel;
use App\EnrollmentStatus;
use App\LessonProgressStatus;
use App\LessonType;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseLearningOutcome;
use App\Models\CourseRequiredTool;
use App\Models\CourseRequirement;
use App\Models\CourseSection;
use App\Models\CourseTargetAudience;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\LessonResource;
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

        $course = Course::where('slug', 'project-management-foundations')->firstOrFail();

        if (! $course->sections()->exists()) {
            $introduction = CourseSection::factory()->for($course)->create([
                'title' => 'Introduction',
                'description' => 'Course orientation and project management fundamentals.',
                'sort_order' => 0,
            ]);
            $planning = CourseSection::factory()->for($course)->create([
                'title' => 'Project Planning',
                'description' => 'Turn a project idea into an actionable plan.',
                'sort_order' => 1,
            ]);

            Lesson::factory()->preview()->video()->for($introduction, 'section')->create([
                'title' => 'Welcome to the Course',
                'slug' => 'welcome',
                'description' => 'Meet your instructor and understand the course journey.',
                'sort_order' => 0,
            ]);
            Lesson::factory()->published()->for($introduction, 'section')->create([
                'title' => 'What Is Project Management?',
                'slug' => 'project-management-overview',
                'type' => LessonType::Text,
                'content' => 'A practical introduction to project management principles.',
                'sort_order' => 1,
            ]);
            $planningLesson = Lesson::factory()->published()->for($planning, 'section')->create([
                'title' => 'Build Your Project Plan',
                'slug' => 'build-project-plan',
                'type' => LessonType::File,
                'content' => null,
                'sort_order' => 0,
            ]);
            LessonResource::factory()->for($planningLesson)->create([
                'title' => 'Project Planning Worksheet',
                'type' => 'pdf',
                'file_path' => 'lesson-resources/project-planning-worksheet.pdf',
            ]);
        }

        $student = User::firstOrCreate(
            ['email' => 'sample.student@jcec.test'],
            User::factory()->raw([
                'name' => 'Sample Student',
                'email' => 'sample.student@jcec.test',
            ]),
        );
        $student->syncRoles(RoleName::Student->value);

        $enrollment = Enrollment::firstOrCreate(
            ['user_id' => $student->id, 'course_id' => $course->id],
            ['status' => EnrollmentStatus::Active, 'enrolled_at' => now()],
        );
        $enrollment->accessGrants()->firstOrCreate(
            ['source_type' => AccessGrantSource::Free, 'source_id' => null],
            ['access_starts_at' => now(), 'access_expires_at' => null],
        );

        $sampleLesson = $course->lessons()
            ->where('lessons.slug', 'project-management-overview')
            ->first();

        if ($sampleLesson !== null) {
            LessonProgress::firstOrCreate(
                ['enrollment_id' => $enrollment->id, 'lesson_id' => $sampleLesson->id],
                [
                    'status' => LessonProgressStatus::InProgress,
                    'watched_seconds' => 120,
                    'last_position_seconds' => 0,
                    'started_at' => now()->subDay(),
                ],
            );
        }
    }
}
