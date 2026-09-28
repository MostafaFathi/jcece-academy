<script setup>
import { useI18n } from 'vue-i18n';
import { commerceError } from '../../utils/commerce';
import BaseAlert from '../ui/BaseAlert.vue';
defineProps({ error: { type: Object, default: null } });
const { t } = useI18n();
</script>
<template><BaseAlert v-if="error" tone="danger"><p>{{ commerceError(error, t) }}</p><ul v-if="Object.keys(error.errors ?? {}).length" class="mt-2 list-inside list-disc text-sm"><li v-for="(messages, field) in error.errors" :key="field">{{ messages[0] }}</li></ul><slot /></BaseAlert></template>
