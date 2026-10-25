<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps({
    comment: { type: Object, required: true },
    instructorId: { type: Number, default: null },
});
defineEmits(['reply']);
const { t, locale } = useI18n();
const dateFormatter = computed(() => new Intl.DateTimeFormat(locale.value === 'ar' ? 'ar-PS' : 'en-US', { dateStyle: 'medium', timeStyle: 'short' }));
</script>

<template>
    <div class="min-w-0">
        <div class="flex min-w-0 flex-wrap items-center gap-2">
            <span class="grid size-9 shrink-0 place-items-center rounded-full bg-brand-soft text-sm font-black text-brand" aria-hidden="true">{{ comment.author?.name?.slice(0, 1) }}</span>
            <span class="min-w-0 truncate text-sm font-bold text-slate-950">{{ comment.author?.name }}</span>
            <span v-if="comment.author?.id === instructorId" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-900">{{ t('learning.instructor') }}</span>
            <time :datetime="comment.created_at" class="ms-auto text-xs text-slate-500">{{ dateFormatter.format(new Date(comment.created_at)) }}</time>
        </div>
        <p v-if="comment.body" class="mt-2 whitespace-pre-wrap break-words text-sm leading-7 text-slate-800">{{ comment.body }}</p>
        <a v-if="comment.attachment?.is_image" :href="comment.attachment.url" target="_blank" rel="noopener noreferrer" class="mt-2 block w-fit max-w-full overflow-hidden rounded-xl border border-slate-200 focus-visible:outline-2 focus-visible:outline-brand">
            <img :src="comment.attachment.url" :alt="comment.attachment.name" loading="lazy" class="max-h-64 max-w-full object-contain" />
            <span class="block max-w-64 truncate px-2 py-1 text-xs text-slate-600">{{ comment.attachment.name }}</span>
        </a>
        <a v-else-if="comment.attachment" :href="comment.attachment.url" class="mt-2 inline-flex max-w-full items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-brand hover:bg-brand-soft focus-visible:outline-2 focus-visible:outline-brand">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5 shrink-0" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Zm0 0v6h6M8 13h8m-8 4h5" /></svg>
            <span class="min-w-0 truncate">{{ comment.attachment.name }}</span>
        </a>
        <button type="button" class="mt-1 min-h-9 rounded-lg px-2 text-xs font-bold text-brand hover:bg-brand-soft focus-visible:outline-2 focus-visible:outline-brand" @click="$emit('reply', comment)">{{ t('lessonDiscussion.reply') }}</button>
    </div>
</template>
