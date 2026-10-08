<script setup>
import { useI18n } from 'vue-i18n';
import { setLocale } from '../../i18n';
import { api } from '../../api/client';
import { useAuthStore } from '../../stores/auth';
import { getActivePinia } from 'pinia';
import { ref } from 'vue';

const { locale, t } = useI18n();
const activePinia = getActivePinia();
const auth = activePinia ? useAuthStore(activePinia) : null;
const failed = ref(false);
async function toggleLocale() {
    const selected = locale.value === 'ar' ? 'en' : 'ar';
    failed.value = false;
    if (auth?.user) {
        try {
            await api.patch('/api/v1/me/locale', { locale: selected });
            auth.setUser({ ...auth.user, preferred_locale: selected });
        } catch {
            failed.value = true;
            return;
        }
    }
    setLocale(selected);
}
</script>

<template>
    <div><button type="button" class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-transparent px-3 text-xs font-extrabold text-slate-600 transition hover:border-slate-200 hover:bg-slate-50 hover:text-brand focus-visible:outline-3 focus-visible:outline-brand" @click="toggleLocale"><svg aria-hidden="true" class="size-4 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9" /><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18" /></svg>{{ t('common.language') }}</button><span v-if="failed" role="alert" class="block text-xs text-red-700">{{ t('profile.error') }}</span></div>
</template>
