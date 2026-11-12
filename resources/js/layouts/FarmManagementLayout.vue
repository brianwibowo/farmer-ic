<script setup lang="ts">
import { LandPlot } from '@lucide/vue';
import AdminWorkspaceLayout from '@/layouts/AdminWorkspaceLayout.vue';
import { useFarmManagementNavigation } from '@/composables/useFarmManagementNavigation';

const { title, indexUrl } = useFarmManagementNavigation();
</script>

<template>
    <AdminWorkspaceLayout :title="title" :href="indexUrl" :icon="LandPlot">
        <slot />
    </AdminWorkspaceLayout>
</template>
