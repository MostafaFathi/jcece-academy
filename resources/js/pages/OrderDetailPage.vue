<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { fetchOrder, downloadPaymentProof } from '../api/commerce';
import { accessState, canSubmitPayment } from '../utils/commerce';
import { formatDate } from '../utils/catalog';
import PageHeading from '../components/ui/PageHeading.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import BaseAlert from '../components/ui/BaseAlert.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import MoneyAmount from '../components/commerce/MoneyAmount.vue';
import CommerceStatus from '../components/commerce/CommerceStatus.vue';
import CommerceError from '../components/commerce/CommerceError.vue';
import ManualPaymentForm from '../components/commerce/ManualPaymentForm.vue';
import FinancialDocumentsPanel from '../components/commerce/FinancialDocumentsPanel.vue';
const props = defineProps({ id: { type: [String, Number], required: true } });
const { t, locale } = useI18n();
const route = useRoute();
const order = ref(null);
const loading = ref(false);
const error = ref(null);
const proofError = ref(null);
const downloading = ref(null);
let requestNumber = 0;
const canPay = computed(() => canSubmitPayment(order.value));
async function load() {
    const currentRequest = ++requestNumber;
    loading.value = true;
    error.value = null;
    try { const result = await fetchOrder(props.id); if (currentRequest === requestNumber) order.value = result; }
    catch (requestError) { if (currentRequest === requestNumber) { error.value = requestError; if ([401, 403, 404].includes(requestError.status)) order.value = null; } }
    finally { if (currentRequest === requestNumber) loading.value = false; }
}
async function submitted(payment) {
    order.value = { ...order.value, payments: [payment, ...(order.value.payments ?? [])] };
    await load();
}
async function download(id) {
    if (downloading.value !== null) return;
    downloading.value = id;
    proofError.value = null;
    try { await downloadPaymentProof(id); } catch (requestError) { proofError.value = requestError; } finally { downloading.value = null; }
}
onMounted(load);
watch(() => props.id, () => { order.value = null; proofError.value = null; load(); });
</script>
<template>
    <div class="space-y-7">
        <PageHeading class="[&_h1]:break-all" :title="order ? order.order_number : t('commerce.orderDetails')" :description="t('commerce.orderDescription')"><template #actions><BaseButton variant="secondary" :loading="loading" @click="load">{{ t('commerce.refreshOrder') }}</BaseButton></template></PageHeading>
        <CommerceError :error="error" /><CommerceError :error="proofError" />
        <LoadingState v-if="loading && !order" />
        <template v-if="order">
            <BaseAlert v-if="route.query.created === '1'">{{ t('commerce.created') }}</BaseAlert>
            <section class="flex flex-wrap items-center justify-between gap-5 rounded-3xl bg-brand-dark p-6 text-white sm:p-8"><div><CommerceStatus :status="order.status" /><p class="mt-4 max-w-2xl text-sm leading-7 text-white/80">{{ t(`commerce.accessStates.${accessState(order)}`) }}</p><p class="mt-2 text-xs text-white/55">{{ formatDate(order.placed_at ?? order.created_at, locale) }}</p></div><MoneyAmount :amount="order.total" :currency="order.currency" class="text-3xl font-black text-accent" /></section>
            <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div class="min-w-0 space-y-6">
                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="text-xl font-black text-slate-950">{{ t('commerce.orderItems') }}</h2><article v-for="item in order.items" :key="item.id" class="border-b border-slate-100 py-5 last:border-0"><div class="flex flex-wrap justify-between gap-3"><div class="min-w-0"><p class="text-xs font-bold text-brand">{{ t(`common.${item.purchasable_type}`) }}</p><h3 class="mt-1 break-words font-black text-slate-950">{{ item.title }}</h3><p class="mt-2 text-sm text-slate-500">{{ item.access_duration_days === null ? t('common.lifetime') : t('common.days', { count: item.access_duration_days }) }} · {{ t('commerce.quantity', { count: item.quantity }) }}</p></div><MoneyAmount :amount="item.total" :currency="order.currency" class="font-bold text-brand" /></div><dl class="mt-3 flex flex-wrap gap-x-6 gap-y-2 text-xs text-slate-500"><div>{{ t('commerce.unitPrice') }}: <MoneyAmount :amount="item.unit_price" :currency="order.currency" /></div><div>{{ t('commerce.discount') }}: <MoneyAmount :amount="item.discount_amount" :currency="order.currency" /></div></dl><ul v-if="item.package_courses?.length" class="mt-4 space-y-2 rounded-xl bg-slate-50 p-4 text-sm text-slate-600"><li v-for="course in item.package_courses" :key="course.id" class="break-words">{{ course.course_title }}</li></ul></article></section>
                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="text-xl font-black text-slate-950">{{ t('commerce.payments') }}</h2><p v-if="!order.payments?.length" class="mt-4 text-sm text-slate-500">{{ t('commerce.noPayment') }}</p><article v-for="payment in order.payments" :key="payment.id" class="mt-5 rounded-2xl border border-slate-200 p-4"><div class="flex flex-wrap justify-between gap-3"><CommerceStatus kind="payment" :status="payment.status" /><MoneyAmount :amount="payment.amount" :currency="payment.currency" class="font-bold" /></div><p class="mt-3 text-sm text-slate-600">{{ t(`commerce.methods.${payment.method}`) }} · {{ formatDate(payment.created_at, locale) }}</p><p v-if="payment.transaction_id" class="mt-2 break-all text-sm text-slate-500">{{ t('commerce.reference') }}: <bdi>{{ payment.transaction_id }}</bdi></p><p v-if="payment.rejection_reason" class="mt-3 rounded-xl bg-red-50 p-3 text-sm text-red-800">{{ payment.rejection_reason }}</p><p v-if="payment.approved_at" class="mt-2 text-xs text-slate-500">{{ t('commerce.approvedAt') }}: {{ formatDate(payment.approved_at, locale) }}</p><BaseButton v-if="payment.proof_available" variant="secondary" class="mt-4" :loading="downloading === payment.id" :disabled="downloading !== null" @click="download(payment.id)">{{ t('commerce.downloadProof') }}</BaseButton></article></section>
                    <ManualPaymentForm v-if="canPay" :key="order.id" :order="order" :refreshing="loading" @submitted="submitted" @refresh="load" />
                    <FinancialDocumentsPanel :order="order" @refresh="load" />
                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="text-xl font-black text-slate-950">{{ t('commerce.refundHistory') }}</h2><p v-if="order.coupon_code" class="mt-3 text-sm text-slate-600">{{ t('commerce.appliedCoupon') }}: <bdi>{{ order.coupon_code }}</bdi></p><dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2"><div><dt>{{ t('commerce.paidAmount') }}</dt><dd class="font-bold"><MoneyAmount :amount="order.refund_balance?.paid" :currency="order.currency" /></dd></div><div><dt>{{ t('commerce.refundedAmount') }}</dt><dd class="font-bold"><MoneyAmount :amount="order.refund_balance?.refunded" :currency="order.currency" /></dd></div></dl><p v-if="!order.refunds?.length" class="mt-4 text-sm text-slate-500">—</p><article v-for="refund in order.refunds ?? []" :key="refund.id" class="mt-4 rounded-xl border border-slate-200 p-4"><div class="flex flex-wrap justify-between gap-3"><strong><MoneyAmount :amount="refund.amount" :currency="refund.currency" /></strong><span>{{ t(`commerce.refund${refund.status[0].toUpperCase()}${refund.status.slice(1)}`) }}</span></div><p class="mt-2 text-sm text-slate-500">{{ refund.reason }}</p></article></section>
                </div>
                <aside class="min-w-0 space-y-5"><section class="rounded-3xl border border-slate-200 bg-white p-6"><h2 class="font-black text-slate-950">{{ t('commerce.summary') }}</h2><dl class="mt-5 space-y-4 text-sm"><div v-for="field in ['subtotal', 'discount_total', 'tax_total', 'total']" :key="field" class="flex flex-wrap justify-between gap-3"><dt class="text-slate-500">{{ t(`commerce.totals.${field}`) }}</dt><dd class="font-bold"><MoneyAmount :amount="order[field]" :currency="order.currency" /></dd></div></dl></section><section class="rounded-3xl border border-slate-200 bg-white p-6"><h2 class="font-black text-slate-950">{{ t('commerce.customerDetails') }}</h2><p class="mt-4 break-words text-sm text-slate-700">{{ order.customer_name }}</p><p class="mt-2 break-all text-sm text-slate-500"><bdi>{{ order.customer_email }}</bdi></p><p class="mt-2 text-sm text-slate-500"><bdi>{{ order.customer_phone }}</bdi></p><p v-if="order.notes" class="mt-4 whitespace-pre-line break-words text-sm text-slate-600">{{ order.notes }}</p></section><RouterLink :to="{ name: 'student.orders.index' }" class="inline-flex text-sm font-bold text-brand">{{ t('commerce.myOrders') }}</RouterLink></aside>
            </div>
        </template>
    </div>
</template>
