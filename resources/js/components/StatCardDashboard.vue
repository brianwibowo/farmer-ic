<script setup lang="ts">
import { computed } from 'vue';

// Definition Props agar dinamis saat dipanggil
interface Props {
    title: string;
    value: string;
    type?: 'income' | 'projection' | 'expense' | 'default';
}

const props = withDefaults(defineProps<Props>(), {
    type: 'default',
});

// Penyesuaian warna teks nilai sesuai jenis data (Opsional)
const valueColorClass = computed(() => {
    switch (props.type) {
        case 'income':
            return 'text-[#1E5E27]'; // Hijau tua tegas dari palette (Secondary)
        case 'projection':
            return 'text-emerald-600'; // Hijau segar (Primary)
        case 'expense':
            return 'text-red-600'; // Merah tegas (Error)
        case 'default':
            return 'text-[#0A0A0A]'; // Hitam pekat (Foreground)
    }
});
</script>

<template>
    <!-- 
      - [PERBAIKAN 1]: Hapus class 'justify-center' dari container utama agar title mentok ke atas padding (p-3)
    -->
    <div
        class="flex aspect-square w-full flex-col items-center rounded-[20px] border border-gray-200 bg-white p-3 text-center shadow-sm transition-all active:scale-[0.98] dark:border-gray-800 dark:bg-sidebar"
    >
        <!-- Title Top: Mentok di paling atas (mengikuti padding container p-3) -->
        <span class="text-xs font-bold leading-tight text-gray-600 sm:text-sm dark:text-gray-300">
            {{ title }}
        </span>

        <!-- 
          - [PERBAIKAN 2]: Ganti 'mt-2' menjadi 'my-auto' agar angka nilai otomatis berada di tengah sisa ruang bawah
        -->
        <span
            class="my-auto text-lg font-black tracking-tight xs:text-xl sm:text-2xl"
            :class="valueColorClass"
        >
            {{ value }}
        </span>
    </div>
</template>