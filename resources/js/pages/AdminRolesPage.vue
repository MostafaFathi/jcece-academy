<script setup>
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';
import { fetchRolePermissionMatrix, fetchRolePermissions, saveRolePermissions } from '../api/role-permissions';
import PageHeading from '../components/ui/PageHeading.vue';
import BaseAlert from '../components/ui/BaseAlert.vue';
import LoadingState from '../components/ui/LoadingState.vue';

const { t, locale } = useI18n();
const auth = useAuthStore();
const roles = ref([]);
const permissions = ref([]);
const selectedRole = ref('admin');
const original = ref([]);
const draft = ref([]);
const version = ref('');
const unmanagedCount = ref(0);
const search = ref('');
const loading = ref(true);
const switching = ref(false);
const saving = ref(false);
const reviewing = ref(false);
const acknowledged = ref(false);
const error = ref('');
const success = ref(false);
const groupOrder = ['access', 'courses', 'curriculum', 'assessments', 'packages', 'commerce', 'certificates', 'reviews', 'support', 'content', 'reports', 'other'];

const dirty = computed(() => [...draft.value].sort().join('|') !== [...original.value].sort().join('|'));
const changes = computed(() => ({
    added: permissions.value.filter((permission) => draft.value.includes(permission.key) && !original.value.includes(permission.key)),
    removed: permissions.value.filter((permission) => !draft.value.includes(permission.key) && original.value.includes(permission.key)),
}));
const sensitiveChanges = computed(() => [...changes.value.added, ...changes.value.removed].some((permission) => permission.sensitive) || (selectedRole.value === 'student' && changes.value.added.length > 0));
const visiblePermissions = computed(() => permissions.value.filter((permission) => {
    const needle = search.value.trim().toLocaleLowerCase(locale.value);
    return !needle || permission.key.toLowerCase().includes(needle) || permissionLabel(permission).toLocaleLowerCase(locale.value).includes(needle);
}));
const groups = computed(() => groupOrder.map((name) => ({ name, permissions: visiblePermissions.value.filter((permission) => permission.group === name) })).filter((group) => group.permissions.length));

function permissionLabel(permission) {
    const parts = permission.key.split('.');
    const action = parts.pop();
    const subject = parts.join('_');
    return `${t(`rolePermissions.actions.${action}`)} ${t(`rolePermissions.subjects.${subject}`)}`;
}
function applySnapshot(snapshot) {
    selectedRole.value = snapshot.role;
    original.value = [...snapshot.permissions];
    draft.value = [...snapshot.permissions];
    version.value = snapshot.version;
    unmanagedCount.value = snapshot.unmanaged_count;
    reviewing.value = false;
    acknowledged.value = false;
    error.value = '';
}
async function load() {
    loading.value = true;
    error.value = '';
    try {
        const result = await fetchRolePermissionMatrix();
        roles.value = result.roles;
        permissions.value = result.permissions;
        applySnapshot(result.roles.find((role) => role.role === selectedRole.value) ?? result.roles[0]);
    } catch (failure) {
        error.value = failure.status === 403 ? 'forbidden' : 'loadError';
    } finally {
        loading.value = false;
    }
}
async function selectRole(role) {
    if (role === selectedRole.value || switching.value) return;
    if (dirty.value && !window.confirm(t('rolePermissions.switchDiscard'))) return;
    switching.value = true;
    success.value = false;
    try {
        applySnapshot(await fetchRolePermissions(role));
    } catch (failure) {
        error.value = failure.status === 403 ? 'forbidden' : 'loadError';
    } finally {
        switching.value = false;
    }
}
function toggle(key, checked) {
    draft.value = checked ? [...new Set([...draft.value, key])] : draft.value.filter((item) => item !== key);
    reviewing.value = false;
    acknowledged.value = false;
    success.value = false;
}
function setGroup(group, checked) {
    group.permissions.filter((permission) => selectedRole.value === 'admin' || permission.key !== 'roles.manage' || !checked).forEach((permission) => {
        draft.value = checked ? [...new Set([...draft.value, permission.key])] : draft.value.filter((item) => item !== permission.key);
    });
    reviewing.value = false;
    acknowledged.value = false;
    success.value = false;
}
function reset() {
    draft.value = [...original.value];
    reviewing.value = false;
    acknowledged.value = false;
    error.value = '';
}
async function save() {
    if (!dirty.value || saving.value || (sensitiveChanges.value && !acknowledged.value)) return;
    saving.value = true;
    error.value = '';
    try {
        const snapshot = await saveRolePermissions(selectedRole.value, [...draft.value].sort(), version.value);
        roles.value = roles.value.map((role) => role.role === snapshot.role ? snapshot : role);
        applySnapshot(snapshot);
        success.value = true;
        await auth.initialize({ force: true });
    } catch (failure) {
        error.value = failure.status === 409 ? 'stale' : failure.status === 403 ? 'forbidden' : failure.status === 422 ? 'lockout' : 'saveError';
    } finally {
        saving.value = false;
    }
}
function roleHas(role, key) {
    return (role.role === selectedRole.value ? draft.value : role.permissions).includes(key);
}
onMounted(load);
</script>

<template>
    <div class="min-w-0 space-y-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <PageHeading :title="t('rolePermissions.title')" :description="t('rolePermissions.description')" />
        <LoadingState v-if="loading" />
        <template v-else>
            <BaseAlert v-if="error" tone="danger">{{ t(`rolePermissions.${error}`) }} <button v-if="error === 'stale' || error === 'loadError'" type="button" class="ms-2 font-bold underline" @click="load">{{ t('rolePermissions.reload') }}</button></BaseAlert>
            <BaseAlert v-if="success" tone="success">{{ t('rolePermissions.saved') }}</BaseAlert>
            <template v-if="roles.length">
                <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                    <h2 class="mb-3 text-sm font-black">{{ t('rolePermissions.role') }}</h2>
                    <div role="tablist" class="flex flex-wrap gap-2" :aria-label="t('rolePermissions.role')">
                        <button v-for="role in roles" :key="role.role" type="button" role="tab" :aria-selected="selectedRole === role.role" :disabled="switching" class="min-h-11 rounded-xl border px-4 py-2 text-sm font-bold focus-visible:outline-3 focus-visible:outline-accent disabled:opacity-50" :class="selectedRole === role.role ? 'border-brand bg-brand text-white' : 'border-slate-300 bg-white text-slate-700'" @click="selectRole(role.role)">{{ t(`labels.roles.${role.role}`) }}</button>
                    </div>
                    <p class="mt-4 text-sm text-slate-600">{{ t('rolePermissions.selected', { count: draft.length, total: permissions.length }) }} <span v-if="dirty" class="ms-2 font-bold text-amber-800">{{ t('rolePermissions.dirty') }}</span></p>
                    <p v-if="unmanagedCount" class="mt-2 text-xs text-amber-800">{{ t('rolePermissions.unmanaged', { count: unmanagedCount }) }}</p>
                </section>

                <BaseAlert tone="info">{{ t('rolePermissions.scopeHint') }}</BaseAlert>
                <label for="permission-search" class="block text-sm font-bold">{{ t('rolePermissions.search') }}</label>
                <input id="permission-search" v-model="search" type="search" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-4 focus-visible:outline-3 focus-visible:outline-accent" :placeholder="t('rolePermissions.search')">
                <p class="text-xs text-slate-500">{{ t('rolePermissions.groupHint') }}</p>
                <p v-if="!groups.length" class="rounded-2xl border bg-white p-6 text-sm">{{ t('rolePermissions.searchEmpty') }}</p>
                <section v-for="group in groups" :key="group.name" class="min-w-0 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-4 sm:p-5">
                        <h2 class="text-lg font-black">{{ t(`rolePermissions.groups.${group.name}`) }} <span class="text-sm font-normal text-slate-500">({{ group.permissions.length }})</span></h2>
                        <div class="flex flex-wrap gap-2"><button type="button" class="min-h-10 rounded-lg border border-slate-300 px-3 text-xs font-bold" @click="setGroup(group, true)">{{ t('rolePermissions.selectGroup') }}</button><button type="button" class="min-h-10 rounded-lg border border-slate-300 px-3 text-xs font-bold" @click="setGroup(group, false)">{{ t('rolePermissions.clearGroup') }}</button></div>
                    </header>
                    <div class="divide-y divide-slate-100">
                        <div v-for="permission in group.permissions" :key="permission.key" class="grid min-w-0 gap-3 p-4 sm:p-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] lg:items-center">
                            <label class="flex min-w-0 items-start gap-3">
                                <input type="checkbox" class="mt-1 size-5 shrink-0 accent-brand" :checked="draft.includes(permission.key)" :disabled="selectedRole !== 'admin' && permission.key === 'roles.manage' && !draft.includes(permission.key)" @change="toggle(permission.key, $event.target.checked)">
                                <span class="min-w-0"><span class="block font-bold">{{ permissionLabel(permission) }}</span><span class="block break-all text-xs text-slate-500" dir="ltr">{{ permission.key }}</span><span v-if="permission.sensitive" class="mt-1 inline-flex items-center gap-1 text-xs font-bold text-amber-800">⚠ {{ t('rolePermissions.sensitive') }}</span><span v-if="permission.key === 'roles.manage' && selectedRole !== 'admin'" class="block text-xs text-slate-600">{{ t('rolePermissions.adminOnly') }}</span></span>
                            </label>
                            <div class="hidden grid-cols-5 gap-1 lg:grid" :aria-label="t('rolePermissions.matrix')">
                                <div v-for="role in roles" :key="role.role" class="min-w-0 text-center text-xs"><span class="block break-words text-slate-500">{{ t(`labels.roles.${role.role}`) }}</span><span class="font-black" :aria-label="roleHas(role, permission.key) ? t('common.yes') : t('common.no')">{{ roleHas(role, permission.key) ? '✓' : '—' }}</span></div>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="sticky bottom-2 z-10 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-xl backdrop-blur">
                    <span class="text-sm font-bold">{{ dirty ? t('rolePermissions.dirty') : t('rolePermissions.noChanges') }}</span>
                    <div class="flex gap-2"><button type="button" :disabled="!dirty || saving" class="min-h-11 rounded-xl border border-slate-300 px-4 font-bold disabled:opacity-50" @click="reset">{{ t('rolePermissions.reset') }}</button><button type="button" :disabled="!dirty || saving || error === 'stale'" class="min-h-11 rounded-xl bg-brand px-5 font-bold text-white disabled:opacity-50" @click="reviewing = true">{{ t('rolePermissions.review') }}</button></div>
                </div>
                <section v-if="reviewing && dirty" class="space-y-4 rounded-3xl border-2 border-brand bg-white p-5 shadow-sm" aria-live="polite">
                    <h2 class="text-lg font-black">{{ t('rolePermissions.reviewTitle') }} — {{ t(`labels.roles.${selectedRole}`) }}</h2>
                    <ul class="space-y-2 text-sm"><li v-for="permission in changes.added" :key="`add-${permission.key}`">+ {{ t('rolePermissions.added') }}: {{ permissionLabel(permission) }}</li><li v-for="permission in changes.removed" :key="`remove-${permission.key}`">− {{ t('rolePermissions.removed') }}: {{ permissionLabel(permission) }}</li></ul>
                    <BaseAlert v-if="sensitiveChanges" tone="warning">{{ t('rolePermissions.sensitiveHint') }} <span v-if="selectedRole === 'student' && changes.added.length">{{ t('rolePermissions.studentElevation') }}</span> <span v-if="selectedRole === 'admin' && changes.removed.some((permission) => permission.key === 'roles.manage')">{{ t('rolePermissions.adminRemoval') }}</span></BaseAlert>
                    <label v-if="sensitiveChanges" class="flex items-start gap-3 text-sm font-bold"><input v-model="acknowledged" type="checkbox" class="mt-1 size-5 accent-brand">{{ t('rolePermissions.confirmSensitive') }}</label>
                    <button type="button" :disabled="saving || (sensitiveChanges && !acknowledged)" class="min-h-11 rounded-xl bg-brand px-5 font-bold text-white disabled:opacity-50" @click="save">{{ t('rolePermissions.save') }}</button>
                </section>
            </template>
        </template>
    </div>
</template>
