<script setup>
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { submitPayment } from '../../api/commerce';
import { canSubmitPayment, paymentMethods } from '../../utils/commerce';
import BaseInput from '../ui/BaseInput.vue';
import BaseButton from '../ui/BaseButton.vue';
import CommerceError from './CommerceError.vue';
const props = defineProps({ order: { type: Object, required: true }, refreshing: Boolean });
const emit = defineEmits(['submitted', 'refresh']);
const { t } = useI18n();
const method = ref('bank_transfer');
const reference = ref('');
const proof = ref(null);
const fileInput = ref(null);
const error = ref(null);
const proofError = ref('');
const submitting = ref(false);
const progress = ref(null);
const verificationNeeded = ref(false);
const sent = ref(false);
watch(() => props.order, () => { verificationNeeded.value = false; });
function selectProof(event) { proof.value = event.target.files?.[0] ?? null; proofError.value = ''; }
async function submit() {
    if (props.refreshing || submitting.value || sent.value || verificationNeeded.value || !canSubmitPayment(props.order)) return;
    error.value = null;
    proofError.value = '';
    if (!proof.value) proofError.value = t('commerce.proofRequired');
    else if (!/\.(pdf|jpe?g|png)$/i.test(proof.value.name) || !['application/pdf', 'image/jpeg', 'image/png'].includes(proof.value.type)) proofError.value = t('commerce.proofType');
    else if (proof.value.size > props.order.payment_proof_max_kilobytes * 1024) proofError.value = t('commerce.proofSize', { count: props.order.payment_proof_max_kilobytes });
    if (proofError.value) return;
    submitting.value = true;
    progress.value = null;
    const form = new FormData();
    form.append('method', method.value);
    if (reference.value.trim()) form.append('transaction_id', reference.value.trim());
    form.append('payment_proof', proof.value);
    try {
        const payment = await submitPayment(props.order.id, form, (event) => { if (event.total) progress.value = Math.round(event.loaded / event.total * 100); });
        sent.value = true;
        proof.value = null;
        if (fileInput.value) fileInput.value.value = '';
        emit('submitted', payment);
    } catch (requestError) {
        error.value = requestError;
        if (!requestError.status || requestError.status >= 500) { verificationNeeded.value = true; emit('refresh'); }
        proofError.value = requestError.errors?.payment_proof?.[0] ?? '';
    } finally { submitting.value = false; }
}
</script>
<template>
    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <h2 class="text-xl font-black text-slate-950">{{ t('commerce.manualPayment') }}</h2><p class="mt-3 text-sm leading-7 text-slate-600">{{ t('commerce.paymentInstructions') }}</p>
        <CommerceError :error="error" class="mt-4" />
        <p v-if="sent" role="status" class="mt-4 rounded-xl bg-amber-50 p-4 text-sm font-bold text-amber-900">{{ t('commerce.proofSubmitted') }}</p>
        <div v-else-if="verificationNeeded" class="mt-4"><p class="text-sm text-amber-900">{{ t('commerce.paymentUncertain') }}</p><BaseButton variant="secondary" class="mt-3" @click="emit('refresh')">{{ t('commerce.refreshOrder') }}</BaseButton></div>
        <form v-else class="mt-6 space-y-5" @submit.prevent="submit">
            <div><label for="payment-method" class="mb-2 block text-sm font-bold text-slate-700">{{ t('commerce.paymentMethod') }}</label><select id="payment-method" v-model="method" :disabled="submitting || refreshing" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white p-3 focus:outline-brand"><option v-for="value in paymentMethods" :key="value" :value="value">{{ t(`commerce.methods.${value}`) }}</option></select></div>
            <BaseInput id="payment-reference" v-model="reference" :label="t('commerce.reference')" :error="error?.errors?.transaction_id?.[0]" maxlength="255" :disabled="submitting || refreshing" />
            <div class="min-w-0 rounded-2xl border border-dashed border-brand/30 bg-brand-soft/30 p-4"><label for="payment-proof" class="mb-2 block text-sm font-bold text-slate-700">{{ t('commerce.paymentProof') }}</label><p id="proof-help" class="mb-3 text-xs leading-6 text-slate-500">{{ t('commerce.proofHelp') }} <span v-if="order.payment_proof_max_kilobytes">{{ t('commerce.proofSize', { count: order.payment_proof_max_kilobytes }) }}</span></p><input id="payment-proof" ref="fileInput" type="file" accept=".pdf,.jpg,.jpeg,.png" :disabled="submitting || refreshing" :aria-invalid="Boolean(proofError)" :aria-describedby="proofError ? 'proof-help proof-error' : 'proof-help'" class="block w-full min-w-0 text-sm text-slate-600 file:me-3 file:rounded-lg file:border-0 file:bg-brand file:px-3 file:py-2 file:text-white" @change="selectProof"><p v-if="proofError" id="proof-error" role="alert" class="mt-2 text-sm text-red-700">{{ proofError }}</p></div>
            <div v-if="submitting && progress !== null"><label for="upload-progress" class="text-sm text-slate-500">{{ t('commerce.uploadProgress', { count: progress }) }}</label><progress id="upload-progress" :value="progress" max="100" class="mt-2 block w-full accent-brand" /></div>
            <BaseButton type="submit" :loading="submitting" :disabled="refreshing" class="w-full">{{ t('commerce.submitProof') }}</BaseButton><p class="text-xs leading-6 text-slate-500">{{ t('commerce.noAccessYet') }}</p>
        </form>
    </section>
</template>
