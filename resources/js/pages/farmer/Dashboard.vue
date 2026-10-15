<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PendingInvitationsModal from '@/components/PendingInvitationsModal.vue';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
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

        <!-- 
          [PERBAIKAN 1]: 
          - Mengubah 'pb-24' menjadi 'pb-4' untuk menghapus ruang kosong bawah yang berlebihan.
          - Menambahkan 'h-full' dan 'justify-between' agar 3 komponen terbagi rata memenuhi tinggi layar HP.
        -->
        <div class="mx-auto flex h-full w-full max-w-md flex-1 flex-col justify-between gap-4 p-4 pb-4">
            
            <!-- [PERBAIKAN 2]: Row 1 Peta Kebun diberi 'flex-1' & 'min-h-[180px]' agar area peta membesar mengisi ruang sisa -->
            <section class="flex flex-1 flex-col space-y-2">
                <h2 class="text-base font-black text-gray-900 dark:text-white">Peta Kebun</h2>
                <div class="relative flex min-h-[180px] flex-1 w-full items-center justify-center overflow-hidden rounded-[20px] border border-sidebar-border/70 bg-gray-200 dark:border-sidebar-border">
                    <PlaceholderPattern />
                    <span class="absolute text-sm font-bold text-gray-500">[ Area Peta Kebun ]</span>
                </div>
            </section>

            <!-- Row 2: Financial Overview -->
            <FinancialOverviewDashboard />

            <!-- Row 3: Pendapatan Komoditas -->
            <CommodityIncome />

        </div>
    </FarmerMobileShell>
</template>