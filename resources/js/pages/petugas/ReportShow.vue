<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import DailyReportContent from '@/components/petugas/DailyReportContent.vue';
import type { DailyReport, DailyReportActivity } from '@/types/daily-activity';
defineProps<{
    farm: { id: string; name: string };
    date: string;
    log: DailyReport | null;
    legacyActivity?: DailyReportActivity | null;
}>();
</script>
<template>
    <Head title="Detail laporan" />
    <div class="space-y-4">
        <Link
            href="/petugas/laporan"
            class="inline-flex min-h-11 items-center text-sm text-secondary"
            >← Kembali ke laporan</Link
        >
        <header>
            <h1 class="text-xl font-semibold">
                {{
                    log?.activities[0]?.name ??
                    legacyActivity?.name ??
                    'Detail laporan'
                }}
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ farm.name }} · {{ date }}
            </p>
        </header>
        <DailyReportContent
            :farm="farm"
            :date="date"
            :log="log"
            :legacy-activity="legacyActivity"
            :can-record="false"
        />
    </div>
</template>
