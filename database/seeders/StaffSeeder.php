<?php

namespace Database\Seeders;

use App\Models\User;
use App\RoleName;
use App\UserStatus;
use Illuminate\Database\Seeder;

class StaffSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $password = (string) config('jcec.development_admin.password');

        if ($password === '') {
            throw new \RuntimeException('A local development seed password is required.');
        }

        $staff = [
            [RoleName::Admin, 'مدير النظام الأول', (string) config('jcec.development_admin.email')],
            [RoleName::Admin, 'مدير النظام الثاني', 'admin2@jcec.test'],
            [RoleName::Instructor, 'أحمد خليل', 'instructor1@jcec.test'],
            [RoleName::Instructor, 'لينا حسن', 'instructor2@jcec.test'],
            [RoleName::ContentManager, 'محرر المحتوى الأول', 'content1@jcec.test'],
            [RoleName::ContentManager, 'محررة المحتوى الثانية', 'content2@jcec.test'],
            [RoleName::SalesSupport, 'موظف المبيعات والدعم الأول', 'support1@jcec.test'],
            [RoleName::SalesSupport, 'موظفة المبيعات والدعم الثانية', 'support2@jcec.test'],
        ];

        foreach ($staff as [$role, $name, $email]) {
            $user = User::query()->firstOrNew(['email' => $email]);
            $user->forceFill([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'email_verified_at' => now(),
                'preferred_locale' => 'ar',
                'status' => UserStatus::Active,
                'country' => 'فلسطين',
            ])->save();
            $user->syncRoles([$role->value]);

            if ($role === RoleName::Instructor) {
                $isProjectInstructor = $email === 'instructor1@jcec.test';
                $user->instructorProfile()->updateOrCreate([], [
                    'job_title' => $isProjectInstructor ? 'مدرب إدارة المشاريع' : 'مدربة التصميم الهندسي الرقمي',
                    'short_bio' => $isProjectInstructor
                        ? 'يقدم محتوى تدريبيًا عمليًا في تخطيط المشاريع وتنظيم فرق العمل.'
                        : 'تقدم محتوى تدريبيًا في أدوات BIM وإعداد الوثائق الهندسية.',
                    'bio' => $isProjectInstructor
                        ? 'يركز التدريب على تحويل أهداف المشروع إلى خطة قابلة للتنفيذ والمتابعة.'
                        : 'يركز التدريب على النمذجة والتنسيق وإعداد مخرجات المشروع الرقمية.',
                    'specialties' => $isProjectInstructor ? ['إدارة المشاريع', 'التخطيط'] : ['BIM', 'التصميم الهندسي'],
                    'is_featured' => true,
                ]);
            }
        }
    }
}
