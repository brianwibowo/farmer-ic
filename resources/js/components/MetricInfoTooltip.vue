<script setup lang="ts">
import { Info } from '@lucide/vue';
import { ref } from 'vue';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

defineProps<{
    label: string;
    description: string;
    period?: string;
    example?: string;
    contentClass?: string;
    touchTarget?: boolean;
}>();

const open = ref(false);
</script>

<template>
    <TooltipProvider :delay-duration="150">
        <Tooltip v-model:open="open" disable-closing-trigger>
            <TooltipTrigger as-child>
                <button
                    type="button"
                    :aria-label="`Informasi ${label}`"
                    :aria-expanded="open"
                    class="inline-flex shrink-0 touch-manipulation items-center justify-center rounded-full transition-colors hover:bg-secondary/10 focus-visible:ring-2 focus-visible:ring-current focus-visible:outline-none"
                    :class="touchTarget ? '-my-2.5 size-11' : '-mt-1 size-6'"
                    @click="open = !open"
                >
                    <Info :size="15" :stroke-width="1.8" aria-hidden="true" />
                </button>
            </TooltipTrigger>
            <TooltipContent
                side="top"
                :side-offset="8"
                :collision-padding="16"
                class="w-64 max-w-[calc(100vw-2rem)] space-y-2 rounded-xl p-3 text-left text-xs leading-5 text-pretty"
                :class="contentClass"
            >
                <p class="font-semibold">{{ label }}</p>
                <p>{{ description }}</p>
                <p v-if="period">
                    <span class="font-semibold">Periode:</span> {{ period }}
                </p>
                <p v-if="example">
                    <span class="font-semibold">Contoh:</span> {{ example }}
                </p>
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
