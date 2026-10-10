<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../../stores/auth';
import { fetchMessageNotifications } from '../../api/messaging';
const props = defineProps({ area: { type: String, required: true } });
const auth = useAuthStore(), { t } = useI18n(), count = ref(0);
const enabled = computed(() => ['student', 'instructor'].includes(props.area) && auth.permissions?.includes('messaging.view'));
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
<template><RouterLink v-if="enabled" :to="{ name: area === 'instructor' ? 'instructor.messages' : 'student.messages' }" class="inline-flex min-h-11 items-center gap-1 rounded-xl border px-2 text-sm text-brand" :aria-label="`${t('messaging.title')}: ${count}`"><span aria-hidden="true">✉</span><span aria-live="polite">{{ count }}</span></RouterLink></template>
