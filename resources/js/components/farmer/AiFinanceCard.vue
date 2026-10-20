<script setup lang="ts">
import { computed } from 'vue';
import { ArrowDown, ArrowUp, CircleDollarSign, TrendingUp } from '@lucide/vue';
import { normalizeAiResponse } from '@/services/aiChatService';

const props = defineProps<{ content: string; metadata?: Record<string, unknown> }>();
const text = computed(() => normalizeAiResponse(props.content || ''));
function amount(label: string): string {
    const match = text.value.match(new RegExp(`\\|\\s*${label}\\s*\\|\\s*([^|\\n]+)`, 'i'));
    return match?.[1]?.trim() ?? '—';
}
const income = computed(() => amount('Pemasukan'));
const expense = computed(() => amount('Pengeluaran'));
const net = computed(() => amount('Laba bersih'));
const source = computed(() => text.value.match(/Sumber:\s*(.+)$/im)?.[1]?.trim() ?? 'Data finance');
</script>
<template>
    <div class="space-y-3">
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
            <div class="rounded-xl border border-success/20 bg-success/10 p-3">
                <div class="flex items-center gap-2 text-xs font-medium text-success"><ArrowUp :size="14" /> Pemasukan</div>
                <div class="mt-1 text-base font-semibold text-foreground">{{ income }}</div>
            </div>
            <div class="rounded-xl border border-error/20 bg-error/10 p-3">
                <div class="flex items-center gap-2 text-xs font-medium text-error"><ArrowDown :size="14" /> Pengeluaran</div>
                <div class="mt-1 text-base font-semibold text-foreground">{{ expense }}</div>
            </div>
            <div class="rounded-xl border border-primary/20 bg-primary/10 p-3">
                <div class="flex items-center gap-2 text-xs font-medium text-secondary"><TrendingUp :size="14" /> Laba bersih</div>
                <div class="mt-1 text-base font-semibold text-foreground">{{ net }}</div>
            </div>
        </div>
        <div class="rounded-lg bg-muted/60 px-3 py-2 text-xs leading-5 text-muted-foreground">
            <span class="font-medium text-foreground">Sumber:</span> {{ source }}
        </div>
        <div class="text-sm leading-6 text-foreground" v-html="text.replace(/\n/g, '<br>')" />
    </div>
</template>
