<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PendingInvitationsModal from '@/components/PendingInvitationsModal.vue';
import DashboardHeader from '@/components/farmer/DashboardHeader.vue';
import KebunMap from '@/components/farmer/KebunMap.vue';
import { dashboard } from '@/routes';
import type { DashboardInvitation, Team } from '@/types';

import FarmerMobileShell from '@/components/farmer/FarmerMobileShell.vue';
import FinancialOverviewDashboard from '@/components/farmer/FinancialOverviewDashboard.vue';
import CommodityIncome from '@/components/farmer/CommodityIncome.vue';

const page = usePage();
const userName = computed(
    () => page.props.auth?.user?.name?.trim() || 'Petani IC',
);

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
            <DashboardHeader />

            <div class="min-w-0">
                <p class="text-xs leading-5 text-muted-foreground">
                    Good morning,
                </p>
                <h1 class="text-base leading-6 font-semibold text-foreground">
                    {{ userName }}
                </h1>
            </div>

            <FinancialOverviewDashboard />

            <KebunMap />

            <CommodityIncome />
        </div>
    </FarmerMobileShell>
</template>
