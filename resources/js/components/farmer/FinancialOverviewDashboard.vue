<script setup lang="ts">
import { ref, computed } from 'vue';
import StatCard from '@/components/StatCardDashboard.vue';

// [LOGIKA DIISOLASI DI SINI]
const activeTab = ref<'pendapatan' | 'pengeluaran'>('pendapatan');

const financialData = {
    pendapatan: [
        { title: 'Pendapatan Kebun', value: 'Rp 25.000.000', type: 'income' },
        { title: 'Proyeksi Pendapatan', value: 'Rp 15.000.000', type: 'projection' },
    ],
    pengeluaran: [
        { title: 'Pengeluaran Kebun', value: 'Rp 8.300.000', type: 'expense' },
        { title: 'Proyeksi Pengeluaran', value: 'Rp 5.000.000', type: 'expense' },
    ],
};

const currentCards = computed(() => financialData[activeTab.value]);
</script>

<template>
    <section class="space-y-2">
        <!-- Filter Toggle Button Kanan Atas -->
        <div class="flex justify-end gap-1.5 text-xs font-bold">
            <button
                type="button"
                @click="activeTab = 'pendapatan'"
                :class="[
                    'rounded-full px-3 py-1.5 transition-all duration-200 active:scale-95',
                    activeTab === 'pendapatan'
                        ? 'bg-[#1E5E27] text-white shadow-sm'
                        : 'bg-gray-200 text-gray-600 hover:bg-gray-300 dark:bg-gray-800 dark:text-gray-400'
                ]"
            >
                Pendapatan
            </button>

            <button
                type="button"
                @click="activeTab = 'pengeluaran'"
                :class="[
                    'rounded-full px-3 py-1.5 transition-all duration-200 active:scale-95',
                    activeTab === 'pengeluaran'
                        ? 'bg-red-600 text-white shadow-sm'
                        : 'bg-gray-200 text-gray-600 hover:bg-gray-300 dark:bg-gray-800 dark:text-gray-400'
                ]"
            >
                Pengeluaran
            </button>
        </div>

        <!-- Grid 2 Stat Card -->
        <div class="grid grid-cols-2 gap-3 bg">
            <StatCard
                v-for="(card, index) in currentCards"
                :key="index"
                :title="card.title"
                :value="card.value"
                :type="card.type as any"
            />
        </div>
    </section>
</template>