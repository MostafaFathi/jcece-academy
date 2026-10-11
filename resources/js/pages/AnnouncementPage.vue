<script setup>
import { watch, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import * as api from '../api/notifications';
const { locale, t } = useI18n(), route = useRoute();const item = ref(null), error = ref(false);
let generation = 0;
watch(() => route.params.id, async (id) => { const token = ++generation; item.value = null; error.value = false; try { const result = await api.fetchAnnouncement(id); if (token === generation) { item.value = result; api.notifyInboxChanged(); } } catch { if (token === generation) error.value = true; } }, { immediate: true });
</script>
<template><article class="mx-auto max-w-3xl space-y-5 p-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'"><p v-if="error" role="alert">{{ t('notifications.unavailable') }}</p><template v-else-if="item"><h1 class="text-2xl font-black">{{ item.title[locale] }}</h1><p class="whitespace-pre-wrap break-words rounded-2xl border border-slate-200 bg-white p-5 leading-8">{{ item.body[locale] }}</p></template><p v-else role="status">{{ t('notifications.loading') }}</p></article></template>
