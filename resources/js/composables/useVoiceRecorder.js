import { onBeforeUnmount, ref } from 'vue';
export function useVoiceRecorder() {
    const recording = ref(false), starting = ref(false), duration = ref(0), file = ref(null), error = ref(null);
    let recorder, stream, timer, startedAt, cancelled = false;
    const stopTracks = () => { stream?.getTracks().forEach((track) => track.stop()); stream = null; clearInterval(timer); };
    async function start() {
        if (recording.value || starting.value) return;
        error.value = null; file.value = null; duration.value = 0;
        if (!navigator.mediaDevices?.getUserMedia || !globalThis.MediaRecorder) { error.value = 'unsupported'; return; }
        starting.value = true;
        try {
            stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            if (cancelled) { stopTracks(); return; }
            const mime = ['audio/webm;codecs=opus', 'audio/ogg;codecs=opus', 'audio/mp4'].find((type) => MediaRecorder.isTypeSupported(type));
            recorder = new MediaRecorder(stream, mime ? { mimeType: mime } : undefined);
            const chunks = []; let size = 0;
            recorder.ondataavailable = (event) => { if (event.data.size) { chunks.push(event.data); size += event.data.size; if (size > 10 * 1024 * 1024) { error.value = 'tooLarge'; stop(); } } };
            recorder.onstop = () => {
                const type = recorder.mimeType, extension = type.includes('mp4') ? 'm4a' : type.includes('ogg') ? 'ogg' : 'webm';
                if (!cancelled && size <= 10 * 1024 * 1024) file.value = new File(chunks, `voice.${extension}`, { type });
                recording.value = false; stopTracks();
            };
            recorder.onerror = () => { error.value = 'recordError'; stop(); };
            recorder.start(1000); startedAt = Date.now(); recording.value = true;
            timer = setInterval(() => { duration.value = Math.floor((Date.now() - startedAt) / 1000); if (duration.value >= 120) stop(); }, 250);
        } catch (failure) { error.value = failure.name === 'NotAllowedError' ? 'micDenied' : 'recordError'; stopTracks(); }
        finally { starting.value = false; }
    }
    function stop() { if (recorder?.state === 'recording') recorder.stop(); clearInterval(timer); }
    onBeforeUnmount(() => { cancelled = true; stop(); stopTracks(); });
    return { recording, starting, duration, file, error, start, stop };
}
