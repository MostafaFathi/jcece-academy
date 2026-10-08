<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';

const route = useRoute();
const { t } = useI18n();
const crumbs = computed(() => route.matched.filter((item) => item.meta.title).map((item) => ({ path: item.path, label: t(item.meta.title) })));
</script>

<template>
    <nav v-if="crumbs.length" :aria-label="t('common.breadcrumbs')" class="mb-6 text-xs font-semibold text-slate-500"><ol class="flex flex-wrap items-center gap-2"><li><RouterLink to="/" class="rounded-md px-1 py-1 transition hover:text-brand focus-visible:outline-3 focus-visible:outline-brand">{{ t('common.home') }}</RouterLink></li><li v-for="crumb in crumbs" :key="crumb.path" class="flex items-center gap-2"><span aria-hidden="true" class="text-slate-300">/</span><span class="rounded-md bg-white px-2 py-1 font-bold text-brand shadow-[0_1px_5px_rgba(36,20,28,.04)]">{{ crumb.label }}</span></li></ol></nav>
</template>
