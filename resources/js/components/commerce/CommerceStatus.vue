<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { orderStatuses, paymentStatuses } from '../../utils/commerce';
const props = defineProps({ status: { type: String, required: true }, kind: { type: String, default: 'order' } });
const { t } = useI18n();
const label = computed(() => (props.kind === 'payment' ? paymentStatuses : orderStatuses).includes(props.status) ? t(`commerce.${props.kind}Statuses.${props.status}`) : t('common.notSpecified'));
</script>
<template><span class="inline-flex rounded-full px-3 py-1.5 text-xs font-bold" :class="['paid', 'completed'].includes(status) ? 'bg-emerald-50 text-emerald-800' : ['rejected', 'cancelled', 'refunded'].includes(status) ? 'bg-red-50 text-red-800' : 'bg-amber-50 text-amber-900'">{{ label }}</span></template>
