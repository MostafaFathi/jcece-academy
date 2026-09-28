<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../../stores/auth';
import { useCartStore } from '../../stores/cart';
import BaseButton from '../ui/BaseButton.vue';
import CommerceError from './CommerceError.vue';
const props = defineProps({ type: { type: String, required: true }, product: { type: Object, required: true } });
const auth = useAuthStore();
const cart = useCartStore();
const router = useRouter();
const { t } = useI18n();
const busy = ref(false);
const error = ref(null);
const added = ref(false);
async function add() {
    if (busy.value || cart.mutating || cart.checkoutLoading) return;
    if (!auth.isAuthenticated) {
        return router.push({ name: 'login', query: { redirect: router.resolve({ name: props.type === 'course' ? 'courses.show' : 'packages.show', params: { slug: props.product.slug } }).fullPath } });
    }
    busy.value = true;
    error.value = null;
    try {
        if (!cart.cart) await cart.load();
        added.value = Boolean(await cart.add(props.type, props.product.id));
    } catch (requestError) { error.value = requestError; } finally { busy.value = false; }
}
</script>
<template><div class="space-y-3"><BaseButton class="w-full" :loading="busy" :disabled="cart.mutating || cart.checkoutLoading || cart.uncertain || (auth.isAuthenticated && cart.hasItem(type, product.id))" @click="add">{{ auth.isAuthenticated && cart.hasItem(type, product.id) ? t('commerce.inCart') : t('commerce.addToCart') }}</BaseButton><CommerceError :error="error" /><p v-if="added" role="status" class="text-sm text-emerald-800">{{ t('commerce.added') }}</p><RouterLink v-if="auth.isAuthenticated && (added || cart.uncertain || cart.hasItem(type, product.id))" :to="{ name: 'student.cart' }" class="inline-flex text-sm font-bold text-brand underline">{{ t('commerce.viewCart') }}</RouterLink></div></template>
