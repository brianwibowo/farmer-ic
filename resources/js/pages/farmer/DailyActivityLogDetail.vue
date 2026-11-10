<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import FarmerMobileShell from '@/components/farmer/FarmerMobileShell.vue';
import DailyReportContent from '@/components/petugas/DailyReportContent.vue';
import type { DailyReport } from '@/types/daily-activity';

defineProps<{
    farm: { id: string; name: string };
    log: DailyReport;
    canUpdate: boolean;
    canRecord: boolean;
}>();
</script>

<template>
    <Head :title="`Laporan harian - ${farm.name}`" />
    <FarmerMobileShell active-key="garden">
        <div class="space-y-5 px-4 pt-4 pb-6 text-foreground">
            <Link
                :href="`/kebun/${farm.id}/daily-logs`"
                class="inline-flex min-h-11 items-center text-sm text-primary underline focus-visible:outline-2 focus-visible:outline-ring"
                >Kembali ke logbook</Link
            >
            <header>
                <h1 class="text-2xl leading-8 font-semibold">
                    Laporan harian · {{ log.work_date }}
                </h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{ farm.name }}
                </p>
            </header>
            <DailyReportContent
                :farm="farm"
                :date="log.work_date"
                :log="log"
                :can-update="canUpdate"
                :can-record="canRecord"
            />
        </div>
    </FarmerMobileShell>
</template>
