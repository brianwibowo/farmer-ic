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
    period: string;
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
                    class="-mt-1 inline-flex size-6 shrink-0 items-center justify-center rounded-full transition-colors hover:bg-secondary/10 focus-visible:ring-2 focus-visible:ring-current focus-visible:outline-none"
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
            >
                <p class="font-semibold">{{ label }}</p>
                <p>{{ description }}</p>
                <p><span class="font-semibold">Periode:</span> {{ period }}</p>
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
