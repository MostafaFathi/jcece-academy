<script setup>
defineProps({ currentPage: { type: Number, default: 1 }, lastPage: { type: Number, default: 1 } });
defineEmits(['change']);
</script>

<template>
    <nav aria-label="Pagination" class="flex items-center justify-between gap-3"><button type="button" class="rounded-lg border px-3 py-2 disabled:opacity-40" :disabled="currentPage <= 1" @click="$emit('change', currentPage - 1)">‹</button><span class="text-sm text-slate-600">{{ currentPage }} / {{ lastPage }}</span><button type="button" class="rounded-lg border px-3 py-2 disabled:opacity-40" :disabled="currentPage >= lastPage" @click="$emit('change', currentPage + 1)">›</button></nav>
</template>
