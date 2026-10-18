import { computed, ref } from 'vue';
import {
    readDemoStorage,
    writeDemoStorage,
} from '@/composables/useDemoStorage';

export type AiMetricMode = 'text' | 'data' | 'infographic' | 'voice';
export type AiMetricStatus = 'positive' | 'negative' | 'error' | 'aborted';

export type AiVoiceMetric = {
    mode: 'server' | 'browser';
    status: 'complete' | 'error';
    recordingMs: number;
    processingMs: number;
    totalMs: number;
};

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
    voice?: AiVoiceMetric;
};

const STORAGE_KEY = 'ai_metrics';
const MAX_EVENTS = 100;

function createId(): string {
    return `metric-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
}

function percentile(values: number[], ratio: number): number {
    if (!values.length) return 0;
    const sorted = [...values].sort((a, b) => a - b);
    const position = (sorted.length - 1) * Math.min(1, Math.max(0, ratio));
    const lower = Math.floor(position);
    const upper = Math.ceil(position);
    if (lower === upper) return sorted[lower] ?? 0;
    const weight = position - lower;
    return (sorted[lower] ?? 0) * (1 - weight) + (sorted[upper] ?? 0) * weight;
}

function finiteNumber(value: unknown, fallback = 0): number {
    const number = typeof value === 'number' ? value : Number(value);
    return Number.isFinite(number) ? Math.max(0, number) : fallback;
}

function normalizeEvents(value: unknown): AiMetricEvent[] {
    if (!Array.isArray(value)) return [];
    return value
        .filter((item): item is Record<string, unknown> =>
            Boolean(item && typeof item === 'object'),
        )
        .map((item) => ({
            id: typeof item.id === 'string' ? item.id : createId(),
            createdAt:
                typeof item.createdAt === 'string'
                    ? item.createdAt
                    : new Date().toISOString(),
            mode:
                item.mode === 'data' ||
                item.mode === 'infographic' ||
                item.mode === 'voice'
                    ? item.mode
                    : 'text',
            status:
                item.status === 'negative' ||
                item.status === 'error' ||
                item.status === 'aborted'
                    ? item.status
                    : 'positive',
            latencyMs: finiteNumber(item.latencyMs),
            ...(typeof item.firstTokenMs === 'number'
                ? { firstTokenMs: finiteNumber(item.firstTokenMs) }
                : {}),
            inputTokens: finiteNumber(item.inputTokens),
            outputTokens: finiteNumber(item.outputTokens),
            ...(item.feedback === 'up' || item.feedback === 'down'
                ? { feedback: item.feedback }
                : {}),
            ...(item.voice && typeof item.voice === 'object'
                ? {
                      voice: {
                          mode:
                              (item.voice as Record<string, unknown>).mode ===
                              'browser'
                                  ? 'browser'
                                  : 'server',
                          status:
                              (item.voice as Record<string, unknown>).status ===
                              'error'
                                  ? 'error'
                                  : 'complete',
                          recordingMs: finiteNumber(
                              (item.voice as Record<string, unknown>)
                                  .recordingMs,
                          ),
                          processingMs: finiteNumber(
                              (item.voice as Record<string, unknown>)
                                  .processingMs,
                          ),
                          totalMs: finiteNumber(
                              (item.voice as Record<string, unknown>).totalMs,
                          ),
                      },
                  }
                : {}),
        })) as AiMetricEvent[];
}

export function useAiTelemetry() {
    const stored = readDemoStorage<unknown>(STORAGE_KEY, []);
    const events = ref<AiMetricEvent[]>(
        normalizeEvents(stored).slice(-MAX_EVENTS),
    );

    function persist(): void {
        writeDemoStorage(STORAGE_KEY, events.value.slice(-MAX_EVENTS));
    }

    function record(event: Omit<AiMetricEvent, 'id' | 'createdAt'>): string {
        const id = createId();
        const normalizedEvent: AiMetricEvent = {
            ...event,
            latencyMs: finiteNumber(event.latencyMs),
            inputTokens: finiteNumber(event.inputTokens),
            outputTokens: finiteNumber(event.outputTokens),
            ...(event.firstTokenMs === undefined
                ? {}
                : { firstTokenMs: finiteNumber(event.firstTokenMs) }),
            id,
            createdAt: new Date().toISOString(),
        };
        events.value = [...events.value, normalizedEvent].slice(-MAX_EVENTS);
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
        const completed = events.value.filter(
            (event) =>
                event.status === 'positive' || event.status === 'negative',
        );
        const latencies = completed.map((event) => event.latencyMs);
        const firstTokenLatencies = completed
            .map((event) => event.firstTokenMs)
            .filter((value): value is number => typeof value === 'number');
        const positive = events.value.filter(
            (event) => event.status === 'positive',
        ).length;
        const negative = events.value.filter(
            (event) => event.status === 'negative',
        ).length;
        const infographic = events.value.filter(
            (event) => event.mode === 'infographic',
        ).length;
        const errors = events.value.filter(
            (event) => event.status === 'error',
        ).length;
        const aborted = events.value.filter(
            (event) => event.status === 'aborted',
        ).length;
        const voiceEvents = events.value.filter(
            (event) => event.mode === 'voice',
        );
        const voice = voiceEvents.length;
        const voiceMetrics = voiceEvents
            .map((event) => event.voice)
            .filter((value): value is AiVoiceMetric => Boolean(value));
        const completedVoiceMetrics = voiceMetrics.filter(
            (metric) => metric.status === 'complete',
        );
        const voiceProcessing = completedVoiceMetrics.map(
            (metric) => metric.processingMs,
        );
        const voiceTotal = completedVoiceMetrics.map(
            (metric) => metric.totalMs,
        );
        const voiceBrowser = voiceMetrics.filter(
            (metric) => metric.mode === 'browser',
        ).length;
        const voiceServer = voiceMetrics.filter(
            (metric) => metric.mode === 'server',
        ).length;
        const voiceErrors = voiceMetrics.filter(
            (metric) => metric.status === 'error',
        ).length;
        const feedbackUp = events.value.filter(
            (event) => event.feedback === 'up',
        ).length;
        const feedbackDown = events.value.filter(
            (event) => event.feedback === 'down',
        ).length;
        const total = events.value.length;

        return {
            total,
            completed: completed.length,
            positive,
            negative,
            errors,
            aborted,
            infographic,
            voice,
            voiceSamples: voiceMetrics.length,
            voiceBrowser,
            voiceServer,
            voiceErrors,
            feedbackUp,
            feedbackDown,
            completionRate: total
                ? Math.round((completed.length / total) * 100)
                : 0,
            positiveRate: completed.length
                ? Math.round((positive / completed.length) * 100)
                : 0,
            averageLatencyMs: latencies.length
                ? Math.round(
                      latencies.reduce((sum, value) => sum + value, 0) /
                          latencies.length,
                  )
                : 0,
            p50LatencyMs: Math.round(percentile(latencies, 0.5)),
            p95LatencyMs: Math.round(percentile(latencies, 0.95)),
            latencySamples: latencies.length,
            averageFirstTokenMs: firstTokenLatencies.length
                ? Math.round(
                      firstTokenLatencies.reduce(
                          (sum, value) => sum + value,
                          0,
                      ) / firstTokenLatencies.length,
                  )
                : 0,
            firstTokenSamples: firstTokenLatencies.length,
            averageVoiceProcessingMs: voiceProcessing.length
                ? Math.round(
                      voiceProcessing.reduce((sum, value) => sum + value, 0) /
                          voiceProcessing.length,
                  )
                : 0,
            p50VoiceProcessingMs: Math.round(percentile(voiceProcessing, 0.5)),
            p95VoiceProcessingMs: Math.round(percentile(voiceProcessing, 0.95)),
            averageVoiceTotalMs: voiceTotal.length
                ? Math.round(
                      voiceTotal.reduce((sum, value) => sum + value, 0) /
                          voiceTotal.length,
                  )
                : 0,
            voiceMetricSamples: voiceProcessing.length,
            estimatedTokens: events.value.reduce(
                (sum, event) => sum + event.inputTokens + event.outputTokens,
                0,
            ),
        };
    });

    return { events, report, record, updateFeedback, reset };
}
