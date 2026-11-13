<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight, MapPin } from '@lucide/vue';
import type { WeeklyAdminContent } from '@/types/notifications';
defineProps<{ content: WeeklyAdminContent }>();
</script>
<template>
    <div class="space-y-2" data-notification-content="weekly_admin">
        <Link
            v-for="farm in content.farms"
            :key="farm.id"
            :href="'/admin/logbook/' + farm.id"
            class="flex min-h-11 items-start gap-3 rounded-xl border border-border p-3 transition-colors hover:bg-muted/50 focus-visible:outline-2 focus-visible:outline-ring"
        >
            <MapPin
                class="mt-1 size-4 shrink-0 text-primary"
                aria-hidden="true"
            />
            <div class="min-w-0 flex-1 space-y-1">
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
                <p class="pt-1 text-xs font-medium text-primary">
                    Lihat logbook
                </p>
            </div>
            <ChevronRight
                class="mt-1 size-4 shrink-0 text-muted-foreground"
                aria-hidden="true"
            />
        </Link>
    </div>
</template>
