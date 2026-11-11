<script setup lang="ts">
import DashboardFarmPreview from "@/components/dashboard/DashboardFarmPreview.vue";
import { withOwnerFarmOrigin } from "@/lib/ownerFarmNavigation";
import type { OwnerDashboardMapFarm } from "@/types/owner-dashboard";

defineProps<{ farm: OwnerDashboardMapFarm }>();
const open = defineModel<boolean>("open", { default: false });
const emit = defineEmits<{ closed: [] }>();
</script>

<template>
    <DashboardFarmPreview
        v-model:open="open"
        :farm="farm"
        :detail-url="withOwnerFarmOrigin(farm.detail_url, 'home')"
        @closed="emit('closed')"
    />
</template>
