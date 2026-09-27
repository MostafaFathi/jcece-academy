<script setup>
import { useI18n } from 'vue-i18n';
import EmptyState from '../components/ui/EmptyState.vue';
import PageHeading from '../components/ui/PageHeading.vue';
defineProps({ titleKey: { type: String, required: true } });
const { t } = useI18n();
</script>

<template><div class="space-y-6"><PageHeading :title="t(titleKey)" /><EmptyState :title="t('common.upcoming')" :description="t('dashboard.next')" /></div></template>
