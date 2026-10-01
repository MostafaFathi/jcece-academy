<script setup>
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../ui/BaseButton.vue';
const props = defineProps({ open: Boolean, title: { type: String, required: true }, busy: Boolean, error: { type: Object, default: null }, minLength: { type: Number, default: 5 } });
const emit = defineEmits(['confirm', 'cancel']);
const { t } = useI18n();
const reason = ref('');
watch(() => props.open, (open) => { if (open) reason.value = ''; });
function submit() { if (!props.busy && reason.value.trim().length >= props.minLength) emit('confirm', reason.value.trim()); }
</script>
<template>
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" role="presentation" @click.self="emit('cancel')"><div role="dialog" aria-modal="true" :aria-label="title" class="w-full max-w-lg space-y-4 rounded-3xl bg-white p-6 shadow-2xl"><h2 class="text-xl font-black">{{ title }}</h2><label for="operation-reason" class="block text-sm font-bold">{{ t('operations.reason') }}</label><textarea id="operation-reason" v-model="reason" rows="4" maxlength="2000" class="w-full rounded-xl border border-slate-300 px-4 py-3" :disabled="busy" /><p v-if="error?.errors?.reason || error?.errors?.rejection_reason" role="alert" class="text-sm text-red-700">{{ (error.errors.reason ?? error.errors.rejection_reason)[0] }}</p><div class="flex gap-3"><BaseButton :disabled="reason.trim().length < minLength" :loading="busy" @click="submit">{{ t('operations.confirm') }}</BaseButton><BaseButton variant="secondary" :disabled="busy" @click="emit('cancel')">{{ t('admin.cancel') }}</BaseButton></div></div></div>
</template>
