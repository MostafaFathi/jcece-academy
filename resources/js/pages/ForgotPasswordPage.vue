<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { requestPasswordReset } from '../api/auth';
import BaseAlert from '../components/ui/BaseAlert.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseInput from '../components/ui/BaseInput.vue';

const { t } = useI18n();
const email = ref('');
const error = ref('');
const loading = ref(false);
const success = ref(false);

async function submit() {
    if (loading.value || success.value) return;
    error.value = '';
    if (!email.value.trim()) { error.value = t('auth.emailRequired'); return; }
    loading.value = true;
    try { await requestPasswordReset(email.value.trim()); success.value = true; }
    catch (failure) { error.value = failure.errors?.email?.[0] ?? (failure.code === 'network' ? t('errors.network') : t('errors.generic')); }
    finally { loading.value = false; }
}
</script>

<template>
    <div class="space-y-6">
        <div><h1 class="text-3xl font-black text-slate-950">{{ t('auth.forgotTitle') }}</h1><p class="mt-2 leading-7 text-slate-600">{{ t('auth.forgotSubtitle') }}</p></div>
        <BaseAlert v-if="success">{{ t('auth.forgotSuccess') }}</BaseAlert>
        <form v-else class="space-y-5" novalidate @submit.prevent="submit"><BaseInput id="forgot-email" v-model="email" :label="t('auth.email')" :error="error" type="email" autocomplete="email" required /><BaseButton type="submit" :loading="loading" class="w-full">{{ t('auth.forgotSubmit') }}</BaseButton></form>
        <RouterLink :to="{ name: 'login' }" class="block text-center text-sm font-bold text-brand">{{ t('common.login') }}</RouterLink>
    </div>
</template>
