<script setup>
defineProps({ type: { type: String, default: 'button' }, variant: { type: String, default: 'primary' }, loading: Boolean, disabled: Boolean });
defineEmits(['click']);
</script>

<template>
    <button
        :type="type"
        :disabled="disabled || loading"
        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-5 py-2.5 text-sm font-extrabold leading-5 transition-all duration-150 focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-brand active:translate-y-px disabled:cursor-not-allowed disabled:opacity-60 disabled:shadow-none motion-reduce:transform-none"
        :class="variant === 'primary' ? 'border border-brand bg-brand text-white shadow-[0_5px_14px_rgba(107,29,50,.16)] hover:-translate-y-0.5 hover:bg-brand-dark hover:shadow-[0_9px_22px_rgba(107,29,50,.22)]' : variant === 'danger' ? 'border border-red-700 bg-red-700 text-white shadow-sm hover:bg-red-800' : 'border border-[#ded5d9] bg-white text-brand shadow-[0_2px_8px_rgba(36,20,28,.04)] hover:border-brand/30 hover:bg-brand-soft'"
        @click="$emit('click', $event)"
    >
        <span v-if="loading" class="size-4 animate-spin rounded-full border-2 border-current border-t-transparent" aria-hidden="true" />
        <slot />
    </button>
</template>
