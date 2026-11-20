<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CircleAlert } from '@lucide/vue';
import { Button } from '@/components/ui/button';

defineProps<{
    title: string;
    showIncidents: boolean;
    summaryLabel: string;
    metrics: { label: string; value: number | string }[];
}>();
</script>

<template>
    <header class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h1>{{ title }}</h1>
            <Button
                v-if="showIncidents"
                as-child
                variant="outline"
                class="min-h-11 rounded-xl bg-card px-3 text-xs"
            >
                <Link href="/petugas/incidents">
                    <CircleAlert
                        class="size-4 text-warning"
                        aria-hidden="true"
                    />
                    Tiket Insiden
                </Link>
            </Button>
        </div>
        <dl
            v-if="metrics.length"
            class="petugas-card grid grid-cols-3 divide-x divide-border py-3 text-center"
            :aria-label="summaryLabel"
        >
            <div
                v-for="metric in metrics"
                :key="metric.label"
                class="flex min-w-0 flex-col gap-1 px-1"
            >
                <dt class="order-2 text-xs leading-4 text-muted-foreground">
                    {{ metric.label }}
                </dt>
                <dd
                    class="text-xl leading-7 font-bold break-words tabular-nums"
                >
                    {{ metric.value }}
                </dd>
            </div>
        </dl>
    </header>
</template>
