<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    Mic,
    Paperclip,
    Send,
    Sparkles,
    Square,
    X,
} from '@lucide/vue';
import {
    computed,
    nextTick,
    onMounted,
    onUnmounted,
    ref,
    watch,
    type VNodeRef,
} from 'vue';
import AiMessageList from '@/components/farmer/AiMessageList.vue';
import AiChatComposer from '@/components/farmer/AiChatComposer.vue';
import AiChatHeader from '@/components/farmer/AiChatHeader.vue';
import AiConversationHistory from '@/components/farmer/AiConversationHistory.vue';
import AiPerformanceReport from '@/components/farmer/AiPerformanceReport.vue';
import FarmerMobileShell from '@/components/farmer/FarmerMobileShell.vue';
import {
    MAX_ATTACHMENT_COUNT,
    type ChatAttachment,
    type ChatMessage,
    useAiChat,
    validateAttachments,
} from '@/composables/useAiChat';
import {
    useAiTelemetry,
    type AiVoiceMetric,
} from '@/composables/useAiTelemetry';
import { useAiVoice } from '@/composables/useAiVoice';
import {
    classifyAiPrompt,
    compactAiPrompt,
    estimateTokens,
    normalizeAiResponse,
    outOfScopeResponse,
    toSpeechText,
} from '@/services/aiChatService';
import { queryDemoData } from '@/services/aiDemoDataService';
import { validateAiResponse } from '@/services/aiResponseValidator';
import { clearDemoStorage } from '@/composables/useDemoStorage';
import type { Team } from '@/types/teams';

type PageProps = { currentTeam?: Team | null };

const page = usePage<PageProps>();
const teamSlug = page.props.currentTeam?.slug;
const aiBase = teamSlug ? `/${encodeURIComponent(teamSlug)}/ai` : '/ai';
const chat = useAiChat({ base: aiBase });
const voice = useAiVoice(
    `${aiBase}/voice/transcribe`,
    `${aiBase}/voice/synthesize`,
);
const {
    messages,
    conversations,
    conversationId,
    isLoadingConversations,
    isSending,
    error: chatError,
    lastRequestMetrics,
} = chat;
const telemetry = useAiTelemetry();
const telemetryReport = telemetry.report;
const telemetryEvents = telemetry.events;

const inputText = ref('');
const pendingAttachments = ref<ChatAttachment[]>([]);
const inputElement = ref<HTMLTextAreaElement | null>(null);
const messageList = ref<HTMLElement | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);
const isHistoryOpen = ref(false);
const isReportOpen = ref(false);
const copiedMessageId = ref<string | null>(null);
const speakingMessageId = ref<string | null>(null);
const voiceInputPending = ref(false);
const metricIds = ref<Record<string, string>>({});
let lastRecordedVoiceError: AiVoiceMetric | null = null;
const speech = typeof window !== 'undefined' ? window.speechSynthesis : null;
const quickPrompts = [
    '📊 Infografis: Panduan Pemupukan Jagung sesuai Fase',
    '💧 Infografis: Jadwal & Kebutuhan Air Kebun',
    '🐛 Infografis: Deteksi & Pengendalian Hama Daun',
    'Bagaimana kondisi kebun saya hari ini?',
];

const setInputElement: VNodeRef = (element) => {
    inputElement.value =
        typeof HTMLTextAreaElement !== 'undefined' &&
        element instanceof HTMLTextAreaElement
            ? element
            : null;
};

const setFileInput: VNodeRef = (element) => {
    fileInput.value =
        typeof HTMLInputElement !== 'undefined' &&
        element instanceof HTMLInputElement
            ? element
            : null;
};

const canSend = computed(
    () =>
        !isSending.value &&
        !voice.isRecording.value &&
        !voice.isPreparing.value &&
        !voice.isTranscribing.value &&
        (inputText.value.trim().length > 0 ||
            pendingAttachments.value.length > 0),
);

function attachmentFromFile(file: File): ChatAttachment {
    return {
        name: file.name,
        size: file.size,
        type: file.type,
        file,
        previewUrl: URL.createObjectURL(file),
    };
}

function addFiles(fileList: FileList | File[]): void {
    const files = Array.from(fileList);
    const validation = validateAttachments(
        files,
        pendingAttachments.value.length,
    );
    if (validation) {
        chatError.value = validation;
        return;
    }
    pendingAttachments.value.push(...files.map(attachmentFromFile));
    chatError.value = null;
}

function removeAttachment(index: number): void {
    const attachment = pendingAttachments.value[index];
    if (attachment?.previewUrl) URL.revokeObjectURL(attachment.previewUrl);
    pendingAttachments.value.splice(index, 1);
}

function onFileChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    if (target.files) addFiles(target.files);
    target.value = '';
}

function onDrop(event: DragEvent): void {
    isDragging.value = false;
    if (event.dataTransfer?.files) addFiles(event.dataTransfer.files);
}

function openFilePicker(): void {
    fileInput.value?.click();
}

function onPaste(event: ClipboardEvent): void {
    const files = Array.from(event.clipboardData?.files ?? []).filter((file) =>
        file.type.startsWith('image/'),
    );
    if (files.length) addFiles(files);
}

function autoResize(): void {
    const element = inputElement.value;
    if (!element) return;
    element.style.height = '0px';
    const height = Math.min(Math.max(element.scrollHeight, 36), 136);
    element.style.height = `${height}px`;
    element.style.overflowY = element.scrollHeight > 136 ? 'auto' : 'hidden';
}

async function scrollToLatest(): Promise<void> {
    await nextTick();
    messageList.value?.scrollTo({
        top: messageList.value.scrollHeight,
        behavior: 'smooth',
    });
}

async function send(): Promise<void> {
    if (!canSend.value) return;
    const text = compactAiPrompt(inputText.value);
    const attachments = [...pendingAttachments.value];
    const classification = classifyAiPrompt(text, attachments.length > 0);
    const requestInfographic = classification.intent === 'infographic';
    const requestMode = requestInfographic
        ? 'infographic'
        : voiceInputPending.value
          ? 'voice'
          : classification.intent === 'data' ||
              classification.intent === 'knowledge'
            ? 'data'
            : 'text';
    const voiceMetric = voiceInputPending.value
        ? (voice.lastTranscriptionMetrics.value ?? undefined)
        : undefined;
    const startedAt = performance.now();
    const localIntent =
        attachments.length === 0 &&
        (classification.intent === 'data' ||
            classification.intent === 'out_of_scope');
    if (localIntent) isSending.value = true;
    inputText.value = '';
    pendingAttachments.value = [];
    voiceInputPending.value = false;
    void nextTick(autoResize);

    if (classification.intent === 'data' && attachments.length === 0) {
        let demoAnswer = null;
        try {
            demoAnswer = await queryDemoData(text);
        } catch {
            demoAnswer = null;
        }
        if (demoAnswer) {
            await showLocalAnswer(
                text,
                demoAnswer.source
                    ? `${demoAnswer.content}\n\nℹ️ **Sumber:** ${demoAnswer.source}`
                    : demoAnswer.content,
                requestMode === 'voice' ? 'voice' : 'data',
                'positive',
                startedAt,
                0,
                voiceMetric,
            );
            isSending.value = false;
            await scrollToLatest();
            inputElement.value?.focus();
            return;
        }
        isSending.value = false;
    }

    if (classification.intent === 'out_of_scope' && attachments.length === 0) {
        await showLocalAnswer(
            text,
            outOfScopeResponse(),
            requestMode === 'voice' ? 'voice' : 'text',
            'negative',
            startedAt,
            420,
            voiceMetric,
        );
        isSending.value = false;
        await scrollToLatest();
        inputElement.value?.focus();
        return;
    }

    if (classification.needsClarification && attachments.length === 0) {
        await showLocalAnswer(
            text,
            'Saya belum menangkap tujuan pertanyaannya. Anda ingin saya membantu yang mana?\n\n1. **Data kebun atau aktivitas** yang tersedia di aplikasi\n2. **Panduan perawatan tanaman**\n3. **Ringkasan visual atau infografis** pertanian\n\nSebutkan kebun, komoditas, periode, atau tujuan Anda agar jawabannya lebih tepat.',
            requestMode === 'voice' ? 'voice' : 'text',
            'negative',
            startedAt,
            300,
            voiceMetric,
        );
        isSending.value = false;
        await scrollToLatest();
        inputElement.value?.focus();
        return;
    }

    const assistant = await chat.sendMessage(
        text,
        attachments,
        requestInfographic,
    );
    if (!assistant) return;
    const metrics = lastRequestMetrics.value;
    const responseText = normalizeAiResponse(assistant?.content ?? '');
    const responseIsNegative =
        /\b(belum tersedia|di luar konteks|tidak tersedia|tidak dapat membantu)\b/i.test(
            responseText,
        );
    const metricId = telemetry.record({
        mode: requestMode,
        status:
            metrics?.status === 'aborted'
                ? 'aborted'
                : metrics?.status === 'error' || assistant?.status === 'error'
                  ? 'error'
                  : responseIsNegative
                    ? 'negative'
                    : 'positive',
        latencyMs:
            metrics?.latencyMs ?? Math.round(performance.now() - startedAt),
        firstTokenMs: metrics?.firstTokenMs,
        inputTokens: estimateTokens(text),
        outputTokens: estimateTokens(responseText),
        voice: voiceMetric,
    });
    if (assistant) metricIds.value[assistant.id] = metricId;
    await scrollToLatest();
    inputElement.value?.focus();
}

function choosePrompt(prompt: string): void {
    inputText.value = prompt;
    void nextTick(() => {
        autoResize();
        inputElement.value?.focus();
    });
}

function toggleVoice(): void {
    if (voice.isTranscribing.value) return;
    if (voice.isRecording.value) voice.stopRecording();
    else void voice.startRecording();
}

async function speak(message: ChatMessage): Promise<void> {
    if (speakingMessageId.value === message.id) {
        voice.stopSpeaking();
        speech?.cancel();
        speakingMessageId.value = null;
        return;
    }
    speech?.cancel();
    if (await voice.speakText(message.content)) {
        speakingMessageId.value = message.id;
        return;
    }
    if (!speech) return;
    const utterance = new SpeechSynthesisUtterance(
        toSpeechText(message.content),
    );
    utterance.lang = 'id-ID';
    utterance.rate = 0.92;
    utterance.onend = () => {
        if (speakingMessageId.value === message.id)
            speakingMessageId.value = null;
    };
    speakingMessageId.value = message.id;
    speech.speak(utterance);
}

function onMessageCopied(messageId: string): void {
    copiedMessageId.value = messageId;
    window.setTimeout(() => {
        if (copiedMessageId.value === messageId) copiedMessageId.value = null;
    }, 1600);
}

function setFeedback(message: ChatMessage, feedback: 'up' | 'down'): void {
    message.feedback = feedback;
    const metricId = metricIds.value[message.id];
    if (metricId) telemetry.updateFeedback(metricId, feedback);
}

function resetDemoData(): void {
    clearDemoStorage();
    window.location.reload();
}

async function showLocalAnswer(
    userText: string,
    answer: string,
    mode: 'text' | 'data' | 'voice',
    status: 'positive' | 'negative',
    startedAt: number,
    delayMs = 420,
    voiceMetric?: AiVoiceMetric,
): Promise<void> {
    const validation = validateAiResponse(answer);
    const safeAnswer = validation.ok
        ? validation.content
        : 'Jawaban lokal tidak lolos pemeriksaan format.';
    const now = new Date().toISOString();
    const userId = `local-user-${Date.now()}`;
    const assistantId = `local-assistant-${Date.now() + 1}`;
    messages.value.push(
        {
            id: userId,
            role: 'user',
            content: userText,
            attachments: [],
            createdAt: now,
            status: 'complete',
        },
        {
            id: assistantId,
            role: 'assistant',
            content: safeAnswer,
            attachments: [],
            createdAt: new Date().toISOString(),
            status: 'complete',
            presentation: 'text',
        },
    );
    if (delayMs > 0) {
        await new Promise((resolve) => window.setTimeout(resolve, delayMs));
    }
    const metricId = telemetry.record({
        mode,
        status: validation.ok ? status : 'negative',
        latencyMs: Math.round(performance.now() - startedAt),
        inputTokens: estimateTokens(userText),
        outputTokens: estimateTokens(safeAnswer),
        voice: voiceMetric,
    });
    metricIds.value[assistantId] = metricId;
}

function retryLast(): void {
    const failedIndex = [...messages.value]
        .reverse()
        .findIndex((message) => message.status === 'error');
    if (failedIndex < 0) return;
    const index = messages.value.length - 1 - failedIndex;
    const previous = messages.value[index - 1];
    if (!previous || previous.role !== 'user') return;
    messages.value.splice(index, 1);
    messages.value.splice(index - 1, 1);
    inputText.value = previous.content;
    pendingAttachments.value = [...previous.attachments];
    void send();
}

function selectConversation(id: string): void {
    isHistoryOpen.value = false;
    void chat.loadConversation(id).then(scrollToLatest);
}

function newConversation(): void {
    messages.value.forEach((message) => {
        message.attachments.forEach((attachment) => {
            if (attachment.previewUrl && attachment.file)
                URL.revokeObjectURL(attachment.previewUrl);
        });
    });
    chat.newConversation();
    isHistoryOpen.value = false;
    inputText.value = '';
    pendingAttachments.value.forEach((attachment) => {
        if (attachment.previewUrl) URL.revokeObjectURL(attachment.previewUrl);
    });
    pendingAttachments.value = [];
}

watch(
    () => voice.isTranscribing.value,
    (transcribing, wasTranscribing) => {
        if (wasTranscribing && !transcribing && voice.transcript.value) {
            inputText.value = voice.transcript.value;
            voiceInputPending.value = true;
            autoResize();
            inputElement.value?.focus();
        }
    },
);
watch(
    () => voice.isRecording.value,
    (recording, wasRecording) => {
        if (
            wasRecording &&
            !recording &&
            !voice.isTranscribing.value &&
            voice.transcript.value
        ) {
            inputText.value = voice.transcript.value;
            voiceInputPending.value = true;
            autoResize();
            inputElement.value?.focus();
        }
    },
);
watch(
    () => voice.lastTranscriptionMetrics.value,
    (metric) => {
        if (
            !metric ||
            metric.status !== 'error' ||
            metric === lastRecordedVoiceError
        ) {
            return;
        }
        lastRecordedVoiceError = metric;
        telemetry.record({
            mode: 'voice',
            status: 'error',
            latencyMs: metric.totalMs,
            inputTokens: 0,
            outputTokens: 0,
            voice: metric,
        });
    },
);
watch(
    () => voice.isSpeaking.value,
    (speaking) => {
        if (!speaking && speakingMessageId.value) {
            speakingMessageId.value = null;
        }
    },
);
watch(() => messages.value.length, scrollToLatest);

onMounted(() => {
    // Always enter the page in the idle state. Recording starts only from the mic button.
    voice.resetVoice();
    void chat.loadConversations().then(() => {
        // Reopen the most recently updated server conversation after refresh so
        // a completed response is visible without requiring a manual reload.
        const latest = conversations.value[0];
        if (latest && messages.value.length === 0) {
            void chat.loadConversation(latest.id).then(scrollToLatest);
        }
    });
});
onUnmounted(() => {
    speech?.cancel();
    messages.value.forEach((message) => {
        message.attachments.forEach((attachment) => {
            if (attachment.previewUrl && attachment.file)
                URL.revokeObjectURL(attachment.previewUrl);
        });
    });
    pendingAttachments.value.forEach((attachment) => {
        if (attachment.previewUrl) URL.revokeObjectURL(attachment.previewUrl);
    });
});
</script>

<template>
    <Head title="AI" />
    <FarmerMobileShell active-key="ai">
        <div
            v-if="!teamSlug"
            class="flex min-h-full flex-col items-center justify-center px-8 text-center"
        >
            <div
                class="flex size-16 items-center justify-center rounded-[22px] bg-surface text-secondary"
            >
                <Sparkles :size="34" stroke-width="1.4" />
            </div>
            <h1 class="mt-5 text-xl font-semibold tracking-tight">
                Asisten Kebun Petani
            </h1>
            <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                Tanyakan kondisi tanaman, diagnosis visual dari foto, atau
                konsultasi perawatan.
            </p>
            <div class="mt-6 flex w-full max-w-[260px] flex-col gap-3">
                <a
                    href="/bypass"
                    class="rounded-xl bg-primary px-5 py-3 text-sm font-semibold text-primary-foreground shadow transition hover:bg-primary/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                >
                    Masuk Langsung (Bypass)
                </a>
                <a
                    href="/login"
                    class="text-xs font-medium text-muted-foreground transition hover:text-foreground hover:underline"
                >
                    Masuk dengan akun lain
                </a>
            </div>
        </div>
        <section
            v-else
            class="relative flex h-full min-h-0 flex-col overflow-hidden bg-background"
            @dragover.prevent="isDragging = true"
            @dragleave.self="isDragging = false"
            @drop.prevent="onDrop($event)"
        >
            <AiChatHeader
                :is-sending="isSending"
                @open-history="isHistoryOpen = true"
                @open-report="isReportOpen = true"
                @new-conversation="newConversation"
            />

            <div
                v-if="isDragging"
                class="pointer-events-none absolute inset-2 z-30 flex items-center justify-center rounded-2xl border-2 border-dashed border-primary bg-card/90 text-sm font-semibold text-secondary"
            >
                Lepaskan gambar di sini
            </div>

            <div
                ref="messageList"
                class="min-h-0 flex-1 scroll-pb-8 space-y-5 overflow-y-auto px-4 py-5"
            >
                <AiMessageList
                    :messages="messages"
                    :quick-prompts="quickPrompts"
                    :copied-message-id="copiedMessageId"
                    :speaking-message-id="speakingMessageId"
                    @choose-prompt="choosePrompt"
                    @speak="speak"
                    @copy-error="(message) => (chatError = message)"
                    @feedback="setFeedback"
                    @retry="retryLast"
                    @copied="onMessageCopied"
                />
            </div>

            <div
                v-if="chatError"
                class="mx-4 mb-2 flex items-start gap-2 rounded-xl border border-error/25 bg-error/5 px-3 py-2 text-xs text-error"
                role="alert"
            >
                <AlertCircle :size="15" class="mt-0.5 shrink-0" /><span
                    class="min-w-0 flex-1"
                    >{{ chatError }}</span
                ><button
                    type="button"
                    aria-label="Tutup pesan kesalahan"
                    class="shrink-0"
                    @click="chatError = null"
                >
                    <X :size="14" />
                </button>
            </div>

            <AiChatComposer
                v-model="inputText"
                :pending-attachments="pendingAttachments"
                :max-attachment-count="MAX_ATTACHMENT_COUNT"
                :voice="voice"
                :is-sending="isSending"
                :can-send="canSend"
                :textarea-ref="setInputElement"
                :file-input-ref="setFileInput"
                @open-file-picker="openFilePicker"
                @file-change="onFileChange"
                @paste="onPaste"
                @resize="autoResize"
                @remove-attachment="removeAttachment"
                @toggle-voice="toggleVoice"
                @stop-streaming="chat.stopStreaming"
                @send="send"
            />

            <AiConversationHistory
                v-if="isHistoryOpen"
                :conversations="conversations"
                :conversation-id="conversationId"
                :is-loading="isLoadingConversations"
                @close="isHistoryOpen = false"
                @new-conversation="newConversation"
                @select="selectConversation"
            />
            <AiPerformanceReport
                v-if="isReportOpen"
                :report="telemetryReport"
                :events="telemetryEvents"
                @close="isReportOpen = false"
                @reset="resetDemoData"
            />
        </section>
    </FarmerMobileShell>
</template>

<style scoped>
.ai-message-content :deep(p + p) {
    margin-top: 0.5rem;
}
.ai-message-content :deep(code) {
    font-family:
        ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}
.ai-message-content :deep(a:hover) {
    text-decoration-thickness: 2px;
}
.ai-message-content :deep(figure) {
    max-width: 100%;
}
.ai-message-content :deep(figure img) {
    display: block;
}
</style>
