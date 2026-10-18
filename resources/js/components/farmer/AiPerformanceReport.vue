<script setup lang="ts">
import { BarChart3, CheckCircle2, Clock3, Gauge, RotateCcw, X } from '@lucide/vue';
import type { AiMetricEvent } from '@/composables/useAiTelemetry';

type Report = {
    total: number;
    completed: number;
    positive: number;
    negative: number;
    errors: number;
    infographic: number;
    voice: number;
    feedbackUp: number;
    feedbackDown: number;
    completionRate: number;
    positiveRate: number;
    averageLatencyMs: number;
    p50LatencyMs: number;
    p95LatencyMs: number;
    averageFirstTokenMs: number;
    estimatedTokens: number;
};

const props = defineProps<{
    report: Report;
    events: AiMetricEvent[];
}>();

const emit = defineEmits<{
    close: [];
    reset: [];
}>();

function duration(value: number): string {
    if (!value) return '—';
    return value >= 1000 ? `${(value / 1000).toFixed(1)} dtk` : `${value} ms`;
}

function modeLabel(mode: AiMetricEvent['mode']): string {
    return { text: 'Teks', data: 'Data', infographic: 'Infografis', voice: 'Voice' }[mode];
}
</script>

<template>
    <aside class="absolute inset-0 z-50 flex flex-col bg-card" aria-label="Laporan performa AI">
        <header class="flex h-[72px] shrink-0 items-center gap-3 border-b border-border/70 px-4">
            <button
                type="button"
                aria-label="Tutup laporan performa"
                class="flex size-9 items-center justify-center rounded-full transition hover:bg-muted"
                @click="emit('close')"
            >
                <X :size="19" />
            </button>
            <div>
                <h2 class="text-base font-semibold">Performa chatbot</h2>
                <p class="text-[11px] text-muted-foreground">Telemetry lokal untuk demo ini</p>
            </div>
            <button
                type="button"
                class="ml-auto flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-[11px] font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                @click="emit('reset')"
            >
                <RotateCcw :size="13" /> Reset demo data
            </button>
        </header>

        <div class="flex-1 overflow-y-auto p-4">
            <div class="grid grid-cols-2 gap-2">
                <div class="rounded-xl border border-border/70 bg-muted/40 p-3">
                    <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground"><BarChart3 :size="14" /> Selesai</div>
                    <p class="mt-1 text-xl font-semibold">{{ props.report.completionRate }}%</p>
                    <p class="text-[10px] text-muted-foreground">{{ props.report.completed }} dari {{ props.report.total }} permintaan</p>
                </div>
                <div class="rounded-xl border border-border/70 bg-muted/40 p-3">
                    <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground"><Gauge :size="14" /> Sesuai konteks</div>
                    <p class="mt-1 text-xl font-semibold text-success">{{ props.report.positiveRate }}%</p>
                    <p class="text-[10px] text-muted-foreground">{{ props.report.positive }} sesuai · {{ props.report.negative }} di luar konteks</p>
                </div>
                <div class="rounded-xl border border-border/70 bg-muted/40 p-3">
                    <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground"><Clock3 :size="14" /> Respons median</div>
                    <p class="mt-1 text-xl font-semibold">{{ duration(props.report.p50LatencyMs) }}</p>
                    <p class="text-[10px] text-muted-foreground">P95 {{ duration(props.report.p95LatencyMs) }}</p>
                </div>
                <div class="rounded-xl border border-border/70 bg-muted/40 p-3">
                    <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground"><CheckCircle2 :size="14" /> Infografis</div>
                    <p class="mt-1 text-xl font-semibold">{{ props.report.infographic }}</p>
                    <p class="text-[10px] text-muted-foreground">{{ props.report.voice }} permintaan voice</p>
                </div>
            </div>

            <div class="mt-4 rounded-xl border border-border/70 p-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-semibold">Kualitas respons</h3>
                    <span class="text-[10px] text-muted-foreground">{{ props.report.errors }} error</span>
                </div>
                <div class="mt-3 space-y-2 text-xs">
                    <div class="flex items-center justify-between"><span class="text-muted-foreground">Waktu mulai jawaban</span><strong>{{ duration(props.report.averageFirstTokenMs) }}</strong></div>
                    <div class="flex items-center justify-between"><span class="text-muted-foreground">Estimasi token</span><strong>{{ props.report.estimatedTokens.toLocaleString('id-ID') }}</strong></div>
                    <div class="flex items-center justify-between"><span class="text-muted-foreground">Feedback pengguna</span><strong>{{ props.report.feedbackUp }} suka · {{ props.report.feedbackDown }} kurang</strong></div>
                </div>
                <p class="mt-3 text-[10px] leading-relaxed text-muted-foreground">Token dihitung dari panjang teks di browser sebagai estimasi, bukan angka billing provider.</p>
            </div>

            <p class="mt-3 rounded-xl border border-primary/20 bg-primary/5 px-3 py-2 text-[10px] leading-relaxed text-muted-foreground">
                Mode Data pada prototype memakai seed data lokal di browser. Saat konektor database siap, resolver ini bisa diganti tanpa mengubah tampilan chat.
            </p>

            <div v-if="props.events.length" class="mt-4">
                <h3 class="mb-2 text-xs font-semibold">Aktivitas terbaru</h3>
                <div class="space-y-1.5">
                    <div v-for="event in props.events.slice(-6).reverse()" :key="event.id" class="flex items-center gap-2 rounded-lg bg-muted/40 px-2.5 py-2 text-[11px]">
                        <span class="size-1.5 shrink-0 rounded-full" :class="event.status === 'error' ? 'bg-error' : event.status === 'negative' ? 'bg-warning' : 'bg-success'" />
                        <span class="flex-1 text-foreground">{{ modeLabel(event.mode) }}</span>
                        <span class="text-muted-foreground">{{ duration(event.latencyMs) }}</span>
                    </div>
                </div>
            </div>
            <p v-else class="py-10 text-center text-xs leading-relaxed text-muted-foreground">Belum ada data performa. Kirim satu pertanyaan untuk mulai merekam metrik.</p>
        </div>
    </aside>
</template>
