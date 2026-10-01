<script setup>
import { reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';
import { fetchOperationalOrders } from '../api/admin-operations';
import { formatAmount, formatDate } from '../utils/catalog';
import PageHeading from '../components/ui/PageHeading.vue';
import BaseAlert from '../components/ui/BaseAlert.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import PaginationNav from '../components/ui/PaginationNav.vue';
import OperationStatusBadge from '../components/admin/OperationStatusBadge.vue';

const route = useRoute(); const router = useRouter(); const auth = useAuthStore(); const { t, locale } = useI18n();
const supportArea = route.path.startsWith('/sales-support');
const listName = supportArea ? 'support.orders' : 'admin.orders.index';
const detailName = supportArea ? 'support.orders.show' : 'admin.orders.show';
const paymentName = supportArea ? 'support.payments.index' : 'admin.payments.index';
const filters = reactive({ search: '', status: '' }); const items = ref([]); const meta = ref(null); const loading = ref(true); const error = ref(null); let sequence = 0;
const statuses = ['pending', 'awaiting_payment', 'paid', 'completed', 'cancelled', 'refunded'];
async function load() {
    const current = ++sequence; loading.value = true; error.value = null;
    filters.search = String(route.query.search ?? ''); filters.status = String(route.query.status ?? '');
    try { const result = await fetchOperationalOrders({ page: Number(route.query.page) || 1, ...(filters.search ? { search: filters.search } : {}), ...(filters.status ? { status: filters.status } : {}) }); if (current === sequence) { items.value = result.items; meta.value = result.meta; } }
    catch (failure) { if (current === sequence) error.value = failure; }
    finally { if (current === sequence) loading.value = false; }
}
watch(() => route.query, load, { immediate: true, deep: true });
function apply() { router.push({ name: listName, query: Object.fromEntries(Object.entries(filters).filter(([, value]) => value)) }); }
function page(value) { router.push({ name: listName, query: { ...route.query, page: value } }); }
</script>
<template>
    <div class="space-y-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'"><PageHeading :title="t('operations.orders')"><template #actions><RouterLink v-if="auth.can('payments.view')" :to="{ name: paymentName }" class="inline-flex min-h-11 items-center rounded-xl border border-brand px-4 py-2 font-bold text-brand">{{ t('operations.payments') }}</RouterLink></template></PageHeading>
        <form class="flex flex-wrap gap-3 rounded-3xl border border-slate-200 bg-white p-4" @submit.prevent="apply"><label class="sr-only" for="order-search">{{ t('operations.search') }}</label><input id="order-search" v-model="filters.search" type="search" :placeholder="t('operations.search')" class="min-h-11 min-w-52 flex-1 rounded-xl border border-slate-300 px-4"><label class="sr-only" for="order-status">{{ t('operations.status') }}</label><select id="order-status" v-model="filters.status" class="min-h-11 rounded-xl border border-slate-300 px-4"><option value="">{{ t('operations.all') }}</option><option v-for="status in statuses" :key="status" :value="status">{{ t(`operations.orderStatus.${status}`) }}</option></select><button type="submit" class="min-h-11 rounded-xl bg-brand px-5 font-bold text-white">{{ t('operations.filter') }}</button></form>
        <LoadingState v-if="loading" /><BaseAlert v-else-if="error" tone="danger">{{ t('operations.loadError') }} <button type="button" class="font-bold underline" @click="load">{{ t('operations.retry') }}</button></BaseAlert><EmptyState v-else-if="!items.length" :title="t('operations.empty')" />
        <div v-else class="grid gap-3"><RouterLink v-for="item in items" :key="item.id" :to="{ name: detailName, params: { id: item.id } }" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:border-brand md:grid-cols-[1fr_1fr_auto_auto] md:items-center"><div><p class="font-black text-slate-950"><bdi>{{ item.order_number }}</bdi></p><p class="text-sm text-slate-600">{{ item.customer_name }} · {{ item.customer_email }}</p></div><p class="text-sm text-slate-500">{{ formatDate(item.placed_at ?? item.created_at, locale) }}</p><OperationStatusBadge group="orderStatus" :status="item.status" /><bdi class="font-black">{{ formatAmount(item.total, locale) }} {{ item.currency }}</bdi></RouterLink></div>
        <PaginationNav v-if="!loading && !error && meta?.last_page > 1" :current-page="meta.current_page" :last-page="meta.last_page" @change="page" />
    </div>
</template>
