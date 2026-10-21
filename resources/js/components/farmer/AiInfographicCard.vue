<script setup lang="ts">
import {
    Activity,
    Check,
    Clock,
    Copy,
    Droplets,
    FileText,
    Layers,
    Lightbulb,
    Maximize2,
    Sparkles,
    Tag,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { normalizeAiResponse } from '@/services/aiChatService';

const props = withDefaults(
    defineProps<{
        content: string;
        isStreaming?: boolean;
        forceVisual?: boolean;
        metadata?: Record<string, unknown>;
    }>(),
    {
        isStreaming: false,
        forceVisual: false,
        metadata: () => ({}),
    },
);

const emit = defineEmits<{
    (e: 'copied'): void;
}>();

const viewMode = ref<'infographic' | 'text'>('infographic');
const isLightboxOpen = ref(false);
const isCopied = ref(false);
const showSources = ref(false);
const imageGeneration = computed(() => {
    const value = props.metadata?.image_generation;
    return value && typeof value === 'object'
        ? (value as { status?: string; reason?: string | null })
        : null;
});
const sources = computed(() => {
    const retrieval = props.metadata?.retrieval;
    if (!retrieval || typeof retrieval !== 'object') return [] as string[];
    const results = (retrieval as { results?: Array<{ id?: string }> }).results ?? [];
    return results.map((item) => item.id).filter((id): id is string => Boolean(id));
});

interface MetricItem {
    icon?: string;
    label: string;
    value: string;
}

interface StepItem {
    step: number;
    title: string;
    description: string;
}

interface ParsedInfographic {
    image: { url: string; alt: string } | null;
    title: string | null;
    metrics: MetricItem[];
    steps: StepItem[];
    tip: string | null;
    intro: string | null;
    fallbackMarkdown: string;
    hasInfographicElements: boolean;
}

function escapeHtml(value: string): string {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

const imageMarkdownPattern =
    /!\[([^\]]*)\]\((https?:\/\/[^)\s]+|\/[^)\s]+|data:image\/[^)\s]+)\)/g;

function removeAllImageMarkdown(value: string): string {
    return value.replace(imageMarkdownPattern, '');
}

function cleanInlineLabel(value: string): string {
    return value
        .replace(/^#{1,6}\s+/, '')
        .replace(/\*\*/g, '')
        .replace(/__([^_]+)__/g, '$1')
        .trim();
}

function inlineMarkdown(value: string): string {
    return value
        .replace(
            /!\[([^\]]*)\]\((https?:\/\/[^)\s]+|\/[^)\s]+|data:image\/[^)\s]+)\)/g,
            '<figure class="my-2 overflow-hidden rounded-xl border border-border bg-muted"><img src="$2" alt="$1" loading="lazy" decoding="async" class="max-h-80 w-full object-contain" /><figcaption class="px-2.5 py-1.5 text-[11px] text-muted-foreground">$1</figcaption></figure>',
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

function renderFullMarkdown(value: string, stripImages = true): string {
    const normalized = normalizeAiResponse(value);
    const prepared = (
        stripImages ? removeAllImageMarkdown(normalized) : normalized
    ).replace(/^(#{1,6}\s+.+)$/gm, '\n$1\n');
    const safe = escapeHtml(prepared);
    if (!safe) return '';
    return safe
        .split(/\n{2,}/)
        .map((block) => {
            const lines = block.split('\n');
            const listLines = lines.filter((line) => /^[-*]\s/.test(line));
            if (listLines.length === lines.length && listLines.length > 0) {
                return `<ul class="my-1 list-disc space-y-1 pl-5">${listLines
                    .map((line) => `<li>${inlineMarkdown(line.slice(2))}</li>`)
                    .join('')}</ul>`;
            }
            const orderedLines = lines.filter((line) =>
                /^\d+[.)]\s/.test(line),
            );
            if (
                orderedLines.length === lines.length &&
                orderedLines.length > 0
            ) {
                return `<ol class="my-1 list-decimal space-y-1 pl-5">${orderedLines
                    .map(
                        (line) =>
                            `<li>${inlineMarkdown(line.replace(/^\d+[.)]\s/, ''))}</li>`,
                    )
                    .join('')}</ol>`;
            }
            if (lines.length === 1 && /^#{1,6}\s+/.test(lines[0])) {
                const heading = lines[0].replace(/^#{1,6}\s+/, '');
                return `<h3 class="mt-1 text-sm font-semibold text-foreground">${inlineMarkdown(heading)}</h3>`;
            }
            if (lines.every((line) => /^>\s?/.test(line))) {
                return `<blockquote class="border-l-2 border-primary/40 pl-3 text-muted-foreground">${lines
                    .map((line) => inlineMarkdown(line.replace(/^>\s?/, '')))
                    .join('<br>')}</blockquote>`;
            }
            return `<p>${lines
                .map((line) =>
                    inlineMarkdown(line.replace(/^\s*[*-]\s+/, '• ')),
                )
                .join('<br>')}</p>`;
        })
        .join('');
}

const parsed = computed<ParsedInfographic>(() => {
    const raw = normalizeAiResponse(props.content || '');
    let text = raw;

    // 1. Extract primary image
    const imageMatch = text.match(
        /!\[([^\]]*)\]\((https?:\/\/[^)\s]+|\/[^)\s]+|data:image\/[^)\s]+)\)/,
    );
    let image: { url: string; alt: string } | null = null;
    if (imageMatch) {
        const alt = imageMatch[1]?.trim() || 'Visual Diagram Infografis';
        const url = imageMatch[2];
        // Render the generated asset once in the Visual tab. Attachments are
        // filtered separately by AiMessageList, so the markdown image is the
        // single canonical visual and no duplicate thumbnail is shown.
        image = { alt, url };
        // Remove image markdown from flow so it doesn't duplicate
        text = text.replace(imageMatch[0], '').trim();
    }

    // 2. Extract Title (### or ## or #)
    let title: string | null = null;
    const titleMatch = text.match(/^#{1,3}\s+(.+)$/m);
    if (titleMatch) {
        title = cleanInlineLabel(titleMatch[1]);
        text = text.replace(titleMatch[0], '').trim();
    }

    if (!title && image) title = image.alt;

    // 3. Extract Tip / Callout (> 💡 or > **Tips or > ⚠️)
    let tip: string | null = null;
    const tipMatch = text.match(
        /^>\s*(💡|⚠️|📌)?\s*(?:\*\*(?:Tips Petani|Catatan|Peringatan|Rekomendasi):\*\*|\b(?:Tips Petani|Catatan|Peringatan|Rekomendasi):?)\s*(.+)$/im,
    );
    if (tipMatch) {
        tip = (tipMatch[2] || tipMatch[0].replace(/^>\s*/, ''))
            .replace(/\*\*/g, '')
            .replace(/^Tips Petani:\s*/i, '')
            .trim();
        text = text.replace(tipMatch[0], '').trim();
    } else {
        const blockquoteMatch = text.match(/^>\s*💡\s*(.+)$/m);
        if (blockquoteMatch) {
            tip = blockquoteMatch[1]
                .replace(/\*\*/g, '')
                .replace(/^Tips Petani:\s*/i, '')
                .trim();
            text = text.replace(blockquoteMatch[0], '').trim();
        }
    }

    // 4. Extract Metrics / Key-Value Parameters (> 💧 Air: ... | ⏳ Waktu: ...)
    const metrics: MetricItem[] = [];
    const metricsLineMatch = text.match(/^>\s*([^>\n]+\|[^>\n]+)$/m);
    if (metricsLineMatch) {
        const lineContent = metricsLineMatch[1];
        const segments = lineContent.split('|');
        for (const segment of segments) {
            const clean = segment.trim();
            if (!clean) continue;
            // Match pattern like: 💧 **Air:** Sedang or **Air:** Sedang or 💧 Air: Sedang
            const parts = clean.match(
                /^([^\w\s*]+)?\s*\*?\*?([^:*]+)\*?\*?:\s*(.+)$/,
            );
            if (parts) {
                metrics.push({
                    icon: parts[1]?.trim(),
                    label: parts[2].trim(),
                    value: parts[3].trim().replace(/\*\*/g, ''),
                });
            }
        }
        text = text.replace(metricsLineMatch[0], '').trim();
    }

    // 5. Extract Steps (1. **Title**: Description or 1. Step)
    const steps: StepItem[] = [];
    const stepRegex =
        /^(\d+)\.\s+(?:\*\*([^*]+)\*\*:\s*|\*([^*]+)\*:\s*|([^:\n]+):\s*)?(.+)$/gm;
    let stepMatch: RegExpExecArray | null;
    const stepLines: string[] = [];

    while ((stepMatch = stepRegex.exec(text)) !== null) {
        const stepNum = parseInt(stepMatch[1], 10);
        const stepTitle = cleanInlineLabel(
            stepMatch[2] ||
                stepMatch[3] ||
                stepMatch[4] ||
                `Langkah ${stepNum}`,
        );
        const stepDesc = stepMatch[5]
            .replace(/^\s*[*-]\s+/, '')
            .replace(/^\*\*Keterangan\*\*:\s*/i, '')
            .replace(/\*\*/g, '')
            .replace(/__([^_]+)__/g, '$1')
            .trim();
        steps.push({
            step: stepNum,
            title: stepTitle,
            description: stepDesc,
        });
        stepLines.push(stepMatch[0]);
    }

    // Remove parsed step lines from remaining intro text
    for (const stepLine of stepLines) {
        text = text.replace(stepLine, '').trim();
    }

    // Intro / description remaining
    const intro = text.trim() ? text.trim() : null;

    const hasInfographicElements =
        props.forceVisual &&
        !props.isStreaming &&
        Boolean(
            image ||
            metrics.length > 0 ||
            (steps.length > 0 && (title || metrics.length > 0)) ||
            (title && steps.length > 1),
        );

    return {
        image,
        title,
        metrics,
        steps,
        tip,
        intro,
        fallbackMarkdown: raw,
        hasInfographicElements,
    };
});

function getMetricIcon(item: MetricItem) {
    if (item.icon?.includes('💧')) return Droplets;
    if (item.icon?.includes('⏳') || item.icon?.includes('⏰')) return Clock;
    if (item.icon?.includes('🏷️') || item.icon?.includes('🌱')) return Tag;
    return Activity;
}

async function copyContent(): Promise<void> {
    try {
        await navigator.clipboard.writeText(props.content);
        isCopied.value = true;
        emit('copied');
        window.setTimeout(() => {
            isCopied.value = false;
        }, 1600);
    } catch {
        // silent fallback
    }
}
</script>

<template>
    <div class="w-full">
        <!-- MODE TOGGLE HEADER (When response has infographic structure) -->
        <div
            v-if="parsed.hasInfographicElements"
            class="mb-2.5 flex items-center justify-between border-b border-border/40 pb-2 text-xs"
        >
            <div class="flex items-center gap-1.5 font-medium text-secondary">
                <Sparkles :size="13" class="text-primary" />
                <span>{{
                    parsed.image ? 'Infografis AI' : 'Ringkasan visual'
                }}</span>
                <span
                    v-if="imageGeneration"
                    class="rounded-full px-2 py-0.5 text-[10px] font-semibold"
                    :class="imageGeneration.status === 'generated' ? 'bg-success/10 text-success' : imageGeneration.status === 'fallback' ? 'bg-warning/15 text-warning' : 'bg-error/10 text-error'"
                    :title="imageGeneration.reason || undefined"
                >
                    {{ imageGeneration.status === 'generated' ? 'Generated' : imageGeneration.status === 'fallback' ? 'Fallback' : 'Failed' }}
                </span>
            </div>
            <span
                v-if="imageGeneration?.status === 'fallback' && imageGeneration.reason"
                class="ml-2 text-[10px] text-warning"
            >Alasan: {{ imageGeneration.reason }}</span>
            <div
                class="flex items-center gap-1 rounded-lg bg-muted/80 p-0.5 text-[11px]"
            >
                <button
                    type="button"
                    class="flex items-center gap-1 rounded-md px-2 py-0.5 font-medium transition"
                    :class="
                        viewMode === 'infographic'
                            ? 'bg-card text-foreground shadow-xs'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="viewMode = 'infographic'"
                >
                    <Layers :size="11" />
                    <span>Visual</span>
                </button>
                <button
                    type="button"
                    class="flex items-center gap-1 rounded-md px-2 py-0.5 font-medium transition"
                    :class="
                        viewMode === 'text'
                            ? 'bg-card text-foreground shadow-xs'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="viewMode = 'text'"
                >
                    <FileText :size="11" />
                    <span>Teks</span>
                </button>
            </div>
        </div>

        <!-- 1. INFOGRAPHIC MODE VIEW -->
        <section
            v-if="parsed.hasInfographicElements && viewMode === 'infographic'"
            class="space-y-3.5"
        >
            <!-- HERO IMAGE BANNER -->
            <div
                v-if="parsed.image"
                class="group relative overflow-hidden rounded-xl border border-border/70 bg-surface/40 shadow-xs transition hover:border-primary/50"
            >
                <img
                    :src="parsed.image.url"
                    :alt="parsed.image.alt"
                    loading="lazy"
                    decoding="async"
                    class="max-h-80 w-full cursor-zoom-in object-contain transition duration-300 group-hover:scale-[1.01] sm:max-h-96"
                    @click="isLightboxOpen = true"
                />
                <!-- Gradient overlay on bottom of image for contrast -->
                <div
                    class="absolute inset-x-0 bottom-0 flex items-center justify-between bg-gradient-to-t from-black/85 via-black/40 to-transparent px-3 py-2 text-white"
                >
                    <p class="truncate text-xs font-medium text-white/95">
                        {{ parsed.image.alt }}
                    </p>
                    <button
                        type="button"
                        aria-label="Perbesar infografis"
                        class="flex size-6 shrink-0 items-center justify-center rounded-md bg-white/20 text-white backdrop-blur transition hover:bg-white/30"
                        @click="isLightboxOpen = true"
                    >
                        <Maximize2 :size="12" />
                    </button>
                </div>
            </div>

            <!-- TITLE & INTRO -->
            <div v-if="parsed.title || parsed.intro" class="space-y-1">
                <h3
                    v-if="parsed.title"
                    class="text-base font-bold tracking-tight text-foreground"
                >
                    {{ parsed.title }}
                </h3>
                <div
                    v-if="parsed.intro"
                    class="text-xs leading-relaxed text-muted-foreground"
                    v-html="renderFullMarkdown(parsed.intro)"
                />
            </div>

            <!-- KEY METRICS GRID (Micro-stat widgets) -->
            <div
                v-if="parsed.metrics.length"
                class="grid grid-cols-2 gap-2"
                :class="
                    parsed.metrics.length > 2 ? 'sm:grid-cols-4' : 'grid-cols-2'
                "
            >
                <div
                    v-for="(metric, idx) in parsed.metrics"
                    :key="idx"
                    class="flex flex-col rounded-xl border border-border/60 bg-surface/30 p-2.5 transition hover:border-primary/40 hover:bg-surface/50"
                >
                    <div
                        class="flex items-center gap-1.5 text-[10px] text-muted-foreground"
                    >
                        <component
                            :is="getMetricIcon(metric)"
                            :size="12"
                            class="shrink-0 text-secondary"
                        />
                        <span class="truncate font-medium">{{
                            metric.label
                        }}</span>
                    </div>
                    <p class="mt-1 text-xs font-bold text-foreground">
                        {{ metric.value }}
                    </p>
                </div>
            </div>

            <!-- ACTION STEPS / TIMELINE -->
            <div v-if="parsed.steps.length" class="space-y-2">
                <p
                    class="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                >
                    Langkah Pelaksanaan
                </p>
                <div class="space-y-2">
                    <div
                        v-for="step in parsed.steps"
                        :key="step.step"
                        class="flex items-start gap-2.5 rounded-xl border border-border/50 bg-card p-3 shadow-xs transition hover:border-primary/40 hover:bg-muted/30"
                    >
                        <div
                            class="flex size-6 shrink-0 items-center justify-center rounded-full bg-secondary text-[11px] font-bold text-primary-foreground shadow-xs"
                        >
                            {{ step.step }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <h4 class="text-xs font-semibold text-foreground">
                                {{ step.title }}
                            </h4>
                            <p
                                class="mt-0.5 text-xs leading-relaxed text-muted-foreground"
                            >
                                {{ step.description }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CALLOUT / TIP BOX -->
            <div
                v-if="parsed.tip"
                class="flex items-start gap-2.5 rounded-xl border border-primary/25 bg-primary/5 p-3 text-xs leading-relaxed text-foreground"
            >
                <Lightbulb :size="16" class="mt-0.5 shrink-0 text-primary" />
                <div>
                    <span class="font-bold text-secondary">Tips Petani:</span>
                    <span class="ml-1 text-muted-foreground">{{
                        parsed.tip
                    }}</span>
                </div>
            </div>

            <div v-if="sources.length" class="rounded-xl border border-border/70 bg-muted/40 p-3">
                <button type="button" class="flex w-full items-center justify-between text-left text-xs font-semibold text-foreground" @click="showSources = !showSources">
                    <span>Sumber dan konteks</span>
                    <span class="text-muted-foreground">{{ showSources ? 'Sembunyikan' : `${sources.length} sumber` }}</span>
                </button>
                <ul v-if="showSources" class="mt-2 space-y-1 text-[11px] leading-4 text-muted-foreground">
                    <li v-for="source in sources" :key="source">{{ source }}</li>
                </ul>
            </div>

            <!-- ACTION FOOTER: QUICK COPY SUMMARY -->
            <div class="flex items-center justify-end pt-1">
                <button
                    type="button"
                    class="flex items-center gap-1 rounded-md px-2 py-1 text-[11px] font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                    @click="copyContent"
                >
                    <Check v-if="isCopied" :size="12" class="text-success" />
                    <Copy v-else :size="12" />
                    <span>{{
                        isCopied
                            ? 'Tersalin'
                            : parsed.image
                              ? 'Salin Infografis'
                              : 'Salin Ringkasan'
                    }}</span>
                </button>
            </div>
        </section>

        <!-- 2. TEXT / FALLBACK STANDARD MARKDOWN VIEW -->
        <div
            v-else
            class="ai-message-content text-sm leading-relaxed"
            v-html="renderFullMarkdown(content, false)"
        />

        <!-- LIGHTBOX MODAL FOR HERO IMAGE -->
        <Teleport to="body">
            <div
                v-if="isLightboxOpen && parsed.image"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4 backdrop-blur-sm"
                @click="isLightboxOpen = false"
            >
                <div
                    class="relative max-h-[90vh] max-w-3xl overflow-hidden rounded-2xl bg-card"
                    @click.stop
                >
                    <button
                        type="button"
                        aria-label="Tutup"
                        class="absolute top-3 right-3 z-10 flex size-8 items-center justify-center rounded-full bg-black/60 text-white transition hover:bg-black/80"
                        @click="isLightboxOpen = false"
                    >
                        <X :size="18" />
                    </button>
                    <img
                        :src="parsed.image.url"
                        :alt="parsed.image.alt"
                        class="max-h-[80vh] w-full object-contain"
                    />
                    <div
                        class="bg-card px-4 py-3 text-center text-xs font-medium text-foreground"
                    >
                        {{ parsed.image.alt }}
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
