<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';
import { fetchAdminPolicyPages, publishPolicyPage, savePolicyPage } from '../api/policy-pages';
import BaseAlert from '../components/ui/BaseAlert.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import PageHeading from '../components/ui/PageHeading.vue';

const { t, locale } = useI18n();
const auth = useAuthStore();
const pages = ref([]);
const selected = ref('privacy');
const draft = reactive({ draft_ar: '', draft_en: '' });
const loading = ref(true);
const busy = ref(false);
const error = ref(null);
const saved = ref(false);
const current = computed(() => pages.value.find((page) => page.slug === selected.value));
const hasUnsavedDraft = computed(() => draft.draft_ar !== (current.value?.draft_ar ?? '') || draft.draft_en !== (current.value?.draft_en ?? ''));

function select(slug) {
    selected.value = slug;
    draft.draft_ar = pages.value.find((page) => page.slug === slug)?.draft_ar ?? '';
    draft.draft_en = pages.value.find((page) => page.slug === slug)?.draft_en ?? '';
    error.value = null; saved.value = false;
}
async function load() {
    loading.value = true; error.value = null;
    try { pages.value = await fetchAdminPolicyPages(); select(selected.value); }
    catch (failure) { error.value = failure; }
    finally { loading.value = false; }
}
async function save() {
    if (busy.value) return;
    busy.value = true; error.value = null; saved.value = false;
    try { const updated = await savePolicyPage(selected.value, { ...draft }); pages.value = pages.value.map((page) => page.slug === updated.slug ? updated : page); saved.value = true; }
    catch (failure) { error.value = failure; }
    finally { busy.value = false; }
}
async function publish() {
    if (busy.value || hasUnsavedDraft.value || !window.confirm(t('policies.publishConfirm'))) return;
    busy.value = true; error.value = null; saved.value = false;
    try { const updated = await publishPolicyPage(selected.value); pages.value = pages.value.map((page) => page.slug === updated.slug ? updated : page); saved.value = true; }
    catch (failure) { error.value = failure; }
    finally { busy.value = false; }
}
onMounted(load);
</script>

<template>
    <div class="space-y-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <PageHeading :title="t('policies.manage')" :description="t('policies.manageDescription')" />
        <BaseAlert tone="warning">{{ t('policies.legalApproval') }}</BaseAlert>
        <LoadingState v-if="loading" />
        <BaseAlert v-else-if="error && !pages.length" tone="danger">{{ t('errors.generic') }} <button class="font-bold underline" @click="load">{{ t('common.retry') }}</button></BaseAlert>
        <template v-else><nav class="flex flex-wrap gap-2" :aria-label="t('policies.manage')"><button v-for="page in pages" :key="page.slug" type="button" class="min-h-11 rounded-xl px-4 py-2 text-sm font-bold" :class="selected === page.slug ? 'bg-brand text-white' : 'bg-white text-brand ring-1 ring-slate-200'" @click="select(page.slug)">{{ t(`policies.${page.slug}`) }}</button></nav>
            <form v-if="current" class="space-y-5 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="save"><p class="text-sm text-slate-600">{{ current.published_at ? `${t('policies.published')} · ${t('policies.version')} ${current.version}` : t('policies.notPublished') }}</p><div><label for="policy-ar" class="mb-2 block font-bold">{{ t('policies.arabicDraft') }}</label><textarea id="policy-ar" v-model="draft.draft_ar" dir="rtl" rows="10" maxlength="50000" class="w-full rounded-xl border border-slate-300 p-4 leading-7" /><p v-if="error?.errors?.draft_ar" class="text-sm text-red-700">{{ error.errors.draft_ar[0] }}</p></div><div><label for="policy-en" class="mb-2 block font-bold">{{ t('policies.englishDraft') }}</label><textarea id="policy-en" v-model="draft.draft_en" dir="ltr" rows="10" maxlength="50000" class="w-full rounded-xl border border-slate-300 p-4 leading-7" /><p v-if="error?.errors?.draft_en" class="text-sm text-red-700">{{ error.errors.draft_en[0] }}</p></div><BaseAlert v-if="saved">{{ t('policies.saved') }}</BaseAlert><BaseAlert v-if="error" tone="danger">{{ t('errors.generic') }}</BaseAlert><div class="flex flex-wrap gap-3"><BaseButton v-if="auth.can('policy_pages.update')" type="submit" :loading="busy">{{ t('admin.save') }}</BaseButton><BaseButton v-if="auth.can('policy_pages.publish')" variant="secondary" :disabled="busy || hasUnsavedDraft" @click="publish">{{ t('policies.publish') }}</BaseButton><RouterLink :to="{ name: `policies.${selected}` }" class="inline-flex min-h-11 items-center text-sm font-bold text-brand underline">{{ t('policies.publicPreview') }}</RouterLink></div></form>
        </template>
    </div>
</template>
