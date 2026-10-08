<script setup>
import { reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { register } from '../api/auth';
import { useAuthStore } from '../stores/auth';
import { useCartStore } from '../stores/cart';
import { homeRouteFor } from '../router/access';
import BaseAlert from '../components/ui/BaseAlert.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseInput from '../components/ui/BaseInput.vue';
import GoogleAuthLink from '../components/auth/GoogleAuthLink.vue';

const { t, locale } = useI18n();
const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const cart = useCartStore();
const form = reactive({ name: '', email: '', password: '', password_confirmation: '' });
const errors = ref({});
const failure = ref(false);
const loading = ref(false);
const success = ref(false);

async function submit() {
    if (loading.value || success.value) return;
    errors.value = {};
    failure.value = false;
    if (!form.name.trim()) errors.value.name = t('auth.nameRequired');
    if (!form.email.trim()) errors.value.email = t('auth.emailRequired');
    if (!form.password) errors.value.password = t('auth.passwordRequired');
    if (!form.password_confirmation) errors.value.password_confirmation = t('auth.confirmRequired');
    else if (form.password !== form.password_confirmation) errors.value.password_confirmation = t('auth.passwordMismatch');
    if (Object.keys(errors.value).length) return;

    loading.value = true;
    let user;
    try {
        user = await register({ ...form, name: form.name.trim(), email: form.email.trim(), locale: locale.value });
    } catch (error) {
        errors.value = Object.fromEntries(Object.entries(error.errors ?? {}).map(([key, messages]) => [key, messages[0]]));
        failure.value = true;
        loading.value = false;
        return;
    }
    auth.setUser(user);
    auth.initialized = true;
    success.value = true;
    loading.value = false;
    const cartDestination = cart.guestCount || route.query.redirect === '/student/cart';
    await router.push(cartDestination ? { name: 'student.cart', query: { welcome: 'registered' } } : homeRouteFor(auth));
}
</script>

<template>
    <div class="space-y-6">
        <div><p class="mb-2 text-xs font-black tracking-widest text-brand uppercase">JCEC ACADEMY</p><h1 class="text-3xl font-black text-slate-950">{{ t('auth.registerTitle') }}</h1><p class="mt-2 leading-7 text-slate-600">{{ t('auth.registerSubtitle') }}</p></div>
        <BaseAlert v-if="success" tone="success">{{ t('auth.registerSuccess') }} <RouterLink :to="{ name: 'student.cart' }" class="font-bold underline">{{ t('commerce.viewCart') }}</RouterLink></BaseAlert>
        <BaseAlert v-else-if="failure" tone="danger">{{ t('errors.generic') }}</BaseAlert>
        <form v-if="!success" class="space-y-4" novalidate @submit.prevent="submit">
            <BaseInput id="register-name" v-model="form.name" :label="t('auth.name')" :error="errors.name" autocomplete="name" maxlength="255" required />
            <BaseInput id="register-email" v-model="form.email" :label="t('auth.email')" :error="errors.email" type="email" autocomplete="email" maxlength="255" required />
            <BaseInput id="register-password" v-model="form.password" :label="t('auth.password')" :error="errors.password" type="password" autocomplete="new-password" required />
            <BaseInput id="register-confirm" v-model="form.password_confirmation" :label="t('auth.confirmPassword')" :error="errors.password_confirmation" type="password" autocomplete="new-password" required />
            <BaseButton type="submit" :loading="loading" class="w-full">{{ t('auth.registerSubmit') }}</BaseButton>
        </form>
        <GoogleAuthLink v-if="!success" :redirect="cart.guestCount || route.query.redirect === '/student/cart' ? '/student/cart' : ''" />
        <RouterLink :to="{ name: 'login', query: cart.guestCount || route.query.redirect === '/student/cart' ? { redirect: '/student/cart' } : {} }" class="block text-center text-sm font-bold text-brand">{{ t('auth.alreadyHaveAccount') }}</RouterLink>
    </div>
</template>
