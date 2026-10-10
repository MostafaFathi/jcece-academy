<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useVoiceRecorder } from '../../composables/useVoiceRecorder';
const props = defineProps({ sending: Boolean, disabled: Boolean, reply: { type: Object, default: null } });
const emit = defineEmits(['send', 'cancel-reply']);
const { t } = useI18n();
const voice = useVoiceRecorder();
const textarea = ref(null);
defineExpose({ focus: () => textarea.value?.focus() });
const body = ref(''), attachment = ref(null), preview = ref(''), picker = ref(false), validation = ref(null);
let clientId = null;
const emojis = ['👍', '❤️', '😂', '🎉', '😮', '🙏', '😊', '✅'];
const mimeKind = computed(() => attachment.value?.type.startsWith('image/') ? 'image' : 'voice');
watch([body, attachment, () => props.reply?.id], () => { clientId = null; });
watch(voice.file, (file) => { if (file) attachment.value = file; });
watch(attachment, (file) => { if (preview.value) URL.revokeObjectURL(preview.value); preview.value = file ? URL.createObjectURL(file) : ''; });
function choose(event) {
    const file = event.target.files?.[0]; event.target.value = ''; validation.value = null;
    if (!file) return;
    const image = ['image/jpeg', 'image/png', 'image/webp'].includes(file.type);
    const audio = /^(audio\/(webm|ogg|mp4|x-m4a|wav|x-wav)|video\/webm)$/.test(file.type);
    if ((!image && !audio) || file.size > (image ? 5 : 10) * 1024 * 1024) { validation.value = 'fileError'; return; }
    attachment.value = file;
}
function send() {
    if (props.sending || props.disabled || voice.recording.value || (!body.value.trim() && !attachment.value)) return;
    clientId ??= crypto.randomUUID();
    emit('send', { payload: { client_id: clientId, body: body.value.trim(), reply_to_id: props.reply?.id }, attachment: attachment.value, acknowledge: () => { body.value = ''; attachment.value = null; clientId = null; emit('cancel-reply'); } });
}
onBeforeUnmount(() => { if (preview.value) URL.revokeObjectURL(preview.value); });
</script>
<template>
    <form class="space-y-3 border-t border-slate-200 p-4" @submit.prevent="send">
        <div v-if="reply" class="flex items-center justify-between gap-2 rounded-xl bg-slate-100 p-3 text-sm"><p class="truncate">{{ t('messaging.reply') }}: {{ reply.deleted ? t('messaging.deleted') : reply.body || t('messaging.attachment') }}</p><button type="button" class="min-h-11 min-w-11" :aria-label="t('messaging.cancelReply')" @click="emit('cancel-reply')">×</button></div>
        <p v-if="validation || voice.error.value" role="alert" class="text-sm text-red-700">{{ t(`messaging.${validation || voice.error.value}`) }}</p>
        <div v-if="preview" class="flex items-center gap-3"><img v-if="mimeKind === 'image'" :src="preview" :alt="t('messaging.preview')" class="max-h-32 max-w-40 rounded-xl"><audio v-else :src="preview" controls class="min-w-0 max-w-full"/><button type="button" class="min-h-11 min-w-11" :aria-label="t('messaging.removeFile')" :disabled="sending" @click="attachment = null">×</button></div>
        <label class="sr-only" for="course-message-body">{{ t('messaging.message') }}</label><textarea ref="textarea" id="course-message-body" v-model="body" :disabled="disabled || sending" maxlength="10000" rows="2" class="w-full resize-y rounded-xl border border-slate-300 p-3" :placeholder="t('messaging.compose')" @keydown.ctrl.enter.prevent="send" @keydown.meta.enter.prevent="send"/>
        <div v-if="picker" class="flex flex-wrap gap-1" :aria-label="t('messaging.emoji')"><button v-for="emoji in emojis" :key="emoji" type="button" :aria-label="emoji" class="min-h-11 min-w-11 rounded-lg hover:bg-slate-100" @click="body += emoji; picker = false">{{ emoji }}</button></div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="min-h-11 rounded-xl border px-3" :disabled="disabled || sending" :aria-expanded="picker" @click="picker = !picker">😊 {{ t('messaging.emoji') }}</button>
            <label class="inline-flex min-h-11 cursor-pointer items-center rounded-xl border px-3">{{ t('messaging.attach') }}<input type="file" accept="image/jpeg,image/png,image/webp,audio/webm,audio/ogg,audio/mp4,audio/wav,.m4a,.webm" :disabled="disabled || sending || voice.recording.value" class="ms-2 max-w-40 text-xs" @change="choose"></label>
            <button type="button" class="min-h-11 rounded-xl border px-3" :disabled="disabled || sending || voice.starting.value" @click="voice.recording.value ? voice.stop() : voice.start()">{{ t(voice.recording.value ? 'messaging.stop' : 'messaging.record') }} <span v-if="voice.recording.value">{{ voice.duration.value }}s</span></button>
            <button type="submit" class="min-h-11 rounded-xl bg-brand px-5 font-bold text-white disabled:opacity-50" :disabled="disabled || sending || voice.recording.value || (!body.trim() && !attachment)">{{ t(sending ? 'messaging.sending' : 'messaging.send') }}</button>
        </div>
        <p class="text-xs text-slate-500">{{ t('messaging.limits') }}</p>
    </form>
</template>
