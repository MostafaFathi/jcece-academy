<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    id: { type: String, required: true },
    modelValue: { type: [String, Number, Array], default: '' },
    loader: { type: Function, required: true },
    resolver: { type: Function, default: null },
    initialOption: { type: Object, default: null },
    multiple: Boolean,
    required: Boolean,
    disabled: Boolean,
    label: { type: String, required: true },
});
const emit = defineEmits(['update:modelValue']);
const { t } = useI18n();
const search = ref('');
const options = ref([]);
const resolved = ref([]);
const page = ref(0);
const lastPage = ref(1);
const loading = ref(false);
const error = ref(false);
const pending = ref('');
let sequence = 0;
const selectedIds = computed(() => props.multiple ? (Array.isArray(props.modelValue) ? props.modelValue.map(Number) : []) : props.modelValue === '' || props.modelValue === null ? [] : [Number(props.modelValue)]);
const choices = computed(() => {
    const merged = [...resolved.value, ...(props.initialOption ? [props.initialOption] : []), ...options.value];
    return merged.filter((item, index) => merged.findIndex((choice) => Number(choice.id) === Number(item.id)) === index);
});
const selectedChoices = computed(() => selectedIds.value.map((id) => choices.value.find((item) => Number(item.id) === id)).filter(Boolean));
function optionLabel(item) { return item.title ?? item.name ?? item.order_number ?? item.email ?? ''; }
async function hydrate() {
    if (!props.resolver) return;
    const missing = selectedIds.value.filter((id) => !choices.value.some((item) => Number(item.id) === id));
    if (!missing.length) return;
    const fetched = await Promise.all(missing.map((id) => props.resolver(id).catch(() => null)));
    resolved.value = [...resolved.value, ...fetched.filter(Boolean)];
}
async function load(reset = false) {
    if (reset) { sequence++; loading.value = false; page.value = 0; lastPage.value = 1; options.value = []; }
    if (loading.value || page.value >= lastPage.value) return;
    const request = ++sequence;
    loading.value = true;
    error.value = false;
    try {
        const result = await props.loader({ page: page.value + 1, ...(search.value.trim() ? { search: search.value.trim() } : {}) });
        if (request !== sequence) return;
        options.value = [...options.value, ...(result.items ?? result.data ?? [])];
        page.value = result.meta?.current_page ?? result.current_page ?? page.value + 1;
        lastPage.value = result.meta?.last_page ?? result.last_page ?? page.value;
        await hydrate();
    } catch { if (request === sequence) error.value = true; }
    finally { if (request === sequence) loading.value = false; }
}
function updateSingle(event) { emit('update:modelValue', event.target.value === '' ? '' : Number(event.target.value)); }
function add() {
    if (!pending.value || selectedIds.value.includes(Number(pending.value))) return;
    emit('update:modelValue', [...selectedIds.value, Number(pending.value)]);
    pending.value = '';
}
function remove(id) { emit('update:modelValue', selectedIds.value.filter((item) => item !== id)); }
watch(() => props.modelValue, hydrate, { deep: true });
watch(() => props.loader, () => { sequence++; loading.value = false; resolved.value = []; load(true); });
onMounted(() => load());
</script>

<template>
    <div class="min-w-0 space-y-2">
        <label :for="id" class="block text-sm font-bold">{{ label }}</label>
        <div class="flex min-w-0 flex-wrap gap-2">
            <input v-model="search" type="search" :aria-label="t('common.search')" :placeholder="t('common.search')" class="min-h-11 min-w-0 flex-1 rounded-xl border border-slate-300 bg-white px-3" @keydown.enter.prevent="load(true)">
            <button type="button" class="min-h-11 rounded-xl border border-slate-300 px-4 text-sm font-bold" :disabled="disabled || loading" @click="load(true)">{{ t('common.search') }}</button>
        </div>
        <div class="flex min-w-0 flex-wrap gap-2">
            <select v-if="multiple" :id="id" v-model="pending" :disabled="disabled || loading" class="min-h-11 min-w-0 flex-1 rounded-xl border border-slate-300 bg-white px-3">
                <option value="">{{ t('common.notSpecified') }}</option>
                <option v-for="item in choices" :key="item.id" :value="item.id" :disabled="selectedIds.includes(Number(item.id))">{{ optionLabel(item) }}</option>
            </select>
            <select v-else :id="id" :value="modelValue" :required="required" :disabled="disabled || loading" class="min-h-11 min-w-0 flex-1 rounded-xl border border-slate-300 bg-white px-3" @change="updateSingle">
                <option value="">{{ t('common.notSpecified') }}</option>
                <option v-for="item in choices" :key="item.id" :value="item.id">{{ optionLabel(item) }}</option>
            </select>
            <button v-if="multiple" type="button" class="min-h-11 rounded-xl border border-slate-300 px-4 text-sm font-bold disabled:opacity-50" :disabled="disabled || !pending" @click="add">{{ t('common.apply') }}</button>
        </div>
        <button v-if="page < lastPage" type="button" class="min-h-11 text-sm font-bold text-brand underline" :disabled="disabled || loading" @click="load()">{{ t('curriculum.loadMoreOptions') }}</button>
        <p v-if="error" role="alert" class="text-sm text-red-700">{{ t('admin.loadError') }} <button type="button" class="underline" @click="load()">{{ t('common.retry') }}</button></p>
        <ul v-if="multiple && selectedChoices.length" class="flex flex-wrap gap-2"><li v-for="item in selectedChoices" :key="item.id" class="flex max-w-full items-center gap-2 rounded-full bg-brand/10 px-3 py-1 text-sm"><span class="truncate">{{ optionLabel(item) }}</span><button type="button" :aria-label="`${t('commerce.remove')} ${optionLabel(item)}`" class="font-black text-red-700" @click="remove(Number(item.id))">×</button></li></ul>
    </div>
</template>
