<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../../stores/auth';
import * as api from '../../api/notifications';
const auth = useAuthStore(), router = useRouter();
const { t, locale } = useI18n();
const open = ref(false), items = ref([]), count = ref(0), loading = ref(false), error = ref(false), trigger = ref(null), panel = ref(null);
let timer, generation = 0;
async function refresh() {
    if (!auth.isAuthenticated || document.hidden || !navigator.onLine || loading.value) return;
    const token = ++generation, userId = auth.user.id;
    loading.value = true;
    try { const result = await api.fetchNotifications({ per_page: 5 }); if (token === generation && userId === auth.user?.id) { items.value = result.data; count.value = result.meta.unread_count; error.value = false; } }
    catch { if (token === generation) error.value = true; }
    finally { if (token === generation) loading.value = false; }
}
async function toggle() { open.value = !open.value; if (open.value) { await refresh(); await nextTick(); panel.value?.querySelector('button, a')?.focus(); } }
async function navigate(item) {
    try { const result = await api.openNotification(item.id); if (!api.safeNotificationPath(result.path)) throw new Error(); open.value = false; api.notifyInboxChanged(); await router.push(result.path); }
    catch { error.value = true; }
}
function close() { open.value = false; trigger.value?.focus(); }
function outside(event) { if (open.value && !panel.value?.contains(event.target) && !trigger.value?.contains(event.target)) open.value = false; }
watch(() => auth.user?.id, () => { generation++; items.value = []; count.value = 0; loading.value = false; open.value = false; void refresh(); });
onMounted(() => { void refresh(); timer = setInterval(refresh, 30000); document.addEventListener('visibilitychange', refresh); window.addEventListener('online', refresh); window.addEventListener('notifications:changed', refresh); document.addEventListener('pointerdown', outside); });
onBeforeUnmount(() => { generation++; clearInterval(timer); document.removeEventListener('visibilitychange', refresh); window.removeEventListener('online', refresh); window.removeEventListener('notifications:changed', refresh); document.removeEventListener('pointerdown', outside); });
</script>
<template>
    <div v-if="auth.isAuthenticated" class="relative" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <button ref="trigger" type="button" class="relative grid size-11 place-items-center rounded-xl border border-slate-200 text-brand hover:bg-brand-soft focus-visible:outline-3 focus-visible:outline-brand" :aria-label="t('notifications.bell', { count })" :aria-expanded="open" aria-controls="notification-dropdown" @click="toggle"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" /></svg><span v-if="count" class="absolute -end-1 -top-1 rounded-full bg-primary px-1.5 text-[10px] font-bold text-brand-dark">{{ count > 99 ? '99+' : count }}</span></button>
        <Teleport to="body"><section v-if="open" id="notification-dropdown" ref="panel" :dir="locale === 'ar' ? 'rtl' : 'ltr'" class="fixed inset-x-3 top-20 z-[80] max-h-[75dvh] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-4 shadow-xl sm:start-auto sm:end-6 sm:w-96" :aria-label="t('notifications.title')" @keydown.esc.stop.prevent="close">
            <div class="flex items-center justify-between gap-3"><h2 class="font-black">{{ t('notifications.title') }}</h2><button type="button" class="grid size-11 place-items-center rounded-xl hover:bg-slate-100" :aria-label="t('notifications.close')" @click="close">×</button></div>
            <p v-if="loading" role="status">{{ t('notifications.loading') }}</p><button v-if="error" type="button" class="min-h-11 text-red-700" @click="refresh">{{ t('notifications.error') }}</button>
            <p v-if="!loading && !items.length" class="py-6 text-sm text-slate-500">{{ t('notifications.empty') }}</p>
            <button v-for="item in items" :key="item.id" type="button" class="block min-h-14 w-full rounded-xl p-3 text-start text-sm hover:bg-brand-soft" :class="!item.read_at ? 'font-bold text-brand' : 'text-slate-600'" @click="navigate(item)"><span class="block break-words">{{ item.titles?.[locale] ?? item.title }}</span><span v-if="item.descriptions?.[locale] ?? item.description" class="mt-1 block break-words text-xs font-normal text-slate-600">{{ item.descriptions?.[locale] ?? item.description }}</span></button>
            <RouterLink to="/notifications" class="block min-h-11 border-t border-slate-100 py-3 text-center font-bold text-brand" @click="open = false">{{ t('notifications.all') }}</RouterLink>
        </section></Teleport>
    </div>
</template>
