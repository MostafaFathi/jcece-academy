<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({ redirect: { type: String, default: '' } });
const { t, locale } = useI18n();

const href = computed(() => {
    const query = new URLSearchParams({ locale: locale.value });
    if (props.redirect) query.set('redirect', props.redirect);
    return `/auth/google/redirect?${query.toString()}`;
});
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center gap-3 text-xs font-semibold text-slate-500" aria-hidden="true"><span class="h-px flex-1 bg-slate-200" /><span>{{ t('auth.or') }}</span><span class="h-px flex-1 bg-slate-200" /></div>
        <a :href="href" class="inline-flex min-h-11 w-full items-center justify-center gap-3 rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-extrabold text-slate-800 shadow-sm transition-colors hover:border-brand hover:bg-slate-50 focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-brand">
            <span class="grid size-6 place-items-center rounded-full border border-slate-200 font-bold text-[#4285f4]" aria-hidden="true">G</span>
            {{ t('auth.google') }}
        </a>
    </div>
</template>
