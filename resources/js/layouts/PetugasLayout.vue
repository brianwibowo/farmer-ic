<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import FarmerBottomNav, {
    type FarmerNavItem,
    type FarmerNavKey,
} from '@/components/farmer/FarmerBottomNav.vue';
import FarmerMobileShell from '@/components/farmer/FarmerMobileShell.vue';

const page = usePage();
const navigating = ref(false);
const navigationError = ref('');
const isHome = computed(() => page.component === 'petugas/Home');
const items: FarmerNavItem[] = [
    { key: 'home', label: 'Beranda', href: '/petugas' },
    { key: 'garden', label: 'Kebun', href: '/petugas/kebun' },
    { key: 'activity', label: 'Aktivitas', href: '/petugas/aktivitas' },
    { key: 'report', label: 'Laporan', href: '/petugas/laporan' },
];
const activeKey = computed<FarmerNavKey>(() => {
    const pathname = page.url.split('?')[0].replace(/\/$/, '');
    if (
        ['/petugas/tasks', '/petugas/penanaman', '/petugas/subbloks'].some(
            (path) => pathname === path || pathname.startsWith(`${path}/`),
        )
    )
        return 'activity';
    const exact = items.find((item) => item.href === pathname);
    if (exact) return exact.key;

    return (
        [...items]
            .sort(
                (first, second) =>
                    (second.href ?? '').length - (first.href ?? '').length,
            )
            .find(
                (item) =>
                    item.href !== undefined &&
                    pathname.startsWith(`${item.href}/`),
            )?.key ?? 'home'
    );
});
const unsubscribe: (() => void)[] = [];
onMounted(() => {
    unsubscribe.push(
        router.on('start', () => {
            navigating.value = true;
            navigationError.value = '';
        }),
        router.on('finish', () => {
            navigating.value = false;
        }),
        router.on('networkError', () => {
            navigationError.value =
                'Halaman belum berhasil dimuat. Periksa koneksi lalu pilih menu kembali.';
        }),
    );
});
onUnmounted(() => unsubscribe.forEach((stop) => stop()));
</script>

<template>
    <FarmerMobileShell
        :active-key="activeKey"
        dashboard-theme
        :show-header="!isHome"
        :flush-content="isHome"
    >
        <div
            class="flex min-h-full flex-col"
            :class="
                isHome
                    ? 'petugas-home-content'
                    : 'petugas-page-content px-4 pt-4 pb-6'
            "
            :aria-busy="navigating"
        >
            <p
                v-if="navigating"
                role="status"
                class="mb-3 text-xs text-muted-foreground"
                :class="{ 'mx-4 mt-4': isHome }"
            >
                Memuat halaman…
            </p>
            <p
                v-if="navigationError"
                role="alert"
                class="mb-3 rounded-xl border border-error/30 bg-error/10 p-3 text-sm text-error"
                :class="{ 'mx-4 mt-4': isHome }"
            >
                {{ navigationError }}
            </p>
            <slot />
        </div>
        <template #navigation>
            <FarmerBottomNav
                :items="items"
                :active-key="activeKey"
                dashboard-theme
                class="[&_[aria-current=page]]:font-semibold [&_[aria-current=page]]:text-secondary dark:[&_[aria-current=page]]:text-primary"
            />
        </template>
    </FarmerMobileShell>
</template>
