<script setup>
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { fetchMyCourses } from '../api/learning';
import { formatDate } from '../utils/catalog';
import PageHeading from '../components/ui/PageHeading.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import PaginationNav from '../components/ui/PaginationNav.vue';
import MediaFrame from '../components/public/MediaFrame.vue';
import ProgressSummary from '../components/learning/ProgressSummary.vue';
import LearningError from '../components/learning/LearningError.vue';
import CourseReviewPanel from '../components/reviews/CourseReviewPanel.vue';

const { t, locale } = useI18n();
const courses = ref([]);
const meta = ref(null);
const loading = ref(true);
const error = ref(null);
let requesting = false;
async function load(page = 1) {
    if (requesting) return;
    requesting = true;
    loading.value = true;
    error.value = null;
    courses.value = [];
    try { const result = await fetchMyCourses(page); courses.value = result.items; meta.value = result.meta; }
    catch (failure) { error.value = failure; }
    finally { loading.value = false; requesting = false; }
}
onMounted(() => load());
</script>

<template>
    <div class="space-y-7" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <PageHeading :title="t('learning.myCourses')" :description="t('learning.description')" />
        <LearningError :error="error"><BaseButton class="mt-3" variant="secondary" @click="load(meta?.current_page ?? 1)">{{ t('common.retry') }}</BaseButton></LearningError>
        <LoadingState v-if="loading" />
        <EmptyState v-else-if="!error && !courses.length" :title="t('learning.empty')" :description="t('learning.emptyDescription')" />
        <div v-else-if="!error" class="grid gap-6 xl:grid-cols-2">
            <article v-for="enrollment in courses" :key="enrollment.id" class="group min-w-0 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm" data-testid="enrollment-card">
                <MediaFrame :src="enrollment.course.thumbnail" :alt="enrollment.course.title"><span class="absolute start-4 top-4 rounded-full px-3 py-1.5 text-xs font-bold shadow-sm" :class="enrollment.has_access ? 'bg-white text-brand' : 'bg-slate-950 text-white'">{{ t(`learning.access.${enrollment.access_state || 'unavailable'}`) }}</span></MediaFrame>
                <div class="space-y-5 p-5 sm:p-6">
                    <div><p class="text-xs font-bold text-brand">{{ t(`learning.status.${enrollment.status}`) }}</p><h2 class="mt-2 break-words text-xl font-black text-slate-950">{{ enrollment.course.title }}</h2><p v-if="enrollment.course.instructor" class="mt-2 text-sm text-slate-500">{{ t('learning.instructor') }}: {{ enrollment.course.instructor.name }}</p></div>
                    <ProgressSummary :summary="enrollment" />
                    <div v-if="enrollment.sequential" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950"><p class="font-bold">{{ t('path.courseOrder', { current: enrollment.sequential.course_order, total: enrollment.sequential.course_count }) }}</p><p v-if="enrollment.access_state === 'locked'" class="mt-1">{{ t('path.ownedLocked') }} {{ t('path.previousProgress', { course: enrollment.sequential.previous_course_title, progress: enrollment.sequential.previous_progress_percentage, required: enrollment.sequential.required_percentage }) }}</p></div>
                    <div class="border-t border-slate-100 pt-4 text-sm"><p v-if="enrollment.is_lifetime" class="font-bold text-brand">{{ t('learning.lifetime') }}</p><p v-else-if="enrollment.access_expires_at" class="text-slate-600">{{ t('learning.expires', { date: formatDate(enrollment.access_expires_at, locale) }) }}</p><p v-if="enrollment.has_access && enrollment.resume" class="mt-2 break-words text-slate-500">{{ t('learning.resume', { lesson: enrollment.resume.lesson.title }) }}</p></div>
                    <RouterLink v-if="enrollment.has_access" :to="{ name: 'student.courses.learn', params: { slug: enrollment.course.slug }, query: enrollment.resume ? { lesson: enrollment.resume.lesson.id } : {} }" class="inline-flex min-h-11 items-center gap-3 rounded-xl bg-brand px-5 py-3 text-sm font-bold text-white hover:bg-brand-dark">{{ t('learning.continue') }}<span aria-hidden="true">{{ locale === 'ar' ? '←' : '→' }}</span></RouterLink>
                    <CourseReviewPanel :slug="enrollment.course.slug" :has-access="enrollment.has_access" />
                </div>
            </article>
        </div>
        <PaginationNav v-if="!loading && !error && meta?.last_page > 1" :current-page="meta.current_page" :last-page="meta.last_page" @change="load" />
    </div>
</template>
