<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ChartNoAxesCombined,
    House,
    LayoutDashboard,
    LayoutGrid,
} from '@lucide/vue';
import { computed } from 'vue';
import type { FarmerNavKey } from '@/components/farmer/FarmerBottomNav.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';

const props = withDefaults(defineProps<{ activeKey?: FarmerNavKey }>(), {
    activeKey: 'home',
});
const { isCurrentUrl } = useCurrentUrl();
const active = computed(() =>
    isCurrentUrl('/ringkasan-eksekutif')
        ? 'executive'
        : props.activeKey === 'home' || props.activeKey === 'projection'
          ? props.activeKey
          : 'more',
);
const items = [
    { key: 'home', label: 'Beranda', href: '/', icon: House },
    {
        key: 'executive',
        label: 'Ringkasan Eksekutif',
        href: '/ringkasan-eksekutif',
        icon: LayoutDashboard,
    },
    {
        key: 'projection',
        label: 'Proyeksi',
        href: '/proyeksi',
        icon: ChartNoAxesCombined,
    },
    { key: 'more', label: 'Lainnya', href: '/lainnya', icon: LayoutGrid },
] as const;
</script>

<template>
    <nav
        aria-label="Navigasi utama Owner"
        class="rounded-xl border border-border bg-muted/40 p-1"
    >
        <ul class="flex items-center gap-1">
            <li v-for="item in items" :key="item.key">
                <Link
                    :href="item.href"
                    :aria-current="
                        active === item.key
                            ? isCurrentUrl(item.href)
                                ? 'page'
                                : 'location'
                            : undefined
                    "
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg px-5 text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    :class="
                        active === item.key
                            ? 'bg-primary/15 text-secondary dark:text-primary'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                    "
                >
                    <component
                        :is="item.icon"
                        class="size-4"
                        aria-hidden="true"
                    />
                    {{ item.label }}
                </Link>
            </li>
        </ul>
    </nav>
</template>
