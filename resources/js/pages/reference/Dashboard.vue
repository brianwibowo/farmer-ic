<!-- Historical source: 73f2555d91d0fb2d6acb7bc70813921edd479ea4 / resources/js/pages/farmer/Dashboard.vue -->
<script setup lang="ts">
import { Head, usePage } from '@/reference/inertia';
import { computed } from 'vue';
import KebunMap from '@/reference/components/farmer/KebunMap.vue';
type DashboardInvitation = never;

import FarmerMobileShell from '@/reference/components/farmer/FarmerMobileShell.vue';
import FinancialOverviewDashboard from '@/reference/components/farmer/FinancialOverviewDashboard.vue';
import CommodityIncome from '@/reference/components/farmer/CommodityIncome.vue';
import RecapFarmCondition from '@/reference/components/farmer/RecapFarmCondition.vue';

const page = usePage();
const userName = computed(
    () => page.props.auth?.user?.name?.trim() || 'Petani IC',
);

defineProps<{
    pendingInvitations?: DashboardInvitation[];
}>();

defineOptions({ layout: [] });
</script>

<template>
    <Head title="Dashboard" />

    <FarmerMobileShell active-key="home">
        <div
            class="mx-auto flex min-h-full w-full max-w-md flex-col gap-4 p-4 *:shrink-0"
        >
            <div class="min-w-0">
                <p class="text-xs leading-5 text-muted-foreground">
                    Good morning,
                </p>
                <h1 class="text-base leading-6 font-semibold text-foreground">
                    {{ userName }}
                </h1>
            </div>

            <RecapFarmCondition />

            <KebunMap />

            <FinancialOverviewDashboard />

            <CommodityIncome />
        </div>
    </FarmerMobileShell>
</template>
