<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';
import * as api from '../api/notifications';
const router = useRouter(), auth = useAuthStore();
const { t, locale } = useI18n();
const items = ref([]), meta = ref({ current_page: 1, last_page: 1, unread_count: 0 }), unread = ref(false), category = ref(''), loading = ref(false), error = ref(null), busy = ref(null);
const categories = ['learning', 'assessment', 'certificate', 'commerce', 'messaging', 'support', 'content', 'security', 'system', 'announcement'];
let generation = 0;
async function load(page = 1) {
    const token = ++generation; loading.value = true; error.value = null;
    try { const result = await api.fetchNotifications({ page, unread: unread.value ? 1 : 0, category: category.value || undefined }); if (token === generation) { items.value = result.data; meta.value = result.meta; } }
    catch (failure) { if (token === generation) error.value = failure; }
    finally { if (token === generation) loading.value = false; }
}
async function action(item, navigate = false) {
    if (busy.value) return; busy.value = item.id; error.value = null;
    try { if (navigate) { const result = await api.openNotification(item.id); if (!api.safeNotificationPath(result.path)) throw new Error(); await router.push(result.path); } else { await api.readNotification(item.id); await load(meta.value.current_page); } api.notifyInboxChanged(); }
    catch (failure) { error.value = failure; }
    finally { busy.value = null; }
}
async function readAll() { if (busy.value) return; busy.value = 'all'; try { await api.readAllNotifications(); await load(); api.notifyInboxChanged(); } catch (failure) { error.value = failure; } finally { busy.value = null; } }
watch([unread, category], () => load()); onMounted(load);
</script>
<template>
    <section class="mx-auto max-w-4xl space-y-5 p-4 sm:p-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <header class="rounded-3xl bg-primary p-6 text-brand-dark"><h1 class="text-2xl font-black">{{ t('notifications.title') }}</h1><p>{{ t('notifications.unread', { count: meta.unread_count }) }}</p></header>
        <nav class="flex flex-wrap gap-3 text-sm font-bold text-brand"><RouterLink to="/notification-settings">{{ t('notifications.preferences') }}</RouterLink><RouterLink v-if="auth.can('announcements.manage')" to="/announcement-management">{{ t('notifications.manageAnnouncements') }}</RouterLink><RouterLink v-if="auth.can('notifications.manage')" to="/notification-management">{{ t('notifications.management') }}</RouterLink></nav>
        <div class="flex flex-wrap items-center gap-3"><label class="flex min-h-11 items-center gap-2"><input v-model="unread" type="checkbox">{{ t('notifications.unreadOnly') }}</label><label class="flex items-center gap-2">{{ t('notifications.category') }}<select v-model="category" class="min-h-11 rounded-xl border border-slate-300 px-3"><option value="">{{ t('notifications.all') }}</option><option v-for="value in categories" :key="value" :value="value">{{ t(`notifications.categories.${value}`) }}</option></select></label><button type="button" class="min-h-11 rounded-xl border border-slate-200 px-3 text-brand" :disabled="!!busy" @click="readAll">{{ t('notifications.readAll') }}</button></div>
        <p v-if="loading" role="status">{{ t('notifications.loading') }}</p><div v-if="error" role="alert" class="rounded-xl bg-red-50 p-4 text-red-800">{{ t(error.status === 404 ? 'notifications.unavailable' : 'notifications.error') }} <button type="button" class="min-h-11 underline" @click="load(meta.current_page)">{{ t('notifications.retry') }}</button></div>
        <p v-if="!loading && !error && !items.length" class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-slate-500">{{ t('notifications.empty') }}</p>
        <article v-for="item in items" :key="item.id" class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4" :class="!item.read_at ? 'border-s-4 border-s-brand' : ''"><button type="button" class="min-w-0 flex-1 text-start" :disabled="!!busy" @click="action(item, true)"><p class="font-bold">{{ item.titles?.[locale] ?? item.title }} <span v-if="item.count > 1">({{ item.count }})</span></p><p class="mt-1 text-xs text-slate-500">{{ t(`notifications.categories.${item.category}`) }} · {{ new Date(item.created_at).toLocaleString(locale) }}</p></button><button v-if="!item.read_at" type="button" class="min-h-11 rounded-xl bg-brand-soft px-3 text-sm font-bold text-brand" :disabled="!!busy" @click="action(item)">{{ t('notifications.read') }}</button></article>
        <div v-if="meta.last_page > 1" class="flex items-center justify-between"><button type="button" class="min-h-11 px-3" :disabled="loading || meta.current_page <= 1" @click="load(meta.current_page - 1)">{{ t('notifications.previous') }}</button><span>{{ meta.current_page }}/{{ meta.last_page }}</span><button type="button" class="min-h-11 px-3" :disabled="loading || meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)">{{ t('notifications.next') }}</button></div>
    </section>
</template>
