<script setup lang="ts">
import {
    Image as ImageIcon,
    LoaderCircle,
    Mic,
    Paperclip,
    Send,
    Square,
    X,
} from '@lucide/vue';
import type { VNodeRef } from 'vue';
import type { ChatAttachment } from '@/types/aiChat';
import type { useAiVoice } from '@/composables/useAiVoice';

type VoiceApi = ReturnType<typeof useAiVoice>;

defineProps<{
    modelValue: string;
    pendingAttachments: ChatAttachment[];
    maxAttachmentCount: number;
    voice: VoiceApi;
    isSending: boolean;
    canSend: boolean;
    textareaRef: VNodeRef;
    fileInputRef: VNodeRef;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
    'open-file-picker': [];
    'file-change': [event: Event];
    paste: [event: ClipboardEvent];
    resize: [];
    'remove-attachment': [index: number];
    'toggle-voice': [];
    'stop-streaming': [];
    send: [];
}>();

function updateValue(event: Event): void {
    emit('update:modelValue', (event.target as HTMLTextAreaElement).value);
}

function onInput(event: Event): void {
    updateValue(event);
    emit('resize');
}
</script>

<template>
    <div class="z-10 shrink-0 border-t border-border/70 bg-card px-3 pt-2 pb-3">
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
                    @click="emit('remove-attachment', index)"
                >
                    <X :size="12" />
                </button>
            </div>
            <span class="self-center text-[10px] text-muted-foreground"
                >{{ pendingAttachments.length }}/{ maxAttachmentCount }}</span
            >
        </div>
        <div
            v-if="voice.isPreparing.value"
            role="status"
            aria-live="polite"
            class="mb-2 flex items-center gap-3 rounded-xl border border-border bg-muted px-3 py-2.5 text-xs text-foreground"
        >
            <span
                class="flex size-8 shrink-0 items-center justify-center rounded-full bg-background text-muted-foreground"
                ><LoaderCircle :size="15" class="animate-spin"
            /></span>
            <span class="flex min-w-0 flex-1 flex-col">
                <span class="font-semibold">Menyiapkan mikrofon</span>
                <span class="text-[11px] text-muted-foreground"
                    >Izinkan akses mikrofon jika browser memintanya.</span
                >
            </span>
            <button
                type="button"
                class="shrink-0 rounded-lg px-2 py-1 font-semibold text-muted-foreground transition hover:bg-background hover:text-foreground"
                @click="voice.cancelPreparing"
            >
                Batal
            </button>
        </div>
        <div
            v-else-if="voice.isRecording.value"
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
                <span class="truncate text-[11px] text-muted-foreground">{{
                    voice.recordingMode.value === 'browser'
                        ? 'Dikte browser aktif · hasil bisa diedit.'
                        : 'Suara diproses menjadi teks, lalu bisa diedit.'
                }}</span>
            </span>
            <span class="shrink-0 font-mono text-xs text-error tabular-nums"
                >{{
                    Math.floor(voice.elapsedSeconds.value / 60)
                        .toString()
                        .padStart(2, '0')
                }}:{{
                    (voice.elapsedSeconds.value % 60)
                        .toString()
                        .padStart(2, '0')
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
            v-if="isSending"
            role="status"
            aria-live="polite"
            class="mb-2 flex items-center gap-2.5 rounded-xl border border-primary/25 bg-primary/5 px-3 py-2.5 text-xs text-foreground"
        >
            <LoaderCircle
                :size="16"
                class="shrink-0 animate-spin text-secondary motion-reduce:animate-none"
            />
            <span class="min-w-0 flex-1">
                <span class="block font-semibold">Asisten sedang bekerja…</span>
                <span class="block text-[11px] text-muted-foreground"
                    >Jawaban biasanya muncul dalam beberapa detik. Jika lebih
                    dari 30 detik, Anda bisa menghentikannya.</span
                >
            </span>
            <button
                type="button"
                class="shrink-0 rounded-lg px-2 py-1 font-semibold text-secondary transition hover:bg-primary/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                @click="emit('stop-streaming')"
            >
                Hentikan
            </button>
        </div>
        <div
            class="flex items-end gap-1.5 rounded-2xl border border-border bg-background p-1.5 shadow-sm transition focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20"
        >
            <button
                type="button"
                aria-label="Tambahkan gambar"
                class="mb-0.5 flex size-9 shrink-0 items-center justify-center rounded-xl text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                @click="emit('open-file-picker')"
            >
                <Paperclip :size="19" stroke-width="1.7" />
            </button>
            <input
                :ref="fileInputRef"
                type="file"
                accept="image/jpeg,image/png,image/webp,image/gif"
                multiple
                class="hidden"
                @change="emit('file-change', $event)"
            />
            <textarea
                :ref="textareaRef"
                :value="modelValue"
                rows="1"
                placeholder="Tulis pesan…"
                aria-label="Pesan untuk asisten kebun"
                class="max-h-[136px] min-h-9 flex-1 resize-none overflow-y-auto border-0 bg-transparent px-1.5 py-2 text-sm leading-5 break-words whitespace-pre-wrap text-foreground outline-none placeholder:text-muted-foreground"
                @input="onInput"
                @paste="emit('paste', $event)"
                @keydown.enter.exact.prevent="emit('send')"
            />
            <button
                type="button"
                :aria-label="
                    voice.isRecording.value
                        ? 'Hentikan rekaman'
                        : voice.isPreparing.value
                          ? 'Menunggu izin mikrofon'
                          : voice.isTranscribing.value
                            ? 'Menunggu transkrip suara'
                            : 'Rekam pesan suara'
                "
                :disabled="
                    voice.isTranscribing.value || voice.isPreparing.value
                "
                class="mb-0.5 flex size-9 shrink-0 items-center justify-center rounded-xl transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                :class="
                    voice.isRecording.value
                        ? 'bg-error text-error-foreground'
                        : 'text-muted-foreground hover:bg-muted hover:text-foreground disabled:cursor-wait disabled:opacity-60'
                "
                @click="emit('toggle-voice')"
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
                title="Hentikan jawaban"
                class="mb-0.5 flex size-9 shrink-0 items-center justify-center rounded-xl bg-secondary text-primary-foreground transition hover:bg-secondary/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                @click="emit('stop-streaming')"
            >
                <Square :size="15" fill="currentColor" />
            </button>
            <button
                v-else
                type="button"
                :disabled="!canSend"
                aria-label="Kirim pesan"
                class="mb-0.5 flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground transition hover:bg-primary/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:cursor-not-allowed disabled:opacity-40"
                @click="emit('send')"
            >
                <Send :size="18" stroke-width="1.8" />
            </button>
        </div>
        <p class="mt-1.5 text-center text-[10px] text-muted-foreground">
            AI dapat membuat kesalahan. Periksa informasi penting sebelum
            bertindak.
        </p>
    </div>
</template>
