<script setup>
import { reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../ui/BaseButton.vue';

const props = defineProps({ review: { type: Object, default: null }, busy: Boolean, serverErrors: { type: Object, default: () => ({}) } });
const emit = defineEmits(['submit']);
const { t } = useI18n();
const form = reactive({ rating: 0, title: '', body: '' });
const validation = ref(null);
watch(() => props.review, (review) => { form.rating = review?.rating ?? 0; form.title = review?.title ?? ''; form.body = review?.body ?? ''; validation.value = null; }, { immediate: true });

function submit() {
    if (props.busy) return;
    validation.value = form.rating < 1 || form.rating > 5 ? 'selectRating' : form.title.length > 255 ? 'titleLong' : form.body.length > 10000 ? 'bodyLong' : null;
    if (validation.value) return;
    emit('submit', { rating: form.rating, title: form.title.trim() || null, body: form.body.trim() || null });
}
</script>

<template>
    <form class="space-y-5" @submit.prevent="submit">
        <fieldset class="space-y-2"><legend class="text-sm font-bold text-slate-800">{{ t('reviews.rating') }}</legend><div class="flex flex-wrap gap-2" role="group" :aria-label="t('reviews.rating')"><label v-for="value in 5" :key="value" class="cursor-pointer"><input v-model.number="form.rating" class="peer sr-only" type="radio" name="course-rating" :value="value" :aria-label="t('reviews.ratingOption', { count: value })" :disabled="busy"><span class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-3 text-sm font-black text-slate-700 transition peer-checked:border-brand peer-checked:bg-brand peer-checked:text-white peer-focus-visible:outline-3 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brand">{{ value }} <span aria-hidden="true">★</span></span></label></div><p v-if="validation === 'selectRating' || serverErrors.rating" role="alert" class="text-sm text-red-700">{{ t('reviews.selectRating') }}</p></fieldset>
        <div><label class="mb-2 block text-sm font-bold text-slate-800" for="review-title">{{ t('reviews.title') }}</label><input id="review-title" v-model="form.title" maxlength="255" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-brand focus:outline-none" :disabled="busy"><p v-if="validation === 'titleLong' || serverErrors.title" role="alert" class="mt-1 text-sm text-red-700">{{ t('reviews.titleLong') }}</p></div>
        <div><label class="mb-2 block text-sm font-bold text-slate-800" for="review-body">{{ t('reviews.body') }}</label><textarea id="review-body" v-model="form.body" maxlength="10000" rows="4" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-brand focus:outline-none" :disabled="busy" /><p v-if="validation === 'bodyLong' || serverErrors.body" role="alert" class="mt-1 text-sm text-red-700">{{ t('reviews.bodyLong') }}</p></div>
        <BaseButton type="submit" :loading="busy">{{ t('reviews.save') }}</BaseButton>
    </form>
</template>
