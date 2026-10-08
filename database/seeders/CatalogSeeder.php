<?php

namespace Database\Seeders;

use App\CourseLevel;
use App\CourseStatus;
use App\CourseTrainingType;
use App\LessonType;
use App\Models\Category;
use App\Models\Course;
use App\Models\Package;
use App\Models\User;
use App\PackageStatus;
use App\PackageType;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $admin = User::query()->where('email', config('jcec.development_admin.email'))->firstOrFail();
        $instructors = [
            User::query()->where('email', 'instructor1@jcec.test')->firstOrFail(),
            User::query()->where('email', 'instructor2@jcec.test')->firstOrFail(),
        ];

        $categories = [
            ['slug' => 'project-management', 'name' => 'إدارة المشاريع', 'description' => 'تخطيط المشاريع ومتابعتها وإدارة فرق العمل.'],
            ['slug' => 'engineering-technology', 'name' => 'التقنية الهندسية', 'description' => 'أدوات التصميم الرقمي والنمذجة والتوثيق الهندسي.'],
            ['slug' => 'professional-skills', 'name' => 'المهارات المهنية', 'description' => 'مهارات عملية للتواصل والتنظيم والعمل المهني.'],
        ];

        foreach ($categories as $order => $attributes) {
            Category::query()->updateOrCreate(['slug' => $attributes['slug']], [
                ...$attributes,
                'sort_order' => $order,
                'is_active' => true,
            ]);
        }

        $courses = [
            ['project-planning-basics', 'أساسيات تخطيط المشاريع', 'project-management', 0, CourseLevel::Beginner, 149, 'تعرّف إلى خطوات تحويل فكرة المشروع إلى خطة واضحة قابلة للتنفيذ.', 'خطة المشروع ومراحله', 'تحديد الأهداف والنطاق'],
            ['project-risk-management', 'إدارة مخاطر المشاريع | Project Risk Management', 'project-management', 0, CourseLevel::Intermediate, 189, 'تعلّم رصد المخاطر وتقييمها ووضع استجابات عملية لها.', 'فهم المخاطر', 'بناء سجل المخاطر'],
            ['bim-modeling-foundations', 'مقدمة في نمذجة معلومات البناء BIM', 'engineering-technology', 1, CourseLevel::Beginner, 229, 'مدخل عملي إلى مفاهيم النمذجة الرقمية وعناصر النموذج الهندسي.', 'مفاهيم BIM', 'تنظيم عناصر النموذج'],
            ['bim-construction-documents', 'إعداد المخططات التنفيذية باستخدام BIM', 'engineering-technology', 1, CourseLevel::Intermediate, 279, 'نظّم مخرجات النموذج وأعد لوحات ومعلومات المشروع.', 'مخرجات النموذج', 'تنسيق اللوحات'],
            ['professional-communication', 'التواصل المهني الفعّال', 'professional-skills', 0, CourseLevel::AllLevels, 99, 'طوّر أسلوبك في التواصل الكتابي والشفهي داخل فريق العمل.', 'أساسيات التواصل', 'التواصل في الاجتماعات'],
            ['excel-for-project-teams', 'Excel لفرق المشاريع', 'professional-skills', 1, CourseLevel::Beginner, 129, 'استخدم الجداول لتنظيم المهام والبيانات ومتابعة التقدم.', 'تنظيم البيانات', 'متابعة المهام'],
        ];

        foreach ($courses as $index => [$slug, $title, $categorySlug, $instructorIndex, $level, $price, $summary, $firstSection, $secondSection]) {
            $course = Course::query()->updateOrCreate(['slug' => $slug], [
                'title' => $title,
                'category_id' => Category::query()->where('slug', $categorySlug)->firstOrFail()->id,
                'instructor_id' => $instructors[$instructorIndex]->id,
                'short_description' => $summary,
                'description' => $summary.' تتضمن الدورة دروسًا نصية تمهيدية، ويمكن للمدرب تطوير محتواها من لوحة التحكم.',
                'level' => $level,
                'training_type' => CourseTrainingType::Recorded,
                'language' => 'ar',
                'duration_minutes' => 20,
                'access_duration_days' => 365,
                'price' => $price,
                'certificate_enabled' => false,
                'discussion_enabled' => false,
                'status' => CourseStatus::Published,
                'is_featured' => $index < 2,
                'published_at' => now()->subDay(),
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);

            $course->learningOutcomes()->updateOrCreate(['sort_order' => 0], ['outcome' => 'فهم المفاهيم الأساسية في '.$title.'.']);
            $course->learningOutcomes()->updateOrCreate(['sort_order' => 1], ['outcome' => 'تطبيق خطوات عملية على موضوع الدورة.']);
            $course->requirements()->updateOrCreate(['sort_order' => 0], ['requirement' => 'لا تُشترط خبرة سابقة؛ يكفي الإلمام باستخدام الحاسوب.']);
            $course->targetAudiences()->updateOrCreate(['sort_order' => 0], ['audience' => 'المهتمون بتطوير مهاراتهم المهنية.']);

            foreach ([$firstSection, $secondSection] as $sectionOrder => $sectionTitle) {
                $section = $course->sections()->updateOrCreate(['sort_order' => $sectionOrder], [
                    'title' => $sectionTitle,
                    'description' => 'دروس تمهيدية في '.$sectionTitle.'.',
                    'is_active' => true,
                ]);

                $section->lessons()->updateOrCreate(['slug' => 'introduction'], [
                    'title' => 'مقدمة: '.$sectionTitle,
                    'type' => LessonType::Text,
                    'description' => 'مدخل موجز إلى موضوع الوحدة.',
                    'content' => 'في هذه الوحدة نتناول '.$sectionTitle.' ضمن دورة '.$title.'. ابدأ بتحديد الهدف، ثم دوّن الخطوات الرئيسية التي ستطبقها في عملك.',
                    'duration_seconds' => 600,
                    'is_preview' => $sectionOrder === 0,
                    'is_published' => true,
                    'sort_order' => 0,
                ]);
            }

            $course->faqs()->updateOrCreate(['sort_order' => 0], [
                'question_ar' => 'ما لغة الدورة؟',
                'answer_ar' => 'المحتوى التدريبي لهذه الدورة باللغة العربية، مع استخدام المصطلحات الإنجليزية المتخصصة عند الحاجة.',
                'is_active' => true,
            ]);
        }

        $packages = [
            ['project-management-bundle', 'باقة إدارة المشاريع', PackageType::Package, 299, ['project-planning-basics', 'project-risk-management']],
            ['bim-professional-path', 'مسار BIM المهني', PackageType::LearningPath, 449, ['bim-modeling-foundations', 'bim-construction-documents']],
            ['career-skills-bundle', 'باقة المهارات المهنية', PackageType::Package, 179, ['professional-communication', 'excel-for-project-teams']],
        ];

        foreach ($packages as [$slug, $title, $type, $price, $courseSlugs]) {
            $package = Package::query()->updateOrCreate(['slug' => $slug], [
                'title' => $title,
                'description' => 'تضم هذه الباقة الدورات التالية: '.implode('، ', Course::query()->whereIn('slug', $courseSlugs)->pluck('title')->all()).'.',
                'type' => $type,
                'price' => $price,
                'access_duration_days' => 365,
                'is_sequential' => $type === PackageType::LearningPath,
                'sequential_completion_percentage' => $type === PackageType::LearningPath ? 100 : null,
                'status' => PackageStatus::Published,
                'published_at' => now()->subDay(),
            ]);

            foreach ($courseSlugs as $order => $courseSlug) {
                $course = Course::query()->where('slug', $courseSlug)->firstOrFail();
                $package->courseMemberships()->updateOrCreate(['course_id' => $course->id], [
                    'sort_order' => $order,
                    'is_required' => true,
                ]);
            }
        }
    }
}
