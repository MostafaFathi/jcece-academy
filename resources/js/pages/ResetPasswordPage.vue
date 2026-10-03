<script setup>
import { reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { resetPassword } from '../api/auth';
import BaseAlert from '../components/ui/BaseAlert.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseInput from '../components/ui/BaseInput.vue';

const props = defineProps({ token: { type: String, required: true } });
const route = useRoute();
const { t } = useI18n();
const form = reactive({ email: typeof route.query.email === 'string' ? route.query.email : '', password: '', password_confirmation: '' });
const errors = ref({});
const failure = ref(false);
const loading = ref(false);
const success = ref(false);

async function submit() {
    if (loading.value || success.value) return;
    errors.value = {};
    failure.value = false;
    if (!form.email.trim()) errors.value.email = t('auth.emailRequired');
    if (!form.password) errors.value.password = t('auth.passwordRequired');
    if (form.password !== form.password_confirmation) errors.value.password_confirmation = t('auth.passwordMismatch');
    if (Object.keys(errors.value).length) return;
    loading.value = true;
    try { await resetPassword({ ...form, email: form.email.trim(), token: props.token }); success.value = true; }
    catch (error) { errors.value = Object.fromEntries(Object.entries(error.errors ?? {}).map(([key, messages]) => [key, messages[0]])); failure.value = true; }
    finally { loading.value = false; }
}
</script>

<template>
    <div class="space-y-6">
        <div><h1 class="text-3xl font-black text-slate-950">{{ t('auth.resetTitle') }}</h1><p class="mt-2 leading-7 text-slate-600">{{ t('auth.resetSubtitle') }}</p></div>
        <BaseAlert v-if="success">{{ t('auth.resetSuccess') }} <RouterLink :to="{ name: 'login' }" class="font-bold underline">{{ t('common.login') }}</RouterLink></BaseAlert>
        <BaseAlert v-else-if="failure" tone="danger">{{ errors.token ? t('auth.resetInvalid') : t('errors.generic') }} <RouterLink v-if="errors.token" :to="{ name: 'forgot-password' }" class="font-bold underline">{{ t('auth.requestAgain') }}</RouterLink></BaseAlert>
        <form v-if="!success" class="space-y-4" novalidate @submit.prevent="submit">
            <BaseInput id="reset-email" v-model="form.email" :label="t('auth.email')" :error="errors.email" type="email" autocomplete="email" maxlength="255" required />
            <BaseInput id="reset-password" v-model="form.password" :label="t('auth.password')" :error="errors.password" type="password" autocomplete="new-password" required />
            <BaseInput id="reset-confirm" v-model="form.password_confirmation" :label="t('auth.confirmPassword')" :error="errors.password_confirmation" type="password" autocomplete="new-password" required />
            <BaseButton type="submit" :loading="loading" class="w-full">{{ t('auth.resetSubmit') }}</BaseButton>
        </form>
    </div>
</template>
