<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { fetchCourse } from '../api/courses';
import { fetchCourseReviews } from '../api/reviews';
import { useAuthStore } from '../stores/auth';
import { formatAmount } from '../utils/catalog';
import BaseAlert from '../components/ui/BaseAlert.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import PaginationNav from '../components/ui/PaginationNav.vue';
import CourseCurriculum from '../components/public/CourseCurriculum.vue';
import MediaFrame from '../components/public/MediaFrame.vue';
import RatingStars from '../components/public/RatingStars.vue';
import ReviewCard from '../components/public/ReviewCard.vue';
import { usePageMeta } from '../composables/usePageMeta';

const props = defineProps({ slug: { type: String, required: true } });
const auth = useAuthStore();
const route = useRoute();
const { locale, t, te } = useI18n();
const course = ref(null);
const reviews = ref([]);
const reviewMeta = ref(null);
const loading = ref(true);
const reviewsLoading = ref(false);
const error = ref(null);

const detailLists = computed(() => course.value ? [
    { key: 'learning_outcomes', title: t('course.outcomes'), field: 'outcome' },
    { key: 'requirements', title: t('course.requirements'), field: 'requirement' },
    { key: 'target_audiences', title: t('course.audience'), field: 'audience' },
    { key: 'required_tools', title: t('course.tools'), field: 'tool' },
].filter((section) => course.value[section.key]?.length) : []);
const levelLabel = computed(() => course.value ? (te(`labels.levels.${course.value.level}`) ? t(`labels.levels.${course.value.level}`) : course.value.level) : '');
const languageLabel = computed(() => course.value ? (te(`labels.languages.${course.value.language}`) ? t(`labels.languages.${course.value.language}`) : course.value.language) : '');

usePageMeta(() => course.value?.title ?? t('nav.courses'), () => course.value?.short_description ?? t('catalog.coursesDescription'));

async function loadReviews(page = 1) {
    reviewsLoading.value = true;
    try {
        const result = await fetchCourseReviews(props.slug, { page, per_page: 6 });
        reviews.value = result.items;
        reviewMeta.value = result.meta;
    } catch {
        reviews.value = [];
        reviewMeta.value = null;
    } finally {
        reviewsLoading.value = false;
    }
}

async function loadCourse() {
    loading.value = true;
    error.value = null;
    try {
        course.value = await fetchCourse(props.slug);
        await loadReviews();
    } catch (requestError) {
        error.value = requestError;
        course.value = null;
    } finally {
        loading.value = false;
    }
}

onMounted(loadCourse);
watch(() => props.slug, loadCourse);
</script>

<template>
    <LoadingState v-if="loading" class="min-h-[60vh]" />
    <main v-else-if="course" class="bg-slate-50">
        <section class="relative overflow-hidden bg-brand-dark text-white"><div class="absolute inset-0 bg-[radial-gradient(circle_at_80%_20%,rgba(255,210,30,.16),transparent_30%)]" /><div class="relative mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 lg:grid-cols-[1fr_390px] lg:px-8 lg:py-20"><div><RouterLink :to="{ name: 'courses.index' }" class="inline-flex items-center gap-2 text-sm font-bold text-white/65 hover:text-white"><span aria-hidden="true">{{ locale === 'ar' ? '→' : '←' }}</span> {{ t('course.backToCourses') }}</RouterLink><div class="mt-7 flex flex-wrap gap-2"><span v-if="course.category" class="rounded-full bg-accent px-3 py-1.5 text-xs font-black text-brand-dark">{{ course.category.name }}</span><span class="rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-xs font-bold">{{ levelLabel }}</span><span class="rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-xs font-bold">{{ languageLabel }}</span></div><h1 class="mt-5 max-w-4xl text-4xl font-black leading-tight tracking-tight sm:text-5xl">{{ course.title }}</h1><p v-if="course.short_description" class="mt-5 max-w-3xl text-lg leading-8 text-white/70">{{ course.short_description }}</p><div class="mt-7 flex flex-wrap items-center gap-5"><RatingStars :rating="course.rating_summary?.average_rating" :count="course.rating_summary?.review_count ?? 0" /><span v-if="course.instructor" class="text-sm text-white/65">{{ t('course.instructor') }}: <strong class="text-white">{{ course.instructor.name }}</strong></span><span v-if="course.duration_minutes" class="text-sm text-white/65">{{ t('catalog.minutes', { count: course.duration_minutes }) }}</span></div></div><div class="lg:translate-y-10"><div class="overflow-hidden rounded-3xl border border-white/15 bg-white p-3 text-slate-900 shadow-2xl"><MediaFrame :src="course.thumbnail" :alt="course.title" /><div class="space-y-5 p-5"><div><p class="text-xs font-bold text-slate-500">{{ t('catalog.price') }}</p><div class="mt-1 flex items-baseline gap-2"><strong class="text-3xl font-black text-brand">{{ Number(course.price) === 0 ? t('catalog.free') : formatAmount(course.price, locale) }}</strong><del v-if="course.compare_price && Number(course.compare_price) > Number(course.price)" class="text-sm text-slate-400">{{ formatAmount(course.compare_price, locale) }}</del></div></div><ul class="space-y-3 text-sm text-slate-600"><li class="flex justify-between gap-4"><span>{{ t('common.access') }}</span><strong class="text-slate-900">{{ course.access_duration_days ? t('course.accessDays', { count: course.access_duration_days }) : t('course.accessUnspecified') }}</strong></li><li class="flex justify-between gap-4"><span>{{ t('course.certificate') }}</span><strong class="text-slate-900">{{ course.certificate_enabled ? t('common.yes') : t('common.no') }}</strong></li></ul><RouterLink v-if="!auth.isAuthenticated" :to="{ name: 'login', query: { redirect: route.fullPath } }" class="flex min-h-12 items-center justify-center rounded-xl bg-brand px-5 font-black text-white hover:bg-brand-dark">{{ t('course.signInCta') }}</RouterLink><button v-else type="button" disabled class="min-h-12 w-full cursor-not-allowed rounded-xl bg-brand px-5 font-black text-white opacity-65">{{ t('course.purchaseSoon') }}</button><p class="text-center text-xs leading-5 text-slate-500">{{ t('common.comingSoon') }}</p></div></div></div></div></section>
        <div class="mx-auto grid max-w-7xl gap-8 px-4 py-16 sm:px-6 lg:grid-cols-[1fr_360px] lg:px-8 lg:py-24"><div class="space-y-10"><section v-if="course.description" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"><h2 class="text-2xl font-black text-slate-950">{{ t('course.overview') }}</h2><p class="mt-5 whitespace-pre-line text-base leading-8 text-slate-600">{{ course.description }}</p></section><CourseCurriculum v-if="course.curriculum?.length" :sections="course.curriculum" /><section><div class="mb-5 flex flex-wrap items-center justify-between gap-4"><div><h2 class="text-2xl font-black text-slate-950">{{ t('course.reviews') }}</h2><p class="mt-1 text-sm text-slate-500">{{ t('course.reviewCount', { count: course.rating_summary?.review_count ?? 0 }) }}</p></div><RatingStars :rating="course.rating_summary?.average_rating" :count="course.rating_summary?.review_count ?? 0" /></div><LoadingState v-if="reviewsLoading" /><div v-else-if="reviews.length" class="grid gap-4 sm:grid-cols-2"><ReviewCard v-for="(review, index) in reviews" :key="`${review.reviewer_name}-${review.published_at}-${index}`" :review="review" /></div><EmptyState v-else :title="t('course.noReviews')" /><PaginationNav v-if="reviewMeta?.last_page > 1" class="mt-6" :current-page="reviewMeta.current_page" :last-page="reviewMeta.last_page" @change="loadReviews" /></section></div><aside class="space-y-5"><section v-for="section in detailLists" :key="section.key" class="rounded-3xl border border-slate-200 bg-white p-6"><h2 class="font-black text-slate-950">{{ section.title }}</h2><ul class="mt-4 space-y-3"><li v-for="item in course[section.key]" :key="item.id" class="flex gap-3 text-sm leading-6 text-slate-600"><span class="mt-2 size-2 shrink-0 rounded-full bg-accent" /><span>{{ item[section.field] }}</span></li></ul></section><section v-if="course.instructor" class="rounded-3xl bg-brand-dark p-6 text-white"><p class="text-xs font-black tracking-wider text-accent uppercase">{{ t('course.instructor') }}</p><h2 class="mt-3 text-xl font-black">{{ course.instructor.name }}</h2><p v-if="course.instructor.specialization" class="mt-2 text-sm text-white/60">{{ course.instructor.specialization }}</p></section></aside></div>
    </main>
    <div v-else class="mx-auto max-w-3xl px-4 py-24"><BaseAlert tone="danger"><strong>{{ error?.status === 404 ? t('course.notFound') : t('course.detailError') }}</strong><button type="button" class="ms-3 font-black underline" @click="loadCourse">{{ t('common.retry') }}</button></BaseAlert></div>
</template>
