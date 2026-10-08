<script setup>
import { reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { createOrderRefund, completeOrderRefund, rejectOrderRefund } from '../../api/admin-operations';
import BaseAlert from '../ui/BaseAlert.vue';
import BaseButton from '../ui/BaseButton.vue';
import MoneyAmount from './MoneyAmount.vue';

const props = defineProps({ order: { type: Object, required: true } });
const emit = defineEmits(['refresh']);
const { t } = useI18n();
const busy = ref(false);
const error = ref(null);
const form = reactive({ amount: '', reason: '', access_effect: 'none', order_item_ids: [], internal_note: '' });
watch(() => form.access_effect, (value) => { form.order_item_ids = []; if (value === 'full') form.amount = props.order.refund_balance?.refundable ?? ''; });
async function submit() {
    if (busy.value) return;
    if (!window.confirm(t('commerce.createRefundConfirm', { amount: form.amount, currency: props.order.currency, effect: t(`commerce.refund${form.access_effect === 'none' ? 'None' : form.access_effect === 'items' ? 'Items' : 'Full'}`) }))) return;
    busy.value = true; error.value = null;
    try { await createOrderRefund(props.order.id, { ...form, amount: Number(form.amount).toFixed(2), order_item_ids: form.order_item_ids.map(Number) }); Object.assign(form, { amount: '', reason: '', access_effect: 'none', order_item_ids: [], internal_note: '' }); emit('refresh'); }
    catch (failure) { error.value = failure; } finally { busy.value = false; }
}
async function process(refund, action) {
    if (busy.value || !window.confirm(action === 'complete' ? t('commerce.completeRefundConfirm', { amount: refund.amount, currency: refund.currency, effect: t(`commerce.refund${refund.access_effect === 'none' ? 'None' : refund.access_effect === 'items' ? 'Items' : 'Full'}`) }) : t('commerce.rejectRefundConfirm', { amount: refund.amount, currency: refund.currency }))) return;
    busy.value = true; error.value = null;
    try { if (action === 'complete') await completeOrderRefund(props.order.id, refund.id); else await rejectOrderRefund(props.order.id, refund.id); emit('refresh'); }
    catch (failure) { error.value = failure; emit('refresh'); } finally { busy.value = false; }
}
</script>

<template>
    <section class="space-y-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        <h2 class="text-xl font-black text-slate-950">{{ t('commerce.manualRefund') }}</h2>
        <BaseAlert>{{ t('commerce.refundWarning') }}</BaseAlert>
        <BaseAlert v-if="error" tone="danger">{{ t('admin.saveError') }}</BaseAlert>
        <dl class="grid gap-3 text-sm sm:grid-cols-4"><div v-for="field in ['paid', 'refunded', 'pending', 'refundable']" :key="field" class="rounded-xl bg-slate-50 p-3"><dt>{{ t(`commerce.${field === 'paid' ? 'paidAmount' : field === 'refunded' ? 'refundedAmount' : field === 'pending' ? 'pendingRefunds' : 'refundable'}`) }}</dt><dd class="mt-1 font-black text-brand"><MoneyAmount :amount="order.refund_balance?.[field]" :currency="order.currency" /></dd></div></dl>
        <form v-if="order.status === 'completed' && order.refund_balance?.refundable !== '0.00'" class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit"><label class="space-y-1 text-sm font-bold">{{ t('commerce.refundAmount') }} ({{ order.currency }})<input v-model="form.amount" type="number" required min="0.01" :max="order.refund_balance?.refundable" step="0.01" :readonly="form.access_effect === 'full'" class="block w-full rounded-xl border border-slate-300 px-4 py-3"><span v-if="error?.errors?.amount" role="alert" class="text-red-700">{{ error.errors.amount[0] }}</span></label><label class="space-y-1 text-sm font-bold">{{ t('commerce.refundReason') }}<input v-model="form.reason" required maxlength="64" class="block w-full rounded-xl border border-slate-300 px-4 py-3"><span v-if="error?.errors?.reason" role="alert" class="text-red-700">{{ error.errors.reason[0] }}</span></label><label class="space-y-1 text-sm font-bold">{{ t('commerce.refundEffect') }}<select v-model="form.access_effect" class="block w-full rounded-xl border border-slate-300 px-4 py-3"><option value="none">{{ t('commerce.refundNone') }}</option><option value="items">{{ t('commerce.refundItems') }}</option><option value="full">{{ t('commerce.refundFull') }}</option></select></label><div v-if="form.access_effect === 'items'" class="space-y-2 text-sm"><p class="font-bold">{{ t('commerce.refundItemsLabel') }}</p><label v-for="line in order.items ?? []" :key="line.id" class="flex gap-2"><input v-model="form.order_item_ids" type="checkbox" :value="line.id" class="size-5 accent-brand"><span>{{ line.title }} — <MoneyAmount :amount="line.total" :currency="order.currency" /></span></label><p v-if="error?.errors?.order_item_ids" role="alert" class="text-red-700">{{ error.errors.order_item_ids[0] }}</p></div><label class="space-y-1 text-sm font-bold sm:col-span-2">{{ t('commerce.internalNote') }}<textarea v-model="form.internal_note" maxlength="2000" rows="2" class="block w-full rounded-xl border border-slate-300 px-4 py-3" /></label><div class="sm:col-span-2"><BaseButton type="submit" :loading="busy">{{ t('commerce.initiateRefund') }}</BaseButton></div></form>
        <div class="space-y-3"><h3 class="font-black">{{ t('commerce.refundHistory') }}</h3><p v-if="!order.refunds?.length" class="text-sm text-slate-500">—</p><article v-for="refund in order.refunds ?? []" :key="refund.id" class="rounded-xl border border-slate-200 p-4"><div class="flex flex-wrap justify-between gap-3"><strong>#{{ refund.id }} · <MoneyAmount :amount="refund.amount" :currency="refund.currency" /></strong><span>{{ t(`commerce.refund${refund.status[0].toUpperCase()}${refund.status.slice(1)}`) }}</span></div><p class="mt-2 text-sm text-slate-600">{{ refund.reason }} · {{ refund.access_effect }}</p><p v-if="refund.internal_note" class="mt-2 text-sm text-slate-500">{{ refund.internal_note }}</p><div v-if="refund.status === 'pending'" class="mt-3 flex gap-2"><BaseButton :disabled="busy" @click="process(refund, 'complete')">{{ t('commerce.completeRefund') }}</BaseButton><BaseButton variant="secondary" :disabled="busy" @click="process(refund, 'reject')">{{ t('commerce.rejectRefund') }}</BaseButton></div></article></div>
    </section>
</template>
