<script setup>
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';
import BaseBadge from '../components/ui/BaseBadge.vue';
import BaseCard from '../components/ui/BaseCard.vue';
import PageHeading from '../components/ui/PageHeading.vue';

const auth = useAuthStore();
const { t } = useI18n();
</script>

<template>
    <div class="space-y-6"><PageHeading :title="t('dashboard.greeting', { name: auth.user?.name })" :description="t('dashboard.subtitle')" /><div class="grid gap-5 lg:grid-cols-3"><BaseCard class="lg:col-span-2"><h2 class="font-extrabold">{{ t('dashboard.account') }}</h2><div class="mt-5 grid gap-4 text-sm sm:grid-cols-2"><div class="rounded-xl bg-slate-50 p-4"><span class="text-slate-500">Email</span><strong class="mt-1 block break-all">{{ auth.user?.email }}</strong></div><div class="rounded-xl bg-slate-50 p-4"><span class="text-slate-500">{{ t('dashboard.roles') }}</span><div class="mt-2 flex flex-wrap gap-2"><BaseBadge v-for="role in auth.roles" :key="role">{{ role }}</BaseBadge></div></div></div></BaseCard><BaseCard class="border-t-4 !border-t-accent"><h2 class="font-extrabold text-brand">{{ t('common.upcoming') }}</h2><p class="mt-3 text-sm leading-6 text-slate-600">{{ t('dashboard.next') }}</p></BaseCard></div><BaseCard><h2 class="font-extrabold">{{ t('dashboard.permissions') }}</h2><div class="mt-4 flex max-h-48 flex-wrap gap-2 overflow-y-auto"><BaseBadge v-for="permission in auth.permissions" :key="permission">{{ permission }}</BaseBadge><span v-if="!auth.permissions.length" class="text-sm text-slate-500">—</span></div></BaseCard></div>
</template>
