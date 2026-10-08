<script setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatDate } from '../../utils/catalog';
import BaseButton from '../ui/BaseButton.vue';
import CommerceError from './CommerceError.vue';
import { issueOrderDocuments, downloadOrderDocument } from '../../api/commerce';
import { issueOperationalDocuments, downloadOperationalDocument } from '../../api/admin-operations';

const props = defineProps({ order: { type: Object, required: true }, admin: { type: Boolean, default: false } });
const emit = defineEmits(['refresh']);
const { t, locale } = useI18n();
const busy = ref(false);
const downloading = ref(null);
const error = ref(null);
const eligible = computed(() => ['completed', 'refunded'].includes(props.order.status) && Boolean(props.order.paid_at));
const documents = computed(() => props.order.financial_documents ?? []);
async function issue() {
    if (busy.value || !eligible.value) return;
    busy.value = true;
    error.value = null;
    try {
        if (props.admin) await issueOperationalDocuments(props.order.id);
        else await issueOrderDocuments(props.order.id);
        emit('refresh');
    } catch (failure) { error.value = failure; }
    finally { busy.value = false; }
}
async function download(document) {
    downloading.value = document.id;
    error.value = null;
    try {
        if (props.admin) await downloadOperationalDocument(document.id);
        else await downloadOrderDocument(document.id);
    } catch (failure) { error.value = failure; }
    finally { downloading.value = null; }
}
</script>

<template>
    <section class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-xl font-black text-slate-950">{{ t('commerce.financialDocuments') }}</h2><BaseButton v-if="eligible" variant="secondary" :loading="busy" @click="issue">{{ t('commerce.prepareReceipt') }}</BaseButton></div>
        <CommerceError :error="error" />
        <p v-if="!eligible" class="text-sm text-slate-500">{{ t('commerce.receiptUnavailable') }}</p>
        <p v-else-if="!documents.length" class="text-sm text-slate-500">{{ t('commerce.receiptNotIssued') }}</p>
        <div v-for="document in documents" :key="document.id" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <div><p class="font-bold text-slate-900">{{ t(document.kind === 'refund' ? 'commerce.refundReceipt' : 'commerce.orderReceipt') }}</p><p class="mt-1 text-sm text-slate-600"><bdi>{{ document.document_number }}</bdi> · {{ formatDate(document.issued_at, locale) }}</p><p class="text-xs text-slate-500"><bdi>{{ document.amount }} {{ document.currency }}</bdi></p></div>
            <BaseButton variant="secondary" :loading="downloading === document.id" :disabled="downloading !== null" @click="download(document)">{{ t('commerce.downloadReceipt') }}</BaseButton>
        </div>
    </section>
</template>
