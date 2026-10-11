<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { fetchCategories } from '../api/categories';
import { fetchCourses } from '../api/courses';
import { fetchPackages } from '../api/packages';
import { fetchInstructors, fetchTestimonials } from '../api/public-site';
import BaseAlert from '../components/ui/BaseAlert.vue';
import HomeCatalogSection from '../components/public/HomeCatalogSection.vue';
import MediaFrame from '../components/public/MediaFrame.vue';
import CategoryCard from '../components/public/CategoryCard.vue';
import CourseCard from '../components/public/CourseCard.vue';
import PackageCard from '../components/public/PackageCard.vue';
import SectionHeading from '../components/public/SectionHeading.vue';
import ReviewCard from '../components/public/ReviewCard.vue';
import { usePageMeta } from '../composables/usePageMeta';

const route = useRoute();
const { locale, t } = useI18n();
const categories = ref([]);
const courses = ref([]);
const packages = ref([]);
const instructors = ref([]);
const testimonials = ref([]);
const loading = ref({ categories: true, courses: true, packages: true, instructors: true, testimonials: true });
const errors = ref({ categories: null, courses: null, packages: null, instructors: null, testimonials: null });
const startupFailed = computed(() => route.query.startup === 'failed');

usePageMeta(() => t('common.home'), () => t('brand.description'));

async function loadSection(key, loader) {
    loading.value[key] = true;
    errors.value[key] = null;

    try {
        const result = await loader();
        if (key === 'categories') categories.value = result;
        if (key === 'courses') courses.value = result.items;
        if (key === 'packages') packages.value = result.items;
        if (key === 'instructors') instructors.value = result.items;
        if (key === 'testimonials') testimonials.value = result.items;
    } catch (error) {
        errors.value[key] = error;
    } finally {
        loading.value[key] = false;
    }
}

function loadHomepage() {
    void loadSection('categories', fetchCategories);
    void loadSection('courses', () => fetchCourses({ sort: 'latest', per_page: 12 }));
    void loadSection('packages', () => fetchPackages({ sort: 'latest', per_page: 12 }));
    void loadSection('instructors', () => fetchInstructors({ per_page: 4 }));
    void loadSection('testimonials', fetchTestimonials);
}

onMounted(loadHomepage);
</script>

<template>
    <div class="overflow-hidden">
        <section class="relative isolate bg-charcoal text-white">
            <div class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_15%_15%,rgba(245,189,22,0.08),transparent_26%),radial-gradient(circle_at_85%_35%,rgba(107,29,50,0.16),transparent_34%),linear-gradient(135deg,#151515_0%,#252525_48%,#1c1c1c_100%)]" />
            <div class="absolute inset-0 -z-10 opacity-[0.08] [background-image:linear-gradient(rgba(255,255,255,.35)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.35)_1px,transparent_1px)] [background-size:48px_48px]" />
            <div class="mx-auto grid min-h-[600px] max-w-7xl items-center gap-10 px-4 py-14 sm:px-6 lg:grid-cols-[1.05fr_0.95fr] lg:px-8 lg:py-20">
                <div class="max-w-3xl">
                    <BaseAlert v-if="startupFailed" tone="danger" class="mb-6"><strong>{{ t('errors.startupTitle') }}</strong> {{ t('errors.startupText') }}</BaseAlert>
                    <p class="mb-6 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-4 py-2 text-xs font-black tracking-wider text-accent backdrop-blur"><span class="size-2 rounded-full bg-accent shadow-[0_0_18px_#FFD21E]" />{{ t('home.eyebrow') }}</p>
                    <h1 class="text-4xl font-black leading-[1.15] tracking-tight sm:text-5xl lg:text-6xl">{{ t('home.heroTitle') }}</h1>
                    <p class="mt-7 max-w-2xl text-base leading-8 text-white/80 sm:text-xl">{{ t('home.heroDescription') }}</p>
                    <div class="mt-9 flex flex-wrap gap-3"><RouterLink :to="{ name: 'courses.index' }" class="inline-flex min-h-13 items-center gap-3 rounded-2xl bg-primary px-6 py-3 font-black text-brand-dark shadow-xl shadow-black/20 transition hover:-translate-y-1 hover:bg-primary-hover focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-accent">{{ t('home.browseCourses') }} <span aria-hidden="true">{{ locale === 'ar' ? '←' : '→' }}</span></RouterLink><RouterLink :to="{ name: 'packages.index' }" class="inline-flex min-h-13 items-center rounded-2xl border border-white/20 bg-white/10 px-6 py-3 font-black text-white backdrop-blur transition hover:-translate-y-1 hover:bg-white/15 focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-accent">{{ t('home.browsePackages') }}</RouterLink></div>
                </div>
                <div class="relative mx-auto w-full max-w-xl">
                    <div class="absolute -inset-6 rounded-[2.5rem] bg-accent/15 blur-3xl" />
                    <div class="relative overflow-hidden rounded-[2rem] border border-white/15 bg-white/10 p-2 shadow-2xl backdrop-blur-xl">
                        <img :src="'/assets/images/slider-image.png'" alt="" width="1452" height="1083" fetchpriority="high" class="aspect-[4/3] w-full rounded-[1.5rem] object-cover">
                    </div>
                </div>
            </div>
        </section>

        <HomeCatalogSection id="categories" :item-count="categories.length" :title="t('home.categoriesTitle')" :destination="{ name: 'courses.index' }" :loading="loading.categories" :error="Boolean(errors.categories)" :empty="!categories.length" :empty-title="t('home.emptyCategories')" dense @retry="loadSection('categories', fetchCategories)">
            <CategoryCard v-for="category in categories.slice(0, 12)" :key="category.id" :category="category" compact />
        </HomeCatalogSection>

        <HomeCatalogSection id="packages" :item-count="packages.length" :title="t('home.packagesTitle')" :destination="{ name: 'packages.index' }" :loading="loading.packages" :error="Boolean(errors.packages)" :empty="!packages.length" :empty-title="t('home.emptyPackages')" @retry="loadSection('packages', () => fetchPackages({ sort: 'latest', per_page: 12 }))">
            <PackageCard v-for="packageItem in packages" :key="packageItem.id" :package-item="packageItem" compact />
        </HomeCatalogSection>

        <HomeCatalogSection id="courses" :item-count="courses.length" :title="t('home.recentTitle')" :destination="{ name: 'courses.index' }" :loading="loading.courses" :error="Boolean(errors.courses)" :empty="!courses.length" :empty-title="t('home.emptyCourses')" @retry="loadSection('courses', () => fetchCourses({ sort: 'latest', per_page: 12 }))">
            <CourseCard v-for="course in courses" :key="course.id" :course="course" compact />
        </HomeCatalogSection>

        <HomeCatalogSection id="instructors" :item-count="instructors.length" :title="t('home.instructorsTitle')" :destination="{ name: 'instructors.index' }" :loading="loading.instructors" :error="Boolean(errors.instructors)" :empty="!instructors.length" :empty-title="t('discovery.noInstructors')" @retry="loadSection('instructors', fetchInstructors)">
            <RouterLink v-for="instructor in instructors" :key="instructor.id" :to="{ name: 'instructors.show', params: { id: instructor.id } }" class="group overflow-hidden rounded-xl border border-slate-200 bg-white text-center shadow-sm transition hover:-translate-y-1 focus-visible:outline-3 focus-visible:outline-brand">
                <MediaFrame :src="instructor.avatar" :alt="instructor.name" ratio="square" />
                <div class="p-4"><h3 class="text-lg font-black text-slate-900">{{ instructor.name }}</h3><p v-if="instructor.profile?.job_title" class="mt-2 text-sm text-brand">{{ instructor.profile.job_title }}</p></div>
            </RouterLink>
        </HomeCatalogSection>

        <section id="how-it-works" class="scroll-mt-24 bg-white py-20 text-slate-900 sm:py-24"><div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8"><SectionHeading :eyebrow="t('home.howEyebrow')" :title="t('home.howTitle')" :description="t('home.howDescription')" centered class="[&_h2]:text-slate-950 [&_p]:text-slate-600" /><div class="mt-14 grid gap-5 md:grid-cols-3"><article v-for="(step, index) in [{ title: t('home.stepExplore'), text: t('home.stepExploreText') }, { title: t('home.stepLearn'), text: t('home.stepLearnText') }, { title: t('home.stepGrow'), text: t('home.stepGrowText') }]" :key="step.title" class="relative rounded-3xl border border-slate-200 bg-slate-50 p-7"><span class="text-5xl font-black text-primary">0{{ index + 1 }}</span><h3 class="mt-5 text-xl font-black">{{ step.title }}</h3><p class="mt-3 text-sm leading-7 text-slate-600">{{ step.text }}</p></article></div></div></section>

        <HomeCatalogSection id="reviews" :item-count="testimonials.length" :title="t('home.reviewsTitle')" :loading="loading.testimonials" :error="Boolean(errors.testimonials)" :empty="!testimonials.length" :empty-title="t('discovery.noReviews')" @retry="loadSection('testimonials', fetchTestimonials)">
            <ReviewCard v-for="review in testimonials" :key="`${review.course?.slug}-${review.published_at}`" :review="review" />
        </HomeCatalogSection>

    </div>
</template>
