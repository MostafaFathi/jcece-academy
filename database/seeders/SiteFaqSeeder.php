<?php

namespace Database\Seeders;

use App\Models\SiteFaq;
use Illuminate\Database\Seeder;

class SiteFaqSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $questions = [
            ['كيف أجد الدورة المناسبة؟', 'استخدم تصنيفات الدورات والفلاتر في الكتالوج لعرض الخيارات المتاحة.'],
            ['كيف أرسل استفسارًا؟', 'يمكنك استخدام نموذج التواصل العام. بعد إنشاء حساب، يمكنك أيضًا فتح تذكرة دعم من لوحة الطالب.'],
            ['متى أحصل على صلاحية الوصول؟', 'تعتمد صلاحية الوصول على اكتمال الطلب واعتماد الدفع وفق طريقة الدفع المستخدمة.'],
        ];

        foreach ($questions as $order => [$question, $answer]) {
            SiteFaq::query()->updateOrCreate(['sort_order' => $order], [
                'question_ar' => $question,
                'answer_ar' => $answer,
                'is_active' => false,
            ]);
        }
    }
}
