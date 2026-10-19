import { onBeforeUnmount, ref } from 'vue';
import {
    audioFilename,
    browserRecognitionError,
    transcribeErrorMessage,
    voiceHeaders,
    type SpeechRecognitionConstructor,
    type SpeechRecognitionLike,
    type VoiceResponse,
} from '@/services/aiVoiceProtocol';
import { createAiVoicePlayback } from '@/services/aiVoicePlayback';

export type AiVoiceTranscriptionMetrics = {
    mode: 'server' | 'browser';
    status: 'complete' | 'error';
    recordingMs: number;
    processingMs: number;
    totalMs: number;
};

const MAX_RECORDING_SECONDS = 120;
const PREPARATION_TIMEOUT_MS = 15_000;
const TRANSCRIPTION_TIMEOUT_MS = 30_000;

declare global {
    interface Window {
        SpeechRecognition?: SpeechRecognitionConstructor;
        webkitSpeechRecognition?: SpeechRecognitionConstructor;
    }
}

export function useAiVoice(
    endpoint = '/ai/voice/transcribe',
    synthesisEndpoint = '/ai/voice/synthesize',
) {
    const playback = createAiVoicePlayback(synthesisEndpoint);
    const { isSpeaking, speakText, stopSpeaking } = playback;
    const isRecording = ref(false);
    const isPreparing = ref(false);
    const isTranscribing = ref(false);
    const transcript = ref('');
    const error = ref<string | null>(null);
    const audioUrl = ref<string | null>(null);
    const elapsedSeconds = ref(0);
    const recordingMode = ref<'server' | 'browser' | null>(null);
    const lastTranscriptionMetrics = ref<AiVoiceTranscriptionMetrics | null>(
        null,
    );
    let mediaRecorder: MediaRecorder | null = null;
    let mediaStream: MediaStream | null = null;
    let timer: number | null = null;
    let recognition: SpeechRecognitionLike | null = null;
    let preparationRun = 0;
    let recordingSession = 0;
    let transcriptionRun = 0;
    let transcriptionController: AbortController | null = null;
    let recordingStartedAt: number | null = null;
    let recognitionStartedAt: number | null = null;
    let recognitionFinalStatus: AiVoiceTranscriptionMetrics['status'] | null =
        null;

    function clearTimer(): void {
        if (timer !== null) window.clearInterval(timer);
        timer = null;
    }

    function clearAudioUrl(): void {
        if (audioUrl.value) URL.revokeObjectURL(audioUrl.value);
        audioUrl.value = null;
    }

    function stopMediaStream(): void {
        mediaStream?.getTracks().forEach((track) => track.stop());
        mediaStream = null;
    }

    function clearRecordingState(): void {
        clearTimer();
        isRecording.value = false;
        recordingMode.value = null;
    }

    function stopRecognition(): void {
        if (!recognition) return;
        try {
            recognition.abort?.();
        } catch {
            // Some browser implementations throw when aborting an ended session.
        }
        recognition = null;
    }

    function cancelTranscription(): void {
        transcriptionRun += 1;
        transcriptionController?.abort();
        transcriptionController = null;
        isTranscribing.value = false;
    }

    function startBrowserRecognition(session: number): boolean {
        const Recognition =
            window.SpeechRecognition ?? window.webkitSpeechRecognition;
        if (!Recognition) return false;
        stopRecognition();
        try {
            recognition = new Recognition();
        } catch {
            return false;
        }
        const currentRecognition = recognition;
        recordingMode.value = 'browser';
        recognitionStartedAt = performance.now();
        recognitionFinalStatus = null;
        recognition.lang = 'id-ID';
        recognition.interimResults = true;
        recognition.continuous = true;
        recognition.onresult = (event) => {
            if (
                recognition !== currentRecognition ||
                session !== recordingSession
            )
                return;
            let value = '';
            for (let index = 0; index < event.results.length; index += 1) {
                value += event.results[index]?.[0]?.transcript ?? '';
            }
            transcript.value = value.trim();
        };
        recognition.onerror = (event) => {
            if (
                recognition !== currentRecognition ||
                session !== recordingSession
            )
                return;
            recognitionFinalStatus = 'error';
            const message = browserRecognitionError(event.error);
            if (message) error.value = message;
            clearRecordingState();
        };
        recognition.onend = () => {
            if (
                recognition !== currentRecognition ||
                session !== recordingSession
            )
                return;
            const startedAt = recognitionStartedAt;
            const totalMs =
                startedAt === null
                    ? Math.max(0, elapsedSeconds.value * 1000)
                    : Math.max(0, Math.round(performance.now() - startedAt));
            const status =
                recognitionFinalStatus ??
                (transcript.value.trim() ? 'complete' : 'error');
            if (startedAt !== null || status === 'error') {
                lastTranscriptionMetrics.value = {
                    mode: 'browser',
                    status,
                    recordingMs: totalMs,
                    processingMs: 0,
                    totalMs,
                };
            }
            recognitionStartedAt = null;
            recognitionFinalStatus = null;
            recognition = null;
            clearRecordingState();
        };
        try {
            recognition.start();
        } catch {
            recognition = null;
            recognitionStartedAt = null;
            recognitionFinalStatus = null;
            recordingMode.value = null;
            return false;
        }
        return true;
    }

    async function transcribe(
        blob: Blob,
        recordingMs: number,
    ): Promise<string> {
        const currentRun = ++transcriptionRun;
        transcriptionController?.abort();
        const controller = new AbortController();
        transcriptionController = controller;
        isTranscribing.value = true;
        error.value = null;
        const processingStartedAt = performance.now();
        const timeout = window.setTimeout(
            () => controller.abort(),
            TRANSCRIPTION_TIMEOUT_MS,
        );
        const form = new FormData();
        form.append('audio', blob, audioFilename(blob));
        form.append('language', 'id-ID');

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                body: form,
                headers: voiceHeaders(),
                signal: controller.signal,
            });
            let payload: VoiceResponse = {};
            try {
                payload = (await response.json()) as VoiceResponse;
            } catch {
                payload = {};
            }
            if (!response.ok)
                throw new Error(
                    transcribeErrorMessage(response.status, payload),
                );
            if (currentRun !== transcriptionRun) return '';
            const value = payload.transcript ?? payload.text ?? '';
            transcript.value = value.trim();
            if (!transcript.value) {
                throw new Error(
                    'Suara tidak menghasilkan teks. Coba bicara lebih dekat ke mikrofon.',
                );
            }
            const processingMs = Math.max(
                0,
                Math.round(performance.now() - processingStartedAt),
            );
            lastTranscriptionMetrics.value = {
                mode: 'server',
                status: 'complete',
                recordingMs,
                processingMs,
                totalMs: recordingMs + processingMs,
            };
            return transcript.value;
        } catch (cause) {
            if (currentRun !== transcriptionRun) return '';
            error.value =
                cause instanceof DOMException && cause.name === 'AbortError'
                    ? 'Transkrip suara timeout. Coba rekam dengan durasi lebih pendek.'
                    : cause instanceof Error
                      ? cause.message
                      : 'Suara tidak dapat diubah menjadi teks.';
            const processingMs = Math.max(
                0,
                Math.round(performance.now() - processingStartedAt),
            );
            lastTranscriptionMetrics.value = {
                mode: 'server',
                status: 'error',
                recordingMs,
                processingMs,
                totalMs: recordingMs + processingMs,
            };
            return transcript.value;
        } finally {
            window.clearTimeout(timeout);
            if (currentRun === transcriptionRun) {
                transcriptionController = null;
                isTranscribing.value = false;
            }
        }
    }

    async function startRecording(): Promise<void> {
        if (
            isRecording.value ||
            isPreparing.value ||
            isTranscribing.value ||
            (mediaRecorder !== null && mediaRecorder.state !== 'inactive')
        )
            return;
        const currentRun = ++preparationRun;
        const session = ++recordingSession;
        isPreparing.value = true;
        const preparationTimeout = window.setTimeout(() => {
            if (currentRun !== preparationRun) return;
            preparationRun += 1;
            isPreparing.value = false;
            stopMediaStream();
            error.value =
                'Izin mikrofon belum diberikan. Coba lagi atau gunakan dikte browser.';
        }, PREPARATION_TIMEOUT_MS);
        error.value = null;
        transcript.value = '';
        lastTranscriptionMetrics.value = null;
        clearAudioUrl();
        elapsedSeconds.value = 0;

        try {
            if (
                !navigator.mediaDevices?.getUserMedia ||
                typeof MediaRecorder === 'undefined'
            ) {
                throw new Error('MediaRecorder tidak tersedia.');
            }
            mediaStream = await navigator.mediaDevices.getUserMedia({
                audio: true,
            });
            if (currentRun !== preparationRun) {
                stopMediaStream();
                return;
            }
            const stream = mediaStream;
            if (!stream)
                throw new Error('Mikrofon tidak tersedia di perangkat ini.');
            const options = MediaRecorder.isTypeSupported(
                'audio/webm;codecs=opus',
            )
                ? { mimeType: 'audio/webm;codecs=opus' }
                : undefined;
            const recorder = new MediaRecorder(stream, options);
            const recorderChunks: Blob[] = [];
            mediaRecorder = recorder;
            recordingMode.value = 'server';
            recorder.ondataavailable = (event) => {
                if (event.data.size > 0) recorderChunks.push(event.data);
            };
            recorder.onstop = () => {
                const startedAt = recordingStartedAt;
                recordingStartedAt = null;
                const recordingMs =
                    startedAt === null
                        ? Math.max(0, elapsedSeconds.value * 1000)
                        : Math.max(
                              0,
                              Math.round(performance.now() - startedAt),
                          );
                const capturedChunks = recorderChunks;
                const blob = new Blob(capturedChunks, {
                    type:
                        recorder.mimeType ||
                        capturedChunks[0]?.type ||
                        'audio/webm',
                });
                if (mediaStream === stream) stopMediaStream();
                if (mediaRecorder === recorder) mediaRecorder = null;
                if (session !== recordingSession) return;
                if (blob.size > 0) {
                    clearAudioUrl();
                    audioUrl.value = URL.createObjectURL(blob);
                    void transcribe(blob, recordingMs);
                } else {
                    lastTranscriptionMetrics.value = {
                        mode: 'server',
                        status: 'error',
                        recordingMs,
                        processingMs: 0,
                        totalMs: recordingMs,
                    };
                }
            };
            recordingStartedAt = performance.now();
            recorder.start();
            isRecording.value = true;
            timer = window.setInterval(() => {
                elapsedSeconds.value += 1;
                if (elapsedSeconds.value >= MAX_RECORDING_SECONDS) {
                    stopRecording();
                }
            }, 1000);
        } catch (cause) {
            stopMediaStream();
            if (currentRun !== preparationRun) return;
            // Browser speech recognition is a useful fallback on devices without MediaRecorder permissions.
            if (!startBrowserRecognition(session)) {
                error.value =
                    cause instanceof Error && cause.name === 'NotAllowedError'
                        ? 'Izin mikrofon diperlukan untuk memakai voice.'
                        : cause instanceof Error &&
                            cause.name === 'NotFoundError'
                          ? 'Mikrofon tidak ditemukan di perangkat ini.'
                          : cause instanceof Error &&
                              cause.name === 'NotReadableError'
                            ? 'Mikrofon sedang dipakai aplikasi lain.'
                            : cause instanceof Error &&
                                cause.name === 'SecurityError'
                              ? 'Akses mikrofon memerlukan koneksi aman (HTTPS).'
                              : 'Perekaman dan dikte browser tidak tersedia di perangkat ini.';
            } else {
                isRecording.value = true;
                timer = window.setInterval(() => {
                    elapsedSeconds.value += 1;
                    if (elapsedSeconds.value >= MAX_RECORDING_SECONDS)
                        stopRecording();
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
        stopMediaStream();
        error.value = null;
    }

    function stopRecording(): void {
        clearTimer();
        if (recognition) {
            const currentRecognition = recognition;
            try {
                currentRecognition.stop();
            } catch {
                stopRecognition();
            }
            isRecording.value = false;
            recordingMode.value = null;
            return;
        }
        if (!mediaRecorder || mediaRecorder.state === 'inactive') {
            stopMediaStream();
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
        recordingSession += 1;
        cancelTranscription();
        stopRecording();
        stopRecognition();
        isPreparing.value = false;
        transcript.value = '';
        error.value = null;
        clearAudioUrl();
        elapsedSeconds.value = 0;
        lastTranscriptionMetrics.value = null;
        recordingMode.value = null;
    }

    onBeforeUnmount(() => {
        preparationRun += 1;
        recordingSession += 1;
        cancelTranscription();
        stopRecording();
        stopRecognition();
        stopSpeaking();
        clearAudioUrl();
        stopMediaStream();
    });

    return {
        isRecording,
        isPreparing,
        isTranscribing,
        transcript,
        error,
        audioUrl,
        lastTranscriptionMetrics,
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
