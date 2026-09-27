<script setup>
defineProps({ title: { type: String, required: true }, description: { type: String, default: '' } });
</script>

<template>
    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center"><div class="mx-auto mb-4 grid size-12 place-items-center rounded-full bg-brand-soft text-xl text-brand">✦</div><h3 class="font-extrabold text-slate-900">{{ title }}</h3><p v-if="description" class="mt-2 text-sm text-slate-600">{{ description }}</p><div class="mt-5"><slot /></div></div>
</template>
