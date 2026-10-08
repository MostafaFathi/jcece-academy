<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../../stores/auth';

const auth = useAuthStore();
const router = useRouter();
const { t } = useI18n();
const open = ref(false);
const root = ref(null);

function closeOnOutsideClick(event) {
    if (open.value && !root.value?.contains(event.target)) {
        open.value = false;
    }
}

onMounted(() => document.addEventListener('pointerdown', closeOnOutsideClick));
onBeforeUnmount(() => document.removeEventListener('pointerdown', closeOnOutsideClick));

async function signOut() {
    try {
        await auth.signOut();
    } catch {} finally {
        await router.push({ name: 'home' });
    }
}
</script>

<template>
    <div ref="root" class="relative" @keydown.esc="open = false">
        <button type="button" class="flex min-h-11 items-center gap-2 rounded-xl border border-[#e9e1e4] bg-white py-1.5 pe-3 ps-1.5 shadow-[0_3px_12px_rgba(36,20,28,.05)] transition hover:border-brand/25 hover:bg-[#fcfafb] focus-visible:outline-3 focus-visible:outline-brand" aria-haspopup="menu" :aria-label="t('common.accountMenu')" :aria-expanded="open" @click="open = !open"><span class="grid size-8 shrink-0 place-items-center rounded-lg bg-[linear-gradient(135deg,#7b203a,#4f1425)] text-sm font-black text-white">{{ auth.user?.name?.charAt(0) }}</span><span class="hidden max-w-32 truncate text-xs font-extrabold text-slate-800 sm:block">{{ auth.user?.name }}</span><svg aria-hidden="true" class="size-3.5 shrink-0 text-slate-500 transition" :class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 7 5 5 5-5" /></svg></button>
        <div v-if="open" role="menu" class="absolute end-0 z-30 mt-2 w-64 overflow-hidden rounded-2xl border border-[#e9e1e4] bg-white p-2 shadow-[0_20px_55px_rgba(36,20,28,.16)]"><div class="rounded-xl bg-[#faf7f8] px-3 py-3"><p class="truncate text-sm font-extrabold text-slate-900">{{ auth.user?.name }}</p><p class="mt-0.5 truncate text-xs text-slate-500">{{ auth.user?.email }}</p></div><RouterLink :to="{ name: 'profile' }" role="menuitem" class="mt-2 block rounded-xl px-3 py-2.5 text-sm font-bold text-brand transition hover:bg-brand-soft focus-visible:outline-3 focus-visible:outline-brand" @click="open = false">{{ t('profile.title') }}</RouterLink><button type="button" role="menuitem" class="w-full rounded-xl px-3 py-2.5 text-start text-sm font-bold text-red-700 transition hover:bg-red-50 focus-visible:outline-3 focus-visible:outline-red-600 disabled:opacity-50" :disabled="auth.logoutLoading" @click="signOut">{{ t('common.logout') }}</button></div>
    </div>
</template>
