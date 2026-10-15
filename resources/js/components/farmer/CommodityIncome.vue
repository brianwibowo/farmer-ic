<script setup lang="ts">
import { ref } from 'vue';
import CommodityCard from '@/components/CommodityCardDashboard.vue';

export type CommodityItem = {
    name: string;
    value: string;
    image?: string;
};

type Props = {
    items?: CommodityItem[];
};

const props = withDefaults(defineProps<Props>(), {
    items: () => [
        { name: 'Jagung', value: 'Rp 8.300.000' },
        { name: 'Kedelai', value: 'Rp 8.300.000' },
        { name: 'Padi', value: 'Rp 12.000.000' },
        { name: 'Jagung', value: 'Rp 8.300.000' },
        { name: 'Kedelai', value: 'Rp 8.300.000' },
        { name: 'Padi', value: 'Rp 12.000.000' },
    ],
});

// =========================================================================
// [PERBAIKAN 1] Peta Gambar Vektor / SVG berdasarkan Nama Komoditas
// Simpan file SVG/PNG kamu di dalam folder `public/images/commodities/`
// =========================================================================
const commodityImageMap: Record<string, string> = {
    jagung: '/images/commodities/jagung.svg',
    kedelai: '/images/commodities/kedelai.svg',
    padi: '/images/commodities/padi.svg',
};

// =========================================================================
// [PERBAIKAN 2] Fungsi Helper Resolver Gambar (Conditional Logic)
// =========================================================================
function resolveCommodityImage(item: CommodityItem): string {
    // 1. Jika item sudah punya properti image manual, pakai itu
    if (item.image) return item.image;

    // 2. Cocokkan nama komoditas (case-insensitive) dengan peta gambar
    const normalizedName = item.name.toLowerCase().trim();
    return commodityImageMap[normalizedName] || '/images/commodities/default.svg';
}
</script>

<template>
    <section class="space-y-2">
        <h2 class="text-base font-black text-gray-900 dark:text-white">
            Pendapatan Komoditas
        </h2>

        <div class="flex gap-3 overflow-x-auto pb-2">
            <CommodityCard
                v-for="(item, idx) in props.items"
                :key="idx"
                :name="item.name"
                :value="item.value"
                :image="resolveCommodityImage(item)"
            />
        </div>
    </section>
</template>