<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useCourseLearning } from '../composables/useCourseLearning';
import LoadingState from '../components/ui/LoadingState.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import ProgressSummary from '../components/learning/ProgressSummary.vue';
import LearningError from '../components/learning/LearningError.vue';
import LearningCurriculum from '../components/learning/LearningCurriculum.vue';
import LessonResources from '../components/learning/LessonResources.vue';
import TextLesson from '../components/learning/TextLesson.vue';
import VideoLesson from '../components/learning/VideoLesson.vue';
import FileLesson from '../components/learning/FileLesson.vue';
import LinkLesson from '../components/learning/LinkLesson.vue';
import CourseAssessments from '../components/learning/CourseAssessments.vue';

const props = defineProps({ slug: { type: String, required: true } });
const route = useRoute();
const { t, locale } = useI18n();
const { course, summary, activeLesson, activeIndex, lessons, loading, busy, error, actionError, saved, downloadingId, load, selectLesson, complete, savePosition, download } = useCourseLearning();
const renderer = computed(() => ({ video: VideoLesson, text: TextLesson, file: FileLesson, link: LinkLesson })[activeLesson.value?.type]);
const videoRenderer = ref(null);
watch(() => props.slug, () => load(props.slug, route.query.lesson), { immediate: true });
async function select(id) { videoRenderer.value?.pause?.(); await selectLesson(id); }
</script>

<template>
    <div class="min-w-0 space-y-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <RouterLink :to="{ name: 'student.courses.index' }" class="inline-flex min-h-11 items-center gap-2 text-sm font-bold text-brand"><span aria-hidden="true">{{ locale === 'ar' ? '→' : '←' }}</span>{{ t('learning.back') }}</RouterLink>
        <LoadingState v-if="loading" />
        <LearningError v-else-if="error" :error="error"><BaseButton class="mt-3" variant="secondary" @click="load(slug, route.query.lesson)">{{ t('common.retry') }}</BaseButton></LearningError>
        <template v-else-if="course">
            <header class="overflow-hidden rounded-3xl bg-brand-dark p-6 text-white sm:p-8"><p class="mb-3 text-xs font-bold uppercase tracking-widest text-accent">JCEC ACADEMY · {{ t('learning.continue') }}</p><h1 class="break-words text-2xl font-black sm:text-3xl">{{ course.course.title }}</h1><p v-if="course.course.instructor" class="mt-3 text-sm text-white/75">{{ course.course.instructor.name }}</p><div class="mt-6 max-w-2xl"><ProgressSummary :summary="summary" /></div></header>
            <LearningError :error="actionError"><BaseButton variant="secondary" class="mt-3" @click="load(slug, activeLesson?.id)">{{ t('common.retry') }}</BaseButton></LearningError>
            <p v-if="saved" role="status" class="text-sm font-bold text-emerald-700">{{ t('learning.saved') }}</p>
            <EmptyState v-if="!lessons.length" :title="t('learning.noLessons')" :description="t('learning.noLessonsDescription')" />
            <div v-else class="grid min-w-0 items-start gap-6 xl:grid-cols-[minmax(15rem,19rem)_minmax(0,1fr)]">
                <LearningCurriculum :sections="course.curriculum" :active-id="activeLesson?.id" :busy="busy" @select="select" />
                <article v-if="activeLesson" class="min-w-0 space-y-6 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" :aria-busy="busy">
                    <div class="border-b border-slate-100 pb-5"><p class="text-xs font-bold text-brand">{{ t(`learning.types.${activeLesson.type}`) }} · {{ t(`learning.lessonStatus.${activeLesson.progress?.status || 'not_started'}`) }}</p><h2 class="mt-3 break-words text-xl font-black text-slate-950 sm:text-2xl">{{ activeLesson.title }}</h2><p v-if="activeLesson.description" class="mt-3 whitespace-pre-wrap break-words text-sm leading-7 text-slate-500">{{ activeLesson.description }}</p></div>
                    <component :is="renderer" v-if="renderer" :key="activeLesson.id" ref="videoRenderer" :lesson="activeLesson" :busy="busy" @save-position="savePosition" />
                    <LessonResources :resources="activeLesson.resources" :busy="busy" :downloading-id="downloadingId" @download="download" />
                    <div class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-100 pt-6"><BaseButton data-testid="complete-lesson" :loading="busy" :disabled="activeLesson.progress?.status === 'completed'" @click="complete">{{ t(activeLesson.progress?.status === 'completed' ? 'learning.completed' : 'learning.complete') }}</BaseButton><nav class="flex flex-wrap gap-2" :aria-label="t('learning.curriculum')"><BaseButton variant="secondary" data-testid="previous-lesson" :disabled="busy || activeIndex <= 0" @click="select(lessons[activeIndex - 1].id)"><span aria-hidden="true">{{ locale === 'ar' ? '→' : '←' }}</span>{{ t('learning.previousLesson') }}</BaseButton><BaseButton variant="secondary" data-testid="next-lesson" :disabled="busy || activeIndex >= lessons.length - 1" @click="select(lessons[activeIndex + 1].id)">{{ t('learning.nextLesson') }}<span aria-hidden="true">{{ locale === 'ar' ? '←' : '→' }}</span></BaseButton></nav></div>
                </article>
            </div>
            <CourseAssessments :key="slug" :course-id="course.course.id" :slug="slug" :lesson-id="activeLesson?.id ?? null" :completed-lessons="summary?.completed_lessons ?? 0" @access-lost="load(slug, activeLesson?.id)" />
        </template>
    </div>
</template>
