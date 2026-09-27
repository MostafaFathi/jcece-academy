<script setup>
defineProps({ type: { type: String, default: 'button' }, variant: { type: String, default: 'primary' }, loading: Boolean, disabled: Boolean });
defineEmits(['click']);
</script>

<template>
    <button
        :type="type"
        :disabled="disabled || loading"
        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-5 py-2.5 text-sm font-bold transition focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:cursor-not-allowed disabled:opacity-60"
        :class="variant === 'primary' ? 'bg-brand text-white shadow-sm hover:bg-brand-dark' : variant === 'danger' ? 'bg-red-600 text-white hover:bg-red-700' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50'"
        @click="$emit('click', $event)"
    >
        <span v-if="loading" class="size-4 animate-spin rounded-full border-2 border-current border-t-transparent" aria-hidden="true" />
        <slot />
    </button>
</template>
