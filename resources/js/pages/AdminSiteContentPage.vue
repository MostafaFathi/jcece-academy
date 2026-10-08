<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { deleteSiteFaq, fetchAdminSiteContent, fetchContactMessages, publishSitePage, saveSiteFaq, saveSitePage } from '../api/public-site';
import BaseAlert from '../components/ui/BaseAlert.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import PageHeading from '../components/ui/PageHeading.vue';

const { t } = useI18n();
const pages = ref([]);
const faqs = ref([]);
const contacts = ref([]);
const selected = ref('about');
const draft = reactive({ draft_ar: '', draft_en: '' });
const faqDraft = reactive({ id: null, question_ar: '', answer_ar: '', question_en: '', answer_en: '', sort_order: 0, is_active: false });
const loading = ref(true);
const busy = ref(false);
const error = ref(null);
const saved = ref(false);

function choose(slug) {
    selected.value = slug;
    const page = pages.value.find((item) => item.slug === slug);
    draft.draft_ar = page?.draft_ar ?? '';
    draft.draft_en = page?.draft_en ?? '';
}

function resetFaq() { Object.assign(faqDraft, { id: null, question_ar: '', answer_ar: '', question_en: '', answer_en: '', sort_order: 0, is_active: false }); }

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const result = await fetchAdminSiteContent();
        pages.value = result.pages;
        faqs.value = result.faqs;
        choose(selected.value);
        const contactResult = await fetchContactMessages();
        contacts.value = contactResult.data ?? [];
    } catch (failure) { error.value = failure; }
    finally { loading.value = false; }
}

async function savePage() {
    if (busy.value) return;
    busy.value = true; error.value = null; saved.value = false;
    try {
        const page = await saveSitePage(selected.value, { ...draft });
        pages.value = pages.value.map((item) => item.slug === page.slug ? page : item);
        saved.value = true;
    } catch (failure) { error.value = failure; }
    finally { busy.value = false; }
}

async function publish() {
    if (busy.value || !window.confirm(t('site.publish') + '?')) return;
    busy.value = true; error.value = null; saved.value = false;
    try {
        const page = await publishSitePage(selected.value);
        pages.value = pages.value.map((item) => item.slug === page.slug ? page : item);
        saved.value = true;
    } catch (failure) { error.value = failure; }
    finally { busy.value = false; }
}

function editFaq(faq) { Object.assign(faqDraft, { ...faq }); }

async function submitFaq() {
    if (busy.value) return;
    busy.value = true; error.value = null;
    try {
        const payload = Object.fromEntries(Object.entries(faqDraft).filter(([key]) => key !== 'id'));
        const faq = await saveSiteFaq(faqDraft.id, payload);
        faqs.value = faqDraft.id ? faqs.value.map((item) => item.id === faq.id ? faq : item) : [...faqs.value, faq];
        resetFaq();
    } catch (failure) { error.value = failure; }
    finally { busy.value = false; }
}

async function removeFaq(id) {
    if (!window.confirm(t('site.removeFaq') + '?')) return;
    try { await deleteSiteFaq(id); faqs.value = faqs.value.filter((item) => item.id !== id); }
    catch (failure) { error.value = failure; }
}

onMounted(load);
</script>

<template>
    <div class="space-y-8">
        <PageHeading :title="t('site.manage')" :description="t('site.manageIntro')" />
        <BaseAlert tone="warning">{{ t('site.editorialRequired') }}</BaseAlert>
        <LoadingState v-if="loading" />
        <template v-else>
            <BaseAlert v-if="error" tone="danger">{{ t('errors.generic') }} <button type="button" class="font-bold underline" @click="load">{{ t('common.retry') }}</button></BaseAlert>
            <BaseAlert v-if="saved" tone="success">{{ t('policies.saved') }}</BaseAlert>
            <section class="rounded-3xl border border-slate-200 bg-white p-6"><h2 class="text-xl font-black">{{ t('site.manage') }}</h2><nav class="mt-5 flex flex-wrap gap-2" :aria-label="t('site.manage')"><button v-for="page in pages" :key="page.slug" type="button" class="min-h-11 rounded-xl px-4 text-sm font-bold" :class="selected === page.slug ? 'bg-brand text-white' : 'bg-slate-100 text-slate-700'" @click="choose(page.slug)">{{ t(`site.${page.slug}`) }}</button></nav><form class="mt-6 space-y-5" @submit.prevent="savePage"><div><label for="site-ar" class="mb-2 block font-bold">{{ t('site.arabic') }}</label><textarea id="site-ar" v-model="draft.draft_ar" dir="rtl" rows="8" maxlength="20000" class="w-full rounded-xl border border-slate-300 p-4" /></div><div><label for="site-en" class="mb-2 block font-bold">{{ t('site.english') }}</label><textarea id="site-en" v-model="draft.draft_en" dir="ltr" rows="8" maxlength="20000" class="w-full rounded-xl border border-slate-300 p-4" /></div><div class="flex flex-wrap gap-3"><button type="submit" :disabled="busy" class="min-h-11 rounded-xl bg-brand px-6 font-bold text-white disabled:opacity-50">{{ t('site.save') }}</button><button type="button" :disabled="busy || !draft.draft_ar || !draft.draft_en" class="min-h-11 rounded-xl border border-brand px-6 font-bold text-brand disabled:opacity-50" @click="publish">{{ t('site.publish') }}</button></div></form></section>
            <section class="rounded-3xl border border-slate-200 bg-white p-6"><h2 class="text-xl font-black">{{ t('site.faq') }}</h2><ul class="mt-5 space-y-3"><li v-for="faq in faqs" :key="faq.id" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 p-4"><span>{{ faq.question_ar }} <small class="text-slate-500">· {{ faq.is_active ? t('site.published') : t('site.draft') }}</small></span><span class="flex gap-3"><button type="button" class="font-bold text-brand underline" @click="editFaq(faq)">{{ t('common.open') }}</button><button type="button" class="font-bold text-red-700 underline" @click="removeFaq(faq.id)">{{ t('site.removeFaq') }}</button></span></li></ul><form class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="submitFaq"><label v-for="field in ['question_ar', 'answer_ar', 'question_en', 'answer_en']" :key="field" class="block"><span class="mb-2 block text-sm font-bold">{{ t(`site.${field.replace('_ar', 'Ar').replace('_en', 'En')}`) }}</span><textarea v-model="faqDraft[field]" :dir="field.endsWith('_ar') ? 'rtl' : 'ltr'" rows="3" required :maxlength="field.startsWith('question') ? 1000 : 5000" class="w-full rounded-xl border border-slate-300 p-3" /></label><label class="block"><span class="mb-2 block text-sm font-bold">{{ t('site.order') }}</span><input v-model.number="faqDraft.sort_order" type="number" min="0" class="min-h-11 w-full rounded-xl border border-slate-300 px-3"></label><label class="flex items-center gap-2"><input v-model="faqDraft.is_active" type="checkbox" class="size-5 accent-brand">{{ t('site.active') }}</label><div class="sm:col-span-2 flex gap-3"><button type="submit" :disabled="busy" class="min-h-11 rounded-xl bg-brand px-6 font-bold text-white disabled:opacity-50">{{ t('site.saveFaq') }}</button><button type="button" class="min-h-11 rounded-xl border border-slate-300 px-5" @click="resetFaq">{{ t('common.reset') }}</button></div></form></section>
            <section class="rounded-3xl border border-slate-200 bg-white p-6"><h2 class="text-xl font-black">{{ t('site.contacts') }}</h2><p v-if="!contacts.length" class="mt-4 text-sm text-slate-500">{{ t('common.noResults') }}</p><ul v-else class="mt-5 space-y-4"><li v-for="item in contacts" :key="item.id" class="rounded-xl border border-slate-200 p-4"><p class="font-bold">{{ item.subject }}</p><p class="mt-1 text-sm text-slate-600">{{ item.name }} · {{ item.email }}</p><p class="mt-3 whitespace-pre-line break-words text-sm">{{ item.message }}</p></li></ul></section>
        </template>
    </div>
</template>
