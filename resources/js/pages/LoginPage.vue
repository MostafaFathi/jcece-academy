<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';
import { homeRouteFor } from '../router/access';
import BaseAlert from '../components/ui/BaseAlert.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseInput from '../components/ui/BaseInput.vue';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const form = reactive({ email: '', password: '', remember: false });
const fieldErrors = ref({});
const errorMessage = ref('');

function validate() {
    const errors = {};

    if (!form.email.trim()) {
        errors.email = t('auth.emailRequired');
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
        errors.email = t('auth.emailInvalid');
    }

    if (!form.password) {
        errors.password = t('auth.passwordRequired');
    }

    fieldErrors.value = errors;

    return Object.keys(errors).length === 0;
}

function localizedError(error) {
    if (error.code === 'network') return t('errors.network');
    if (error.code === 'server') return t('errors.server');

    return error.status === 422 ? t('auth.invalid') : (error.message || t('errors.generic'));
}

async function submit() {
    fieldErrors.value = {};
    errorMessage.value = '';

    if (!validate()) {
        return;
    }

    try {
        await auth.signIn(form);
        const redirect = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/') && !route.query.redirect.startsWith('//') ? route.query.redirect : null;
        await router.push(redirect || homeRouteFor(auth));
    } catch (error) {
        fieldErrors.value = Object.fromEntries(Object.entries(error.errors ?? {}).map(([key, messages]) => [key, messages[0]]));
        errorMessage.value = localizedError(error);
    }
}
</script>

<template>
    <div>
        <div class="mb-8 text-center lg:text-start"><img :src="'/assets/images/logo-1.png'" alt="JCEC Academy" class="mx-auto mb-5 size-24 object-contain lg:hidden"><p class="mb-3 text-xs font-black tracking-[0.18em] text-brand uppercase">JCEC ACADEMY</p><h1 class="text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">{{ t('auth.title') }}</h1><p class="mt-3 leading-7 text-slate-600">{{ t('auth.subtitle') }}</p></div>
        <BaseAlert v-if="errorMessage" tone="danger" class="mb-5">{{ errorMessage }}</BaseAlert>
        <form class="space-y-5" novalidate @submit.prevent="submit"><BaseInput id="email" v-model="form.email" type="email" autocomplete="email" :label="t('auth.email')" :error="fieldErrors.email" required /><BaseInput id="password" v-model="form.password" type="password" autocomplete="current-password" :label="t('auth.password')" :error="fieldErrors.password" required /><div class="flex flex-wrap items-center justify-between gap-3"><label class="flex cursor-pointer items-center gap-3 text-sm font-semibold text-slate-700"><input v-model="form.remember" type="checkbox" class="size-4 rounded accent-brand">{{ t('auth.remember') }}</label><span class="text-xs text-slate-400">{{ t('auth.secure') }}</span></div><BaseButton type="submit" :loading="auth.loginLoading" class="w-full">{{ t('auth.submit') }}</BaseButton></form>
        <div class="mt-6 flex flex-wrap justify-between gap-3 text-sm font-bold text-brand"><RouterLink :to="{ name: 'forgot-password' }">{{ t('auth.forgotLink') }}</RouterLink><RouterLink :to="{ name: 'register' }">{{ t('auth.registerLink') }}</RouterLink></div>
    </div>
</template>
