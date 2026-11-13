<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight } from '@lucide/vue';
import type { HarvestTransactionRecord } from '@/types/harvest-transactions';

const props = defineProps<{
    reports: HarvestTransactionRecord[];
}>();

const monthLabels = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Agu',
    'Sep',
    'Okt',
    'Nov',
    'Des',
];

function formatDecimal(value: string | null, digits = 2): string {
    if (value === null || value === '') {
        return '0';
    }
    const [whole, fraction] = Number(value).toFixed(digits).split('.');
    const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return fraction ? `${grouped},${fraction}` : grouped;
}

function formatDate(value: string): string {
    const [year, month, day] = value.split('-').map(Number);
    return `${String(day).padStart(2, '0')} ${monthLabels[month - 1] ?? ''} ${year}`;
}
</script>

<template>
    <div class="divide-y divide-border rounded-xl border border-border bg-card">
        <article
            v-for="record in reports"
            :key="record.id"
            class="grid grid-cols-2 gap-3 p-3 text-sm sm:grid-cols-[minmax(0,1fr)_90px_100px_minmax(0,1.2fr)_auto] sm:items-start"
        >
            <div class="col-span-2 sm:col-span-1">
                <p class="font-medium">
                    {{ formatDate(record.activity_date) }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ record.performer_name ?? 'Petugas tidak tercatat' }}
                </p>
            </div>
            <div>
                <p class="text-xs text-muted-foreground">Luas</p>
                <p class="mt-1 font-medium tabular-nums">
                    {{ formatDecimal(record.harvested_area_ha) }} ha
                </p>
            </div>
            <div>
                <p class="text-xs text-muted-foreground">Berat</p>
                <p class="mt-1 font-medium tabular-nums">
                    {{ formatDecimal(record.gross_weight_ton) }} ton
                </p>
            </div>
            <div class="col-span-2 sm:col-span-1">
                <p
                    v-if="record.notes"
                    class="text-xs leading-5 text-muted-foreground"
                >
                    {{ record.notes }}
                </p>
                <p v-else class="text-xs text-muted-foreground">
                    Tanpa catatan
                </p>
            </div>
            <div
                class="col-span-2 flex flex-wrap items-center gap-2 sm:col-span-1 sm:justify-end"
            >
                <a
                    v-for="photo in record.photos"
                    :key="photo.id"
                    :href="photo.url"
                    target="_blank"
                    rel="noopener"
                    class="overflow-hidden rounded-md border border-border"
                    :aria-label="photo.caption ?? photo.label ?? 'Foto panen'"
                >
                    <img
                        :src="photo.url"
                        :alt="photo.caption ?? photo.label ?? 'Foto panen'"
                        class="size-10 object-cover"
                        loading="lazy"
                    />
                </a>
                <span
                    v-if="!record.photos.length"
                    class="text-xs text-muted-foreground"
                >
                    Tanpa foto
                </span>
                <Link
                    v-if="record.log_url"
                    :href="record.log_url"
                    class="inline-flex min-h-9 items-center gap-1 text-xs font-medium text-primary"
                >
                    Logbook <ArrowUpRight class="size-3" />
                </Link>
            </div>
        </article>
    </div>
</template>
