import { ref } from 'vue';
import { toSpeechText } from '@/services/aiChatService';
import { voiceHeaders } from '@/services/aiVoiceProtocol';

/** Owns audio playback state so recording and transcription stay independent. */
export function createAiVoicePlayback(synthesisEndpoint: string) {
    const isSpeaking = ref(false);
    let speechAudio: HTMLAudioElement | null = null;
    let speechAudioUrl: string | null = null;
    let speechRun = 0;

    function stopSpeaking(): void {
        speechRun += 1;
        speechAudio?.pause();
        if (speechAudioUrl) URL.revokeObjectURL(speechAudioUrl);
        speechAudio = null;
        speechAudioUrl = null;
        isSpeaking.value = false;
    }

    async function speakText(text: string): Promise<boolean> {
        stopSpeaking();
        const currentRun = speechRun;
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
            if (currentRun !== speechRun) return false;
            const url = URL.createObjectURL(blob);
            speechAudioUrl = url;
            speechAudio = new Audio(url);
            speechAudio.onended = () => {
                if (currentRun !== speechRun) return;
                URL.revokeObjectURL(url);
                speechAudio = null;
                speechAudioUrl = null;
                isSpeaking.value = false;
            };
            speechAudio.onerror = () => {
                if (currentRun !== speechRun) return;
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

    return { isSpeaking, speakText, stopSpeaking };
}
