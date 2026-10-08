<script setup>
import { onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { fetchInstructor } from '../api/public-site';
import BaseAlert from '../components/ui/BaseAlert.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import CourseCard from '../components/public/CourseCard.vue';
import MediaFrame from '../components/public/MediaFrame.vue';
import { usePageMeta } from '../composables/usePageMeta';

const props = defineProps({ id: { type: String, required: true } });
const { t } = useI18n();
const instructor = ref(null);
const loading = ref(true);
const error = ref(null);
usePageMeta(() => instructor.value?.name ?? t('discovery.instructorProfile'), () => instructor.value?.profile?.short_bio ?? '');

async function load() {
    loading.value = true;
    error.value = null;
    try { instructor.value = await fetchInstructor(props.id); }
    catch (failure) { error.value = failure; instructor.value = null; }
    finally { loading.value = false; }
}
onMounted(load);
watch(() => props.id, load);
</script>

<template>
    <LoadingState v-if="loading" class="min-h-[50vh]" />
    <main v-else-if="instructor" class="bg-slate-50">
        <section class="bg-brand-dark text-white"><div class="mx-auto grid max-w-7xl gap-8 px-4 py-14 sm:px-6 md:grid-cols-[210px_1fr] lg:px-8"><MediaFrame :src="instructor.avatar" :alt="instructor.name" ratio="square" class="max-w-52 rounded-3xl" /><div><RouterLink :to="{ name: 'instructors.index' }" class="text-sm font-bold text-accent underline">{{ t('discovery.instructors') }}</RouterLink><h1 class="mt-4 text-4xl font-black">{{ instructor.name }}</h1><p v-if="instructor.profile?.job_title" class="mt-3 text-lg text-white/80">{{ instructor.profile.job_title }}</p><p v-if="instructor.profile?.short_bio" class="mt-5 max-w-3xl leading-8 text-white/70">{{ instructor.profile.short_bio }}</p></div></div></section>
        <div class="mx-auto max-w-7xl space-y-12 px-4 py-14 sm:px-6 lg:px-8">
            <section v-if="instructor.profile?.bio"><h2 class="text-2xl font-black">{{ t('discovery.biography') }}</h2><p class="mt-5 whitespace-pre-line break-words leading-8 text-slate-700">{{ instructor.profile.bio }}</p></section>
            <section v-if="instructor.profile?.specialties?.length"><h2 class="text-2xl font-black">{{ t('discovery.specialties') }}</h2><ul class="mt-4 flex flex-wrap gap-2"><li v-for="specialty in instructor.profile.specialties" :key="specialty" class="rounded-full bg-white px-4 py-2 text-sm font-bold text-brand ring-1 ring-slate-200">{{ specialty }}</li></ul></section>
            <section><h2 class="text-2xl font-black">{{ t('discovery.publishedCourses') }}</h2><div v-if="instructor.courses?.length" class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-3"><CourseCard v-for="course in instructor.courses" :key="course.id" :course="course" /></div><EmptyState v-else class="mt-6" :title="t('home.emptyCourses')" /></section>
        </div>
    </main>
    <div v-else class="mx-auto max-w-3xl px-4 py-24"><BaseAlert tone="danger">{{ error?.status === 404 ? t('errors.notFoundTitle') : t('errors.generic') }} <button type="button" class="font-bold underline" @click="load">{{ t('common.retry') }}</button></BaseAlert></div>
</template>
