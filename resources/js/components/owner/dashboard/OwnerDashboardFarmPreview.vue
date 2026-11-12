<script setup lang="ts">
import DashboardFarmPreview from '@/components/dashboard/DashboardFarmPreview.vue';
import type { OwnerDashboardMapFarm } from '@/types/owner-dashboard';

defineProps<{ farm: OwnerDashboardMapFarm }>();
const open = defineModel<boolean>('open', { default: false });
const emit = defineEmits<{ closed: [] }>();
</script>

<template>
    <DashboardFarmPreview
        v-model:open="open"
        :farm="farm"
        admin-theme
        @closed="emit('closed')"
    >
        <template v-if="$slots.summary" #summary>
            <slot name="summary" />
        </template>
        <template v-if="$slots.footer" #footer>
            <slot name="footer" />
        </template>
    </DashboardFarmPreview>
</template>
