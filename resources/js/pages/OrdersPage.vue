<script setup>
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { fetchOrders } from '../api/commerce';
import { formatDate } from '../utils/catalog';
import PageHeading from '../components/ui/PageHeading.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import PaginationNav from '../components/ui/PaginationNav.vue';
import MoneyAmount from '../components/commerce/MoneyAmount.vue';
import CommerceStatus from '../components/commerce/CommerceStatus.vue';
import CommerceError from '../components/commerce/CommerceError.vue';
const { t, locale } = useI18n();
const orders = ref([]);
const meta = ref(null);
const loading = ref(true);
const error = ref(null);
async function load(page = 1) {
    if (loading.value && orders.value.length) return;
    loading.value = true;
    error.value = null;
    orders.value = [];
    try { const result = await fetchOrders(page); orders.value = result.items; meta.value = result.meta; }
    catch (requestError) { error.value = requestError; }
    finally { loading.value = false; }
}
onMounted(() => load());
</script>
<template><div class="space-y-7"><PageHeading :title="t('commerce.myOrders')" :description="t('commerce.ordersDescription')" /><CommerceError :error="error"><BaseButton variant="secondary" class="mt-3" @click="load()">{{ t('common.retry') }}</BaseButton></CommerceError><LoadingState v-if="loading" /><EmptyState v-else-if="!error && !orders.length" :title="t('commerce.emptyOrders')" /><div v-else class="grid gap-4 lg:grid-cols-2"><article v-for="order in orders" :key="order.id" class="min-w-0 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><div class="flex flex-wrap items-center justify-between gap-3"><h2 class="break-all text-lg font-black text-slate-950"><bdi>{{ order.order_number }}</bdi></h2><CommerceStatus :status="order.status" /></div><p class="mt-3 text-sm text-slate-500">{{ formatDate(order.created_at, locale) }}</p><p v-if="order.financial_documents?.length" class="mt-3 text-xs font-bold text-brand">{{ t('commerce.receiptAvailable') }} · {{ order.financial_documents.length }}</p><div class="mt-6 flex flex-wrap items-center justify-between gap-4 border-t border-slate-100 pt-5"><MoneyAmount :amount="order.total" :currency="order.currency" class="text-xl font-black text-brand" /><RouterLink :to="{ name: 'student.orders.show', params: { id: order.id } }" class="rounded-xl bg-brand-soft px-4 py-3 text-sm font-bold text-brand">{{ t('commerce.viewOrder') }}</RouterLink></div></article></div><PaginationNav v-if="!loading && !error && meta?.last_page > 1" :current-page="meta.current_page" :last-page="meta.last_page" @change="load" /></div></template>
