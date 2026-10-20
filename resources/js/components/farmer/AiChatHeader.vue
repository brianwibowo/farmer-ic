<script setup lang="ts">
import { BarChart3, ChevronLeft, Plus, Sparkles } from '@lucide/vue';

const props = withDefaults(
    defineProps<{
        isSending?: boolean;
    }>(),
    {
        isSending: false,
    },
);

const emit = defineEmits<{
    openHistory: [];
    openReport: [];
    newConversation: [];
}>();
</script>

<template>
    <header
        class="z-20 flex h-[72px] shrink-0 items-center justify-between border-b border-border/70 bg-card px-5"
    >
        <div class="flex items-center gap-3">
            <button
                type="button"
                aria-label="Buka riwayat percakapan"
                class="flex size-9 items-center justify-center rounded-full text-foreground transition hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                @click="emit('openHistory')"
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
                    <span
                        class="size-1.5 rounded-full"
                        :class="
                            props.isSending
                                ? 'animate-pulse bg-primary'
                                : 'bg-success'
                        "
                    />
                    {{
                        props.isSending
                            ? 'Menyiapkan jawaban…'
                            : 'Siap membantu'
                    }}
                </p>
            </div>
        </div>
        <div class="flex items-center gap-1">
            <button
                type="button"
                aria-label="Buka laporan performa AI"
                title="Laporan performa AI"
                class="flex size-9 items-center justify-center rounded-full text-foreground transition hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                @click="emit('openReport')"
            >
                <BarChart3 :size="19" stroke-width="1.8" />
            </button>
            <button
                type="button"
                aria-label="Percakapan baru"
                title="Percakapan baru"
                class="flex size-9 items-center justify-center rounded-full text-foreground transition hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                @click="emit('newConversation')"
            >
                <Plus :size="21" stroke-width="1.8" />
            </button>
        </div>
    </header>
</template>
