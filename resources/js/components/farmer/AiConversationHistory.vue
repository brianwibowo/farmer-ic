<script setup lang="ts">
import { ChevronLeft, LoaderCircle, Plus } from '@lucide/vue';
import type { Conversation } from '@/types/aiChat';

defineProps<{
    conversations: Conversation[];
    conversationId: string | null;
    isLoading: boolean;
}>();

const emit = defineEmits<{
    close: [];
    newConversation: [];
    select: [id: string];
}>();
</script>

<template>
    <aside class="absolute inset-0 z-40 flex flex-col bg-card">
        <header
            class="flex h-[72px] shrink-0 items-center gap-3 border-b border-border/70 px-4"
        >
            <button
                type="button"
                aria-label="Tutup riwayat"
                class="flex size-9 items-center justify-center rounded-full transition hover:bg-muted"
                @click="emit('close')"
            >
                <ChevronLeft :size="21" />
            </button>
            <h2 class="text-base font-semibold">Riwayat percakapan</h2>
            <button
                type="button"
                aria-label="Percakapan baru"
                class="ml-auto flex size-9 items-center justify-center rounded-full transition hover:bg-muted"
                @click="emit('newConversation')"
            >
                <Plus :size="20" />
            </button>
        </header>
        <div class="flex-1 overflow-y-auto p-3">
            <button
                type="button"
                class="mb-2 flex w-full items-center gap-3 rounded-xl border border-dashed border-primary/50 px-3 py-3 text-left text-sm font-medium text-secondary transition hover:bg-muted"
                @click="emit('newConversation')"
            >
                <Plus :size="17" /> Percakapan baru
            </button>
            <p
                v-if="isLoading"
                class="flex items-center justify-center gap-2 py-8 text-xs text-muted-foreground"
            >
                <LoaderCircle :size="15" class="animate-spin" /> Memuat riwayat…
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
                        conversation.id === conversationId ? 'bg-muted' : ''
                    "
                    @click="emit('select', conversation.id)"
                >
                    <p class="truncate text-sm font-medium text-foreground">
                        {{ conversation.title }}
                    </p>
                    <p class="mt-1 truncate text-xs text-muted-foreground">
                        {{ conversation.preview || 'Belum ada pesan' }}
                    </p>
                </button>
            </div>
        </div>
    </aside>
</template>
