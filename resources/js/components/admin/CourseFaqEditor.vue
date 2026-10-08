<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { deleteCourseFaq, fetchCourseFaqs, saveCourseFaq } from '../../api/public-site';
import BaseAlert from '../ui/BaseAlert.vue';

const props = defineProps({ courseId: { type: Number, required: true } });
const { t } = useI18n();
const faqs = ref([]);
const draft = reactive({ id: null, question_ar: '', answer_ar: '', question_en: '', answer_en: '', sort_order: 0, is_active: false });
const error = ref(null);
const busy = ref(false);

function reset() { Object.assign(draft, { id: null, question_ar: '', answer_ar: '', question_en: '', answer_en: '', sort_order: 0, is_active: false }); }
async function load() {
    try { faqs.value = await fetchCourseFaqs(props.courseId); }
    catch (failure) { error.value = failure; }
}
async function save() {
    if (busy.value) return;
    busy.value = true; error.value = null;
    try {
        const payload = Object.fromEntries(Object.entries(draft).filter(([key]) => key !== 'id'));
        const result = await saveCourseFaq(props.courseId, draft.id, payload);
        faqs.value = draft.id ? faqs.value.map((item) => item.id === result.id ? result : item) : [...faqs.value, result];
        reset();
    } catch (failure) { error.value = failure; }
    finally { busy.value = false; }
}
async function remove(id) {
    if (!window.confirm(t('site.removeFaq') + '?')) return;
    try { await deleteCourseFaq(props.courseId, id); faqs.value = faqs.value.filter((item) => item.id !== id); }
    catch (failure) { error.value = failure; }
}
onMounted(load);
</script>

<template>
    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="text-xl font-black">{{ t('discovery.courseFaq') }}</h2><BaseAlert v-if="error" tone="danger" class="mt-4">{{ t('errors.generic') }}</BaseAlert><ul class="mt-5 space-y-3"><li v-for="faq in faqs" :key="faq.id" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 p-3"><span class="text-sm">{{ faq.question_ar }} · {{ faq.is_active ? t('site.published') : t('site.draft') }}</span><span class="flex gap-3"><button type="button" class="text-sm font-bold text-brand underline" @click="Object.assign(draft, faq)">{{ t('common.open') }}</button><button type="button" class="text-sm font-bold text-red-700 underline" @click="remove(faq.id)">{{ t('site.removeFaq') }}</button></span></li></ul><form class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="save"><label v-for="field in ['question_ar', 'answer_ar', 'question_en', 'answer_en']" :key="field"><span class="mb-2 block text-sm font-bold">{{ t(`site.${field.replace('_ar', 'Ar').replace('_en', 'En')}`) }}</span><textarea v-model="draft[field]" :dir="field.endsWith('_ar') ? 'rtl' : 'ltr'" rows="3" required :maxlength="field.startsWith('question') ? 1000 : 5000" class="w-full rounded-xl border border-slate-300 p-3" /></label><label><span class="mb-2 block text-sm font-bold">{{ t('site.order') }}</span><input v-model.number="draft.sort_order" type="number" min="0" class="min-h-11 w-full rounded-xl border border-slate-300 px-3"></label><label class="flex items-center gap-2"><input v-model="draft.is_active" type="checkbox" class="size-5 accent-brand">{{ t('site.active') }}</label><div class="sm:col-span-2 flex gap-3"><button type="submit" :disabled="busy" class="min-h-11 rounded-xl bg-brand px-5 font-bold text-white disabled:opacity-50">{{ t('site.saveFaq') }}</button><button type="button" class="min-h-11 rounded-xl border border-slate-300 px-5" @click="reset">{{ t('common.reset') }}</button></div></form></section>
</template>
