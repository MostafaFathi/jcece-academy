<script setup>
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { createReview, fetchMyReview, updateReview } from '../../api/reviews';
import BaseAlert from '../ui/BaseAlert.vue';
import BaseButton from '../ui/BaseButton.vue';
import CourseReviewForm from './CourseReviewForm.vue';

const props = defineProps({ slug: { type: String, required: true }, hasAccess: { type: Boolean, required: true } });
const { t } = useI18n();
const review = ref(null);
const loading = ref(true);
const editing = ref(false);
const busy = ref(false);
const error = ref(null);
const saveError = ref(null);
const accessDenied = ref(false);
let requestVersion = 0;

async function load() {
    const version = ++requestVersion;
    loading.value = true;
    error.value = null;
    try { const result = await fetchMyReview(props.slug); if (version === requestVersion) review.value = result; }
    catch (failure) { if (version === requestVersion) { if (failure.status === 404) review.value = null; else error.value = failure; } }
    finally { if (version === requestVersion) loading.value = false; }
}
watch(() => props.slug, load, { immediate: true });
watch(() => props.hasAccess, (hasAccess) => { if (!hasAccess) editing.value = false; });

async function save(payload) {
    if (busy.value || !props.hasAccess || accessDenied.value) return;
    busy.value = true;
    saveError.value = null;
    try {
        review.value = review.value ? await updateReview(review.value.id, payload) : await createReview(props.slug, payload);
        editing.value = false;
    } catch (failure) {
        saveError.value = failure;
        if (failure.status === 403) { accessDenied.value = true; editing.value = false; }
        if (failure.status === 422 && !review.value) await load();
    } finally { busy.value = false; }
}
</script>

<template>
    <section class="min-w-0 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" :aria-label="t('reviews.heading')">
        <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-lg font-black text-slate-950">{{ t('reviews.heading') }}</h2><p class="mt-1 text-sm text-slate-500">{{ t('reviews.description') }}</p></div><span v-if="review" class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-900">{{ t(`reviews.status.${review.status}`) }}</span></div>
        <p v-if="loading" role="status" class="mt-4 text-sm text-slate-500">{{ t('common.loading') }}</p>
        <BaseAlert v-else-if="error" class="mt-4" tone="danger">{{ t('reviews.unavailable') }} <button type="button" class="font-bold underline" @click="load">{{ t('common.retry') }}</button></BaseAlert>
        <template v-else>
            <div v-if="review" class="mt-5 space-y-3 border-t border-slate-100 pt-5"><p class="font-bold text-brand" :aria-label="t('reviews.ratingOption', { count: review.rating })">{{ review.rating }} / 5 <span aria-hidden="true">★</span></p><h3 v-if="review.title" class="break-words font-bold text-slate-900">{{ review.title }}</h3><p v-if="review.body" class="whitespace-pre-wrap break-words text-sm leading-7 text-slate-700">{{ review.body }}</p><p class="text-sm text-slate-600">{{ t(`reviews.${review.status}Note`) }}</p><p v-if="review.moderation_reason && ['rejected', 'hidden'].includes(review.status)" class="text-sm text-slate-600">{{ t('reviews.reason') }}: {{ review.moderation_reason }}</p></div>
            <BaseAlert v-if="!hasAccess || accessDenied" class="mt-4">{{ t(review ? 'reviews.noAccess' : 'reviews.ineligible') }}</BaseAlert>
            <BaseAlert v-if="saveError" class="mt-4" tone="danger">{{ t('reviews.saveFailed') }}</BaseAlert>
            <template v-if="hasAccess && !accessDenied"><BaseButton v-if="!editing" class="mt-5" variant="secondary" @click="editing = true">{{ t(review ? 'reviews.edit' : 'reviews.write') }}</BaseButton><div v-else class="mt-5 border-t border-slate-100 pt-5"><p v-if="review?.status === 'published'" class="mb-4 text-sm font-semibold text-amber-800">{{ t('reviews.publishedNote') }}</p><CourseReviewForm :review="review" :busy="busy" :server-errors="saveError?.errors" @submit="save" /></div></template>
        </template>
    </section>
</template>
