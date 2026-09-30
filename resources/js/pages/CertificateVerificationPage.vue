<script setup>
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { verifyCertificate } from '../api/assessments';
import { formatDate } from '../utils/catalog';
import LoadingState from '../components/ui/LoadingState.vue';
import BaseButton from '../components/ui/BaseButton.vue';

const props = defineProps({ token: { type: String, required: true } });
const { t, locale } = useI18n();
const record = ref(null);
const loading = ref(true);
const error = ref(null);
async function load() {
    loading.value = true; record.value = null; error.value = null;
    try { record.value = await verifyCertificate(props.token); }
    catch (failure) { if (failure.status === 404) record.value = { status: 'unknown' }; else error.value = failure; }
    finally { loading.value = false; }
}
watch(() => props.token, load, { immediate: true });
</script>
<template>
    <div class="mx-auto max-w-2xl space-y-6 py-12" :dir="locale === 'ar' ? 'rtl' : 'ltr'"><LoadingState v-if="loading" /><div v-else class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm sm:p-10"><p class="text-xs font-black uppercase tracking-widest text-brand">JCEC ACADEMY</p><h1 class="mt-3 text-2xl font-black">{{ t('assessments.verify') }}</h1><div v-if="error" role="alert" class="mt-6 text-red-700">{{ t('assessments.verificationError') }} <BaseButton variant="secondary" class="mt-3" @click="load">{{ t('common.retry') }}</BaseButton></div><div v-else-if="record?.status === 'unknown'" role="status" class="mt-6 rounded-2xl bg-slate-100 p-5 text-slate-700">{{ t('assessments.unknownCertificate') }}</div><div v-else-if="record" class="mt-6 space-y-4"><p class="rounded-2xl p-5 text-lg font-black" :class="record.status === 'issued' ? 'bg-emerald-100 text-emerald-900' : 'bg-red-100 text-red-900'">{{ t(record.status === 'issued' ? 'assessments.validCertificate' : 'assessments.revokedCertificate') }}</p><dl class="grid gap-4 text-sm sm:grid-cols-2"><div><dt class="text-slate-500">{{ t('assessments.certificateNumber') }}</dt><dd class="mt-1 font-bold">{{ record.certificate_number }}</dd></div><div><dt class="text-slate-500">{{ t('assessments.studentName') }}</dt><dd class="mt-1 font-bold">{{ record.student_name }}</dd></div><div><dt class="text-slate-500">{{ t('assessments.courseTitle') }}</dt><dd class="mt-1 font-bold">{{ record.course_title }}</dd></div><div><dt class="text-slate-500">{{ t('assessments.issued') }}</dt><dd class="mt-1 font-bold">{{ formatDate(record.issued_at, locale) }}</dd></div></dl></div></div></div>
</template>
