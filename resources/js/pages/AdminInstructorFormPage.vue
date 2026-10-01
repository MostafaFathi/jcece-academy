<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';
import { createAdminInstructor, fetchAdminInstructor, updateAdminInstructor } from '../api/admin-instructors';
import PageHeading from '../components/ui/PageHeading.vue';
import BaseAlert from '../components/ui/BaseAlert.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import LoadingState from '../components/ui/LoadingState.vue';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const { t, locale } = useI18n();
const isEdit = computed(() => Boolean(route.params.id));
const canManageAccount = computed(() => auth.can('instructors.manage'));
const statuses = ['active', 'inactive', 'blocked'];
const form = reactive({ name: '', email: '', password: '', password_confirmation: '', status: 'active', job_title: '', short_bio: '', bio: '', years_experience: '', specialties: '', linkedin_url: '', facebook_url: '', instagram_url: '', website_url: '', is_featured: false });
const fields = ['job_title', 'short_bio', 'bio', 'years_experience', 'linkedin_url', 'facebook_url', 'instagram_url', 'website_url'];
const loaded = ref(false);
const loading = ref(true);
const busy = ref(false);
const error = ref(null);

onMounted(async () => {
    if (!isEdit.value) { loaded.value = true; loading.value = false; return; }
    try {
        const instructor = await fetchAdminInstructor(route.params.id);
        Object.assign(form, { name: instructor.name, email: instructor.email ?? '', status: instructor.status, ...instructor.profile, specialties: instructor.profile?.specialties?.join('\n') ?? '', years_experience: instructor.profile?.years_experience ?? '' });
        loaded.value = true;
    } catch (failure) { error.value = failure; }
    finally { loading.value = false; }
});

async function submit() {
    if (busy.value) return;
    error.value = null;
    const payload = { specialties: form.specialties.split('\n').map((value) => value.trim()).filter(Boolean), is_featured: form.is_featured, years_experience: form.years_experience === '' ? null : Number(form.years_experience) };
    for (const key of fields.filter((key) => key !== 'years_experience')) payload[key] = form[key] || null;
    if (canManageAccount.value) {
        Object.assign(payload, { name: form.name.trim(), email: form.email.trim(), status: form.status });
        if (!isEdit.value || form.password) Object.assign(payload, { password: form.password, password_confirmation: form.password_confirmation });
    }
    busy.value = true;
    try {
        if (isEdit.value) await updateAdminInstructor(route.params.id, payload);
        else await createAdminInstructor(payload);
        await router.push({ name: 'admin.instructors.index' });
    } catch (failure) { error.value = failure; }
    finally { busy.value = false; }
}
</script>

<template>
    <div class="mx-auto max-w-4xl space-y-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <RouterLink :to="{ name: 'admin.instructors.index' }" class="text-sm font-bold text-brand">{{ t('common.back') }}</RouterLink>
        <PageHeading :title="t(isEdit ? 'admin.editInstructor' : 'admin.addInstructor')" :description="t('admin.instructorFormDescription')" />
        <LoadingState v-if="loading" />
        <BaseAlert v-else-if="!loaded" tone="danger">{{ t(error?.status === 403 ? 'admin.notAllowed' : 'admin.loadError') }}</BaseAlert>
        <template v-else>
            <BaseAlert v-if="error" tone="danger">{{ t(error.status === 422 ? 'admin.validation' : error.status === 403 ? 'admin.notAllowed' : 'admin.saveError') }}</BaseAlert>
            <form class="space-y-6" @submit.prevent="submit">
                <section v-if="canManageAccount" class="space-y-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"><h2 class="text-lg font-black">{{ t('admin.instructorAccount') }}</h2><div class="grid gap-5 sm:grid-cols-2"><div><label for="instructor-name" class="mb-2 block text-sm font-bold">{{ t('admin.name') }}</label><input id="instructor-name" v-model="form.name" required maxlength="255" class="w-full rounded-xl border border-slate-300 px-4 py-3"><p v-if="error?.errors?.name" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors.name[0] }}</p></div><div><label for="instructor-email" class="mb-2 block text-sm font-bold">{{ t('admin.email') }}</label><input id="instructor-email" v-model="form.email" type="email" dir="ltr" required maxlength="255" class="w-full rounded-xl border border-slate-300 px-4 py-3"><p v-if="error?.errors?.email" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors.email[0] }}</p></div><div><label for="instructor-status" class="mb-2 block text-sm font-bold">{{ t('admin.status') }}</label><select id="instructor-status" v-model="form.status" class="w-full rounded-xl border border-slate-300 px-4 py-3"><option v-for="status in statuses" :key="status" :value="status">{{ t(`labels.statuses.${status}`) }}</option></select></div></div><div class="grid gap-5 border-t border-slate-100 pt-5 sm:grid-cols-2"><div><label for="instructor-password" class="mb-2 block text-sm font-bold">{{ t('admin.password') }}</label><input id="instructor-password" v-model="form.password" type="password" autocomplete="new-password" :required="!isEdit" class="w-full rounded-xl border border-slate-300 px-4 py-3"><p v-if="error?.errors?.password" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors.password[0] }}</p></div><div><label for="instructor-confirm" class="mb-2 block text-sm font-bold">{{ t('admin.passwordConfirmation') }}</label><input id="instructor-confirm" v-model="form.password_confirmation" type="password" autocomplete="new-password" :required="!isEdit || Boolean(form.password)" class="w-full rounded-xl border border-slate-300 px-4 py-3"></div><p v-if="isEdit" class="sm:col-span-2 text-xs text-slate-500">{{ t('admin.blankPassword') }}</p></div></section>
                <section class="space-y-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"><h2 class="text-lg font-black">{{ t('admin.instructorProfile') }}</h2><div class="grid gap-5 sm:grid-cols-2"><div><label for="job-title" class="mb-2 block text-sm font-bold">{{ t('admin.jobTitle') }}</label><input id="job-title" v-model="form.job_title" maxlength="255" class="w-full rounded-xl border border-slate-300 px-4 py-3"><p v-if="error?.errors?.job_title" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors.job_title[0] }}</p></div><div><label for="years-experience" class="mb-2 block text-sm font-bold">{{ t('admin.yearsExperience') }}</label><input id="years-experience" v-model="form.years_experience" type="number" min="0" max="80" class="w-full rounded-xl border border-slate-300 px-4 py-3"><p v-if="error?.errors?.years_experience" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors.years_experience[0] }}</p></div></div><div><label for="short-bio" class="mb-2 block text-sm font-bold">{{ t('admin.shortBio') }}</label><textarea id="short-bio" v-model="form.short_bio" maxlength="1000" rows="2" class="w-full rounded-xl border border-slate-300 px-4 py-3" /><p v-if="error?.errors?.short_bio" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors.short_bio[0] }}</p></div><div><label for="instructor-bio" class="mb-2 block text-sm font-bold">{{ t('admin.bio') }}</label><textarea id="instructor-bio" v-model="form.bio" maxlength="10000" rows="5" class="w-full rounded-xl border border-slate-300 px-4 py-3" /><p v-if="error?.errors?.bio" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors.bio[0] }}</p></div><div><label for="specialties" class="mb-2 block text-sm font-bold">{{ t('admin.specialties') }}</label><textarea id="specialties" v-model="form.specialties" rows="3" class="w-full rounded-xl border border-slate-300 px-4 py-3" /><p class="mt-1 text-xs text-slate-500">{{ t('admin.onePerLine') }}</p><p v-if="error?.errors?.specialties" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors.specialties[0] }}</p></div><div class="grid gap-5 sm:grid-cols-2"><div v-for="key in ['linkedin_url', 'facebook_url', 'instagram_url', 'website_url']" :key="key"><label :for="key" class="mb-2 block text-sm font-bold">{{ t(`admin.${key}`) }}</label><input :id="key" v-model="form[key]" type="url" maxlength="255" dir="ltr" class="w-full rounded-xl border border-slate-300 px-4 py-3"><p v-if="error?.errors?.[key]" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors[key][0] }}</p></div></div><label class="flex items-center gap-3 text-sm font-bold"><input v-model="form.is_featured" type="checkbox" class="size-5 accent-brand">{{ t('admin.featured') }}</label></section>
                <div class="flex flex-wrap gap-3"><BaseButton type="submit" :loading="busy">{{ t(isEdit ? 'admin.save' : 'admin.create') }}</BaseButton><RouterLink :to="{ name: 'admin.instructors.index' }" class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-5 text-sm font-bold">{{ t('admin.cancel') }}</RouterLink></div>
            </form>
        </template>
    </div>
</template>
