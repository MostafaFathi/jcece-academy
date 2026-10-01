<script setup>
defineProps({ tone: { type: String, default: 'info' } });
</script>

<template>
    <div role="alert" class="rounded-xl border p-4 text-sm" :class="tone === 'danger' ? 'border-red-200 bg-red-50 text-red-800' : tone === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-900'"><slot /></div>
</template>
