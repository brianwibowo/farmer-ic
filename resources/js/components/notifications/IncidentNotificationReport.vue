<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import type { IncidentContent } from '@/types/notifications';
import { date } from './format';
defineProps<{ content: IncidentContent }>();
</script>
<template>
    <div class="space-y-2" data-notification-content="incident_report">
        <Link
            v-for="incident in content.incidents"
            :key="incident.id"
            :href="'/admin/logbook/' + incident.farm_id"
            class="flex flex-wrap items-start gap-3 rounded-xl border border-border p-3 hover:bg-muted/50 focus-visible:outline-2 focus-visible:outline-ring"
        >
            <TriangleAlert
                class="mt-1 size-4 shrink-0 text-warning"
                aria-hidden="true"
            />
            <div class="min-w-40 flex-1 space-y-1">
                <h3 class="text-sm font-semibold">{{ incident.farm_name }}</h3>
                <p class="text-sm">
                    {{ incident.category || 'Insiden' }} ·
                    {{ incident.severity || 'Tingkat belum tersedia' }}
                </p>
                <p class="text-xs text-muted-foreground">
                    {{ date(incident.date) }}
                </p>
                <p
                    v-if="incident.description"
                    class="text-sm leading-6 break-words"
                >
                    {{ incident.description }}
                </p>
            </div>
            <span
                class="ml-auto inline-flex min-h-11 shrink-0 items-center rounded-lg bg-primary px-4 text-sm font-medium text-primary-foreground"
                >Lihat logbook</span
            >
        </Link>
    </div>
</template>
