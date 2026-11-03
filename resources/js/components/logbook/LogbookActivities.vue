<script setup lang="ts">
import { Ruler, TriangleAlert } from '@lucide/vue';
import type { LogbookActivity } from '@/types/logbook';
defineProps<{ activities: LogbookActivity[] }>();
const number = (value: string | number | null, unit = '') =>
    value === null
        ? '—'
        : new Intl.NumberFormat('id-ID', { maximumFractionDigits: 3 }).format(
              Number(value),
          ) + unit;
</script>
<template>
    <section class="space-y-[18px]" aria-labelledby="logbook-activities-title">
        <h2 id="logbook-activities-title" class="admin-section-title">
            Rincian kegiatan
        </h2>
        <p
            v-if="!activities.length"
            class="admin-panel text-sm text-muted-foreground"
        >
            Tidak ada rincian kegiatan yang dapat ditampilkan dalam cakupan
            akses Anda.
        </p>
        <article
            v-for="activity in activities"
            :key="activity.id"
            class="admin-panel space-y-5"
        >
            <header class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs text-muted-foreground">
                        Kegiatan {{ activity.position }} ·
                        {{
                            activity.type === 'block_mapping'
                                ? 'Pemetaan blok'
                                : 'Aktivitas kebun'
                        }}
                    </p>
                    <h3
                        class="mt-1 flex items-center gap-2 text-base font-semibold"
                    >
                        <Ruler class="size-4 text-primary" />{{
                            activity.block_mapping?.block_name ??
                            'Rincian kegiatan'
                        }}
                    </h3>
                </div>
                <span
                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold"
                    :class="
                        activity.obstacle_status === 'reported'
                            ? 'bg-warning/10 text-warning'
                            : 'bg-muted text-muted-foreground'
                    "
                    ><TriangleAlert
                        v-if="activity.obstacle_status === 'reported'"
                        class="size-3.5"
                    />{{
                        activity.obstacle_status === 'reported'
                            ? 'Ada kendala'
                            : activity.obstacle_status === 'none'
                              ? 'Tidak ada kendala'
                              : 'Kendala belum dicatat'
                    }}</span
                >
            </header>
            <dl
                v-if="activity.block_mapping"
                class="grid grid-cols-2 gap-3 lg:grid-cols-3"
            >
                <div
                    v-for="metric in [
                        {
                            label: 'Panjang blok',
                            value: number(
                                activity.block_mapping.length_m,
                                ' m',
                            ),
                        },
                        {
                            label: 'Lebar blok',
                            value: number(activity.block_mapping.width_m, ' m'),
                        },
                        {
                            label: 'Baris per bedeng',
                            value: number(activity.block_mapping.rows_per_bed),
                        },
                        {
                            label: 'Jarak antarbaris',
                            value: number(
                                activity.block_mapping.row_spacing_cm,
                                ' cm',
                            ),
                        },
                        {
                            label: 'Jarak tanam',
                            value: number(
                                activity.block_mapping.plant_spacing_cm,
                                ' cm',
                            ),
                        },
                    ]"
                    :key="metric.label"
                    class="rounded-xl bg-background p-4"
                >
                    <dt class="text-xs text-muted-foreground">
                        {{ metric.label }}
                    </dt>
                    <dd class="mt-2 text-base font-bold tabular-nums">
                        {{ metric.value }}
                    </dd>
                </div>
            </dl>
            <div class="grid gap-4 md:grid-cols-2">
                <div
                    v-for="note in [
                        { label: 'Catatan kegiatan', value: activity.notes },
                        { label: 'Kendala', value: activity.obstacles },
                        {
                            label: 'Usulan solusi',
                            value: activity.proposed_solution,
                        },
                        {
                            label: 'Tindakan yang dilakukan',
                            value: activity.action_taken,
                        },
                    ].filter((note) => note.value)"
                    :key="note.label"
                    class="rounded-xl border border-border p-4"
                >
                    <h4 class="text-sm font-semibold">{{ note.label }}</h4>
                    <p
                        class="mt-2 text-sm leading-6 whitespace-pre-wrap text-muted-foreground"
                    >
                        {{ note.value }}
                    </p>
                </div>
            </div>
        </article>
    </section>
</template>
