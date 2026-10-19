<script setup lang="ts">
import {
    AlertCircle,
    Bot,
    Check,
    Copy,
    Image as ImageIcon,
    LoaderCircle,
    RefreshCw,
    Sparkles,
    Square,
    ThumbsDown,
    ThumbsUp,
    Volume2,
} from '@lucide/vue';
import AiInfographicCard from '@/components/farmer/AiInfographicCard.vue';
import type { ChatAttachment, ChatMessage } from '@/types/aiChat';

const props = defineProps<{
    messages: ChatMessage[];
    quickPrompts: string[];
    copiedMessageId: string | null;
    speakingMessageId: string | null;
}>();

const emit = defineEmits<{
    choosePrompt: [prompt: string];
    speak: [message: ChatMessage];
    copyError: [message: string];
    feedback: [message: ChatMessage, feedback: 'up' | 'down'];
    retry: [];
    copied: [messageId: string];
}>();

function escapeHtml(value: string): string {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function formatTime(value: string): string {
    return new Intl.DateTimeFormat('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function inlineImageSources(value: string): string[] {
    const pattern = /!\[[^\]]*\]\(((?:https?:\/\/|\/|data:image\/)[^)]+)\)/gi;
    return Array.from(value.matchAll(pattern), (match) => match[1]).filter(
        (source): source is string => Boolean(source),
    );
}

function visibleAttachments(message: ChatMessage): ChatAttachment[] {
    if (message.role !== 'assistant') return message.attachments;
    const inlineSources = inlineImageSources(message.content);
    return message.attachments.filter((attachment) => {
        const source = attachment.url;
        const isGeneratedInfographic = Boolean(
            source && /\/storage\/ai\/infographics\//i.test(source),
        );
        return (
            !source ||
            (!isGeneratedInfographic && !inlineSources.includes(source))
        );
    });
}

async function copyMessage(message: ChatMessage): Promise<void> {
    try {
        await navigator.clipboard.writeText(message.content);
        emit('copied', message.id);
    } catch {
        emit('copyError', 'Jawaban tidak dapat disalin.');
    }
}
</script>

<template>
    <div
        v-if="props.messages.length === 0"
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
            <span>Mode respons adaptif</span>
        </div>
        <h1 class="text-xl font-semibold tracking-tight text-foreground">
            Halo, ada yang bisa saya bantu?
        </h1>
        <p
            class="mt-2 max-w-[280px] text-sm leading-relaxed text-muted-foreground"
        >
            Tanyakan kondisi kebun, aktivitas, atau panduan visual pertanian
            Anda. AI akan memilih format data, jawaban, atau infografis yang
            paling sesuai.
        </p>
        <div class="mt-6 flex w-full flex-col gap-2">
            <button
                v-for="prompt in props.quickPrompts"
                :key="prompt"
                type="button"
                class="rounded-xl border border-border bg-card px-3 py-2.5 text-left text-xs text-foreground transition hover:border-primary hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                @click="emit('choosePrompt', prompt)"
            >
                {{ prompt }}
            </button>
        </div>
    </div>

    <article
        v-for="message in props.messages"
        :key="message.id"
        class="group flex gap-2.5"
        :class="message.role === 'user' ? 'justify-end' : 'justify-start'"
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
                    v-if="visibleAttachments(message).length"
                    class="mb-2 grid grid-cols-2 gap-1.5"
                >
                    <a
                        v-for="attachment in visibleAttachments(message)"
                        :key="attachment.id ?? attachment.name"
                        :href="attachment.previewUrl ?? attachment.url"
                        target="_blank"
                        rel="noreferrer"
                        class="overflow-hidden rounded-lg bg-black/10"
                    >
                        <img
                            v-if="attachment.previewUrl ?? attachment.url"
                            :src="attachment.previewUrl ?? attachment.url"
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
                        :is-streaming="message.status === 'streaming'"
                        :force-visual="message.presentation === 'infographic'"
                        @copied="emit('copied', message.id)"
                    />
                    <div
                        v-else
                        class="text-sm leading-relaxed"
                        v-html="
                            escapeHtml(message.content).replaceAll('\n', '<br>')
                        "
                    />
                </div>
                <div
                    v-if="message.status === 'streaming'"
                    class="mt-2 flex items-center gap-2 rounded-lg bg-muted/60 px-2.5 py-2 text-xs text-muted-foreground"
                    aria-label="Jawaban sedang ditulis"
                    role="status"
                    aria-live="polite"
                >
                    <LoaderCircle
                        :size="15"
                        class="shrink-0 animate-spin motion-reduce:animate-none"
                    />
                    <span class="min-w-0">
                        <span class="block font-medium text-foreground">{{
                            message.progress ||
                            (message.content
                                ? 'AI sedang menulis…'
                                : 'Menyiapkan jawaban…')
                        }}</span>
                        <span class="block text-[11px] leading-4">
                            Jawaban biasanya muncul dalam beberapa detik. Jika
                            lebih dari 30 detik, Anda bisa menghentikannya.
                        </span>
                    </span>
                </div>
                <div
                    v-if="message.status === 'cancelled'"
                    class="mt-2 flex items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <Square :size="12" /> Jawaban dihentikan.
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
                v-if="
                    message.role === 'assistant' &&
                    message.status === 'complete' &&
                    message.content
                "
                class="mt-1 flex items-center justify-start gap-1 px-1 text-[10px] text-muted-foreground"
            >
                <span class="mr-1">Membantu?</span>
                <button
                    type="button"
                    class="rounded p-1 transition hover:bg-muted hover:text-success"
                    :class="
                        message.feedback === 'up'
                            ? 'bg-success/10 text-success'
                            : ''
                    "
                    aria-label="Jawaban membantu"
                    @click="emit('feedback', message, 'up')"
                >
                    <ThumbsUp :size="12" />
                </button>
                <button
                    type="button"
                    class="rounded p-1 transition hover:bg-muted hover:text-error"
                    :class="
                        message.feedback === 'down'
                            ? 'bg-error/10 text-error'
                            : ''
                    "
                    aria-label="Jawaban kurang membantu"
                    @click="emit('feedback', message, 'down')"
                >
                    <ThumbsDown :size="12" />
                </button>
            </div>
            <div
                class="mt-1 flex items-center gap-2 px-1 text-[10px] text-muted-foreground"
                :class="
                    message.role === 'user' ? 'justify-end' : 'justify-start'
                "
            >
                <span>{{ formatTime(message.createdAt) }}</span>
                <template
                    v-if="message.role === 'assistant' && message.content"
                >
                    <button
                        type="button"
                        class="rounded p-0.5 transition hover:text-foreground"
                        :aria-label="
                            props.speakingMessageId === message.id
                                ? 'Hentikan suara'
                                : 'Bacakan jawaban'
                        "
                        @click="emit('speak', message)"
                    >
                        <Square
                            v-if="props.speakingMessageId === message.id"
                            :size="12"
                            fill="currentColor"
                        /><Volume2 v-else :size="13" />
                    </button>
                    <button
                        type="button"
                        class="rounded p-0.5 transition hover:text-foreground"
                        aria-label="Salin jawaban"
                        @click="copyMessage(message)"
                    >
                        <Check
                            v-if="props.copiedMessageId === message.id"
                            :size="13"
                            class="text-success"
                        /><Copy v-else :size="13" />
                    </button>
                </template>
            </div>
            <button
                v-if="
                    message.status === 'error' && message.role === 'assistant'
                "
                type="button"
                class="mt-1 flex items-center gap-1 px-1 text-[11px] font-medium text-secondary hover:underline"
                @click="emit('retry')"
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
</template>
