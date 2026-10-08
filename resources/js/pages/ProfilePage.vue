<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { api } from '../api/client';
import { changePassword, fetchProfile, removeAvatar, updateProfile, uploadAvatar } from '../api/profile';
import { setLocale } from '../i18n';
import { useAuthStore } from '../stores/auth';
import { publicMediaUrl } from '../utils/catalog';
import { countryOptions } from '../utils/countries';
import BaseAlert from '../components/ui/BaseAlert.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import { usePageMeta } from '../composables/usePageMeta';

const { t, locale } = useI18n();
const auth = useAuthStore();
const form = reactive({ name: '', phone: '', country: '', city: '', specialization: '' });
const password = reactive({ current_password: '', password: '', password_confirmation: '' });
const loading = ref(true);
const busy = ref(false);
const error = ref(null);
const success = ref('');
const avatarFile = ref(null);
const countries = computed(() => countryOptions(locale.value, form.country));
usePageMeta(() => t('profile.title'), () => t('profile.description'));

function assign(user) {
    auth.setUser(user);
    for (const key of Object.keys(form)) form[key] = user[key] ?? '';
}

async function load() {
    loading.value = true;
    error.value = null;
    try { assign(await fetchProfile()); }
    catch (failure) { error.value = failure; }
    finally { loading.value = false; }
}

async function save() {
    if (busy.value) return;
    busy.value = true; error.value = null; success.value = '';
    try { assign(await updateProfile({ ...form })); success.value = t('profile.saved'); }
    catch (failure) { error.value = failure; }
    finally { busy.value = false; }
}

async function saveAvatar(event) {
    const file = event.target.files?.[0];
    if (!file || busy.value) return;
    busy.value = true; error.value = null; success.value = '';
    try { assign(await uploadAvatar(file)); success.value = t('profile.saved'); }
    catch (failure) { error.value = failure; }
    finally { event.target.value = ''; busy.value = false; }
}

async function clearAvatar() {
    if (busy.value) return;
    busy.value = true; error.value = null; success.value = '';
    try { assign(await removeAvatar()); success.value = t('profile.saved'); }
    catch (failure) { error.value = failure; }
    finally { busy.value = false; }
}

async function savePassword() {
    if (busy.value) return;
    busy.value = true; error.value = null; success.value = '';
    try { await changePassword({ ...password }); Object.assign(password, { current_password: '', password: '', password_confirmation: '' }); success.value = t('profile.passwordChanged'); }
    catch (failure) { error.value = failure; }
    finally { busy.value = false; }
}

async function changeLanguage(event) {
    const selected = event.target.value;
    try {
        await api.patch('api/v1/me/locale', { locale: selected });
        setLocale(selected);
        auth.setUser({ ...auth.user, preferred_locale: selected });
    } catch (failure) { error.value = failure; event.target.value = locale.value; }
}

onMounted(load);
</script>

<template>
    <div class="min-h-screen bg-slate-50"><div class="mx-auto max-w-4xl space-y-8 px-4 py-12 sm:px-6 lg:px-8 lg:py-20">
        <div><h1 class="text-4xl font-black text-slate-950">{{ t('profile.title') }}</h1><p class="mt-3 text-slate-600">{{ t('profile.description') }}</p></div>
        <LoadingState v-if="loading" />
        <template v-else>
            <BaseAlert v-if="success" tone="success" role="status" aria-live="polite">{{ success }}</BaseAlert>
            <BaseAlert v-if="error && error.status !== 422" tone="danger">{{ t('profile.error') }}</BaseAlert>
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"><h2 class="text-xl font-black">{{ t('profile.avatar') }}</h2><div class="mt-5 flex flex-wrap items-center gap-5"><label for="profile-avatar" class="group relative block size-24 shrink-0 cursor-pointer rounded-full focus-within:outline-3 focus-within:outline-offset-2 focus-within:outline-brand" :class="busy ? 'cursor-not-allowed opacity-60' : ''"><input id="profile-avatar" ref="avatarFile" type="file" accept="image/jpeg,image/png,image/webp" :disabled="busy" class="sr-only" @change="saveAvatar"><img v-if="publicMediaUrl(auth.user?.avatar)" :src="publicMediaUrl(auth.user.avatar)" :alt="auth.user?.name ?? ''" class="size-24 rounded-full object-cover"><span v-else class="grid size-24 place-items-center rounded-full bg-brand text-3xl font-black text-white" aria-hidden="true">{{ auth.user?.name?.charAt(0) }}</span><span aria-hidden="true" class="absolute -bottom-1 -end-1 grid size-8 place-items-center rounded-full border-2 border-white bg-accent text-sm font-black text-brand-dark shadow-sm">✎</span></label><div><label for="profile-avatar" class="cursor-pointer text-sm font-bold text-brand underline">{{ t('profile.upload') }}</label><p class="mt-2 text-xs text-slate-500">{{ t('profile.avatarHint') }}</p><button v-if="auth.user?.avatar" type="button" class="mt-2 block text-sm font-bold text-red-700 underline" :disabled="busy" @click="clearAvatar">{{ t('profile.remove') }}</button><p v-if="error?.errors?.avatar" role="alert" class="mt-2 text-sm text-red-700">{{ error.errors.avatar[0] }}</p></div></div></section>
            <form class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8" @submit.prevent="save"><h2 class="text-xl font-black">{{ t('profile.title') }}</h2><div class="mt-6 grid gap-5 sm:grid-cols-2"><div v-for="field in ['name', 'phone', 'country', 'city', 'specialization']" :key="field"><label :for="`profile-${field}`" class="mb-2 block text-sm font-bold">{{ t(`profile.${field}`) }}</label><select v-if="field === 'country'" :id="`profile-${field}`" v-model="form.country" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 focus-visible:outline-3 focus-visible:outline-brand" :aria-invalid="Boolean(error?.errors?.country)" :aria-describedby="error?.errors?.country ? 'profile-country-error' : undefined"><option value="">{{ t('profile.selectCountry') }}</option><option v-for="country in countries" :key="country.value" :value="country.value">{{ country.label }}</option></select><input v-else :id="`profile-${field}`" v-model="form[field]" :type="field === 'phone' ? 'tel' : 'text'" :required="field === 'name'" :maxlength="field === 'name' || field === 'specialization' ? 255 : field === 'phone' ? 50 : 100" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 focus-visible:outline-3 focus-visible:outline-brand" :aria-invalid="Boolean(error?.errors?.[field])" :aria-describedby="error?.errors?.[field] ? `profile-${field}-error` : undefined"><p v-if="error?.errors?.[field]" :id="`profile-${field}-error`" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors[field][0] }}</p></div><div><label for="profile-email" class="mb-2 block text-sm font-bold">{{ t('profile.email') }}</label><input id="profile-email" :value="auth.user?.email" type="email" readonly class="min-h-11 w-full rounded-xl border border-slate-200 bg-slate-100 px-3 text-slate-600"></div></div><button type="submit" :disabled="busy" class="mt-6 min-h-11 rounded-xl bg-brand px-6 font-bold text-white disabled:opacity-50">{{ t('profile.save') }}</button></form>
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"><h2 class="text-xl font-black">{{ t('profile.language') }}</h2><p class="mt-2 text-sm text-slate-600">{{ t('profile.languageHint') }}</p><label for="profile-language" class="sr-only">{{ t('profile.language') }}</label><select id="profile-language" :value="auth.user?.preferred_locale ?? locale" class="mt-4 min-h-11 rounded-xl border border-slate-300 px-3" @change="changeLanguage"><option value="ar">العربية</option><option value="en">English</option></select></section>
            <form class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8" @submit.prevent="savePassword"><h2 class="text-xl font-black">{{ t('profile.password') }}</h2><div class="mt-6 grid gap-5 sm:grid-cols-2"><div v-for="field in ['current_password', 'password', 'password_confirmation']" :key="field"><label :for="`profile-${field}`" class="mb-2 block text-sm font-bold">{{ t(field === 'current_password' ? 'profile.currentPassword' : field === 'password' ? 'profile.newPassword' : 'profile.confirmPassword') }}</label><input :id="`profile-${field}`" v-model="password[field]" type="password" required :autocomplete="field === 'current_password' ? 'current-password' : 'new-password'" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 focus-visible:outline-3 focus-visible:outline-brand" :aria-invalid="Boolean(error?.errors?.[field])" :aria-describedby="error?.errors?.[field] ? `profile-${field}-error` : undefined"><p v-if="error?.errors?.[field]" :id="`profile-${field}-error`" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors[field][0] }}</p></div></div><button type="submit" :disabled="busy" class="mt-6 min-h-11 rounded-xl bg-brand px-6 font-bold text-white disabled:opacity-50">{{ t('profile.changePassword') }}</button></form>
        </template>
    </div></div>
</template>
