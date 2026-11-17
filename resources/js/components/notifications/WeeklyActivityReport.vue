<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { MapPin } from '@lucide/vue';
import { number } from './format';
import type { WeeklyAdminContent } from '@/types/notifications';
defineProps<{ content: WeeklyAdminContent }>();
</script>
<template>
    <div class="space-y-2" data-notification-content="weekly_admin">
        <Link
            v-for="farm in content.farms"
            :key="farm.id"
            :href="'/admin/logbook/' + farm.id"
            class="flex min-h-11 flex-wrap items-start gap-3 rounded-xl border border-border p-3 transition-colors hover:bg-muted/50 focus-visible:outline-2 focus-visible:outline-ring"
        >
            <MapPin
                class="mt-1 size-4 shrink-0 text-primary"
                aria-hidden="true"
            />
            <div class="min-w-40 flex-1 space-y-1">
                <h3 class="text-sm font-semibold">{{ farm.name }}</h3>
                <p class="text-xs text-muted-foreground">
                    {{ farm.activity_count }} aktivitas dilaporkan
                </p>
                <p
                    v-if="farm.activity_count === 0"
                    class="text-xs text-muted-foreground"
                >
                    Belum ada aktivitas pada periode ini.
                </p>
                <div
                    v-for="(block, index) in farm.blocks.filter(
                        (b) =>
                            b.activities.length ||
                            b.incidents.length ||
                            b.obstacle_count,
                    )"
                    :key="block.id ?? index"
                    class="text-xs leading-5"
                >
                    <span class="font-medium">{{ block.name }}:</span>
                    {{
                        block.activities
                            .map((a) => a.label + ' ' + a.count + ' kali')
                            .join(', ')
                    }}.
                    <span v-if="block.harvest_count" class="block font-medium"
                        >Panen: {{ number(block.harvest_ton ?? 0) }} ton dari
                        {{ block.harvest_count }} laporan<span
                            v-if="block.harvest_missing"
                        >
                            ({{ block.harvest_missing }} berat belum
                            tercatat)</span
                        >.</span
                    >
                    <span
                        v-if="block.incidents.length"
                        class="block text-warning"
                        >Insiden:
                        {{
                            block.incidents
                                .map(
                                    (i) =>
                                        i.label + ' ' + i.count + ' kejadian',
                                )
                                .join(', ')
                        }}.</span
                    >
                    <span
                        v-if="block.obstacle_count"
                        class="block text-muted-foreground"
                        >Hambatan: {{ block.obstacle_count }} laporan.
                        {{ block.obstacle_summary }}</span
                    >
                </div>
            </div>
            <span
                class="ml-auto inline-flex min-h-11 shrink-0 items-center rounded-lg bg-primary px-4 text-sm font-medium text-primary-foreground"
                >Lihat logbook</span
            >
        </Link>
    </div>
</template>
