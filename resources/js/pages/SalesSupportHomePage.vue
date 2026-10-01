<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';
import PageHeading from '../components/ui/PageHeading.vue';

const auth = useAuthStore();
const { t, locale } = useI18n();
const workspaces = computed(() => [
    { permission: 'support_tickets.view', route: 'support.tickets', title: t('operations.support'), description: t('operations.supportWorkspace') },
    { permission: 'orders.view', route: 'support.orders', title: t('operations.orders'), description: t('operations.orderWorkspace') },
    { permission: 'payments.view', route: 'support.payments.index', title: t('operations.payments'), description: t('operations.paymentWorkspace') },
].filter((workspace) => auth.can(workspace.permission)));
</script>

<template>
    <div class="space-y-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <PageHeading :title="t('operations.workspace')" :description="t('operations.workspaceIntro')" />
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <RouterLink v-for="workspace in workspaces" :key="workspace.route" :to="{ name: workspace.route }" class="group rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-brand hover:shadow-md">
                <span class="mb-5 block h-1 w-12 rounded-full bg-accent" aria-hidden="true" />
                <h2 class="text-xl font-black text-slate-950 group-hover:text-brand">{{ workspace.title }}</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ workspace.description }}</p>
            </RouterLink>
        </div>
    </div>
</template>
