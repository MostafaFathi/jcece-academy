<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';
import { createAdminUser, fetchAdminUser, updateAdminUser } from '../api/admin-users';
import PageHeading from '../components/ui/PageHeading.vue';
import BaseAlert from '../components/ui/BaseAlert.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import LoadingState from '../components/ui/LoadingState.vue';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const { t, te, locale } = useI18n();
const isEdit = computed(() => Boolean(route.params.id));
const canManage = computed(() => auth.can('users.manage'));
const canAssignRoles = computed(() => auth.hasRole('admin') && auth.can('users.manage') && auth.can('roles.manage'));
const roles = ['student', 'content_manager', 'sales_support', 'admin'];
const selectedRoleToAdd = ref('');
const availableRoles = computed(() => roles.filter((role) => !form.roles.includes(role)));
const statuses = ['active', 'inactive', 'blocked'];
const form = reactive({ name: '', email: '', status: 'active', roles: ['student'], password: '', password_confirmation: '' });
const originalRoles = ref([]);
const loading = ref(true);
const loaded = ref(false);
const busy = ref(false);
const error = ref(null);
const userPermissionSources = ref(null);

function permissionLabel(key) {
    const parts = key.split('.');
    const action = parts.pop();
    if (!te(`rolePermissions.actions.${action}`) || !te(`rolePermissions.subjects.${parts.join('_')}`)) {
        return `${t('rolePermissions.customPermission')} (${key})`;
    }
    return `${t(`rolePermissions.actions.${action}`)} ${t(`rolePermissions.subjects.${parts.join('_')}`)}`;
}

function addRole() {
    if (selectedRoleToAdd.value && !form.roles.includes(selectedRoleToAdd.value)) {
        form.roles.push(selectedRoleToAdd.value);
    }
    selectedRoleToAdd.value = '';
}

function removeRole(role) {
    if (role !== 'instructor' && form.roles.length > 1) {
        form.roles = form.roles.filter((item) => item !== role);
    }
}

onMounted(async () => {
    if (!isEdit.value) { loaded.value = true; loading.value = false; return; }
    try {
        const user = await fetchAdminUser(route.params.id);
        Object.assign(form, { name: user.name, email: user.email, status: user.status, roles: user.roles ?? [] });
        userPermissionSources.value = user.permission_sources ?? null;
        originalRoles.value = [...form.roles];
        loaded.value = true;
    } catch (failure) { error.value = failure; }
    finally { loading.value = false; }
});

async function submit() {
    if (busy.value) return;
    error.value = null;
    const payload = { name: form.name.trim(), email: form.email.trim() };
    if (canManage.value) {
        payload.status = form.status;
        if (!isEdit.value || JSON.stringify([...form.roles].sort()) !== JSON.stringify([...originalRoles.value].sort())) {
            if (isEdit.value && !window.confirm(t('admin.confirmRoleChange'))) return;
            payload.roles = form.roles;
        }
        if (!isEdit.value || form.password) {
            payload.password = form.password;
            payload.password_confirmation = form.password_confirmation;
        }
    }
    busy.value = true;
    try {
        if (isEdit.value) await updateAdminUser(route.params.id, payload);
        else await createAdminUser(payload);
        await router.push({ name: 'admin.users.index' });
    } catch (failure) { error.value = failure; }
    finally { busy.value = false; }
}
</script>

<template>
    <div class="mx-auto max-w-4xl space-y-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <RouterLink :to="{ name: 'admin.users.index' }" class="text-sm font-bold text-brand">{{ t('common.back') }}</RouterLink>
        <PageHeading :title="t(isEdit ? 'admin.editUser' : 'admin.addUser')" />
        <LoadingState v-if="loading" />
        <BaseAlert v-else-if="!loaded" tone="danger">{{ t(error?.status === 403 ? 'admin.notAllowed' : 'admin.loadError') }}</BaseAlert>
        <template v-else>
            <BaseAlert v-if="error" tone="danger">{{ t(error.status === 422 ? 'admin.validation' : error.status === 403 ? 'admin.notAllowed' : 'admin.saveError') }}</BaseAlert>
            <form class="space-y-6 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" @submit.prevent="submit">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div><label for="user-name" class="mb-2 block text-sm font-bold">{{ t('admin.name') }}</label><input id="user-name" v-model="form.name" required maxlength="255" class="w-full rounded-xl border border-slate-300 px-4 py-3"><p v-if="error?.errors?.name" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors.name[0] }}</p></div>
                    <div><label for="user-email" class="mb-2 block text-sm font-bold">{{ t('admin.email') }}</label><input id="user-email" v-model="form.email" type="email" required maxlength="255" dir="ltr" class="w-full rounded-xl border border-slate-300 px-4 py-3"><p v-if="error?.errors?.email" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors.email[0] }}</p></div>
                    <div v-if="canManage"><label for="user-status" class="mb-2 block text-sm font-bold">{{ t('admin.status') }}</label><select id="user-status" v-model="form.status" class="w-full rounded-xl border border-slate-300 px-4 py-3"><option v-for="status in statuses" :key="status" :value="status">{{ t(`labels.statuses.${status}`) }}</option></select><p v-if="error?.errors?.status" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors.status[0] }}</p></div>
                </div>
                <fieldset v-if="canAssignRoles" class="space-y-3 border-t border-slate-100 pt-5"><legend class="text-sm font-bold">{{ t('admin.roles') }}</legend><div class="flex flex-wrap gap-2"><span v-for="role in form.roles" :key="role" class="inline-flex min-h-11 items-center gap-2 rounded-full bg-brand/10 px-4 text-sm font-bold">{{ t(`labels.roles.${role}`) }}<button v-if="role !== 'instructor' && form.roles.length > 1" type="button" :aria-label="`${t('admin.removeRole')} ${t(`labels.roles.${role}`)}`" class="text-red-700" @click="removeRole(role)">×</button></span></div><div class="flex max-w-md flex-wrap gap-2"><label class="sr-only" for="user-add-role">{{ t('admin.addRole') }}</label><select id="user-add-role" v-model="selectedRoleToAdd" class="min-h-11 min-w-0 flex-1 rounded-xl border border-slate-300 px-4"><option value="">{{ t('admin.addRole') }}</option><option v-for="role in availableRoles" :key="role" :value="role">{{ t(`labels.roles.${role}`) }}</option></select><button type="button" :disabled="!selectedRoleToAdd" class="min-h-11 rounded-xl border border-slate-300 px-4 text-sm font-bold disabled:opacity-50" @click="addRole">{{ t('admin.addRole') }}</button></div><p class="text-xs text-slate-500">{{ t('admin.instructorRoleNote') }}</p><p v-if="error?.errors?.roles || error?.errors?.['roles.0']" role="alert" class="text-sm text-red-700">{{ error?.errors?.roles?.[0] ?? error.errors['roles.0'][0] }}</p></fieldset>
                <section v-if="isEdit && canAssignRoles && loaded && userPermissionSources" class="space-y-3 border-t border-slate-100 pt-5"><h2 class="text-sm font-bold">{{ t('rolePermissions.userSpecific') }}</h2><p class="text-xs text-slate-600">{{ t('rolePermissions.userSpecificHint') }}</p><div class="grid gap-4 sm:grid-cols-2"><div><h3 class="text-sm font-bold">{{ t('rolePermissions.inherited') }}</h3><p class="mt-1 break-words text-xs text-slate-600">{{ userPermissionSources.inherited.map(permissionLabel).join(' · ') || '—' }}</p></div><div><h3 class="text-sm font-bold">{{ t('rolePermissions.direct') }}</h3><p class="mt-1 break-words text-xs text-slate-600">{{ userPermissionSources.direct.map(permissionLabel).join(' · ') || '—' }}</p></div></div></section>
                <p v-if="!isEdit && canManage && !canAssignRoles" class="text-sm text-slate-600">{{ t('rolePermissions.studentDefault') }}</p>
                <fieldset v-if="canManage" class="grid gap-5 border-t border-slate-100 pt-5 sm:grid-cols-2"><legend class="text-sm font-bold">{{ t(isEdit ? 'admin.changePassword' : 'admin.password') }}</legend><p v-if="isEdit" class="sm:col-span-2 text-xs text-slate-500">{{ t('admin.blankPassword') }}</p><div><label for="user-password" class="mb-2 block text-sm font-bold">{{ t('admin.password') }}</label><input id="user-password" v-model="form.password" type="password" autocomplete="new-password" :required="!isEdit" class="w-full rounded-xl border border-slate-300 px-4 py-3"><p v-if="error?.errors?.password" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors.password[0] }}</p></div><div><label for="user-confirm" class="mb-2 block text-sm font-bold">{{ t('admin.passwordConfirmation') }}</label><input id="user-confirm" v-model="form.password_confirmation" type="password" autocomplete="new-password" :required="!isEdit || Boolean(form.password)" class="w-full rounded-xl border border-slate-300 px-4 py-3"></div></fieldset>
                <div class="flex flex-wrap gap-3 border-t border-slate-100 pt-5"><BaseButton type="submit" :loading="busy">{{ t(isEdit ? 'admin.save' : 'admin.create') }}</BaseButton><RouterLink :to="{ name: 'admin.users.index' }" class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-5 text-sm font-bold">{{ t('admin.cancel') }}</RouterLink></div>
            </form>
        </template>
    </div>
</template>
