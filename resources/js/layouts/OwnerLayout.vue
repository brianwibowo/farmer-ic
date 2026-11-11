<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import {
    ChartNoAxesCombined,
    LandPlot,
    LayoutDashboard,
    LayoutGrid,
} from '@lucide/vue';
import AdminWorkspaceLayout from '@/layouts/AdminWorkspaceLayout.vue';

const page = usePage();
const section = computed(() => {
    const path = page.url.split('?')[0];
    if (path === '/ringkasan-eksekutif')
        return {
            title: 'Ringkasan Eksekutif',
            href: '/ringkasan-eksekutif',
            icon: LayoutDashboard,
        };
    if (path === '/proyeksi')
        return {
            title: 'Proyeksi',
            href: '/proyeksi',
            icon: ChartNoAxesCombined,
        };
    if (path === '/kebun' || path.startsWith('/kebun/'))
        return { title: 'Lahan', href: '/kebun', icon: LandPlot };
    return { title: 'Dashboard', href: '/', icon: LayoutGrid };
});
const loading = ref(false);
const feedback = ref('');
const failed = ref(false);
const stop: Array<() => void> = [];
onMounted(() => {
    stop.push(
        router.on('start', () => {
            loading.value = true;
            feedback.value = '';
            failed.value = false;
        }),
        router.on('error', () => {
            failed.value = true;
            feedback.value = 'Periksa filter lalu coba lagi.';
        }),
        router.on('networkError', () => {
            failed.value = true;
            feedback.value = 'Koneksi terputus. Silakan coba lagi.';
            return false;
        }),
        router.on('httpException', () => {
            failed.value = true;
            feedback.value =
                'Halaman belum berhasil dimuat. Silakan coba lagi.';
            return false;
        }),
        router.on('finish', () => {
            loading.value = false;
        }),
    );
});
onUnmounted(() => stop.forEach((unsubscribe) => unsubscribe()));
</script>

<template>
    <AdminWorkspaceLayout
        :title="section.title"
        :href="section.href"
        :icon="section.icon"
    >
        <div class="min-w-0 space-y-5" :aria-busy="loading">
            <p
                v-if="loading || feedback"
                :role="failed ? 'alert' : 'status'"
                class="text-xs leading-5"
                :class="failed ? 'text-error' : 'text-muted-foreground'"
            >
                {{ loading ? 'Memuat data…' : feedback }}
            </p>
            <slot />
        </div>
    </AdminWorkspaceLayout>
</template>
