<script setup lang="ts">
import type { WeeklyOwnerContent } from '@/types/notifications';
import HarvestSummaryTable from './HarvestSummaryTable.vue';
defineProps<{ content: WeeklyOwnerContent }>();
</script>
<template>
    <section
        data-notification-content="weekly_owner"
        class="rounded-xl border border-border bg-muted/20 p-3 @md:p-4"
        aria-label="Total seluruh lahan"
    >
        <h3 class="text-sm font-semibold">Total seluruh lahan</h3>
        <p class="mt-1 mb-3 text-xs text-muted-foreground">
            Target per siklus dan hasil panen pada periode laporan.
        </p>
        <HarvestSummaryTable
            :summary="content.totals"
            label="Total target dan panen seluruh lahan"
        />
    </section>
</template>
