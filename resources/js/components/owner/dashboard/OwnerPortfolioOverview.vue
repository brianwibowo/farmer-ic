<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, Check, Clock3, Sprout, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import type {
    OwnerDashboardData,
    OwnerRiskOverview,
} from '@/types/owner-dashboard';
import OwnerChartCard from './OwnerChartCard.vue';
import { chartNumber } from './chartFormat';

const props = defineProps<{
    statuses: OwnerDashboardData['insights']['batchStatus'];
    risks: OwnerRiskOverview;
}>();
const risksExpanded = ref(false);
const visibleRisks = computed(() =>
    risksExpanded.value ? props.risks.items : props.risks.items.slice(0, 1),
);
watch(
    () => props.risks,
    () => (risksExpanded.value = false),
);

const circumference = 2 * Math.PI * 44;
const total = computed(() =>
    props.statuses.reduce((sum, status) => sum + status.count, 0),
);
const statusStyles: Record<
    string,
    { className: string; markerClass: string; icon: typeof Sprout }
> = {
    active: {
        className: 'text-secondary dark:text-primary',
        markerClass: 'rounded-full border border-current',
        icon: Sprout,
    },
    planned: {
        className: 'text-muted-foreground',
        markerClass: 'rounded-sm border border-dashed border-current',
        icon: Clock3,
    },
    harvested: {
        className: 'text-success',
        markerClass: 'rounded-sm border border-current',
        icon: Check,
    },
    cancelled: {
        className: 'text-error',
        markerClass: 'rounded-full border border-current',
        icon: X,
    },
};
const fallbackStyle = {
    className: 'text-muted-foreground',
    markerClass: 'rounded-sm border border-current',
    icon: Clock3,
};
const segments = computed(() => {
    let offset = 0;
    return props.statuses.map((status) => {
        const length = total.value
            ? (status.count / total.value) * circumference
            : 0;
        const segment = {
            ...status,
            ...(statusStyles[status.key] ?? fallbackStyle),
            length,
            offset,
        };
        offset += length;
        return segment;
    });
});
const severityLabels: Record<string, string> = {
    critical: 'Kritis',
    high: 'Tinggi',
    medium: 'Sedang',
    low: 'Rendah',
    unknown: 'Belum dinilai',
};

function severityClass(severity: string): string {
    if (severity === 'critical' || severity === 'high') {
        return 'border-error/30 bg-error/10 text-error';
    }
    return 'border-border bg-muted text-foreground';
}
</script>

<template>
    <OwnerChartCard
        id="owner-portfolio"
        eyebrow="Kondisi saat ini"
        title="Gambaran tanam"
        description="Lihat komposisi status batch dan catatan risiko yang masih terbuka."
    >
        <div
            v-if="total"
            class="grid grid-cols-[112px_minmax(0,1fr)] items-center gap-4 min-[390px]:grid-cols-[128px_minmax(0,1fr)]"
        >
            <div class="relative aspect-square">
                <svg
                    viewBox="0 0 120 120"
                    class="size-full -rotate-90"
                    aria-hidden="true"
                >
                    <circle
                        cx="60"
                        cy="60"
                        r="44"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="13"
                        class="text-muted"
                    />
                    <circle
                        v-for="segment in segments.filter((item) => item.count)"
                        :key="segment.key"
                        cx="60"
                        cy="60"
                        r="44"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="13"
                        :stroke-dasharray="`${segment.length} ${circumference}`"
                        :stroke-dashoffset="-segment.offset"
                        :class="segment.className"
                    />
                </svg>
                <p
                    class="absolute inset-0 flex flex-col items-center justify-center gap-0.5 text-center"
                >
                    <strong
                        class="text-2xl leading-8 font-semibold tabular-nums"
                    >
                        {{ chartNumber(total, 0) }}
                    </strong>
                    <span
                        class="max-w-16 text-[10px] leading-4 text-muted-foreground"
                    >
                        batch tercatat
                    </span>
                </p>
            </div>
            <dl
                class="min-w-0 space-y-3"
                aria-label="Jumlah batch menurut status"
            >
                <div
                    v-for="status in statuses"
                    :key="status.key"
                    class="flex items-center justify-between gap-2 text-xs leading-5"
                >
                    <dt class="flex min-w-0 items-center gap-2">
                        <span
                            class="flex size-5 shrink-0 items-center justify-center"
                            :class="[
                                (statusStyles[status.key] ?? fallbackStyle)
                                    .className,
                                (statusStyles[status.key] ?? fallbackStyle)
                                    .markerClass,
                            ]"
                            aria-hidden="true"
                        >
                            <component
                                :is="
                                    (statusStyles[status.key] ?? fallbackStyle)
                                        .icon
                                "
                                class="size-3"
                            />
                        </span>
                        <span class="break-words">{{ status.label }}</span>
                    </dt>
                    <dd class="shrink-0 font-medium tabular-nums">
                        {{ chartNumber(status.count, 0) }}
                    </dd>
                </div>
            </dl>
        </div>
        <p
            v-else
            class="rounded-xl border border-dashed border-border bg-muted/40 px-4 py-8 text-center text-sm leading-6 text-muted-foreground"
            role="status"
        >
            Belum ada batch tanam tercatat pada kebun yang dipilih.
        </p>

        <section
            class="mt-5 border-t border-border pt-4"
            aria-labelledby="owner-risk-title"
        >
            <div class="flex items-center justify-between gap-3">
                <h3
                    id="owner-risk-title"
                    class="text-sm leading-5 font-semibold"
                >
                    Perlu perhatian
                </h3>
                <span
                    v-if="risks.total"
                    class="shrink-0 rounded-full border border-error/30 bg-error/10 px-2.5 py-1 text-[10px] leading-4 font-medium text-error"
                >
                    {{ chartNumber(risks.total, 0) }} risiko terbuka
                </span>
            </div>
            <div v-if="risks.total" class="mt-3 space-y-4">
                <article
                    v-for="risk in visibleRisks"
                    :key="risk.id"
                    class="min-w-0 rounded-xl border border-border bg-muted/30 p-3"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="rounded-full border px-2 py-0.5 text-[10px] leading-4 font-medium"
                            :class="severityClass(risk.severity)"
                        >
                            {{
                                severityLabels[risk.severity] ?? 'Belum dinilai'
                            }}
                        </span>
                        <p
                            class="min-w-0 text-xs leading-5 break-words text-muted-foreground"
                        >
                            {{ risk.farm }}
                        </p>
                    </div>
                    <p class="mt-2 text-sm leading-6 break-words">
                        {{ risk.description }}
                    </p>
                    <p
                        class="mt-1.5 text-xs leading-5 break-words text-muted-foreground"
                    >
                        {{
                            risk.recommendation ||
                            'Rekomendasi penanganan belum dicatat.'
                        }}
                    </p>
                    <Link
                        :href="risk.href"
                        class="mt-1 inline-flex min-h-11 items-center gap-1.5 rounded-md text-xs font-medium text-secondary hover:underline focus-visible:outline-2 focus-visible:outline-ring dark:text-primary"
                    >
                        Buka daftar kebun
                        <ArrowUpRight class="size-3.5" aria-hidden="true" />
                    </Link>
                </article>
                <Button
                    v-if="risks.items.length > 1"
                    type="button"
                    variant="outline"
                    class="min-h-11 w-full text-xs"
                    :aria-expanded="risksExpanded"
                    @click="risksExpanded = !risksExpanded"
                >
                    <template v-if="risksExpanded">Ringkas catatan</template>
                    <template v-else>
                        Lihat {{ chartNumber(risks.items.length - 1, 0) }}
                        catatan lainnya
                    </template>
                </Button>
                <p
                    v-if="risks.total > visibleRisks.length"
                    class="text-xs leading-5 text-muted-foreground"
                >
                    Menampilkan {{ chartNumber(visibleRisks.length, 0) }} dari
                    {{ chartNumber(risks.total, 0) }} risiko terbuka.
                </p>
            </div>
            <p
                v-else
                class="mt-3 text-xs leading-5 text-muted-foreground"
                role="status"
            >
                Belum ada risiko terbuka yang tercatat. Kondisi tanaman tetap
                perlu ditinjau melalui pengamatan lapangan.
            </p>
        </section>
    </OwnerChartCard>
</template>
