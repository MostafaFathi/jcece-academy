<script setup>
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { fetchPolicyPage } from '../api/policy-pages';
import BaseAlert from '../components/ui/BaseAlert.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import PageHeading from '../components/ui/PageHeading.vue';

const props = defineProps({ slug: { type: String, required: true } });
const { t, locale } = useI18n();
const page = ref(null);
const loading = ref(true);
const error = ref(null);
let sequence = 0;
async function load() {
    const current = ++sequence;
    loading.value = true; error.value = null; page.value = null;
    try { const result = await fetchPolicyPage(props.slug, locale.value); if (current === sequence) page.value = result; }
    catch (failure) { if (current === sequence) error.value = failure; }
    finally { if (current === sequence) loading.value = false; }
}
watch([() => props.slug, locale], load, { immediate: true });
</script>

<template>
    <section class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:py-20" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <PageHeading :title="t(`policies.${slug}`)" :description="t('policies.description')" />
        <LoadingState v-if="loading" />
        <BaseAlert v-else-if="error?.status === 404" tone="warning" class="mt-8">{{ t('policies.notPublished') }}</BaseAlert>
        <BaseAlert v-else-if="error" tone="danger" class="mt-8">{{ t('errors.generic') }} <button class="font-bold underline" @click="load">{{ t('common.retry') }}</button></BaseAlert>
        <article v-else-if="page" class="mt-8 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10"><p class="whitespace-pre-wrap break-words leading-9 text-slate-800">{{ page.body }}</p><p class="mt-8 border-t border-slate-100 pt-4 text-xs text-slate-500">{{ t('policies.version') }} {{ page.version }}</p></article>
    </section>
</template>
