<script setup>
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';
import BaseBadge from '../components/ui/BaseBadge.vue';

const auth = useAuthStore();
const { t, te } = useI18n();
const roleLabel = (role) => te(`labels.roles.${role}`) ? t(`labels.roles.${role}`) : role;
const statusLabel = (status) => status && te(`labels.statuses.${status}`) ? t(`labels.statuses.${status}`) : (status || t('common.notSpecified'));
</script>

<template>
    <div class="space-y-6">
        <section class="relative overflow-hidden rounded-[2rem] bg-brand-dark p-7 text-white shadow-xl shadow-brand/10 sm:p-10"><div class="absolute -end-16 -top-20 size-72 rounded-full bg-accent/15 blur-2xl" /><div class="absolute -bottom-24 start-1/3 size-56 rounded-full border-[35px] border-white/[0.03]" /><div class="relative flex flex-wrap items-end justify-between gap-7"><div><p class="text-xs font-black tracking-[0.18em] text-accent uppercase">{{ t('dashboard.workspace') }}</p><h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">{{ t('dashboard.greeting', { name: auth.user?.name }) }}</h1><p class="mt-3 max-w-2xl leading-7 text-white/60">{{ t('dashboard.subtitle') }}</p></div><div class="grid size-18 place-items-center rounded-2xl border border-white/10 bg-white/10 text-2xl font-black backdrop-blur">{{ auth.user?.name?.charAt(0) }}</div></div></section>
        <div class="grid gap-6 lg:grid-cols-[1.35fr_0.65fr]">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-7"><div class="flex items-center justify-between gap-4"><h2 class="text-lg font-black text-slate-950">{{ t('dashboard.account') }}</h2><span class="size-2 rounded-full bg-emerald-500 shadow-[0_0_0_5px_rgba(16,185,129,.1)]" /></div><dl class="mt-6 grid gap-4 sm:grid-cols-2"><div class="rounded-2xl bg-slate-50 p-4"><dt class="text-xs font-bold text-slate-500">{{ t('dashboard.email') }}</dt><dd class="mt-2 break-all text-sm font-black text-slate-900">{{ auth.user?.email }}</dd></div><div class="rounded-2xl bg-slate-50 p-4"><dt class="text-xs font-bold text-slate-500">{{ t('dashboard.profileStatus') }}</dt><dd class="mt-2 text-sm font-black text-emerald-700">{{ statusLabel(auth.user?.status) }}</dd></div></dl><div class="mt-5"><h3 class="text-xs font-bold text-slate-500">{{ t('dashboard.roles') }}</h3><div class="mt-3 flex flex-wrap gap-2"><BaseBadge v-for="role in auth.roles" :key="role">{{ roleLabel(role) }}</BaseBadge></div></div></section>
            <RouterLink :to="{ name: 'courses.index' }" class="group relative overflow-hidden rounded-3xl bg-accent p-7 text-brand-dark shadow-lg shadow-accent/15 transition hover:-translate-y-1 focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-brand"><div class="absolute -end-10 -top-10 size-36 rounded-full bg-white/35 transition group-hover:scale-125" /><span class="relative grid size-12 place-items-center rounded-2xl bg-brand text-xl text-white">↗</span><h2 class="relative mt-7 text-xl font-black">{{ t('dashboard.publicCatalog') }}</h2><p class="relative mt-3 text-sm leading-7 text-brand-dark/70">{{ t('dashboard.publicCatalogText') }}</p></RouterLink>
        </div>
    </div>
</template>
