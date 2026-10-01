<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
const props = defineProps({ group: { type: String, required: true }, status: { type: String, required: true } });
const { t } = useI18n();
const tone = { completed: 'bg-emerald-100 text-emerald-900', paid: 'bg-emerald-100 text-emerald-900', published: 'bg-emerald-100 text-emerald-900', issued: 'bg-emerald-100 text-emerald-900', resolved: 'bg-emerald-100 text-emerald-900', pending_review: 'bg-amber-100 text-amber-950', pending: 'bg-amber-100 text-amber-950', awaiting_payment: 'bg-amber-100 text-amber-950', waiting_for_student: 'bg-amber-100 text-amber-950', rejected: 'bg-red-100 text-red-800', revoked: 'bg-red-100 text-red-800', closed: 'bg-slate-200 text-slate-800' };
const statusTone = computed(() => props.group === 'orderStatus' && props.status === 'paid' ? 'bg-amber-100 text-amber-950' : tone[props.status] ?? 'bg-slate-100 text-slate-700');
</script>
<template><span class="inline-flex rounded-full px-3 py-1 text-xs font-bold" :class="statusTone">{{ t(`operations.${group}.${status}`) }}</span></template>
