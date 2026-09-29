import { computed, onScopeDispose, ref } from 'vue';
import * as learning from '../api/learning';
import { blocksLearning } from '../utils/learning';

export function useCourseLearning() {
    const course = ref(null);
    const progress = ref(null);
    const activeId = ref(null);
    const loading = ref(true);
    const busy = ref(false);
    const error = ref(null);
    const actionError = ref(null);
    const saved = ref(false);
    const downloadingId = ref(null);
    let slug;
    let generation = 0;
    const lessons = computed(() => course.value?.curriculum.flatMap((section) => section.lessons) ?? []);
    const activeLesson = computed(() => lessons.value.find((lesson) => lesson.id === activeId.value) ?? null);
    const activeIndex = computed(() => lessons.value.findIndex((lesson) => lesson.id === activeId.value));
    const summary = computed(() => progress.value?.enrollment ?? course.value);

    function clearContent() { course.value = null; progress.value = null; activeId.value = null; saved.value = false; downloadingId.value = null; }
    function fail(failure) {
        if (blocksLearning(failure)) { generation++; clearContent(); error.value = failure; }
        else { actionError.value = failure; }
    }
    function synchronize(result) {
        if (!result.enrollment?.has_access) throw { status: 403 };
        progress.value = result;
        const records = new Map(result.lesson_progress.map((record) => [record.lesson_id, record]));
        lessons.value.forEach((lesson) => { lesson.progress = records.get(lesson.id) ?? null; });
    }
    async function load(identifier, requestedLesson) {
        const current = ++generation;
        slug = identifier;
        clearContent(); loading.value = true; busy.value = false; error.value = null; actionError.value = null;
        try {
            const [details, records] = await Promise.all([learning.fetchLearningCourse(slug), learning.fetchCourseProgress(slug)]);
            if (generation !== current) return;
            if (!details.has_access) throw { status: 403 };
            course.value = details;
            synchronize(records);
            const selected = lessons.value.find((lesson) => String(lesson.id) === String(requestedLesson))
                ?? lessons.value.find((lesson) => lesson.id === records.enrollment.resume?.lesson.id)
                ?? lessons.value.find((lesson) => lesson.progress?.status !== 'completed') ?? lessons.value[0];
            activeId.value = selected?.id ?? null;
        } catch (failure) {
            if (generation === current) { clearContent(); error.value = failure; }
        } finally { if (generation === current) loading.value = false; }
    }
    async function operation(work, afterSync, context) {
        if (busy.value || loading.value || !course.value) return;
        const current = generation;
        const identifier = slug;
        busy.value = true; actionError.value = null; saved.value = false;
        try {
            await work(identifier);
            if (generation !== current) return;
            const result = await learning.fetchCourseProgress(identifier);
            if (generation !== current) return;
            synchronize(result);
            afterSync?.();
        } catch (failure) { if (generation === current) fail(context ? { status: failure.status, context } : failure); }
        finally { if (generation === current || !course.value) busy.value = false; }
    }
    function selectLesson(id) {
        if (!lessons.value.some((lesson) => lesson.id === id)) return;
        return operation(async () => {}, () => { activeId.value = id; });
    }
    function complete() {
        const lesson = activeLesson.value;
        if (!lesson || lesson.progress?.status === 'completed') return;
        return operation((identifier) => learning.completeLesson(identifier, lesson.id), () => { saved.value = true; });
    }
    function savePosition(seconds) {
        const lesson = activeLesson.value;
        if (!lesson || lesson.type !== 'video' || !Number.isFinite(seconds)) return;
        const position = Math.max(0, Math.floor(Math.min(seconds, lesson.duration_seconds ?? seconds)));
        return operation((identifier) => learning.saveLessonProgress(identifier, lesson.id, { last_position_seconds: position }), () => { saved.value = true; });
    }
    async function download(resource) {
        const lesson = activeLesson.value;
        if (busy.value || !lesson || !resource.download_available || !resource.is_downloadable || !lesson.resources.some((item) => item.id === resource.id)) return;
        downloadingId.value = resource.id;
        try { await operation((identifier) => learning.downloadLessonResource(identifier, lesson.id, resource.id), null, 'download'); }
        finally { if (downloadingId.value === resource.id) downloadingId.value = null; }
    }
    onScopeDispose(() => { generation++; clearContent(); });
    return { course, summary, activeLesson, activeIndex, lessons, loading, busy, error, actionError, saved, downloadingId, load, selectLesson, complete, savePosition, download };
}
