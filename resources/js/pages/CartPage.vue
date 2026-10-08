<script setup>
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useCartStore } from '../stores/cart';
import PageHeading from '../components/ui/PageHeading.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseAlert from '../components/ui/BaseAlert.vue';
import MediaFrame from '../components/public/MediaFrame.vue';
import MoneyAmount from '../components/commerce/MoneyAmount.vue';
import CommerceError from '../components/commerce/CommerceError.vue';
import CartSummary from '../components/commerce/CartSummary.vue';
const cart = useCartStore();
const { t } = useI18n();
const couponCode = ref('');
async function load() { try { await cart.load(); } catch {} }
async function remove(id) { try { await cart.remove(id); } catch {} }
async function clear() { try { await cart.clear(); } catch {} }
async function applyCoupon() { try { await cart.applyCoupon(couponCode.value); couponCode.value = ''; } catch {} }
async function removeCoupon() { try { await cart.removeCoupon(); } catch {} }
onMounted(load);
</script>
<template>
    <div class="space-y-7">
        <PageHeading :title="t('commerce.cart')" :description="t('commerce.cartDescription')"><template #actions><RouterLink :to="{ name: 'courses.index' }" class="text-sm font-bold text-brand">{{ t('commerce.continueShopping') }}</RouterLink></template></PageHeading>
        <CommerceError :error="cart.error"><BaseButton variant="secondary" class="mt-3" @click="load">{{ t('common.retry') }}</BaseButton></CommerceError>
        <BaseAlert v-if="cart.uncertain">{{ t('commerce.uncertain') }} <RouterLink :to="{ name: 'student.checkout' }" class="font-bold underline">{{ t('commerce.resumeCheckout') }}</RouterLink></BaseAlert>
        <LoadingState v-if="cart.loading" />
        <EmptyState v-else-if="cart.cart && !cart.count" :title="t('commerce.emptyCart')" :description="t('commerce.emptyCartText')" />
        <div v-else-if="cart.cart" class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
            <div class="min-w-0 space-y-4">
                <div class="flex items-center justify-between gap-4"><h2 class="font-bold text-slate-600">{{ t('commerce.itemCount', { count: cart.count }) }}</h2><BaseButton variant="secondary" :loading="cart.mutating" :disabled="cart.uncertain" @click="clear">{{ t('commerce.clearCart') }}</BaseButton></div>
                <article v-for="item in cart.items" :key="item.id" class="flex min-w-0 flex-col gap-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center">
                    <MediaFrame :src="item.product?.thumbnail" :alt="item.product?.title ?? ''" class="w-full shrink-0 sm:w-28" />
                    <div class="min-w-0 flex-1"><p class="text-xs font-bold text-brand">{{ t(`common.${item.purchasable_type}`) }}</p><h3 class="mt-2 break-words text-lg font-black text-slate-950">{{ item.product?.title ?? t('common.unavailable') }}</h3><p v-if="item.product" class="mt-2 text-sm text-slate-500">{{ item.product.access_duration_days === null ? t('common.lifetime') : t('common.days', { count: item.product.access_duration_days }) }}</p><p v-if="!item.available" class="mt-2 text-sm font-bold text-red-700">{{ t('commerce.unavailableItem') }}</p></div>
                    <div class="flex shrink-0 items-center justify-between gap-4 sm:flex-col sm:items-end"><MoneyAmount v-if="item.product" :amount="item.product.price" :currency="cart.currency" class="font-black text-brand" /><BaseButton variant="secondary" :disabled="cart.mutating || cart.uncertain" :aria-label="t('commerce.removeItem', { title: item.product?.title ?? '' })" @click="remove(item.id)">{{ t('commerce.remove') }}</BaseButton></div>
                </article>
            </div>
            <CartSummary><form class="mb-5 space-y-2" @submit.prevent="applyCoupon"><label for="cart-coupon" class="text-sm font-bold">{{ t('commerce.couponCode') }}</label><div class="flex gap-2"><input id="cart-coupon" v-model="couponCode" maxlength="64" :disabled="cart.mutating || cart.uncertain" class="min-w-0 flex-1 rounded-xl border border-white/30 bg-white px-3 py-2 text-slate-950"><BaseButton type="submit" :disabled="!couponCode.trim() || cart.mutating || cart.uncertain">{{ t('commerce.applyCoupon') }}</BaseButton></div><button v-if="cart.cart?.coupon_code" type="button" class="text-sm font-bold text-accent underline" :disabled="cart.mutating || cart.uncertain" @click="removeCoupon">{{ t('commerce.removeCoupon') }}</button></form><RouterLink v-if="cart.ready && !cart.mutating && !cart.uncertain" :to="{ name: 'student.checkout' }" class="flex min-h-12 items-center justify-center rounded-xl bg-accent px-4 font-black text-brand-dark">{{ t('commerce.checkout') }}</RouterLink><p v-else class="text-sm text-accent">{{ t('commerce.cartNeedsAttention') }}</p></CartSummary>
        </div>
    </div>
</template>
