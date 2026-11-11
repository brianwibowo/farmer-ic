<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import FarmerMobileShell from '@/components/farmer/FarmerMobileShell.vue';
import DailyReportContent from '@/components/petugas/DailyReportContent.vue';
import FarmManagementLayout from '@/layouts/FarmManagementLayout.vue';
import type { DailyReport } from '@/types/daily-activity';

const page = usePage();
defineProps<{
    farm: { id: string; name: string };
    log: DailyReport;
    canUpdate: boolean;
    canRecord: boolean;
}>();
</script>

<template>
    <Head :title="`Detail Logbook - ${farm.name}`" />
    <component
        :is="
            page.props.navigation?.owner
                ? FarmManagementLayout
                : FarmerMobileShell
        "
        v-bind="page.props.navigation?.owner ? {} : { activeKey: 'garden' }"
    >
        <div class="space-y-5 px-4 pt-4 pb-6 text-foreground">
            <header class="flex items-start gap-3">
                <Link
                    :href="`/kebun/${farm.id}/daily-logs`"
                    class="inline-flex size-11 shrink-0 items-center justify-center rounded-lg border border-border bg-card text-primary transition-colors hover:border-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    aria-label="Kembali ke logbook"
                    ><ArrowLeft :size="18"
                /></Link>
                <div class="min-w-0">
                    <h1 class="text-xl leading-7 font-semibold text-foreground">
                        Detail Logbook
                    </h1>
                    <p class="mt-1 text-sm font-medium text-primary">
                        {{ farm.name }} · {{ log.work_date }}
                    </p>
                </div>
            </header>
            <DailyReportContent
                :farm="farm"
                :date="log.work_date"
                :log="log"
                :can-update="canUpdate"
                :can-record="canRecord"
            />
        </div>
    </component>
</template>
