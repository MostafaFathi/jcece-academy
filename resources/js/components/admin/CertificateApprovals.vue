<script setup>
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { approveCertificateRequest, fetchCertificateApprovalRequests } from '../../api/certificate-policy';
import BaseButton from '../ui/BaseButton.vue';

const { t } = useI18n();
const requests = ref([]);
const loading = ref(true);
const error = ref(null);
const busyId = ref(null);

async function load() {
    loading.value = true; error.value = null;
    try { requests.value = (await fetchCertificateApprovalRequests({ status: 'pending' })).data; }
    catch (failure) { error.value = failure; }
    finally { loading.value = false; }
}

async function approve(id) {
    if (busyId.value) return;
    busyId.value = id; error.value = null;
    try { await approveCertificateRequest(id); await load(); }
    catch (failure) { error.value = failure; }
    finally { busyId.value = null; }
}

onMounted(load);
</script>

<template>
    <section class="space-y-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        <h2 class="text-xl font-black">{{ t('certificatePolicy.approvals') }}</h2>
        <p v-if="error" role="alert" class="text-sm text-red-700">{{ t('operations.saveError') }} <button type="button" class="underline" @click="load">{{ t('common.retry') }}</button></p>
        <p v-if="loading" role="status">{{ t('common.loading') }}</p>
        <p v-else-if="!requests.length" class="text-sm text-slate-500">{{ t('certificatePolicy.empty') }}</p>
        <div v-else class="space-y-3"><article v-for="request in requests" :key="request.id" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 p-4"><div><p class="font-bold">{{ request.course?.title }}</p><p class="text-sm text-slate-600">{{ request.user?.name }} · {{ request.user?.email }}</p></div><BaseButton :loading="busyId === request.id" :disabled="Boolean(busyId)" @click="approve(request.id)">{{ t('certificatePolicy.approve') }}</BaseButton></article></div>
    </section>
</template>
