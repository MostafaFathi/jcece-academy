<script setup>
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { createGroup, fetchMessagingStudents, startPrivate, updateMembers } from '../../api/messaging';
import MessagingIcon from './MessagingIcon.vue';
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
    <section class="overflow-hidden rounded-[1.5rem] border border-brand/20 bg-white shadow-[0_10px_30px_rgba(52,19,31,.08)]" :aria-label="t('messaging.setup')" @keydown.esc="emit('cancel')">
        <header class="flex items-center justify-between gap-3 border-b border-slate-100 bg-brand-soft/60 px-4 py-4 sm:px-5"><div class="flex min-w-0 items-center gap-3"><span class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand text-white"><MessagingIcon :name="mode === 'private' ? 'user' : 'users'" class="size-5" /></span><div class="min-w-0"><p class="text-xs font-bold text-brand">{{ t('messaging.setup') }}</p><h3 class="truncate text-base font-black text-slate-950">{{ t(mode === 'private' ? 'messaging.startPrivate' : mode === 'members' ? 'messaging.manageMembers' : 'messaging.newGroup') }}</h3></div></div><button type="button" class="grid size-11 shrink-0 place-items-center rounded-xl text-slate-600 transition hover:bg-white focus-visible:outline-3 focus-visible:outline-brand" :aria-label="t('messaging.cancel')" @click="emit('cancel')"><MessagingIcon name="close" class="size-4" /></button></header>
        <div class="p-4 sm:p-5">
            <div v-if="mode === 'group'" class="mb-5 flex items-center gap-3 text-xs font-bold"><span class="grid size-7 place-items-center rounded-full" :class="step === 1 ? 'bg-brand text-white' : 'bg-brand-soft text-brand'">1</span><span class="text-slate-600">{{ t('messaging.groupDetails') }}</span><span class="h-px flex-1 bg-slate-200" /><span class="grid size-7 place-items-center rounded-full" :class="step === 2 ? 'bg-brand text-white' : 'bg-slate-100 text-slate-500'">2</span><span class="text-slate-600">{{ t('messaging.audience') }}</span></div>
            <p v-if="error" role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-semibold text-red-800">{{ error.errors?.student_ids?.[0] ?? error.errors?.title?.[0] ?? t('messaging.error') }}</p>
            <form class="space-y-4" @submit.prevent="step === 1 && mode === 'group' ? step = 2 : submit()">
                <div v-if="mode === 'group' && step === 1" class="grid gap-4 sm:grid-cols-2"><label class="block text-sm font-bold text-slate-700">{{ t('messaging.groupTitle') }}<input v-model="title" required maxlength="120" class="mt-1.5 min-h-11 w-full rounded-xl border border-slate-300 px-3 font-normal"></label><label class="block text-sm font-bold text-slate-700">{{ t('messaging.audience') }}<select v-model="kind" class="mt-1.5 min-h-11 w-full rounded-xl border border-slate-300 px-3 font-normal"><option value="selected">{{ t('messaging.selectedStudents') }}</option><option value="all">{{ t('messaging.allStudents') }}</option></select></label></div>
                <div v-else class="space-y-3">
                    <p v-if="!needsStudents" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950">{{ t('messaging.dynamicHint') }}</p>
                    <template v-else>
                        <div class="flex min-w-0 flex-wrap items-end gap-2"><label class="min-w-0 flex-1 text-sm font-bold text-slate-700">{{ t('messaging.findStudent') }}<input v-model="studentSearch" class="mt-1.5 min-h-11 w-full rounded-xl border border-slate-300 px-3 font-normal" @keydown.enter.prevent="page = 1; load()"></label><button type="button" class="min-h-11 rounded-xl border border-slate-200 px-4 text-sm font-bold text-brand transition hover:bg-brand-soft" @click="page = 1; load()">{{ t('messaging.search') }}</button></div>
                        <p v-if="loading" role="status" class="text-sm text-slate-500">{{ t('messaging.loading') }}</p>
                        <p v-else-if="!students.length" class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500">{{ t('messaging.noStudents') }}</p>
                        <fieldset :disabled="busy || loading" class="max-h-64 space-y-1 overflow-y-auto rounded-xl border border-slate-200 p-2"><legend class="sr-only">{{ t('messaging.selectedStudents') }}</legend><label v-for="student in students" :key="student.id" class="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg px-3 text-sm font-semibold text-slate-700 transition hover:bg-brand-soft"><input v-if="mode === 'private'" type="radio" name="chat-student" :value="student.id" :checked="studentIds[0] === student.id" class="size-4 accent-brand" @change="studentIds = [student.id]"><input v-else v-model="studentIds" type="checkbox" :value="student.id" class="size-4 accent-brand">{{ student.name }}</label></fieldset>
                        <div class="flex items-center justify-between gap-2 text-xs font-bold text-slate-600"><div class="flex items-center gap-2"><button type="button" class="min-h-11 rounded-lg px-2 text-brand disabled:text-slate-400" :disabled="page <= 1" @click="page--; load()">{{ t('messaging.previous') }}</button><span>{{ page }}/{{ lastPage }}</span><button type="button" class="min-h-11 rounded-lg px-2 text-brand disabled:text-slate-400" :disabled="page >= lastPage" @click="page++; load()">{{ t('messaging.next') }}</button></div><span v-if="mode !== 'private'" class="rounded-full bg-brand-soft px-3 py-1.5 text-brand">{{ t('messaging.selectedCount', { count: studentIds.length }) }}</span></div>
                    </template>
                </div>
                <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-4"><button type="button" class="min-h-11 rounded-xl border border-slate-200 px-4 text-sm font-bold text-slate-700 transition hover:bg-slate-50" :disabled="busy" @click="emit('cancel')">{{ t('messaging.cancel') }}</button><button v-if="step === 2 && mode === 'group'" type="button" class="min-h-11 rounded-xl border border-slate-200 px-4 text-sm font-bold text-brand" @click="step = 1">{{ t('messaging.previous') }}</button><button type="submit" class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-brand px-5 text-sm font-extrabold text-white transition hover:bg-brand-dark disabled:opacity-50" :disabled="busy || (mode === 'private' && !studentIds.length)">{{ t(step === 1 && mode === 'group' ? 'messaging.next' : 'messaging.save') }}<MessagingIcon :name="step === 1 && mode === 'group' ? 'chevron' : 'check'" class="size-4" /></button></div>
            </form>
        </div>
    </section>
</template>
