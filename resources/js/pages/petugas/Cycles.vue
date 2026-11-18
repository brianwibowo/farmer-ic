<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import FarmCyclesList from '@/components/blocks/FarmCyclesList.vue';
import type { FarmCyclesPageProps } from '@/types/farm-cycles';
defineOptions({ inheritAttrs: false });
defineProps<FarmCyclesPageProps>();
</script>

<template>
    <Head title="Detail cycle" />
    <FarmCyclesList :farm="farm" :cycles="cycles" :back-url="backUrl" />
</template>
