<script setup lang="ts">
import FarmerBottomNav, {
    type FarmerNavKey,
} from '@/components/farmer/FarmerBottomNav.vue';
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

type Props = {
    activeKey?: FarmerNavKey;
};

const props = withDefaults(defineProps<Props>(), {
    activeKey: 'home',
});

const emit = defineEmits<{
    select: [key: FarmerNavKey];
}>();

const selectedKey = ref<FarmerNavKey>(props.activeKey);

const routeMap: Record<FarmerNavKey, string> = {
    home: '/', // Beranda / Dashboard utama
    garden: '/kebun', // Halaman Kebun
    ai: '/ai', // Halaman AI
    finance: '/keuangan', // Halaman Keuangan
    activity: '/aktivitas', // Halaman Aktivitas
};
watch(
    () => props.activeKey,
    (key) => {
        selectedKey.value = key;
    },
);

function select(key: FarmerNavKey): void {
    selectedKey.value = key;

    // [PERBAIKAN 3] Jalankan router.visit untuk semua tombol secara dinamis
    const targetUrl = routeMap[key];
    if (targetUrl && window.location.pathname !== targetUrl) {
        void router.visit(targetUrl);
    }

    emit('select', key);
}
</script>

<template>
    <div
        class="flex min-h-dvh w-full justify-center bg-card px-6 pt-6 text-foreground max-[480px]:px-0 max-[480px]:pt-0"
    >
        <section
            class="relative flex h-[min(874px,calc(100dvh-24px))] w-full max-w-[440px] flex-col overflow-hidden rounded-t-[24px] rounded-b-[20px] bg-card max-[480px]:h-dvh max-[480px]:max-w-none max-[480px]:rounded-none"
        >
            <main class="min-h-0 flex-1 overflow-y-auto pb-20">
                <slot />
            </main>

            <FarmerBottomNav :active-key="selectedKey" @select="select" />
        </section>
    </div>
</template>
