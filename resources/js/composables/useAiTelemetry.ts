import { computed, ref } from 'vue';
import { readDemoStorage, writeDemoStorage } from '@/composables/useDemoStorage';

export type AiMetricMode = 'text' | 'data' | 'infographic' | 'voice';
export type AiMetricStatus = 'positive' | 'negative' | 'error';

export type AiMetricEvent = {
    id: string;
    createdAt: string;
    mode: AiMetricMode;
    status: AiMetricStatus;
    latencyMs: number;
    firstTokenMs?: number;
    inputTokens: number;
    outputTokens: number;
    feedback?: 'up' | 'down';
};

const STORAGE_KEY = 'ai_metrics';
const MAX_EVENTS = 100;

function createId(): string {
    return `metric-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
}

function percentile(values: number[], ratio: number): number {
    if (!values.length) return 0;
    const sorted = [...values].sort((a, b) => a - b);
    return sorted[Math.min(sorted.length - 1, Math.ceil(sorted.length * ratio) - 1)] ?? 0;
}

export function useAiTelemetry() {
    const stored = readDemoStorage<unknown>(STORAGE_KEY, []);
    const events = ref<AiMetricEvent[]>(Array.isArray(stored) ? stored : []);

    function persist(): void {
        writeDemoStorage(STORAGE_KEY, events.value.slice(-MAX_EVENTS));
    }

    function record(event: Omit<AiMetricEvent, 'id' | 'createdAt'>): string {
        const id = createId();
        events.value = [
            ...events.value,
            { ...event, id, createdAt: new Date().toISOString() },
        ].slice(-MAX_EVENTS);
        persist();
        return id;
    }

    function updateFeedback(id: string, feedback: 'up' | 'down'): void {
        const event = events.value.find((item) => item.id === id);
        if (!event) return;
        event.feedback = feedback;
        persist();
    }

    function reset(): void {
        events.value = [];
        persist();
    }

    const report = computed(() => {
        const completed = events.value.filter((event) => event.status !== 'error');
        const latencies = completed.map((event) => event.latencyMs);
        const firstTokenLatencies = completed
            .map((event) => event.firstTokenMs)
            .filter((value): value is number => typeof value === 'number');
        const positive = events.value.filter((event) => event.status === 'positive').length;
        const negative = events.value.filter((event) => event.status === 'negative').length;
        const infographic = events.value.filter((event) => event.mode === 'infographic').length;
        const errors = events.value.filter((event) => event.status === 'error').length;
        const voice = events.value.filter((event) => event.mode === 'voice').length;
        const feedbackUp = events.value.filter((event) => event.feedback === 'up').length;
        const feedbackDown = events.value.filter((event) => event.feedback === 'down').length;
        const total = events.value.length;

        return {
            total,
            completed: completed.length,
            positive,
            negative,
            errors,
            infographic,
            voice,
            feedbackUp,
            feedbackDown,
            completionRate: total ? Math.round((completed.length / total) * 100) : 0,
            positiveRate: completed.length ? Math.round((positive / completed.length) * 100) : 0,
            averageLatencyMs: latencies.length
                ? Math.round(latencies.reduce((sum, value) => sum + value, 0) / latencies.length)
                : 0,
            p50LatencyMs: Math.round(percentile(latencies, 0.5)),
            p95LatencyMs: Math.round(percentile(latencies, 0.95)),
            averageFirstTokenMs: firstTokenLatencies.length
                ? Math.round(firstTokenLatencies.reduce((sum, value) => sum + value, 0) / firstTokenLatencies.length)
                : 0,
            estimatedTokens: events.value.reduce(
                (sum, event) => sum + event.inputTokens + event.outputTokens,
                0,
            ),
        };
    });

    return { events, report, record, updateFeedback, reset };
}
