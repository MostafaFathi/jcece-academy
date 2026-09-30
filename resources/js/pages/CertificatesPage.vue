<script setup>
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { fetchCertificates, downloadCertificate } from '../api/assessments';
import { formatDate } from '../utils/catalog';
import BaseButton from '../components/ui/BaseButton.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import PageHeading from '../components/ui/PageHeading.vue';
import PaginationNav from '../components/ui/PaginationNav.vue';

const { t, locale } = useI18n();
const certificates = ref([]);
const meta = ref(null);
const loading = ref(true);
const busyId = ref(null);
const error = ref(null);
async function load(page = 1) {
    loading.value = true; error.value = null;
    try { const result = await fetchCertificates(page); certificates.value = result.items; meta.value = result.meta; }
    catch (failure) { error.value = failure; certificates.value = []; }
    finally { loading.value = false; }
}
async function download(id) {
    if (busyId.value) return;
    busyId.value = id; error.value = null;
    try { await downloadCertificate(id); } catch (failure) { error.value = failure; }
    finally { busyId.value = null; }
}
onMounted(load);
</script>
<template>
    <div class="space-y-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <PageHeading :title="t('assessments.certificates')" :description="t('assessments.certificatesDescription')" />
        <div v-if="error" role="alert" class="rounded-2xl border border-red-200 bg-red-50 p-4 text-red-800">{{ t('assessments.requestFailed') }} <BaseButton variant="secondary" class="mt-2" @click="load(meta?.current_page || 1)">{{ t('common.retry') }}</BaseButton></div>
        <LoadingState v-if="loading" />
        <EmptyState v-else-if="!certificates.length && !error" :title="t('assessments.noCertificates')" />
        <div v-else class="grid gap-5 lg:grid-cols-2"><article v-for="certificate in certificates" :key="certificate.id" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-xs font-bold text-brand">{{ certificate.certificate_number }}</p><h2 class="mt-2 text-xl font-black">{{ certificate.course_title }}</h2></div><span class="rounded-full px-3 py-1 text-xs font-bold" :class="certificate.status === 'issued' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'">{{ t(certificate.status === 'issued' ? 'assessments.issued' : 'assessments.revoked') }}</span></div><p class="mt-3 text-sm text-slate-600">{{ certificate.student_name }}</p><p class="mt-1 text-sm text-slate-500">{{ t('assessments.issuedAt', { date: formatDate(certificate.issued_at, locale) }) }}</p><p v-if="certificate.status === 'revoked' && certificate.revocation_reason" class="mt-3 text-sm text-red-700">{{ certificate.revocation_reason }}</p><BaseButton class="mt-5" variant="secondary" :loading="busyId === certificate.id" :disabled="Boolean(busyId)" @click="download(certificate.id)">{{ t('assessments.download') }} PDF</BaseButton></article></div>
        <PaginationNav v-if="meta?.last_page > 1" :current-page="meta.current_page" :last-page="meta.last_page" @change="load" />
    </div>
</template>
