<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { fetchCategories } from '../api/categories';
import { fetchCourses } from '../api/courses';
import { fetchPackages } from '../api/packages';
import { fetchInstructors, fetchTestimonials } from '../api/public-site';
import BaseAlert from '../components/ui/BaseAlert.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import CatalogSkeleton from '../components/public/CatalogSkeleton.vue';
import CategoryCard from '../components/public/CategoryCard.vue';
import CourseCard from '../components/public/CourseCard.vue';
import MediaFrame from '../components/public/MediaFrame.vue';
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
const spotlightCourse = computed(() => courses.value[0] ?? null);

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
    void loadSection('courses', () => fetchCourses({ sort: 'latest', per_page: 6 }));
    void loadSection('packages', () => fetchPackages({ sort: 'latest', per_page: 3 }));
    void loadSection('instructors', () => fetchInstructors({ per_page: 4 }));
    void loadSection('testimonials', fetchTestimonials);
}

onMounted(loadHomepage);
</script>

<template>
    <div class="overflow-hidden">
        <section class="relative isolate bg-slate-950 text-white">
            <div class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_15%_15%,rgba(255,210,30,0.18),transparent_26%),radial-gradient(circle_at_85%_35%,rgba(139,35,66,0.58),transparent_34%),linear-gradient(135deg,#16080d_0%,#35101d_48%,#6b1d32_100%)]" />
            <div class="absolute inset-0 -z-10 opacity-[0.08] [background-image:linear-gradient(rgba(255,255,255,.35)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.35)_1px,transparent_1px)] [background-size:48px_48px]" />
            <div class="mx-auto grid min-h-[680px] max-w-7xl items-center gap-14 px-4 py-20 sm:px-6 lg:grid-cols-[1.05fr_0.95fr] lg:px-8 lg:py-28">
                <div class="max-w-3xl">
                    <BaseAlert v-if="startupFailed" tone="danger" class="mb-6"><strong>{{ t('errors.startupTitle') }}</strong> {{ t('errors.startupText') }}</BaseAlert>
                    <p class="mb-6 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-4 py-2 text-xs font-black tracking-wider text-accent backdrop-blur"><span class="size-2 rounded-full bg-accent shadow-[0_0_18px_#FFD21E]" />{{ t('home.eyebrow') }}</p>
                    <h1 class="text-4xl font-black leading-[1.15] tracking-tight sm:text-6xl lg:text-7xl">{{ t('home.heroTitle') }}</h1>
                    <p class="mt-7 max-w-2xl text-base leading-8 text-white/70 sm:text-xl">{{ t('home.heroDescription') }}</p>
                    <div class="mt-9 flex flex-wrap gap-3"><RouterLink :to="{ name: 'courses.index' }" class="inline-flex min-h-13 items-center gap-3 rounded-2xl bg-accent px-6 py-3 font-black text-brand-dark shadow-xl shadow-black/20 transition hover:-translate-y-1 hover:bg-yellow-300 focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-white">{{ t('home.browseCourses') }} <span aria-hidden="true">{{ locale === 'ar' ? '←' : '→' }}</span></RouterLink><RouterLink :to="{ name: 'packages.index' }" class="inline-flex min-h-13 items-center rounded-2xl border border-white/20 bg-white/10 px-6 py-3 font-black text-white backdrop-blur transition hover:-translate-y-1 hover:bg-white/15 focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-accent">{{ t('home.browsePackages') }}</RouterLink></div>
                </div>
                <div class="relative mx-auto w-full max-w-xl"><div class="absolute -inset-6 rounded-[2.5rem] bg-accent/15 blur-3xl" /><div class="relative rotate-1 overflow-hidden rounded-[2rem] border border-white/15 bg-white/10 p-3 shadow-2xl backdrop-blur-xl"><div v-if="spotlightCourse" class="overflow-hidden rounded-[1.5rem] bg-white text-slate-950"><MediaFrame :src="spotlightCourse.thumbnail" :alt="spotlightCourse.title" /><div class="p-6"><p class="text-xs font-black tracking-wider text-brand uppercase">{{ t('home.recentEyebrow') }}</p><h2 class="mt-2 text-2xl font-black">{{ spotlightCourse.title }}</h2><p v-if="spotlightCourse.short_description" class="mt-3 line-clamp-2 text-sm leading-6 text-slate-600">{{ spotlightCourse.short_description }}</p><RouterLink :to="{ name: 'courses.show', params: { slug: spotlightCourse.slug } }" class="mt-5 inline-flex items-center gap-2 text-sm font-black text-brand">{{ t('common.viewDetails') }} <span aria-hidden="true">{{ locale === 'ar' ? '←' : '→' }}</span></RouterLink></div></div><div v-else class="grid aspect-[4/3] place-items-center rounded-[1.5rem] bg-white"><img :src="'/assets/images/logo-1.png'" alt="JCEC Academy" class="max-h-80 w-full object-contain p-8"></div></div><div class="absolute -bottom-8 -start-8 hidden rounded-2xl border border-white/15 bg-slate-950/80 p-4 text-white shadow-xl backdrop-blur sm:block"><p class="text-xs text-white/55">JCEC ACADEMY</p><strong class="mt-1 block text-sm text-accent">LEARN · BUILD · LEAD</strong></div></div>
            </div>
        </section>

        <section class="bg-white py-20 sm:py-24"><div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8"><div class="flex flex-wrap items-end justify-between gap-6"><SectionHeading :eyebrow="t('home.recentEyebrow')" :title="t('home.recentTitle')" :description="t('home.recentDescription')" /><RouterLink :to="{ name: 'courses.index' }" class="rounded-xl px-4 py-2 text-sm font-black text-brand hover:bg-brand-soft">{{ t('common.viewAll') }} <span aria-hidden="true">{{ locale === 'ar' ? '←' : '→' }}</span></RouterLink></div><BaseAlert v-if="errors.courses" tone="danger" class="mt-8">{{ t('catalog.loadError') }} <button type="button" class="font-black underline" @click="loadSection('courses', () => fetchCourses({ sort: 'latest', per_page: 6 }))">{{ t('common.retry') }}</button></BaseAlert><div v-if="loading.courses" class="mt-10 grid gap-6 md:grid-cols-2 xl:grid-cols-3"><CatalogSkeleton v-for="index in 3" :key="index" /></div><div v-else-if="courses.length" class="mt-10 grid gap-6 md:grid-cols-2 xl:grid-cols-3"><CourseCard v-for="course in courses" :key="course.id" :course="course" /></div><EmptyState v-else-if="!errors.courses" class="mt-10" :title="t('home.emptyCourses')" /></div></section>

        <section class="border-y border-slate-200 bg-slate-50 py-20 sm:py-24"><div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8"><SectionHeading :eyebrow="t('home.categoriesEyebrow')" :title="t('home.categoriesTitle')" :description="t('home.categoriesDescription')" centered /><BaseAlert v-if="errors.categories" tone="danger" class="mt-8">{{ t('catalog.loadError') }} <button type="button" class="font-black underline" @click="loadSection('categories', fetchCategories)">{{ t('common.retry') }}</button></BaseAlert><div v-if="loading.categories" class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4"><div v-for="index in 4" :key="index" class="h-44 animate-pulse rounded-3xl bg-slate-200" /></div><div v-else-if="categories.length" class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4"><CategoryCard v-for="category in categories.slice(0, 8)" :key="category.id" :category="category" /></div><EmptyState v-else-if="!errors.categories" class="mt-10" :title="t('home.emptyCategories')" /></div></section>

        <section v-if="loading.packages || packages.length || errors.packages" class="bg-white py-20 sm:py-24"><div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8"><div class="flex flex-wrap items-end justify-between gap-6"><SectionHeading :eyebrow="t('home.packagesEyebrow')" :title="t('home.packagesTitle')" :description="t('home.packagesDescription')" /><RouterLink :to="{ name: 'packages.index' }" class="rounded-xl px-4 py-2 text-sm font-black text-brand hover:bg-brand-soft">{{ t('common.viewAll') }} <span aria-hidden="true">{{ locale === 'ar' ? '←' : '→' }}</span></RouterLink></div><BaseAlert v-if="errors.packages" tone="danger" class="mt-8">{{ t('catalog.loadError') }} <button type="button" class="font-black underline" @click="loadSection('packages', () => fetchPackages({ sort: 'latest', per_page: 3 }))">{{ t('common.retry') }}</button></BaseAlert><div v-if="loading.packages" class="mt-10 grid gap-6 md:grid-cols-2 xl:grid-cols-3"><CatalogSkeleton v-for="index in 3" :key="index" /></div><div v-else-if="packages.length" class="mt-10 grid gap-6 md:grid-cols-2 xl:grid-cols-3"><PackageCard v-for="packageItem in packages" :key="packageItem.id" :package-item="packageItem" /></div></div></section>

        <section id="how-it-works" class="scroll-mt-24 bg-brand-dark py-20 text-white sm:py-24"><div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8"><SectionHeading :eyebrow="t('home.howEyebrow')" :title="t('home.howTitle')" :description="t('home.howDescription')" centered class="[&_h2]:text-white [&_p]:text-white/60" /><div class="mt-14 grid gap-5 md:grid-cols-3"><article v-for="(step, index) in [{ title: t('home.stepExplore'), text: t('home.stepExploreText') }, { title: t('home.stepLearn'), text: t('home.stepLearnText') }, { title: t('home.stepGrow'), text: t('home.stepGrowText') }]" :key="step.title" class="relative rounded-3xl border border-white/10 bg-white/[0.06] p-7"><span class="text-5xl font-black text-accent/25">0{{ index + 1 }}</span><h3 class="mt-5 text-xl font-black">{{ step.title }}</h3><p class="mt-3 text-sm leading-7 text-white/60">{{ step.text }}</p></article></div></div></section>

        <section class="bg-slate-50 py-20 sm:py-24"><div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8"><SectionHeading :eyebrow="t('home.whyEyebrow')" :title="t('home.whyTitle')" centered /><div class="mt-12 grid gap-6 md:grid-cols-3"><article v-for="item in [{ icon: '01', title: t('home.whyPractical'), text: t('home.whyPracticalText') }, { icon: '02', title: t('home.whyClear'), text: t('home.whyClearText') }, { icon: '03', title: t('home.whyFlexible'), text: t('home.whyFlexibleText') }]" :key="item.title" class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm"><span class="grid size-12 place-items-center rounded-2xl bg-accent text-sm font-black text-brand-dark">{{ item.icon }}</span><h3 class="mt-5 text-xl font-black text-slate-950">{{ item.title }}</h3><p class="mt-3 text-sm leading-7 text-slate-600">{{ item.text }}</p></article></div></div></section>

        <section class="bg-white py-20"><div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8"><div class="relative overflow-hidden rounded-[2rem] bg-brand p-8 text-white shadow-2xl shadow-brand/20 sm:p-12 lg:flex lg:items-center lg:justify-between lg:gap-10"><div class="absolute -end-20 -top-20 size-64 rounded-full bg-accent/15" /><div class="relative max-w-2xl"><h2 class="text-3xl font-black sm:text-4xl">{{ t('home.ctaTitle') }}</h2><p class="mt-4 leading-7 text-white/70">{{ t('home.ctaText') }}</p></div><RouterLink :to="{ name: 'courses.index' }" class="relative mt-7 inline-flex rounded-2xl bg-accent px-6 py-3 font-black text-brand-dark transition hover:-translate-y-1 lg:mt-0">{{ t('home.browseCourses') }}</RouterLink></div></div></section>
        <section class="border-t border-slate-200 bg-slate-50 py-20"><div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8"><div class="flex flex-wrap items-end justify-between gap-4"><SectionHeading :title="t('discovery.featuredInstructors')" /><RouterLink :to="{ name: 'instructors.index' }" class="font-bold text-brand underline">{{ t('common.viewAll') }}</RouterLink></div><BaseAlert v-if="errors.instructors" tone="danger" class="mt-6">{{ t('catalog.loadError') }}</BaseAlert><div v-else-if="instructors.length" class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4"><RouterLink v-for="instructor in instructors" :key="instructor.id" :to="{ name: 'instructors.show', params: { id: instructor.id } }" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 focus-visible:outline-3 focus-visible:outline-brand"><h3 class="font-black text-slate-900">{{ instructor.name }}</h3><p v-if="instructor.profile?.job_title" class="mt-2 text-sm text-brand">{{ instructor.profile.job_title }}</p><p v-if="instructor.profile?.short_bio" class="mt-3 line-clamp-3 text-sm leading-6 text-slate-600">{{ instructor.profile.short_bio }}</p></RouterLink></div><p v-else-if="!loading.instructors" class="mt-6 text-slate-600">{{ t('discovery.noInstructors') }}</p></div></section>
        <section class="bg-white py-20"><div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8"><SectionHeading :title="t('discovery.learnerReviews')" /><BaseAlert v-if="errors.testimonials" tone="danger" class="mt-6">{{ t('catalog.loadError') }}</BaseAlert><div v-else-if="testimonials.length" class="mt-8 grid gap-5 md:grid-cols-2 lg:grid-cols-3"><ReviewCard v-for="review in testimonials" :key="`${review.course?.slug}-${review.published_at}`" :review="review" /></div><p v-else-if="!loading.testimonials" class="mt-6 text-slate-600">{{ t('discovery.noReviews') }}</p></div></section>
    </div>
</template>
