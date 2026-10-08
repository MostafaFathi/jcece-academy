<script setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../../stores/auth';
import { useCartStore } from '../../stores/cart';
import BaseButton from '../ui/BaseButton.vue';
import CommerceError from './CommerceError.vue';
const props = defineProps({ type: { type: String, required: true }, product: { type: Object, required: true } });
const auth = useAuthStore();
const cart = useCartStore();
const { t } = useI18n();
const busy = ref(false);
const error = ref(null);
const added = ref(false);
const guestError = ref(false);
const toastVisible = ref(false);
const checkoutDestination = computed(() => {
    if (!auth.isAuthenticated) return { name: 'login', query: { redirect: '/student/cart' } };
    return { name: cart.ready ? 'student.checkout' : 'student.cart' };
});
async function add() {
    if (busy.value || cart.mutating || cart.checkoutLoading) return;
    added.value = false;
    guestError.value = false;
    if (!auth.isAuthenticated) {
        added.value = cart.addGuest(props.type, props.product.id);
        guestError.value = !added.value;
        toastVisible.value = added.value;
        return;
    }
    busy.value = true;
    error.value = null;
    try {
        if (!cart.cart) await cart.load();
        added.value = Boolean(await cart.add(props.type, props.product.id));
        toastVisible.value = added.value;
    } catch (requestError) { error.value = requestError; } finally { busy.value = false; }
}
</script>
<template>
    <div class="space-y-3">
        <BaseButton class="w-full" :loading="busy" :disabled="cart.mutating || cart.checkoutLoading || cart.uncertain || (auth.isAuthenticated ? cart.hasItem(type, product.id) : cart.hasGuestItem(type, product.id))" @click="add">{{ (auth.isAuthenticated ? cart.hasItem(type, product.id) : cart.hasGuestItem(type, product.id)) ? t('commerce.inCart') : t('commerce.addToCart') }}</BaseButton>
        <CommerceError :error="error" />
        <p v-if="guestError" role="alert" class="text-sm font-semibold text-red-700">{{ t('commerce.guestSaveFailed') }}</p>
        <p v-if="added" class="text-sm text-emerald-800">{{ t(auth.isAuthenticated ? 'commerce.added' : 'commerce.guestAdded') }}</p>
        <RouterLink v-if="added || cart.uncertain || (auth.isAuthenticated ? cart.hasItem(type, product.id) : cart.hasGuestItem(type, product.id))" :to="auth.isAuthenticated ? { name: 'student.cart' } : { name: 'login', query: { redirect: '/student/cart' } }" class="inline-flex text-sm font-bold text-brand underline">{{ t('commerce.viewCart') }}</RouterLink>
        <Teleport to="body">
            <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="-translate-y-3 opacity-0" leave-active-class="transition duration-150 ease-in" leave-to-class="-translate-y-3 opacity-0">
                <div v-if="toastVisible" class="fixed start-4 end-4 top-4 z-[100] rounded-2xl border border-emerald-200 bg-white p-4 text-slate-900 shadow-2xl shadow-slate-950/20 sm:start-auto sm:end-6 sm:w-96">
                    <div class="flex items-start gap-3">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-emerald-100 font-black text-emerald-700" aria-hidden="true">✓</span>
                        <div class="min-w-0 flex-1" role="status" aria-live="polite">
                            <p class="font-black text-slate-950">{{ t('commerce.addedToastTitle') }}</p>
                            <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ product.title }}</p>
                            <p v-if="!auth.isAuthenticated" class="mt-1 text-xs text-slate-500">{{ t('commerce.guestAdded') }}</p>
                        </div>
                        <button type="button" class="rounded-lg p-1 text-xl leading-none text-slate-500 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-brand" :aria-label="t('common.close')" @click="toastVisible = false">×</button>
                    </div>
                    <RouterLink :to="checkoutDestination" class="mt-4 flex min-h-11 items-center justify-center rounded-xl bg-brand px-4 py-2 text-sm font-black text-white transition hover:bg-brand-dark focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-brand" @click="toastVisible = false">{{ cart.ready || !auth.isAuthenticated ? t('commerce.checkout') : t('commerce.viewCart') }}</RouterLink>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
