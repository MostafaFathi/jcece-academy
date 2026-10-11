<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { publicMediaUrl } from '../../utils/catalog';

const props = defineProps({ id: { type: String, required: true }, label: { type: String, required: true }, source: { type: String, default: null }, file: { type: File, default: null }, error: { type: String, default: '' } });
const emit = defineEmits(['select', 'clear']);
const { t } = useI18n();
const input = ref(null);
const preview = ref(null);
const imageSource = computed(() => preview.value || publicMediaUrl(props.source));

watch(() => props.file, (file) => {
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = file ? URL.createObjectURL(file) : null;
});
onBeforeUnmount(() => { if (preview.value) URL.revokeObjectURL(preview.value); });

function select(event) {
    emit('select', event.target.files?.[0] ?? null);
}

function clear() {
    if (input.value) input.value.value = '';
    emit('clear');
}
</script>

<template>
    <div>
        <span class="mb-2 block text-sm font-bold">{{ label }}</span>
        <div class="flex flex-wrap items-center gap-4">
            <label :for="id" class="grid size-28 shrink-0 cursor-pointer place-items-center overflow-hidden rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 text-3xl font-black text-brand focus-within:outline-3 focus-within:outline-brand">
                <img v-if="imageSource" :src="imageSource" :alt="label" class="size-full object-cover">
                <span v-else aria-hidden="true">+</span>
            </label>
            <div class="min-w-0 space-y-2">
                <label :for="id" class="block text-sm font-bold text-brand">{{ t('admin.selectCatalogImage') }}</label>
                <input :id="id" ref="input" type="file" accept="image/jpeg,image/png,image/webp" class="block max-w-full text-sm file:me-3 file:rounded-lg file:border-0 file:bg-brand-soft file:px-4 file:py-2 file:font-bold file:text-brand" :aria-invalid="Boolean(error)" @change="select">
                <button v-if="imageSource" type="button" class="text-sm font-bold text-red-700 underline" @click="clear">{{ t('admin.removeCatalogImage') }}</button>
                <p class="text-xs text-slate-500">{{ t('admin.catalogImageHint') }}</p>
                <p v-if="error" role="alert" class="text-sm text-red-700">{{ error }}</p>
            </div>
        </div>
    </div>
</template>
