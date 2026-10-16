<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PendingInvitationsModal from '@/components/PendingInvitationsModal.vue';
import KebunMap from '@/components/farmer/KebunMap.vue';
import { dashboard } from '@/routes';
import type { DashboardInvitation, Team } from '@/types';

import FarmerMobileShell from '@/components/farmer/FarmerMobileShell.vue';
import FinancialOverviewDashboard from '@/components/farmer/FinancialOverviewDashboard.vue';
import CommodityIncome from '@/components/farmer/CommodityIncome.vue';

defineProps<{
    pendingInvitations?: DashboardInvitation[];
}>();

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: props.currentTeam
                    ? dashboard(props.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});
</script>

<template>
    <Head title="Dashboard" />

    <FarmerMobileShell active-key="home">
        <PendingInvitationsModal
            v-if="pendingInvitations && pendingInvitations.length > 0"
            :invitations="pendingInvitations"
        />

        <div
            class="mx-auto flex min-h-full w-full max-w-md flex-col gap-4 p-4 *:shrink-0"
        >
            <FinancialOverviewDashboard />

            <KebunMap />

            <CommodityIncome />
        </div>
    </FarmerMobileShell>
</template>
