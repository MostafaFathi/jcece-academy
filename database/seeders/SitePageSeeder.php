<?php

namespace Database\Seeders;

use App\Models\SitePage;
use Illuminate\Database\Seeder;

class SitePageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (SitePage::SLUGS as $slug) {
            SitePage::query()->firstOrCreate(['slug' => $slug]);
        }

        SitePage::query()->where('slug', 'about')->whereNull('published_at')->update([
            'draft_ar' => 'أكاديمية الجزيرة للتدريب المهني منصة للتعلّم وتطوير المهارات. تصفح الدورات والباقات، واختر المسار المناسب لك. هذه صياغة تجريبية تحتاج اعتماد فريق المحتوى قبل النشر.',
        ]);
    }
}
