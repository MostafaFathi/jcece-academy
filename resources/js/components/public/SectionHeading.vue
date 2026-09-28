<script setup>
defineProps({ eyebrow: { type: String, default: '' }, title: { type: String, required: true }, description: { type: String, default: '' }, centered: Boolean });
</script>

<template>
    <div :class="centered ? 'mx-auto max-w-3xl text-center' : 'max-w-3xl'"><p v-if="eyebrow" class="mb-3 text-xs font-black tracking-[0.22em] text-brand uppercase">{{ eyebrow }}</p><h2 class="text-3xl font-black leading-tight tracking-tight text-slate-950 sm:text-4xl">{{ title }}</h2><p v-if="description" class="mt-4 text-base leading-8 text-slate-600 sm:text-lg">{{ description }}</p><slot /></div>
</template>
