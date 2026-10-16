<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    Bot,
    Check,
    ChevronLeft,
    Copy,
    Image as ImageIcon,
    LoaderCircle,
    Mic,
    Paperclip,
    Plus,
    RefreshCw,
    Send,
    Sparkles,
    Square,
    Volume2,
    X,
} from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import AiInfographicCard from '@/components/farmer/AiInfographicCard.vue';
import FarmerMobileShell from '@/components/farmer/FarmerMobileShell.vue';
import {
    MAX_ATTACHMENT_COUNT,
    type ChatAttachment,
    type ChatMessage,
    useAiChat,
    validateAttachments,
} from '@/composables/useAiChat';
import { useAiVoice } from '@/composables/useAiVoice';
import { infographicForPrompt } from '@/data/aiInfographics';
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
} = chat;

const inputText = ref('');
const pendingAttachments = ref<ChatAttachment[]>([]);
const inputElement = ref<HTMLTextAreaElement | null>(null);
const messageList = ref<HTMLElement | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);
const isHistoryOpen = ref(false);
const copiedMessageId = ref<string | null>(null);
const speakingMessageId = ref<string | null>(null);
const speech = typeof window !== 'undefined' ? window.speechSynthesis : null;
const quickPrompts = [
    '📊 Infografis: Panduan Pemupukan Jagung Optimal',
    '💧 Infografis: Jadwal & Kebutuhan Air Kebun',
    '🐛 Infografis: Deteksi & Pengendalian Hama Daun',
    'Bagaimana kondisi kebun saya hari ini?',
];

const canSend = computed(
    () =>
        !isSending.value &&
        (inputText.value.trim().length > 0 ||
            pendingAttachments.value.length > 0),
);

function escapeHtml(value: string): string {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

/** Render a small, safe Markdown subset after escaping all source text. */
function renderMarkdown(value: string): string {
    const safe = escapeHtml(value.trim());
    if (!safe) return '';
    return safe
        .split(/\n{2,}/)
        .map((block) => {
            const lines = block.split('\n');
            const listLines = lines.filter((line) => /^[-*]\s/.test(line));
            if (listLines.length === lines.length && listLines.length > 0) {
                return `<ul class="my-1 list-disc space-y-1 pl-5">${listLines.map((line) => `<li>${inlineMarkdown(line.slice(2))}</li>`).join('')}</ul>`;
            }
            return `<p>${lines.map(inlineMarkdown).join('<br>')}</p>`;
        })
        .join('');
}

function inlineMarkdown(value: string): string {
    return value
        .replace(
            /!\[([^\]]*)\]\((https:\/\/[^)\s]+|\/[^)\s]+)\)/g,
            '<figure class="my-2 overflow-hidden rounded-xl border border-border bg-muted"><img src="$2" alt="$1" loading="lazy" decoding="async" class="max-h-64 w-full object-cover" /><figcaption class="px-2.5 py-1.5 text-[11px] text-muted-foreground">$1</figcaption></figure>',
        )
        .replace(
            /\[([^\]]+)\]\((https:\/\/[^)\s]+|\/[^)\s]+)\)/g,
            '<a href="$2" target="_blank" rel="noreferrer" class="font-medium text-secondary underline underline-offset-2">$1</a>',
        )
        .replace(
            /`([^`]+)`/g,
            '<code class="rounded bg-black/10 px-1 py-0.5 text-[0.9em]">$1</code>',
        )
        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
        .replace(/__([^_]+)__/g, '<strong>$1</strong>')
        .replace(/\*([^*]+)\*/g, '<em>$1</em>');
}

function formatTime(value: string): string {
    return new Intl.DateTimeFormat('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function formatRecordingTime(seconds: number): string {
    const minutes = Math.floor(seconds / 60)
        .toString()
        .padStart(2, '0');
    const remainingSeconds = (seconds % 60).toString().padStart(2, '0');
    return `${minutes}:${remainingSeconds}`;
}

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
    element.style.height = 'auto';
    element.style.height = `${Math.min(element.scrollHeight, 136)}px`;
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
    const text = inputText.value;
    const attachments = [...pendingAttachments.value];
    const infographic = infographicForPrompt(text);
    const requestInfographic =
        infographic !== null &&
        /\b(infografis|diagram|visual|poster|bagan)\b/i.test(text);
    inputText.value = '';
    pendingAttachments.value = [];
    autoResize();
    await chat.sendMessage(text, attachments, requestInfographic);
    if (infographic && !requestInfographic) {
        await new Promise((resolve) => window.setTimeout(resolve, 450));
        messages.value.push({
            id: `infographic-${Date.now()}`,
            role: 'assistant',
            content: `### 🌿 ${infographic.title}\n\n![${infographic.imageAlt}](${infographic.image})\n\n${infographic.description}\n\n> 💧 **Air:** Sedang | ⏳ **Waktu:** Terjadwal | 📊 **Prioritas:** Utama\n\n${infographic.bullets.map((bullet, index) => `${index + 1}. **Langkah ${index + 1}**: ${bullet}`).join('\n')}\n\n> 💡 **Tips Petani:** Gunakan foto dan catatan terbaru sebelum mengambil keputusan di lapangan.`,
            attachments: [],
            createdAt: new Date().toISOString(),
            status: 'complete',
        });
    }
    await scrollToLatest();
    inputElement.value?.focus();
}

function choosePrompt(prompt: string): void {
    inputText.value = prompt;
    autoResize();
    inputElement.value?.focus();
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
    const utterance = new SpeechSynthesisUtterance(message.content);
    utterance.lang = 'id-ID';
    utterance.rate = 0.92;
    utterance.onend = () => {
        if (speakingMessageId.value === message.id)
            speakingMessageId.value = null;
    };
    speakingMessageId.value = message.id;
    speech.speak(utterance);
}

async function copyMessage(message: ChatMessage): Promise<void> {
    try {
        await navigator.clipboard.writeText(message.content);
        copiedMessageId.value = message.id;
        window.setTimeout(() => {
            if (copiedMessageId.value === message.id)
                copiedMessageId.value = null;
        }, 1600);
    } catch {
        chatError.value = 'Jawaban tidak dapat disalin.';
    }
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
    void chat.sendMessage(previous.content, previous.attachments);
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
            autoResize();
            inputElement.value?.focus();
        }
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
    void chat.loadConversations();
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
            class="relative flex min-h-full flex-col bg-background"
            @dragover.prevent="isDragging = true"
            @dragleave.self="isDragging = false"
            @drop.prevent="onDrop($event)"
        >
            <header
                class="sticky top-0 z-20 flex h-[72px] shrink-0 items-center justify-between border-b border-border/70 bg-card/95 px-5 backdrop-blur"
            >
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        aria-label="Buka riwayat percakapan"
                        class="flex size-9 items-center justify-center rounded-full text-foreground transition hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        @click="isHistoryOpen = true"
                    >
                        <ChevronLeft class="rotate-180" :size="20" />
                    </button>
                    <div
                        class="flex size-10 items-center justify-center rounded-2xl bg-secondary text-primary-foreground shadow-sm"
                    >
                        <Sparkles :size="21" stroke-width="1.7" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-foreground">
                            Asisten Kebun
                        </p>
                        <p
                            class="flex items-center gap-1 text-[11px] text-muted-foreground"
                        >
                            <span class="size-1.5 rounded-full bg-success" />
                            Siap membantu
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    aria-label="Percakapan baru"
                    class="flex size-9 items-center justify-center rounded-full text-foreground transition hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    @click="newConversation"
                >
                    <Plus :size="21" stroke-width="1.8" />
                </button>
            </header>

            <div
                v-if="isDragging"
                class="pointer-events-none absolute inset-2 z-30 flex items-center justify-center rounded-2xl border-2 border-dashed border-primary bg-card/90 text-sm font-semibold text-secondary"
            >
                Lepaskan gambar di sini
            </div>

            <div
                ref="messageList"
                class="min-h-0 flex-1 space-y-5 overflow-y-auto px-4 py-5"
            >
                <div
                    v-if="messages.length === 0"
                    class="flex min-h-[460px] flex-col items-center justify-center text-center"
                >
                    <div
                        class="mb-4 flex size-16 items-center justify-center rounded-[22px] bg-surface text-secondary"
                    >
                        <Bot :size="34" stroke-width="1.4" />
                    </div>
                    <div
                        class="mb-2 inline-flex items-center gap-1.5 rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-semibold text-secondary"
                    >
                        <Sparkles :size="13" />
                        <span>Mode Infografis Aktif</span>
                    </div>
                    <h1
                        class="text-xl font-semibold tracking-tight text-foreground"
                    >
                        Halo, ada yang bisa saya bantu?
                    </h1>
                    <p
                        class="mt-2 max-w-[280px] text-sm leading-relaxed text-muted-foreground"
                    >
                        Tanyakan kondisi kebun, aktivitas, atau panduan visual
                        pertanian Anda. Jawaban disajikan dalam format
                        infografis ringkas dan mudah dipahami.
                    </p>
                    <div class="mt-6 flex w-full flex-col gap-2">
                        <button
                            v-for="prompt in quickPrompts"
                            :key="prompt"
                            type="button"
                            class="rounded-xl border border-border bg-card px-3 py-2.5 text-left text-xs text-foreground transition hover:border-primary hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                            @click="choosePrompt(prompt)"
                        >
                            {{ prompt }}
                        </button>
                    </div>
                </div>

                <article
                    v-for="message in messages"
                    :key="message.id"
                    class="group flex gap-2.5"
                    :class="
                        message.role === 'user'
                            ? 'justify-end'
                            : 'justify-start'
                    "
                >
                    <div
                        v-if="message.role === 'assistant'"
                        class="mt-1 flex size-7 shrink-0 items-center justify-center rounded-full bg-secondary text-primary-foreground"
                    >
                        <Sparkles :size="14" stroke-width="1.8" />
                    </div>
                    <div
                        class="w-full"
                        :class="
                            message.role === 'user'
                                ? 'max-w-[84%] items-end'
                                : 'max-w-[94%] items-start'
                        "
                    >
                        <div
                            class="rounded-2xl px-3.5 py-3 text-sm leading-relaxed shadow-sm"
                            :class="
                                message.role === 'user'
                                    ? 'rounded-br-md bg-primary text-primary-foreground'
                                    : 'rounded-bl-md border border-border/70 bg-card text-foreground'
                            "
                        >
                            <div
                                v-if="message.attachments.length"
                                class="mb-2 grid grid-cols-2 gap-1.5"
                            >
                                <a
                                    v-for="attachment in message.attachments"
                                    :key="attachment.id ?? attachment.name"
                                    :href="
                                        attachment.previewUrl ?? attachment.url
                                    "
                                    target="_blank"
                                    rel="noreferrer"
                                    class="overflow-hidden rounded-lg bg-black/10"
                                >
                                    <img
                                        v-if="
                                            attachment.previewUrl ??
                                            attachment.url
                                        "
                                        :src="
                                            attachment.previewUrl ??
                                            attachment.url
                                        "
                                        :alt="attachment.name"
                                        class="aspect-square w-full object-cover"
                                    />
                                    <span
                                        v-else
                                        class="flex aspect-square items-center justify-center"
                                        ><ImageIcon :size="20"
                                    /></span>
                                </a>
                            </div>
                            <div v-if="message.content">
                                <AiInfographicCard
                                    v-if="message.role === 'assistant'"
                                    :content="message.content"
                                    :is-streaming="
                                        message.status === 'streaming'
                                    "
                                    @copied="copiedMessageId = message.id"
                                />
                                <div
                                    v-else
                                    class="text-sm leading-relaxed"
                                    v-html="
                                        escapeHtml(message.content).replaceAll(
                                            '\n',
                                            '<br>',
                                        )
                                    "
                                />
                            </div>
                            <div
                                v-if="message.status === 'streaming'"
                                class="mt-1 flex items-center gap-1 text-muted-foreground"
                                aria-label="Jawaban sedang ditulis"
                            >
                                <span
                                    class="size-1.5 animate-pulse rounded-full bg-current"
                                /><span
                                    class="size-1.5 animate-pulse rounded-full bg-current [animation-delay:120ms]"
                                /><span
                                    class="size-1.5 animate-pulse rounded-full bg-current [animation-delay:240ms]"
                                />
                            </div>
                            <div
                                v-if="message.status === 'error'"
                                class="mt-2 flex items-center gap-1.5 text-xs text-error"
                            >
                                <AlertCircle :size="14" />
                                {{ message.error || 'Pesan gagal dikirim.' }}
                            </div>
                        </div>
                        <div
                            class="mt-1 flex items-center gap-2 px-1 text-[10px] text-muted-foreground"
                            :class="
                                message.role === 'user'
                                    ? 'justify-end'
                                    : 'justify-start'
                            "
                        >
                            <span>{{ formatTime(message.createdAt) }}</span
                            ><template
                                v-if="
                                    message.role === 'assistant' &&
                                    message.content
                                "
                                ><button
                                    type="button"
                                    class="rounded p-0.5 transition hover:text-foreground"
                                    :aria-label="
                                        speakingMessageId === message.id
                                            ? 'Hentikan suara'
                                            : 'Bacakan jawaban'
                                    "
                                    @click="speak(message)"
                                >
                                    <Square
                                        v-if="speakingMessageId === message.id"
                                        :size="12"
                                        fill="currentColor"
                                    /><Volume2 v-else :size="13" /></button
                                ><button
                                    type="button"
                                    class="rounded p-0.5 transition hover:text-foreground"
                                    aria-label="Salin jawaban"
                                    @click="copyMessage(message)"
                                >
                                    <Check
                                        v-if="copiedMessageId === message.id"
                                        :size="13"
                                        class="text-success"
                                    /><Copy v-else :size="13" /></button
                            ></template>
                        </div>
                        <button
                            v-if="
                                message.status === 'error' &&
                                message.role === 'assistant'
                            "
                            type="button"
                            class="mt-1 flex items-center gap-1 px-1 text-[11px] font-medium text-secondary hover:underline"
                            @click="retryLast"
                        >
                            <RefreshCw :size="12" /> Coba lagi
                        </button>
                    </div>
                    <div
                        v-if="message.role === 'user'"
                        class="mt-1 flex size-7 shrink-0 items-center justify-center rounded-full bg-muted text-foreground"
                    >
                        <span class="text-[11px] font-semibold">Anda</span>
                    </div>
                </article>
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

            <div
                class="sticky bottom-0 z-10 border-t border-border/70 bg-card/95 px-3 pt-2 pb-3 backdrop-blur"
            >
                <div
                    v-if="pendingAttachments.length"
                    class="mb-2 flex gap-2 overflow-x-auto pb-0.5"
                >
                    <div
                        v-for="(attachment, index) in pendingAttachments"
                        :key="attachment.previewUrl ?? attachment.name"
                        class="relative size-14 shrink-0 overflow-hidden rounded-lg border border-border bg-muted"
                    >
                        <img
                            v-if="attachment.previewUrl"
                            :src="attachment.previewUrl"
                            :alt="attachment.name"
                            class="size-full object-cover"
                        /><ImageIcon v-else class="m-4" :size="22" /><button
                            type="button"
                            class="absolute top-0.5 right-0.5 flex size-5 items-center justify-center rounded-full bg-black/70 text-white"
                            :aria-label="`Hapus ${attachment.name}`"
                            @click="removeAttachment(index)"
                        >
                            <X :size="12" />
                        </button>
                    </div>
                    <span class="self-center text-[10px] text-muted-foreground"
                        >{{ pendingAttachments.length }}/{{
                            MAX_ATTACHMENT_COUNT
                        }}</span
                    >
                </div>
                <div
                    v-if="voice.isRecording.value"
                    role="status"
                    aria-live="polite"
                    class="mb-2 flex items-center gap-3 rounded-xl border border-error/20 bg-error/5 px-3 py-2.5 text-xs text-foreground"
                >
                    <span
                        class="relative flex size-8 shrink-0 items-center justify-center rounded-full bg-error/10 text-error"
                    >
                        <span
                            class="absolute inset-0 animate-ping rounded-full bg-error/20"
                        ></span
                        ><Mic :size="15" class="relative" />
                    </span>
                    <span class="flex min-w-0 flex-1 flex-col">
                        <span class="font-semibold">Rekaman aktif</span>
                        <span class="truncate text-[11px] text-muted-foreground"
                            >Silakan bicara, tekan Selesai jika sudah
                            cukup.</span
                        >
                    </span>
                    <span
                        class="shrink-0 font-mono text-xs text-error tabular-nums"
                        >{{
                            formatRecordingTime(voice.elapsedSeconds.value)
                        }}</span
                    >
                    <button
                        type="button"
                        class="shrink-0 rounded-lg px-2 py-1 font-semibold text-error transition hover:bg-error/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        @click="voice.stopRecording"
                    >
                        Selesai
                    </button>
                </div>
                <div
                    v-else-if="voice.isTranscribing.value"
                    role="status"
                    aria-live="polite"
                    class="mb-2 flex items-center gap-3 rounded-xl border border-border bg-muted px-3 py-2.5 text-xs text-foreground"
                >
                    <span
                        class="flex size-8 shrink-0 items-center justify-center rounded-full bg-background text-muted-foreground"
                        ><LoaderCircle :size="15" class="animate-spin"
                    /></span>
                    <span class="flex min-w-0 flex-1 flex-col">
                        <span class="font-semibold">Menyiapkan teks</span>
                        <span class="text-[11px] text-muted-foreground"
                            >Tunggu sebentar, suara sedang diproses.</span
                        >
                    </span>
                </div>
                <div
                    v-if="voice.error.value"
                    role="alert"
                    class="mb-2 rounded-xl border border-error/25 bg-error/5 px-3 py-2 text-xs text-error"
                >
                    {{ voice.error.value }}
                </div>
                <div
                    class="flex items-end gap-1.5 rounded-2xl border border-border bg-background p-1.5 transition focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20"
                >
                    <button
                        type="button"
                        aria-label="Tambahkan gambar"
                        class="mb-0.5 flex size-9 shrink-0 items-center justify-center rounded-xl text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        @click="openFilePicker"
                    >
                        <Paperclip :size="19" stroke-width="1.7" />
                    </button>
                    <input
                        ref="fileInput"
                        type="file"
                        accept="image/jpeg,image/png,image/webp,image/gif"
                        multiple
                        class="hidden"
                        @change="onFileChange"
                    />
                    <textarea
                        ref="inputElement"
                        v-model="inputText"
                        rows="1"
                        placeholder="Tulis pesan…"
                        aria-label="Pesan untuk asisten kebun"
                        class="max-h-[136px] min-h-9 flex-1 resize-none border-0 bg-transparent px-1.5 py-2 text-sm leading-5 text-foreground outline-none placeholder:text-muted-foreground"
                        @input="autoResize"
                        @paste="onPaste"
                        @keydown.enter.exact.prevent="send"
                    />
                    <button
                        type="button"
                        :aria-label="
                            voice.isRecording.value
                                ? 'Hentikan rekaman'
                                : voice.isTranscribing.value
                                  ? 'Menunggu transkrip suara'
                                  : 'Rekam pesan suara'
                        "
                        :disabled="voice.isTranscribing.value"
                        class="mb-0.5 flex size-9 shrink-0 items-center justify-center rounded-xl transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        :class="
                            voice.isRecording.value
                                ? 'bg-error text-error-foreground'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground disabled:cursor-wait disabled:opacity-60'
                        "
                        @click="toggleVoice"
                    >
                        <Square
                            v-if="voice.isRecording.value"
                            :size="16"
                            fill="currentColor"
                        /><LoaderCircle
                            v-else-if="voice.isTranscribing.value"
                            :size="18"
                            class="animate-spin"
                        /><Mic v-else :size="19" stroke-width="1.7" />
                    </button>
                    <button
                        v-if="isSending"
                        type="button"
                        aria-label="Hentikan jawaban"
                        class="mb-0.5 flex size-9 shrink-0 items-center justify-center rounded-xl bg-secondary text-primary-foreground transition hover:bg-secondary/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        @click="chat.stopStreaming"
                    >
                        <Square :size="15" fill="currentColor" />
                    </button>
                    <button
                        v-else
                        type="button"
                        :disabled="!canSend"
                        aria-label="Kirim pesan"
                        class="mb-0.5 flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground transition hover:bg-primary/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:cursor-not-allowed disabled:opacity-40"
                        @click="send"
                    >
                        <Send :size="18" stroke-width="1.8" />
                    </button>
                </div>
                <p class="mt-1.5 text-center text-[10px] text-muted-foreground">
                    AI dapat membuat kesalahan. Periksa informasi penting
                    sebelum bertindak.
                </p>
            </div>

            <aside
                v-if="isHistoryOpen"
                class="absolute inset-0 z-40 flex flex-col bg-card"
            >
                <header
                    class="flex h-[72px] shrink-0 items-center gap-3 border-b border-border/70 px-4"
                >
                    <button
                        type="button"
                        aria-label="Tutup riwayat"
                        class="flex size-9 items-center justify-center rounded-full transition hover:bg-muted"
                        @click="isHistoryOpen = false"
                    >
                        <ChevronLeft :size="21" />
                    </button>
                    <h2 class="text-base font-semibold">Riwayat percakapan</h2>
                    <button
                        type="button"
                        aria-label="Percakapan baru"
                        class="ml-auto flex size-9 items-center justify-center rounded-full transition hover:bg-muted"
                        @click="newConversation"
                    >
                        <Plus :size="20" />
                    </button>
                </header>
                <div class="flex-1 overflow-y-auto p-3">
                    <button
                        type="button"
                        class="mb-2 flex w-full items-center gap-3 rounded-xl border border-dashed border-primary/50 px-3 py-3 text-left text-sm font-medium text-secondary transition hover:bg-muted"
                        @click="newConversation"
                    >
                        <Plus :size="17" /> Percakapan baru
                    </button>
                    <p
                        v-if="isLoadingConversations"
                        class="flex items-center justify-center gap-2 py-8 text-xs text-muted-foreground"
                    >
                        <LoaderCircle :size="15" class="animate-spin" /> Memuat
                        riwayat…
                    </p>
                    <p
                        v-else-if="conversations.length === 0"
                        class="px-3 py-8 text-center text-xs leading-relaxed text-muted-foreground"
                    >
                        Belum ada percakapan tersimpan.
                    </p>
                    <div v-else class="space-y-1">
                        <button
                            v-for="conversation in conversations"
                            :key="conversation.id"
                            type="button"
                            class="w-full rounded-xl px-3 py-3 text-left transition hover:bg-muted"
                            :class="
                                conversation.id === conversationId
                                    ? 'bg-muted'
                                    : ''
                            "
                            @click="selectConversation(conversation.id)"
                        >
                            <p
                                class="truncate text-sm font-medium text-foreground"
                            >
                                {{ conversation.title }}
                            </p>
                            <p
                                class="mt-1 truncate text-xs text-muted-foreground"
                            >
                                {{ conversation.preview || 'Belum ada pesan' }}
                            </p>
                        </button>
                    </div>
                </div>
            </aside>
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
