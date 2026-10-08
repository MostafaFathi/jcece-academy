<script setup>
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { fetchInstructors } from '../api/public-site';
import BaseAlert from '../components/ui/BaseAlert.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import PaginationNav from '../components/ui/PaginationNav.vue';
import MediaFrame from '../components/public/MediaFrame.vue';
import { usePageMeta } from '../composables/usePageMeta';

const { t } = useI18n();
const instructors = ref([]);
const meta = ref(null);
const loading = ref(true);
const error = ref(null);
usePageMeta(() => t('discovery.instructors'), () => t('discovery.featuredInstructors'));

async function load(page = 1) {
    loading.value = true;
    error.value = null;
    try {
        const result = await fetchInstructors({ page, per_page: 12 });
        instructors.value = result.items;
        meta.value = result.meta;
    } catch (failure) { error.value = failure; }
    finally { loading.value = false; }
}
onMounted(load);
</script>

<template>
    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8 lg:py-20">
        <h1 class="text-4xl font-black text-slate-950">{{ t('discovery.instructors') }}</h1>
        <LoadingState v-if="loading" class="mt-10" />
        <BaseAlert v-else-if="error" tone="danger" class="mt-10">{{ t('errors.generic') }} <button type="button" class="font-bold underline" @click="load">{{ t('common.retry') }}</button></BaseAlert>
        <div v-else-if="instructors.length" class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <article v-for="instructor in instructors" :key="instructor.id" class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <MediaFrame :src="instructor.avatar" :alt="instructor.name" ratio="square" class="max-h-64" />
                <div class="p-6"><h2 class="text-xl font-black">{{ instructor.name }}</h2><p v-if="instructor.profile?.job_title" class="mt-1 text-sm font-bold text-brand">{{ instructor.profile.job_title }}</p><p v-if="instructor.profile?.short_bio" class="mt-3 line-clamp-3 text-sm leading-7 text-slate-600">{{ instructor.profile.short_bio }}</p><p class="mt-3 text-xs text-slate-500">{{ t('catalog.courseCount', { count: instructor.course_count ?? 0 }) }}</p><RouterLink :to="{ name: 'instructors.show', params: { id: instructor.id } }" class="mt-5 inline-flex min-h-11 items-center rounded-xl bg-brand px-5 text-sm font-black text-white focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-accent">{{ t('discovery.viewProfile') }}</RouterLink></div>
            </article>
        </div>
        <EmptyState v-else class="mt-10" :title="t('discovery.noInstructors')" />
        <PaginationNav v-if="meta?.last_page > 1" class="mt-8" :current-page="meta.current_page" :last-page="meta.last_page" @change="load" />
    </div>
</template>
