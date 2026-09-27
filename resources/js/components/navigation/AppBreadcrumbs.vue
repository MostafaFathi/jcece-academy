<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';

const route = useRoute();
const { t } = useI18n();
const crumbs = computed(() => route.matched.filter((item) => item.meta.title).map((item) => ({ path: item.path, label: t(item.meta.title) })));
</script>

<template>
    <nav v-if="crumbs.length" aria-label="Breadcrumb" class="mb-5 text-sm text-slate-500"><ol class="flex flex-wrap items-center gap-2"><li><RouterLink to="/" class="hover:text-brand">{{ t('common.home') }}</RouterLink></li><li v-for="crumb in crumbs" :key="crumb.path" class="flex items-center gap-2"><span aria-hidden="true">/</span><span class="font-semibold text-slate-700">{{ crumb.label }}</span></li></ol></nav>
</template>
