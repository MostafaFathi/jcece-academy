<script setup>
import { useI18n } from 'vue-i18n';
import { learningErrorKey } from '../../utils/learning';
import BaseAlert from '../ui/BaseAlert.vue';
defineProps({ error: { type: Object, default: null } });
const { t } = useI18n();
</script>
<template><BaseAlert v-if="error" tone="danger"><p>{{ t(learningErrorKey(error)) }}</p><slot /></BaseAlert></template>
