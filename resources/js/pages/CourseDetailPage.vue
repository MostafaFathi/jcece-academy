<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { fetchCourse } from '../api/courses';
import { fetchCourseReviews } from '../api/reviews';
import { safeLearningUrl } from '../utils/learning';
import MoneyAmount from '../components/commerce/MoneyAmount.vue';
import AddToCartButton from '../components/commerce/AddToCartButton.vue';
import BaseAlert from '../components/ui/BaseAlert.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import PaginationNav from '../components/ui/PaginationNav.vue';
import CourseCurriculum from '../components/public/CourseCurriculum.vue';
import CourseCard from '../components/public/CourseCard.vue';
import PackageCard from '../components/public/PackageCard.vue';
import MediaFrame from '../components/public/MediaFrame.vue';
import RatingStars from '../components/public/RatingStars.vue';
import ReviewCard from '../components/public/ReviewCard.vue';
import { usePageMeta } from '../composables/usePageMeta';

const props = defineProps({ slug: { type: String, required: true } });
const { locale, t, te } = useI18n();
const course = ref(null);
const reviews = ref([]);
const reviewMeta = ref(null);
const selectedPreview = ref(null);
const loading = ref(true);
const reviewsLoading = ref(false);
const error = ref(null);
const promoUrl = computed(() => safeLearningUrl(course.value?.promo_video_url));
const promoIsVideo = computed(() => promoUrl.value && /\.(mp4|webm)(?:\?|$)/i.test(promoUrl.value));
const previewUrl = computed(() => safeLearningUrl(selectedPreview.value?.video_url));
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
    } catch { reviews.value = []; reviewMeta.value = null; }
    finally { reviewsLoading.value = false; }
}

async function loadCourse() {
    loading.value = true;
    error.value = null;
    selectedPreview.value = null;
    try { course.value = await fetchCourse(props.slug); await loadReviews(); }
    catch (failure) { error.value = failure; course.value = null; }
    finally { loading.value = false; }
}

function faqText(faq, field) { return locale.value === 'en' ? (faq[`${field}_en`] ?? faq[`${field}_ar`]) : faq[`${field}_ar`]; }
onMounted(loadCourse);
watch(() => props.slug, loadCourse);
</script>

<template>
    <LoadingState v-if="loading" class="min-h-[60vh]" />
    <main v-else-if="course" class="bg-slate-50">
        <section class="relative overflow-hidden bg-brand-dark text-white"><div class="relative mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 lg:grid-cols-[1fr_390px] lg:px-8 lg:py-20"><div><RouterLink :to="{ name: 'courses.index' }" class="text-sm font-bold text-white/75 underline">{{ t('course.backToCourses') }}</RouterLink><div class="mt-7 flex flex-wrap gap-2"><span v-if="course.category" class="rounded-full bg-accent px-3 py-1.5 text-xs font-black text-brand-dark">{{ course.category.name }}</span><span class="rounded-full border border-white/20 px-3 py-1.5 text-xs">{{ levelLabel }}</span><span class="rounded-full border border-white/20 px-3 py-1.5 text-xs">{{ languageLabel }}</span><span class="rounded-full border border-white/20 px-3 py-1.5 text-xs">{{ t(`discovery.trainingTypes.${course.training_type ?? 'recorded'}`) }}</span></div><h1 class="mt-5 text-4xl font-black leading-tight sm:text-5xl">{{ course.title }}</h1><p v-if="course.short_description" class="mt-5 max-w-3xl text-lg leading-8 text-white/75">{{ course.short_description }}</p><div class="mt-7 flex flex-wrap items-center gap-5"><RatingStars :rating="course.rating_summary?.average_rating" :count="course.rating_summary?.review_count ?? 0" /><RouterLink v-if="course.instructor" :to="{ name: 'instructors.show', params: { id: course.instructor.id } }" class="text-sm font-bold text-accent underline">{{ course.instructor.name }}</RouterLink><span v-if="course.duration_minutes" class="text-sm text-white/75">{{ t('catalog.minutes', { count: course.duration_minutes }) }}</span></div></div><div class="rounded-3xl border border-white/15 bg-white p-3 text-slate-900 shadow-2xl"><MediaFrame :src="course.thumbnail" :alt="course.title" /><div class="space-y-5 p-5"><div><p class="text-xs font-bold text-slate-500">{{ t('catalog.price') }}</p><strong class="text-3xl font-black text-brand"><MoneyAmount :amount="course.price" :currency="course.currency" /></strong><del v-if="course.compare_price && Number(course.compare_price) > Number(course.price)" class="ms-3 text-sm text-slate-400"><MoneyAmount :amount="course.compare_price" :currency="course.currency" /></del></div><ul class="space-y-3 text-sm"><li class="flex justify-between gap-4"><span>{{ t('common.access') }}</span><strong>{{ course.access_duration_days ? t('course.accessDays', { count: course.access_duration_days }) : t('common.lifetime') }}</strong></li><li class="flex justify-between gap-4"><span>{{ t('course.certificate') }}</span><strong>{{ course.certificate_enabled ? t('common.yes') : t('common.no') }}</strong></li></ul><AddToCartButton type="course" :product="course" /></div></div></div></section>
        <div class="mx-auto grid max-w-7xl gap-8 px-4 py-16 sm:px-6 lg:grid-cols-[1fr_320px] lg:px-8"><div class="min-w-0 space-y-10"><section v-if="course.description" class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-8"><h2 class="text-2xl font-black">{{ t('course.overview') }}</h2><p class="mt-5 whitespace-pre-line break-words leading-8 text-slate-600">{{ course.description }}</p></section><section v-if="promoUrl" class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-8"><h2 class="text-2xl font-black">{{ t('discovery.promo') }}</h2><video v-if="promoIsVideo" :src="promoUrl" controls playsinline preload="metadata" class="mt-5 aspect-video w-full rounded-2xl bg-slate-950" /><a v-else :href="promoUrl" target="_blank" rel="noopener noreferrer" class="mt-5 inline-flex min-h-11 items-center rounded-xl bg-brand px-5 font-bold text-white">{{ t('discovery.promo') }} ↗</a></section><CourseCurriculum v-if="course.curriculum?.length" :sections="course.curriculum" @preview="selectedPreview = $event" /><section v-if="selectedPreview" class="rounded-3xl border-2 border-brand bg-white p-6 sm:p-8" role="region" :aria-label="t('discovery.preview')"><div class="flex items-start justify-between gap-4"><h2 class="text-2xl font-black">{{ selectedPreview.title }}</h2><button type="button" class="rounded-lg px-3 py-2 font-bold text-brand underline" @click="selectedPreview = null">{{ t('common.close') }}</button></div><p v-if="selectedPreview.content" class="mt-5 whitespace-pre-line break-words leading-8 text-slate-700">{{ selectedPreview.content }}</p><video v-if="selectedPreview.type === 'video' && previewUrl" :src="previewUrl" controls playsinline preload="metadata" class="mt-5 aspect-video w-full rounded-xl bg-slate-950" /><a v-else-if="selectedPreview.type === 'link' && previewUrl" :href="previewUrl" target="_blank" rel="noopener noreferrer" class="mt-5 inline-flex min-h-11 items-center rounded-xl bg-brand px-5 font-bold text-white">{{ t('discovery.previewLink') }} ↗</a><p v-else-if="!selectedPreview.content" class="mt-5 text-slate-600">{{ t('discovery.previewUnavailable') }}</p></section><section v-if="course.faqs?.length"><h2 class="text-2xl font-black">{{ t('discovery.courseFaq') }}</h2><div class="mt-5 space-y-3"><details v-for="faq in course.faqs" :key="faq.id" class="rounded-2xl border border-slate-200 bg-white p-5"><summary class="cursor-pointer font-bold focus-visible:outline-3 focus-visible:outline-brand">{{ faqText(faq, 'question') }}</summary><p class="mt-4 whitespace-pre-line break-words leading-8 text-slate-600">{{ faqText(faq, 'answer') }}</p></details></div></section><section><div class="mb-5 flex flex-wrap items-center justify-between gap-4"><div><h2 class="text-2xl font-black">{{ t('course.reviews') }}</h2><p class="mt-1 text-sm text-slate-500">{{ t('course.reviewCount', { count: course.rating_summary?.review_count ?? 0 }) }}</p></div><RatingStars :rating="course.rating_summary?.average_rating" :count="course.rating_summary?.review_count ?? 0" /></div><LoadingState v-if="reviewsLoading" /><div v-else-if="reviews.length" class="grid gap-4 sm:grid-cols-2"><ReviewCard v-for="(review, index) in reviews" :key="`${review.reviewer_name}-${review.published_at}-${index}`" :review="review" /></div><EmptyState v-else :title="t('course.noReviews')" /><PaginationNav v-if="reviewMeta?.last_page > 1" class="mt-6" :current-page="reviewMeta.current_page" :last-page="reviewMeta.last_page" @change="loadReviews" /></section></div><aside class="space-y-5"><section v-for="section in detailLists" :key="section.key" class="rounded-3xl border border-slate-200 bg-white p-6"><h2 class="font-black">{{ section.title }}</h2><ul class="mt-4 space-y-3"><li v-for="item in course[section.key]" :key="item.id" class="flex gap-3 text-sm leading-6 text-slate-600"><span class="mt-2 size-2 shrink-0 rounded-full bg-accent" aria-hidden="true" /><span>{{ item[section.field] }}</span></li></ul></section><section v-if="course.instructor" class="rounded-3xl bg-brand-dark p-6 text-white"><p class="text-xs font-bold text-accent">{{ t('course.instructor') }}</p><h2 class="mt-3 text-xl font-black">{{ course.instructor.name }}</h2><p v-if="course.instructor.specialization" class="mt-2 text-sm text-white/70">{{ course.instructor.specialization }}</p><RouterLink :to="{ name: 'instructors.show', params: { id: course.instructor.id } }" class="mt-4 inline-flex text-sm font-bold text-accent underline">{{ t('discovery.viewProfile') }}</RouterLink></section></aside></div>
        <section v-if="course.related_courses?.length || course.included_in_packages?.length" class="mx-auto max-w-7xl space-y-12 px-4 pb-20 sm:px-6 lg:px-8"><div v-if="course.related_courses?.length"><h2 class="text-2xl font-black">{{ t('discovery.relatedCourses') }}</h2><div class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-4"><CourseCard v-for="item in course.related_courses" :key="item.id" :course="item" /></div></div><div v-if="course.included_in_packages?.length"><h2 class="text-2xl font-black">{{ t('discovery.includedPackages') }}</h2><div class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-3"><PackageCard v-for="item in course.included_in_packages" :key="item.id" :package-item="item" /></div></div></section>
    </main>
    <div v-else class="mx-auto max-w-3xl px-4 py-24"><BaseAlert tone="danger"><strong>{{ error?.status === 404 ? t('course.notFound') : t('course.detailError') }}</strong> <button type="button" class="font-bold underline" @click="loadCourse">{{ t('common.retry') }}</button></BaseAlert></div>
</template>
