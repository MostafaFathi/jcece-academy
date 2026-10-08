<script setup>
import { reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { fetchSiteFaqs, fetchSitePage, submitContact } from '../api/public-site';
import BaseAlert from '../components/ui/BaseAlert.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import { usePageMeta } from '../composables/usePageMeta';

const props = defineProps({ slug: { type: String, required: true } });
const { t, locale } = useI18n();
const page = ref(null);
const faqs = ref([]);
const loading = ref(true);
const error = ref(null);
const contact = reactive({ name: '', email: '', subject: '', message: '' });
const contactError = ref(null);
const sent = ref(false);
const busy = ref(false);
let sequence = 0;

usePageMeta(() => t(`site.${props.slug}`), () => page.value?.body?.slice(0, 160) ?? t('site.intro'));

async function load() {
    const current = ++sequence;
    loading.value = true;
    error.value = null;
    try {
        const [pageResult, faqResult] = await Promise.all([
            fetchSitePage(props.slug).catch((failure) => { if (failure.status !== 404) throw failure; return null; }),
            props.slug === 'faq' ? fetchSiteFaqs() : Promise.resolve([]),
        ]);
        if (current === sequence) { page.value = pageResult; faqs.value = faqResult; }
    } catch (failure) { if (current === sequence) error.value = failure; }
    finally { if (current === sequence) loading.value = false; }
}

async function send() {
    if (busy.value) return;
    busy.value = true;
    contactError.value = null;
    sent.value = false;
    try { await submitContact({ ...contact }); sent.value = true; Object.assign(contact, { name: '', email: '', subject: '', message: '' }); }
    catch (failure) { contactError.value = failure; }
    finally { busy.value = false; }
}

watch([() => props.slug, locale], load, { immediate: true });
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <div class="mx-auto max-w-5xl px-4 py-12 sm:px-6 lg:px-8 lg:py-20">
            <p class="text-xs font-black tracking-widest text-brand uppercase">JCEC ACADEMY</p>
            <h1 class="mt-3 text-4xl font-black text-slate-950">{{ t(`site.${slug}`) }}</h1>
            <LoadingState v-if="loading" class="mt-8" />
            <BaseAlert v-else-if="error" tone="danger" class="mt-8">{{ t('errors.generic') }} <button type="button" class="font-bold underline" @click="load">{{ t('common.retry') }}</button></BaseAlert>
            <template v-else>
                <article v-if="page" class="mt-8 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10"><p class="whitespace-pre-line break-words leading-9 text-slate-700">{{ page.body }}</p></article>
                <BaseAlert v-else-if="slug !== 'contact'" tone="warning" class="mt-8">{{ t('site.notPublished') }}</BaseAlert>
                <section v-if="slug === 'faq'" class="mt-12"><h2 class="text-2xl font-black">{{ t('site.faq') }}</h2><div v-if="faqs.length" class="mt-6 space-y-3"><details v-for="faq in faqs" :key="faq.id" class="rounded-2xl border border-slate-200 bg-white p-5"><summary class="cursor-pointer font-bold focus-visible:outline-3 focus-visible:outline-brand">{{ faq.question }}</summary><p class="mt-4 whitespace-pre-line break-words leading-8 text-slate-600">{{ faq.answer }}</p></details></div><p v-else class="mt-6 text-slate-600">{{ t('site.noFaq') }}</p></section>
                <section v-if="slug === 'contact'" class="mt-10 max-w-2xl rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10"><h2 class="text-2xl font-black">{{ t('site.contact') }}</h2><p class="mt-2 text-sm text-slate-600">{{ t('site.contactIntro') }}</p><BaseAlert v-if="sent" tone="success" class="mt-6" role="status">{{ t('site.sent') }}</BaseAlert><BaseAlert v-if="contactError && contactError.status !== 422" tone="danger" class="mt-6">{{ t('errors.generic') }}</BaseAlert><form class="mt-6 space-y-5" @submit.prevent="send"><div v-for="field in ['name', 'email', 'subject', 'message']" :key="field"><label :for="`contact-${field}`" class="mb-2 block text-sm font-bold">{{ t(field === 'name' ? 'profile.name' : field === 'email' ? 'profile.email' : `site.${field}`) }}</label><textarea v-if="field === 'message'" :id="`contact-${field}`" v-model="contact[field]" rows="6" required minlength="10" maxlength="5000" class="w-full rounded-xl border border-slate-300 p-3 focus-visible:outline-3 focus-visible:outline-brand" :aria-invalid="Boolean(contactError?.errors?.[field])" :aria-describedby="contactError?.errors?.[field] ? `contact-${field}-error` : undefined" /><input v-else :id="`contact-${field}`" v-model="contact[field]" :type="field === 'email' ? 'email' : 'text'" required maxlength="255" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 focus-visible:outline-3 focus-visible:outline-brand" :aria-invalid="Boolean(contactError?.errors?.[field])" :aria-describedby="contactError?.errors?.[field] ? `contact-${field}-error` : undefined"><p v-if="contactError?.errors?.[field]" :id="`contact-${field}-error`" role="alert" class="mt-1 text-sm text-red-700">{{ contactError.errors[field][0] }}</p></div><button type="submit" :disabled="busy" class="min-h-11 rounded-xl bg-brand px-6 font-black text-white disabled:opacity-50 focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-accent">{{ t('site.send') }}</button></form></section>
            </template>
        </div>
    </div>
</template>
