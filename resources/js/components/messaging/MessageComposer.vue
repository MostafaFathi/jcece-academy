<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useVoiceRecorder } from '../../composables/useVoiceRecorder';
import { createRequestId } from '../../utils/request-id';
import MessagingIcon from './MessagingIcon.vue';
import { emojiCategories } from './emoji-catalog';

const props = defineProps({ sending: Boolean, disabled: Boolean, reply: { type: Object, default: null }, sendError: { type: Object, default: null } });
const emit = defineEmits(['send', 'cancel-reply']);
const { t, locale } = useI18n();
const voice = useVoiceRecorder();
const textarea = ref(null);
const body = ref('');
const attachment = ref(null);
const preview = ref('');
const picker = ref(false);
const pickerElement = ref(null);
const emojiTrigger = ref(null);
const activeEmojiCategory = ref('smileys');
const pickerPosition = ref({ top: 0, left: 0, width: 320, height: 350 });
const validation = ref(null);
const displayedEmojis = computed(() => emojiCategories.find((category) => category.id === activeEmojiCategory.value)?.emojis ?? []);
const sendErrorText = computed(() => {
    if (!props.sendError) return null;
    const attachmentError = props.sendError.errors?.attachment?.[0];
    if (attachmentError?.includes('Server media validation must be available')) return t('messaging.voiceServerUnavailable');
    return attachmentError ?? props.sendError.errors?.body?.[0] ?? t('messaging.error');
});
const mimeKind = computed(() => attachment.value?.type.startsWith('image/') ? 'image' : 'voice');
let clientId = null;

defineExpose({ focus: () => textarea.value?.focus() });
watch([body, attachment, () => props.reply?.id], () => { clientId = null; });
watch(voice.file, (file) => { if (file) attachment.value = file; });
watch(attachment, (file) => {
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = file ? URL.createObjectURL(file) : '';
});

function choose(event) {
    const file = event.target.files?.[0];
    event.target.value = '';
    validation.value = null;
    if (!file) return;
    const image = ['image/jpeg', 'image/png', 'image/webp'].includes(file.type);
    const audio = /^(audio\/(webm|ogg|mp4|x-m4a|wav|x-wav)|video\/webm)$/.test(file.type);
    if ((!image && !audio) || file.size > (image ? 5 : 10) * 1024 * 1024) { validation.value = 'fileError'; return; }
    attachment.value = file;
}

function send() {
    if (props.sending || props.disabled || voice.recording.value || (!body.value.trim() && !attachment.value)) return;
    try { clientId ??= createRequestId(); }
    catch { validation.value = 'secureIdUnavailable'; return; }
    validation.value = null;
    emit('send', { payload: { client_id: clientId, body: body.value.trim(), reply_to_id: props.reply?.id }, attachment: attachment.value, acknowledge: () => { body.value = ''; attachment.value = null; clientId = null; emit('cancel-reply'); } });
}

function onComposerKeydown(event) {
    if (event.key !== 'Enter' || event.shiftKey || event.isComposing || event.keyCode === 229) return;
    event.preventDefault();
    send();
}

function replyAttachment(message) {
    return message?.attachment ?? message?.attachments?.[0] ?? null;
}

async function togglePicker() {
    if (picker.value) { picker.value = false; emojiTrigger.value?.focus(); return; }
    const bounds = emojiTrigger.value.getBoundingClientRect();
    const width = Math.min(352, window.innerWidth - 16);
    const height = Math.min(350, window.innerHeight - 16);
    pickerPosition.value = {
        width, height,
        left: Math.min(Math.max(bounds.left + bounds.width / 2 - width / 2, 8), window.innerWidth - width - 8),
        top: bounds.top >= height + 8 ? bounds.top - height - 8 : Math.min(bounds.bottom + 8, window.innerHeight - height - 8),
    };
    picker.value = true;
    await nextTick();
    pickerElement.value?.querySelector('button')?.focus({ preventScroll: true });
}

function insertEmoji(emoji) {
    const start = textarea.value?.selectionStart ?? body.value.length;
    const end = textarea.value?.selectionEnd ?? body.value.length;
    body.value = `${body.value.slice(0, start)}${emoji}${body.value.slice(end)}`;
    void nextTick(() => { textarea.value?.focus(); textarea.value?.setSelectionRange(start + emoji.length, start + emoji.length); });
}

function closePickerOnOutside(event) {
    if (picker.value && !pickerElement.value?.contains(event.target) && !emojiTrigger.value?.contains(event.target)) picker.value = false;
}

function closePicker() { picker.value = false; }
function closePickerOnScroll(event) {
    if (!pickerElement.value?.contains(event.target)) closePicker();
}

onMounted(() => {
    document.addEventListener('pointerdown', closePickerOnOutside);
    window.addEventListener('scroll', closePickerOnScroll, true);
    window.addEventListener('resize', closePicker);
});
onBeforeUnmount(() => {
    if (preview.value) URL.revokeObjectURL(preview.value);
    document.removeEventListener('pointerdown', closePickerOnOutside);
    window.removeEventListener('scroll', closePickerOnScroll, true);
    window.removeEventListener('resize', closePicker);
});
</script>

<template>
    <form class="space-y-3 border-t border-slate-100 bg-white p-4 sm:p-5" @submit.prevent="send">
        <div v-if="reply" class="flex items-center justify-between gap-3 rounded-xl border-s-4 border-brand bg-brand-soft px-3 py-2 text-sm">
            <div class="flex min-w-0 flex-1 items-center gap-2">
                <img v-if="!reply.deleted && replyAttachment(reply)?.kind === 'image'" :src="replyAttachment(reply).url" :alt="replyAttachment(reply).name" class="size-12 shrink-0 rounded-lg object-cover">
                <span v-else-if="!reply.deleted && replyAttachment(reply)?.kind === 'voice'" class="grid size-12 shrink-0 place-items-center rounded-lg bg-white text-brand"><MessagingIcon name="microphone" class="size-5" /></span>
                <p class="min-w-0 truncate text-slate-700"><span class="font-extrabold text-brand">{{ t('messaging.reply') }}:</span> {{ reply.deleted ? t('messaging.deleted') : reply.body || replyAttachment(reply)?.name || t('messaging.attachment') }}</p>
            </div>
            <button type="button" class="grid size-11 shrink-0 place-items-center rounded-lg text-brand transition hover:bg-white focus-visible:outline-3 focus-visible:outline-brand" :aria-label="t('messaging.cancelReply')" @click="emit('cancel-reply')"><MessagingIcon name="close" class="size-4" /></button>
        </div>
        <p v-if="validation || voice.error.value" role="alert" class="rounded-xl bg-red-50 px-3 py-2 text-sm font-semibold text-red-700">{{ t(`messaging.${validation || voice.error.value}`) }}</p>
        <p v-if="sendErrorText" role="alert" class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-800">{{ sendErrorText }}</p>
        <div v-if="preview" class="flex min-w-0 flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3"><img v-if="mimeKind === 'image'" :src="preview" :alt="t('messaging.preview')" class="max-h-32 max-w-40 rounded-lg object-contain"><audio v-else :src="preview" controls class="min-w-0 max-w-full" /><span class="min-w-0 flex-1 break-all text-xs font-semibold text-slate-600">{{ attachment.name }}</span><button type="button" class="grid size-11 shrink-0 place-items-center rounded-lg text-red-700 transition hover:bg-red-50 focus-visible:outline-3 focus-visible:outline-red-600" :aria-label="t('messaging.removeFile')" :disabled="sending" @click="attachment = null"><MessagingIcon name="close" class="size-4" /></button></div>
        <label class="sr-only" for="course-message-body">{{ t('messaging.message') }}</label><textarea ref="textarea" id="course-message-body" v-model="body" :disabled="disabled || sending" maxlength="10000" rows="3" class="w-full resize-y rounded-xl border border-slate-300 bg-white p-3 text-xl leading-8 text-slate-900 placeholder:text-base placeholder:text-slate-400 disabled:bg-slate-50" :placeholder="t('messaging.compose')" @keydown.enter="onComposerKeydown" />
        <div class="flex flex-wrap items-center justify-end gap-2">
            <button ref="emojiTrigger" type="button" class="grid size-11 place-items-center rounded-xl border border-slate-200 bg-white text-slate-700 transition hover:border-brand/30 hover:bg-brand-soft hover:text-brand focus-visible:outline-3 focus-visible:outline-brand disabled:opacity-50" :disabled="disabled || sending" :aria-expanded="picker" aria-controls="course-emoji-picker" aria-haspopup="dialog" :aria-label="t('messaging.emoji')" :title="t('messaging.emoji')" @click="togglePicker"><MessagingIcon name="smile" class="size-5" /></button>
            <div><input id="course-message-file" type="file" accept="image/jpeg,image/png,image/webp,audio/webm,audio/ogg,audio/mp4,audio/wav,.m4a,.webm" :aria-label="t('messaging.attach')" :disabled="disabled || sending || voice.recording.value" class="peer sr-only" @change="choose"><label for="course-message-file" :title="t('messaging.attach')" class="grid size-11 cursor-pointer place-items-center rounded-xl border border-slate-200 bg-white text-slate-700 transition hover:border-brand/30 hover:bg-brand-soft hover:text-brand peer-focus-visible:outline-3 peer-focus-visible:outline-brand peer-disabled:cursor-not-allowed peer-disabled:opacity-50"><MessagingIcon name="paperclip" class="size-5" /></label></div>
            <button type="button" class="inline-flex min-h-11 min-w-11 items-center justify-center gap-2 rounded-xl border px-2 text-sm font-bold transition focus-visible:outline-3 focus-visible:outline-brand disabled:opacity-50" :class="voice.recording.value ? 'border-red-200 bg-red-50 text-red-700' : 'border-slate-200 bg-white text-slate-700 hover:border-brand/30 hover:bg-brand-soft hover:text-brand'" :aria-label="t(voice.recording.value ? 'messaging.stop' : 'messaging.record')" :title="t(voice.recording.value ? 'messaging.stop' : 'messaging.record')" :aria-pressed="voice.recording.value" :disabled="disabled || sending || voice.starting.value" @click="voice.recording.value ? voice.stop() : voice.start()"><MessagingIcon :name="voice.recording.value ? 'stop' : 'microphone'" class="size-5" /><span v-if="voice.recording.value" class="tabular-nums">{{ voice.duration.value }}s</span></button>
            <button type="submit" class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-brand px-5 text-sm font-extrabold text-white shadow-[0_5px_14px_rgba(107,29,50,.16)] transition hover:bg-brand-dark focus-visible:outline-3 focus-visible:outline-brand disabled:cursor-not-allowed disabled:opacity-50" :disabled="disabled || sending || voice.recording.value || (!body.trim() && !attachment)"><MessagingIcon name="send" class="size-4" />{{ t(sending ? 'messaging.sending' : 'messaging.send') }}</button>
        </div>
        <p class="text-xs leading-5 text-slate-500">{{ t('messaging.uploadLimits') }} {{ t('messaging.composerShortcut') }}</p>
        <Teleport to="body">
            <div v-if="picker" ref="pickerElement" id="course-emoji-picker" role="dialog" :aria-label="t('messaging.emoji')" :dir="locale === 'ar' ? 'rtl' : 'ltr'" class="fixed z-[70] flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_18px_50px_rgba(30,20,26,.24)]" :style="{ top: `${pickerPosition.top}px`, left: `${pickerPosition.left}px`, width: `${pickerPosition.width}px`, height: `${pickerPosition.height}px` }" @keydown.esc.stop.prevent="picker = false; emojiTrigger?.focus()">
                <div class="flex gap-0.5 overflow-x-auto border-b border-slate-100 p-2" :aria-label="t('messaging.emojiCategories')">
                    <button v-for="category in emojiCategories" :key="category.id" type="button" class="grid size-9 shrink-0 place-items-center rounded-lg text-lg transition hover:bg-brand-soft focus-visible:outline-3 focus-visible:outline-brand" :class="activeEmojiCategory === category.id ? 'bg-brand-soft ring-1 ring-brand/20' : ''" :aria-label="t(`messaging.emojiCategory.${category.id}`)" :title="t(`messaging.emojiCategory.${category.id}`)" :aria-pressed="activeEmojiCategory === category.id" @click="activeEmojiCategory = category.id">{{ category.icon }}</button>
                </div>
                <p class="px-3 py-2 text-xs font-bold text-slate-600">{{ t(`messaging.emojiCategory.${activeEmojiCategory}`) }}</p>
                <div class="grid flex-1 grid-cols-6 content-start gap-0.5 overflow-y-auto px-2 pb-2 sm:grid-cols-7">
                    <button v-for="emoji in displayedEmojis" :key="emoji" type="button" class="grid size-11 place-items-center rounded-lg text-3xl transition hover:bg-brand-soft focus-visible:outline-3 focus-visible:outline-brand" :aria-label="emoji" @click="insertEmoji(emoji)">{{ emoji }}</button>
                </div>
            </div>
        </Teleport>
    </form>
</template>
