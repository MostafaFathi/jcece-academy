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

async function submit() {
    fieldErrors.value = {};
    errorMessage.value = '';

    try {
        await auth.signIn(form);
        const redirect = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/') ? route.query.redirect : null;
        await router.push(redirect || homeRouteFor(auth));
    } catch (error) {
        fieldErrors.value = Object.fromEntries(Object.entries(error.errors ?? {}).map(([key, messages]) => [key, messages[0]]));
        errorMessage.value = error.message || t('auth.invalid');
    }
}
</script>

<template>
    <div><div class="mb-8 text-center lg:text-start"><img :src="'/assets/images/logo-1.png'" alt="JCEC Academy" class="mx-auto mb-5 size-24 object-contain lg:hidden"><h1 class="text-3xl font-black text-slate-950">{{ t('auth.title') }}</h1><p class="mt-2 text-slate-600">{{ t('auth.subtitle') }}</p></div><BaseAlert v-if="errorMessage" tone="danger" class="mb-5">{{ errorMessage }}</BaseAlert><form class="space-y-5" novalidate @submit.prevent="submit"><BaseInput id="email" v-model="form.email" type="email" autocomplete="email" :label="t('auth.email')" :error="fieldErrors.email" required /><BaseInput id="password" v-model="form.password" type="password" autocomplete="current-password" :label="t('auth.password')" :error="fieldErrors.password" required /><label class="flex cursor-pointer items-center gap-3 text-sm font-semibold text-slate-700"><input v-model="form.remember" type="checkbox" class="size-4 rounded accent-brand">{{ t('auth.remember') }}</label><BaseButton type="submit" :loading="auth.loginLoading" class="w-full">{{ t('common.login') }}</BaseButton></form></div>
</template>
