<script setup>
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { createGroup, fetchMessagingStudents, startPrivate, updateMembers } from '../../api/messaging';
const props = defineProps({ course: { type: Object, required: true }, mode: { type: String, default: 'group' }, conversation: { type: Object, default: null } });
const emit = defineEmits(['done', 'cancel']);
const { t } = useI18n();
const title = ref(''), kind = ref('selected'), studentIds = ref(props.conversation?.student_ids ?? []), students = ref([]), studentSearch = ref(''), page = ref(1), lastPage = ref(1), step = ref(1), busy = ref(false), loading = ref(false), error = ref(null);
const needsStudents = computed(() => props.mode !== 'group' || kind.value === 'selected');
let loadToken = 0;
async function load() {
    const token = ++loadToken; loading.value = true;
    try { const result = await fetchMessagingStudents(props.course.id, { search: studentSearch.value, page: page.value }); if (token === loadToken) { students.value = result.items; lastPage.value = result.meta.last_page; } }
    catch (failure) { if (token === loadToken) error.value = failure; }
    finally { if (token === loadToken) loading.value = false; }
}
watch(() => props.course.id, () => { page.value = 1; load(); }, { immediate: true });
async function submit() {
    if (busy.value) return;
    busy.value = true; error.value = null;
    try {
        const result = props.mode === 'private' ? await startPrivate(props.course.id, studentIds.value[0]) : props.mode === 'members' ? await updateMembers(props.conversation.id, studentIds.value) : await createGroup(props.course.id, { title: title.value, kind: kind.value, student_ids: kind.value === 'selected' ? studentIds.value : [] });
        emit('done', result);
    } catch (failure) { error.value = failure; }
    finally { busy.value = false; }
}
</script>
<template>
    <section class="space-y-4 rounded-2xl border border-brand bg-white p-4" :aria-label="t('messaging.setup')" @keydown.esc="emit('cancel')">
        <h3 class="font-bold">{{ t(mode === 'private' ? 'messaging.startPrivate' : mode === 'members' ? 'messaging.manageMembers' : 'messaging.newGroup') }}</h3>
        <p v-if="error" role="alert" class="text-red-700">{{ error.errors?.student_ids?.[0] ?? error.errors?.title?.[0] ?? t('messaging.error') }}</p>
        <form class="space-y-3" @submit.prevent="step === 1 && mode === 'group' ? step = 2 : submit()">
            <div v-if="mode === 'group' && step === 1" class="space-y-3"><label class="block">{{ t('messaging.groupTitle') }}<input v-model="title" required maxlength="120" class="mt-1 min-h-11 w-full rounded-xl border px-3"></label><label class="block">{{ t('messaging.audience') }}<select v-model="kind" class="mt-1 min-h-11 w-full rounded-xl border px-3"><option value="selected">{{ t('messaging.selectedStudents') }}</option><option value="all">{{ t('messaging.allStudents') }}</option></select></label></div>
            <div v-else class="space-y-3">
                <p v-if="!needsStudents">{{ t('messaging.dynamicHint') }}</p>
                <template v-else>
                    <label class="block">{{ t('messaging.findStudent') }}<input v-model="studentSearch" class="min-h-11 w-full rounded-xl border px-3" @keydown.enter.prevent="page = 1; load()"></label><button type="button" class="min-h-11 rounded-xl border px-3" @click="page = 1; load()">{{ t('messaging.search') }}</button>
                    <p v-if="loading" role="status">{{ t('messaging.loading') }}</p>
                    <p v-else-if="!students.length">{{ t('messaging.noStudents') }}</p>
                    <fieldset :disabled="busy || loading" class="max-h-64 space-y-1 overflow-auto"><legend class="sr-only">{{ t('messaging.selectedStudents') }}</legend><label v-for="student in students" :key="student.id" class="flex min-h-11 items-center gap-2"><input v-if="mode === 'private'" type="radio" name="chat-student" :value="student.id" :checked="studentIds[0] === student.id" @change="studentIds = [student.id]"><input v-else v-model="studentIds" type="checkbox" :value="student.id">{{ student.name }}</label></fieldset>
                    <div class="flex gap-2"><button type="button" class="min-h-11 rounded-xl border px-3" :disabled="page <= 1" @click="page--; load()">{{ t('messaging.previous') }}</button><span>{{ page }}/{{ lastPage }}</span><button type="button" class="min-h-11 rounded-xl border px-3" :disabled="page >= lastPage" @click="page++; load()">{{ t('messaging.next') }}</button></div>
                    <p v-if="mode !== 'private'" class="text-sm">{{ t('messaging.selectedCount', { count: studentIds.length }) }}</p>
                </template>
            </div>
            <div class="flex flex-wrap gap-2"><button type="button" class="min-h-11 rounded-xl border px-4" :disabled="busy" @click="emit('cancel')">{{ t('messaging.cancel') }}</button><button v-if="step === 2 && mode === 'group'" type="button" class="min-h-11 rounded-xl border px-4" @click="step = 1">{{ t('messaging.previous') }}</button><button type="submit" class="min-h-11 rounded-xl bg-brand px-4 text-white disabled:opacity-50" :disabled="busy || (mode === 'private' && !studentIds.length)">{{ t(step === 1 && mode === 'group' ? 'messaging.next' : 'messaging.save') }}</button></div>
        </form>
    </section>
</template>
