<script setup>
defineProps({ open: Boolean, title: { type: String, required: true } });
defineEmits(['close']);
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/50 p-4" role="presentation" @click.self="$emit('close')">
        <section role="dialog" aria-modal="true" :aria-label="title" class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
            <div class="mb-4 flex items-center justify-between gap-4"><h2 class="text-xl font-extrabold">{{ title }}</h2><button type="button" class="rounded-lg p-2 hover:bg-slate-100" @click="$emit('close')">×</button></div>
            <slot />
        </section>
    </div>
</template>
