<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import FarmerMobileShell from '@/components/farmer/FarmerMobileShell.vue';
import type { FarmerNavKey } from '@/components/farmer/FarmerBottomNav.vue';
import OwnerDesktopNav from '@/components/owner/OwnerDesktopNav.vue';

const page = usePage();
const active = computed<FarmerNavKey>(() => {
    const path = page.url.split('?')[0].replace(/\/$/, '') || '/';
    if (path === '/') return 'home';
    if (path === '/proyeksi' || path.startsWith('/proyeksi/'))
        return 'projection';
    if (path === '/kebun' || path.startsWith('/kebun/')) return 'garden';
    return 'more';
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
        router.on('success', () => {
            feedback.value = 'Data berhasil dimuat.';
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
    <FarmerMobileShell
        :active-key="active"
        show-appearance-toggle
        responsive-desktop
    >
        <template #desktop-navigation>
            <OwnerDesktopNav :active-key="active" />
        </template>
        <div
            class="owner-layout-content min-w-0 space-y-5 px-4 pt-4 pb-6"
            :aria-busy="loading"
        >
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
    </FarmerMobileShell>
</template>

<style scoped>
@media (min-width: 640px) {
    .owner-layout-content {
        padding: 24px 24px 32px;
    }
}
@media (min-width: 1024px) {
    .owner-layout-content {
        padding: 24px 32px 40px;
    }
}
@media (min-width: 1920px) {
    .owner-layout-content {
        padding: 32px 48px 48px;
    }
}
</style>
