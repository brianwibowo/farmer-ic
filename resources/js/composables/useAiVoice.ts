import { onBeforeUnmount, ref } from 'vue';
import { toSpeechText } from '@/services/aiChatService';

type SpeechRecognitionEventLike = Event & {
    results: ArrayLike<ArrayLike<{ transcript: string }>>;
};

type SpeechRecognitionLike = {
    lang: string;
    interimResults: boolean;
    continuous: boolean;
    onresult: ((event: SpeechRecognitionEventLike) => void) | null;
    onerror: ((event: Event) => void) | null;
    onend: (() => void) | null;
    start: () => void;
    stop: () => void;
};

type SpeechRecognitionConstructor = new () => SpeechRecognitionLike;

type VoiceResponse = {
    transcript?: string;
    text?: string;
};

const MAX_RECORDING_SECONDS = 120;

declare global {
    interface Window {
        SpeechRecognition?: SpeechRecognitionConstructor;
        webkitSpeechRecognition?: SpeechRecognitionConstructor;
    }
}

function csrfToken(): string | null {
    const element = document.querySelector<HTMLMetaElement>(
        'meta[name="csrf-token"]',
    );
    if (element?.content) return element.content;
    const cookie = document.cookie
        .split('; ')
        .find((item) => item.startsWith('XSRF-TOKEN='));
    return cookie
        ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length))
        : null;
}

function voiceHeaders(): Record<string, string> {
    const token = csrfToken();
    return {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
    };
}

export function useAiVoice(
    endpoint = '/ai/voice/transcribe',
    synthesisEndpoint = '/ai/voice/synthesize',
) {
    const isRecording = ref(false);
    const isPreparing = ref(false);
    const isTranscribing = ref(false);
    const transcript = ref('');
    const error = ref<string | null>(null);
    const audioUrl = ref<string | null>(null);
    const isSpeaking = ref(false);
    const elapsedSeconds = ref(0);
    const recordingMode = ref<'server' | 'browser' | null>(null);
    let mediaRecorder: MediaRecorder | null = null;
    let mediaStream: MediaStream | null = null;
    let chunks: Blob[] = [];
    let timer: number | null = null;
    let recognition: SpeechRecognitionLike | null = null;
    let speechAudio: HTMLAudioElement | null = null;
    let speechAudioUrl: string | null = null;
    let preparationRun = 0;

    function clearTimer(): void {
        if (timer !== null) window.clearInterval(timer);
        timer = null;
    }

    function clearAudioUrl(): void {
        if (audioUrl.value) URL.revokeObjectURL(audioUrl.value);
        audioUrl.value = null;
    }

    function stopSpeaking(): void {
        speechAudio?.pause();
        if (speechAudioUrl) URL.revokeObjectURL(speechAudioUrl);
        speechAudio = null;
        speechAudioUrl = null;
        isSpeaking.value = false;
    }

    async function speakText(text: string): Promise<boolean> {
        stopSpeaking();
        const speechText = toSpeechText(text);
        if (!speechText) return false;

        try {
            const response = await fetch(synthesisEndpoint, {
                method: 'POST',
                headers: {
                    ...voiceHeaders(),
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ text: speechText }),
            });
            if (!response.ok) return false;

            const blob = await response.blob();
            const url = URL.createObjectURL(blob);
            speechAudioUrl = url;
            speechAudio = new Audio(url);
            speechAudio.onended = () => {
                URL.revokeObjectURL(url);
                speechAudio = null;
                speechAudioUrl = null;
                isSpeaking.value = false;
            };
            speechAudio.onerror = () => {
                URL.revokeObjectURL(url);
                speechAudio = null;
                speechAudioUrl = null;
                isSpeaking.value = false;
            };
            await speechAudio.play();
            isSpeaking.value = true;
            return true;
        } catch {
            stopSpeaking();
            return false;
        }
    }

    function startBrowserRecognition(): boolean {
        const Recognition =
            window.SpeechRecognition ?? window.webkitSpeechRecognition;
        if (!Recognition) return false;
        try {
            recognition = new Recognition();
        } catch {
            return false;
        }
        recordingMode.value = 'browser';
        recognition.lang = 'id-ID';
        recognition.interimResults = true;
        recognition.continuous = true;
        recognition.onresult = (event) => {
            let value = '';
            for (let index = 0; index < event.results.length; index += 1) {
                value += event.results[index]?.[0]?.transcript ?? '';
            }
            transcript.value = value.trim();
        };
        recognition.onerror = () => {
            error.value =
                'Pengenalan suara di browser gagal. Coba bicara lagi.';
            clearTimer();
            isRecording.value = false;
            recordingMode.value = null;
        };
        recognition.onend = () => {
            recognition = null;
            clearTimer();
            isRecording.value = false;
        };
        try {
            recognition.start();
        } catch {
            recognition = null;
            recordingMode.value = null;
            return false;
        }
        return true;
    }

    async function transcribe(blob: Blob): Promise<string> {
        isTranscribing.value = true;
        error.value = null;
        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), 30000);
        const form = new FormData();
        form.append('audio', blob, 'voice-message.webm');
        form.append('language', 'id-ID');

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                body: form,
                headers: voiceHeaders(),
                signal: controller.signal,
            });
            if (!response.ok)
                throw new Error('Transkrip suara belum tersedia.');
            const payload = (await response.json()) as VoiceResponse;
            const value = payload.transcript ?? payload.text ?? '';
            transcript.value = value.trim();
            return transcript.value;
        } catch (cause) {
            error.value =
                cause instanceof DOMException && cause.name === 'AbortError'
                    ? 'Transkrip suara timeout. Coba rekam dengan durasi lebih pendek.'
                    : cause instanceof Error
                      ? cause.message
                      : 'Suara tidak dapat diubah menjadi teks.';
            return transcript.value;
        } finally {
            window.clearTimeout(timeout);
            isTranscribing.value = false;
        }
    }

    async function startRecording(): Promise<void> {
        if (isRecording.value) return;
        const currentRun = ++preparationRun;
        isPreparing.value = true;
        const preparationTimeout = window.setTimeout(() => {
            if (currentRun !== preparationRun) return;
            isPreparing.value = false;
            error.value =
                'Izin mikrofon belum diberikan. Coba lagi atau gunakan dikte browser.';
        }, 15_000);
        error.value = null;
        transcript.value = '';
        clearAudioUrl();
        elapsedSeconds.value = 0;

        try {
            if (!navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === 'undefined') {
                throw new Error('MediaRecorder tidak tersedia.');
            }
            mediaStream = await navigator.mediaDevices.getUserMedia({
                audio: true,
            });
            if (currentRun !== preparationRun) {
                mediaStream.getTracks().forEach((track) => track.stop());
                mediaStream = null;
                return;
            }
            chunks = [];
            const options = MediaRecorder.isTypeSupported(
                'audio/webm;codecs=opus',
            )
                ? { mimeType: 'audio/webm;codecs=opus' }
                : undefined;
            mediaRecorder = new MediaRecorder(mediaStream, options);
            recordingMode.value = 'server';
            mediaRecorder.ondataavailable = (event) => {
                if (event.data.size > 0) chunks.push(event.data);
            };
            mediaRecorder.onstop = () => {
                const blob = new Blob(chunks, {
                    type: mediaRecorder?.mimeType || 'audio/webm',
                });
                if (blob.size > 0) {
                    audioUrl.value = URL.createObjectURL(blob);
                    void transcribe(blob);
                }
                mediaStream?.getTracks().forEach((track) => track.stop());
                mediaStream = null;
            };
            mediaRecorder.start();
            isRecording.value = true;
            timer = window.setInterval(() => {
                elapsedSeconds.value += 1;
                if (elapsedSeconds.value >= MAX_RECORDING_SECONDS) {
                    stopRecording();
                }
            }, 1000);
        } catch (cause) {
            mediaStream?.getTracks().forEach((track) => track.stop());
            mediaStream = null;
            // Browser speech recognition is a useful fallback on devices without MediaRecorder permissions.
            if (!startBrowserRecognition()) {
                error.value =
                    cause instanceof Error && cause.name === 'NotAllowedError'
                        ? 'Izin mikrofon diperlukan untuk memakai voice.'
                        : 'Mikrofon tidak tersedia di perangkat ini.';
            } else {
                isRecording.value = true;
                timer = window.setInterval(() => {
                    elapsedSeconds.value += 1;
                    if (elapsedSeconds.value >= MAX_RECORDING_SECONDS) stopRecording();
                }, 1000);
            }
        } finally {
            window.clearTimeout(preparationTimeout);
            if (currentRun === preparationRun) isPreparing.value = false;
        }
    }

    function cancelPreparing(): void {
        preparationRun += 1;
        isPreparing.value = false;
        error.value = 'Permintaan mikrofon dibatalkan.';
    }

    function stopRecording(): void {
        clearTimer();
        if (recognition) {
            recognition.stop();
            recognition = null;
            isRecording.value = false;
            recordingMode.value = null;
            return;
        }
        if (!mediaRecorder || mediaRecorder.state === 'inactive') {
            isRecording.value = false;
            recordingMode.value = null;
            return;
        }
        mediaRecorder.stop();
        isRecording.value = false;
        recordingMode.value = null;
    }

    function resetVoice(): void {
        preparationRun += 1;
        stopRecording();
        transcript.value = '';
        error.value = null;
        clearAudioUrl();
        elapsedSeconds.value = 0;
        recordingMode.value = null;
    }

    onBeforeUnmount(() => {
        stopRecording();
        stopSpeaking();
        clearAudioUrl();
        mediaStream?.getTracks().forEach((track) => track.stop());
    });

    return {
        isRecording,
        isPreparing,
        isTranscribing,
        transcript,
        error,
        audioUrl,
        isSpeaking,
        elapsedSeconds,
        recordingMode,
        startRecording,
        cancelPreparing,
        stopRecording,
        resetVoice,
        speakText,
        stopSpeaking,
    };
}
