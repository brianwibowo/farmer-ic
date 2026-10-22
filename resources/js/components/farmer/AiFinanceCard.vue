<script setup lang="ts">
import { computed } from 'vue';
import { ArrowDown, ArrowUp, TrendingUp } from '@lucide/vue';
import { normalizeAiResponse } from '@/services/aiChatService';

const props = defineProps<{ content: string; metadata?: Record<string, unknown> }>();
const text = computed(() => normalizeAiResponse(props.content || ''));

// Model output is untrusted text.  This component builds a small HTML view
// with v-html below, so escape the source before applying the intentional
// markdown-to-HTML replacements.  Without this step a provider response (or
// a poisoned knowledge document echoed by the model) could inject arbitrary
// markup into the chat.
function escapeHtml(value: string): string {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

const snapshotPoint = computed(() => {
    const snapshot = props.metadata?.finance_snapshot;
    const prompt = String(props.metadata?.finance_prompt ?? '').toLowerCase();
    if (!snapshot || typeof snapshot !== 'object') return null;
    const quarters = (snapshot as { quarters?: Record<string, { points?: Array<Record<string, unknown>> }> }).quarters ?? {};
    const points = Object.values(quarters).flatMap((quarter) => quarter.points ?? []);
    const month = ['januari', 'februari', 'maret', 'april', 'mei', 'juni', 'juli', 'agustus', 'september', 'oktober', 'november', 'desember']
        .find((name) => prompt.includes(name));
    return month ? points.find((item) => String(item.label ?? '').toLowerCase().includes(month)) ?? null : null;
});

function amount(label: string): string {
    const alias = label === 'Pemasukan' ? '(?:Pemasukan|Pendapatan)'
        : label === 'Pengeluaran' ? '(?:Pengeluaran|Biaya|Beban)'
        : '(?:Laba\\s+bersih|Laba|Profit)';

    // 1. Check table: | Pemasukan | Rp... |
    const tableMatch = text.value.match(new RegExp(`\\|\\s*${alias}\\s*\\|\\s*([^|\\n]+)`, 'i'));
    if (tableMatch?.[1]) {
        const val = tableMatch[1].replace(/[*_]/g, '').trim();
        const amt = val.match(/Rp\s*[\d.,]+/i);
        return amt ? amt[0].replace(/\s+/g, '') : val;
    }

    // 2. Check line / bullet: [*-•]? **?Pemasukan**?:? Rp...
    const lineMatch = text.value.match(new RegExp(`(?:^|[\\n•*-])\\s*\\**\\s*${alias}\\s*\\**\\s*:\\s*([^\\n]+)`, 'im'));
    if (lineMatch?.[1]) {
        const val = lineMatch[1].replace(/[*_]/g, '').trim();
        const amt = val.match(/Rp\s*[\d.,]+/i);
        return amt ? amt[0].replace(/\s+/g, '') : val;
    }

    // 3. Fallback to snapshotPoint if present
    if (snapshotPoint.value) {
        const key = label === 'Pemasukan' ? 'revenue' : label === 'Pengeluaran' ? 'expense' : 'net';
        const fallback = snapshotPoint.value[key];
        if (typeof fallback === 'number') return `Rp${fallback.toLocaleString('id-ID')}`;
    }

    return '—';
}

const income = computed(() => amount('Pemasukan'));
const expense = computed(() => amount('Pengeluaran'));
const parsedNet = computed(() => amount('Laba bersih'));
const net = computed(() => {
    if (parsedNet.value !== '—') return parsedNet.value;
    const toNumber = (value: string) => Number(value.replace(/[^0-9-]/g, '')) || 0;
    const total = toNumber(income.value) - toNumber(expense.value);
    return total > 0 ? `Rp${total.toLocaleString('id-ID')}` : '—';
});

const hasMetrics = computed(() => income.value !== '—' || expense.value !== '—' || net.value !== '—');

const image = computed(() => {
    const match = text.value.match(/!\[([^\]]*)\]\((https?:\/\/[^)\s]+|\/[^)\s]+)\)/);
    return match ? { alt: match[1] || 'Gambar keuangan', url: match[2] } : null;
});

const source = computed(() => text.value.match(/Sumber:\s*(.+)$/im)?.[1]?.trim() ?? 'Data finance');

const body = computed(() => text.value
    .replace(/!\[[^\]]*\]\([^)]*\)/g, '')
    .replace(/Sumber:\s*.+$/im, '')
    .replace(/^#{1,6}\s*/gm, '')
    .trim());

function formatMarkdownTable(tableMarkdown: string): string {
    const lines = tableMarkdown.trim().split('\n').filter(line => line.trim().startsWith('|'));
    if (lines.length < 2) return tableMarkdown;

    const parseRow = (line: string) =>
        line.split('|').slice(1, -1).map(c => c.trim().replace(/\*\*([^*]+)\*\*/g, '$1'));

    const headerCols = parseRow(lines[0]);
    const isSeparator = /^\|?(\s*:?-+:?\s*\|)+\s*$/.test(lines[1]);
    const bodyRows = (isSeparator ? lines.slice(2) : lines.slice(1)).map(parseRow);

    let html = '<div class="overflow-x-auto my-3"><table class="w-full text-xs text-left border-collapse border border-border rounded-lg">';
    if (headerCols.length > 0) {
        html += '<thead class="bg-muted text-muted-foreground font-semibold"><tr>';
        for (const col of headerCols) {
            html += `<th class="px-2.5 py-1.5 border-b border-border">${col}</th>`;
        }
        html += '</tr></thead>';
    }
    html += '<tbody>';
    for (const row of bodyRows) {
        html += '<tr class="border-b border-border/50 hover:bg-muted/40">';
        for (const cell of row) {
            html += `<td class="px-2.5 py-1.5">${cell}</td>`;
        }
        html += '</tr>';
    }
    html += '</tbody></table></div>';
    return html;
}

function formatBody(value: string): string {
    // Escape first, then add only the fixed table/list/strong wrappers below.
    // This keeps v-html safe while preserving the existing markdown layout.
    let safeValue = escapeHtml(value);

    // 1. Convert markdown tables into HTML tables
    let formatted = safeValue.replace(/(\|.+?\|\n\|[-:| ]+\|\n(?:\|.+?\|\n?)+)/g, (match) => {
        return '\n' + formatMarkdownTable(match) + '\n';
    });

    // 2. Bold text
    formatted = formatted.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');

    // 3. Remove citations [S1]
    formatted = formatted.replace(/\[S\d+\]/g, '');

    // 4. Line breaks for non-table lines
    const parts = formatted.split(/(<div class="overflow-x-auto[\s\S]*?<\/div>)/);
    formatted = parts.map(part => {
        if (part.startsWith('<div class="overflow-x-auto')) return part;
        return part
            .replace(/^[•*-]\s+(.+)$/gm, '<div class="flex items-start gap-1.5 my-0.5"><span class="text-primary">•</span><span>$1</span></div>')
            .replace(/\n\n+/g, '<br><br>')
            .replace(/\n/g, '<br>');
    }).join('');

    return formatted;
}
</script>
<template>
    <div class="space-y-3">
        <!-- Generated image if present -->
        <div v-if="image" class="overflow-hidden rounded-xl border border-border bg-card">
            <img :src="image.url" :alt="image.alt" class="max-h-96 w-full object-contain" />
        </div>

        <!-- 3 KPI Cards: Only show if metrics were successfully detected -->
        <div v-if="hasMetrics" class="grid grid-cols-1 gap-2 sm:grid-cols-3">
            <div class="rounded-xl border border-success/20 bg-success/10 p-3">
                <div class="flex items-center gap-1.5 text-xs font-medium text-success"><ArrowUp :size="14" /> Pemasukan</div>
                <div class="mt-1 min-w-0 break-words text-sm font-semibold leading-5 text-foreground sm:text-base">{{ income }}</div>
            </div>
            <div class="rounded-xl border border-error/20 bg-error/10 p-3">
                <div class="flex items-center gap-1.5 text-xs font-medium text-error"><ArrowDown :size="14" /> Pengeluaran</div>
                <div class="mt-1 min-w-0 break-words text-sm font-semibold leading-5 text-foreground sm:text-base">{{ expense }}</div>
            </div>
            <div class="rounded-xl border border-primary/20 bg-primary/10 p-3">
                <div class="flex items-center gap-1.5 text-xs font-medium text-secondary"><TrendingUp :size="14" /> Laba bersih</div>
                <div class="mt-1 min-w-0 break-words text-sm font-semibold leading-5 text-foreground sm:text-base">{{ net }}</div>
            </div>
        </div>

        <div v-if="body" class="text-sm leading-6 text-foreground" v-html="formatBody(body)" />
        <div class="rounded-lg bg-muted/60 px-3 py-2 text-xs leading-5 text-muted-foreground">
            <span class="font-medium text-foreground">Sumber:</span> {{ source }}
        </div>
    </div>
</template>
