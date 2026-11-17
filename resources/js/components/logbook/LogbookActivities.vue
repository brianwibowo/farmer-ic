<script setup lang="ts">
import LogbookActivityCard from '@/components/logbook/LogbookActivityCard.vue';
import type { LogbookReportActivity } from '@/types/logbook';

defineProps<{ activities: LogbookReportActivity[] }>();
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
        <LogbookActivityCard
            v-for="activity in activities"
            :key="activity.id"
            :activity="activity"
            show-detail-link
        />
    </section>
</template>
