<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';
import { useCartStore } from '../stores/cart';
import PageHeading from '../components/ui/PageHeading.vue';
import BaseInput from '../components/ui/BaseInput.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseAlert from '../components/ui/BaseAlert.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import CommerceError from '../components/commerce/CommerceError.vue';
import CartSummary from '../components/commerce/CartSummary.vue';
const auth = useAuthStore();
const cart = useCartStore();
const router = useRouter();
const { t } = useI18n();
const form = reactive({ customer_name: auth.user?.name ?? '', customer_email: auth.user?.email ?? '', customer_phone: auth.user?.phone ?? '', notes: '' });
const fields = ref({});
const navigationError = ref(null);
const createdOrder = ref(null);
watch(() => cart.uncertain && cart.intentCustomer, (customer) => {
    if (customer) {
        for (const key of Object.keys(form)) form[key] = customer[key];
    }
}, { immediate: true });
async function load() { try { await cart.load(); } catch {} }
async function submit() {
    if (cart.checkoutLoading || cart.loading || cart.mutating || createdOrder.value) return;
    fields.value = {};
    for (const key of ['customer_name', 'customer_email', 'customer_phone']) {
        if (!form[key].trim()) fields.value[key] = t('commerce.required');
    }
    if (form.customer_email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.customer_email)) fields.value.customer_email = t('auth.emailInvalid');
    if (Object.keys(fields.value).length || (!cart.ready && !cart.uncertain)) return;
    try {
        const order = await cart.placeOrder({ ...form });
        if (order) {
            createdOrder.value = order;
            await router.push({ name: 'student.orders.show', params: { id: order.id }, query: { created: '1' } });
        }
    } catch (error) {
        if (createdOrder.value) navigationError.value = error;
        fields.value = Object.fromEntries(Object.entries(error.errors ?? {}).map(([key, messages]) => [key, messages[0]]));
    }
}
async function startNew() { cart.newIntent(); await load(); }
onMounted(load);
</script>
<template>
    <div class="space-y-7">
        <PageHeading :title="t('commerce.checkout')" :description="t('commerce.checkoutDescription')" />
        <CommerceError :error="cart.error || navigationError"><BaseButton v-if="!cart.uncertain" variant="secondary" class="mt-3" @click="load">{{ t('common.retry') }}</BaseButton></CommerceError>
        <BaseAlert v-if="createdOrder">{{ t('commerce.created') }} <RouterLink :to="{ name: 'student.orders.show', params: { id: createdOrder.id } }" class="font-bold underline">{{ t('commerce.viewOrder') }}</RouterLink></BaseAlert>
        <BaseAlert v-if="cart.uncertain"><p>{{ t('commerce.uncertain') }}</p><p class="mt-2">{{ t('commerce.newIntentWarning') }}</p><RouterLink :to="{ name: 'student.orders.index' }" class="me-4 inline-flex py-3 font-bold underline">{{ t('commerce.myOrders') }}</RouterLink><BaseButton variant="secondary" :disabled="cart.checkoutLoading || (cart.uncertain && Boolean(cart.intentCustomer))" @click="startNew">{{ t('commerce.startNew') }}</BaseButton></BaseAlert>
        <LoadingState v-if="cart.loading" />
        <div v-else-if="cart.ready || cart.uncertain" class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
            <form class="min-w-0 space-y-5 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8" @submit.prevent="submit">
                <h2 class="text-xl font-black text-slate-950">{{ t('commerce.customerDetails') }}</h2>
                <BaseInput id="customer_name" v-model="form.customer_name" :label="t('commerce.customerName')" :error="fields.customer_name" autocomplete="name" maxlength="255" required :disabled="cart.checkoutLoading || (cart.uncertain && Boolean(cart.intentCustomer))" />
                <BaseInput id="customer_email" v-model="form.customer_email" :label="t('common.email')" :error="fields.customer_email" type="email" autocomplete="email" maxlength="255" required :disabled="cart.checkoutLoading || (cart.uncertain && Boolean(cart.intentCustomer))" />
                <BaseInput id="customer_phone" v-model="form.customer_phone" :label="t('commerce.customerPhone')" :error="fields.customer_phone" type="tel" autocomplete="tel" maxlength="50" required :disabled="cart.checkoutLoading || (cart.uncertain && Boolean(cart.intentCustomer))" />
                <div><label for="checkout-notes" class="mb-2 block text-sm font-bold text-slate-700">{{ t('commerce.notes') }} ({{ t('common.optional') }})</label><textarea id="checkout-notes" v-model="form.notes" maxlength="2000" rows="3" :disabled="cart.checkoutLoading || (cart.uncertain && Boolean(cart.intentCustomer))" class="w-full rounded-xl border border-slate-300 p-3 focus:border-brand focus:outline-brand" /><p v-if="fields.notes" class="text-sm text-red-700">{{ fields.notes }}</p></div>
                <p class="rounded-xl bg-slate-50 p-4 text-sm leading-7 text-slate-600">{{ t('commerce.noAccessYet') }}</p>
                <BaseButton type="submit" :loading="cart.checkoutLoading" :disabled="Boolean(createdOrder)" class="w-full">{{ cart.uncertain ? t('commerce.retrySame') : t('commerce.confirmOrder') }}</BaseButton>
                <RouterLink :to="{ name: 'student.cart' }" class="block text-center text-sm font-bold text-brand">{{ t('commerce.backToCart') }}</RouterLink>
            </form>
            <CartSummary v-if="cart.cart" /><p v-else class="rounded-3xl bg-white p-6 text-sm text-slate-600">{{ t('commerce.retrySnapshot') }}</p>
        </div>
        <BaseAlert v-else-if="cart.cart">{{ t('commerce.cartNeedsAttention') }} <RouterLink :to="{ name: 'student.cart' }" class="font-bold underline">{{ t('commerce.backToCart') }}</RouterLink></BaseAlert>
    </div>
</template>
