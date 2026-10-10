<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../../stores/auth';
import { useRoute } from 'vue-router';
import { fetchMessageNotifications } from '../../api/messaging';
import MessagingIcon from './MessagingIcon.vue';
const props = defineProps({ area: { type: String, required: true } });
const auth = useAuthStore(), route = useRoute(), { t } = useI18n(), count = ref(0);
const destination = computed(() => props.area === 'instructor' ? 'instructor.messages' : 'student.messages');
const enabled = computed(() => ['student', 'instructor'].includes(props.area) && auth.can('messaging.view') && route.name !== destination.value);
let timer, disposed = false, busy = false, delay = 15000;
async function update() {
    clearTimeout(timer);
    if (disposed || !enabled.value || busy) return;
    busy = true;
    try {
        if (!document.hidden && navigator.onLine) { count.value = (await fetchMessageNotifications()).unread_count; delay = 15000; }
    } catch { count.value = 0; delay = 30000; }
    finally { busy = false; if (!disposed && enabled.value) timer = setTimeout(update, delay); }
}
watch(enabled, (value) => { if (value) update(); else { clearTimeout(timer); count.value = 0; } }, { immediate: true });
onBeforeUnmount(() => { disposed = true; clearTimeout(timer); });
</script>
<template>
    <RouterLink v-if="enabled" :to="{ name: destination }" class="fixed bottom-[calc(1rem+env(safe-area-inset-bottom))] end-4 z-40 grid size-14 place-items-center rounded-2xl border border-white/20 bg-brand text-white shadow-[0_14px_35px_rgba(68,20,37,.32)] transition hover:-translate-y-1 hover:bg-brand-dark focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-brand sm:bottom-7 sm:end-7" :aria-label="count ? `${t('messaging.conversations')}: ${t('messaging.unreadCount', { count })}` : t('messaging.conversations')" :title="t('messaging.conversations')">
        <MessagingIcon name="messages" class="size-6" />
        <span v-if="count" aria-live="polite" class="absolute -start-2 -top-2 grid min-w-6 place-items-center rounded-full border-2 border-white bg-accent px-1 text-xs font-black text-brand-dark">{{ count > 99 ? '99+' : count }}</span>
    </RouterLink>
</template>
