<script setup>
defineOptions({ inheritAttrs: false });
defineProps({ modelValue: { type: [String, Number], default: '' }, label: { type: String, required: true }, error: { type: String, default: '' }, id: { type: String, required: true } });
defineEmits(['update:modelValue']);
</script>

<template>
    <div>
        <label :for="id" class="mb-2 block text-sm font-bold text-slate-700">{{ label }}</label>
        <input
            :id="id"
            v-bind="$attrs"
            :value="modelValue"
            class="min-h-11 w-full rounded-xl border bg-white px-4 py-2.5 text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-brand focus:ring-3 focus:ring-brand/15"
            :class="error ? 'border-red-500' : 'border-slate-300'"
            :aria-invalid="Boolean(error)"
            :aria-describedby="error ? `${id}-error` : undefined"
            @input="$emit('update:modelValue', $event.target.value)"
        >
        <p v-if="error" :id="`${id}-error`" class="mt-1.5 text-sm text-red-700">{{ error }}</p>
    </div>
</template>
