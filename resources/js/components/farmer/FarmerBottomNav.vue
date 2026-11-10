<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ClipboardList, FileText, House, LandPlot } from '@lucide/vue';
import { computed, type Component } from 'vue';
import FarmerNavIcon from './FarmerNavIcon.vue';

export type FarmerNavKey =
    | 'home'
    | 'garden'
    | 'ai'
    | 'projection'
    | 'finance'
    | 'activity'
    | 'more'
    | 'report';

export type FarmerNavItem = {
    key: FarmerNavKey;
    label: string;
    href?: string;
    emphasis?: boolean;
};

type Props = {
    activeKey?: FarmerNavKey;
    items?: FarmerNavItem[];
    dashboardTheme?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    activeKey: 'home',
    dashboardTheme: false,
});

const dashboardIcons: Partial<Record<FarmerNavKey, Component>> = {
    home: House,
    garden: LandPlot,
    activity: ClipboardList,
    report: FileText,
};

const emit = defineEmits<{
    select: [key: FarmerNavKey];
}>();

const ownerItems: FarmerNavItem[] = [
    { key: 'home', label: 'Beranda', href: '/' },
    { key: 'projection', label: 'Proyeksi', emphasis: true, href: '/proyeksi' },
    { key: 'more', label: 'Lainnya', href: '/lainnya' },
];

const page = usePage();
const navigationItems = computed<FarmerNavItem[]>(() => {
    if (props.items) return props.items;
    if (page.props.navigation?.admin && props.activeKey !== 'ai') {
        return [
            { key: 'home', label: 'Beranda', href: '/admin/dashboard' },
            ...(page.props.navigation.farms
                ? [
                      {
                          key: 'garden' as const,
                          label: 'Kebun',
                          href: '/admin/farm-management',
                      },
                  ]
                : []),
            { key: 'finance', label: 'Keuangan', href: '/finance' },
            ...(page.props.navigation.users
                ? [
                      {
                          key: 'more' as const,
                          label: 'Pengguna',
                          href: '/admin/user-management',
                      },
                  ]
                : []),
        ];
    }
    if (page.props.navigation?.petugas && props.activeKey !== 'ai') {
        return [
            { key: 'home', label: 'Beranda', href: '/petugas' },
            { key: 'garden', label: 'Kebun', href: '/petugas/kebun' },
            { key: 'activity', label: 'Aktivitas', href: '/petugas/aktivitas' },
            { key: 'report', label: 'Laporan', href: '/petugas/laporan' },
        ];
    }
    return ownerItems;
});
const active = computed<FarmerNavKey>(() =>
    navigationItems.value === ownerItems &&
    !ownerItems.some((item) => item.key === props.activeKey)
        ? 'more'
        : props.activeKey,
);

function select(key: FarmerNavKey): void {
    emit('select', key);
}
</script>

<template>
    <nav
        aria-label="Navigasi utama"
        class="farmer-bottom-nav relative z-10 shrink-0 bg-card px-2"
        :class="
            props.dashboardTheme
                ? 'petugas-bottom-nav border-t border-border'
                : 'rounded-t-[18px]'
        "
    >
        <ul
            class="grid h-[76px] items-end"
            :style="{
                gridTemplateColumns: `repeat(${navigationItems.length}, minmax(0, 1fr))`,
            }"
        >
            <li
                v-for="item in navigationItems"
                :key="item.key"
                class="flex h-full items-end justify-center"
            >
                <component
                    :is="item.href ? Link : 'button'"
                    :href="item.href"
                    :type="item.href ? undefined : 'button'"
                    :aria-current="
                        active === item.key
                            ? active === props.activeKey
                                ? 'page'
                                : 'location'
                            : undefined
                    "
                    :aria-label="item.label"
                    :class="[
                        'flex w-full shrink-0 flex-col items-center justify-center gap-1.5 text-[10px] leading-tight font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring min-[390px]:text-[11px]',
                        item.emphasis
                            ? 'mb-[18px] aspect-square max-w-[68px] gap-1 rounded-full bg-primary text-secondary-foreground hover:brightness-105 active:scale-95'
                            : active === item.key
                              ? 'h-full pb-2 text-primary'
                              : 'h-full pb-2 text-foreground hover:text-primary',
                    ]"
                    @click="select(item.key)"
                >
                    <component
                        v-if="props.dashboardTheme && dashboardIcons[item.key]"
                        :is="dashboardIcons[item.key]"
                        class="size-5"
                        aria-hidden="true"
                    />
                    <FarmerNavIcon
                        v-else
                        :name="item.key"
                        :class="item.emphasis ? 'size-8' : 'size-7'"
                    />
                    <span>{{ item.label }}</span>
                </component>
            </li>
        </ul>
    </nav>
</template>

<style scoped>
.farmer-bottom-nav {
    padding-bottom: env(safe-area-inset-bottom, 0px);
    box-shadow: 0 3px 14px
        color-mix(in srgb, var(--foreground) 18%, transparent);
}

.farmer-bottom-nav.petugas-bottom-nav {
    box-shadow: none;
}

.petugas-bottom-nav ul {
    height: 4.5rem;
}

.petugas-bottom-nav a,
.petugas-bottom-nav button {
    font-size: 0.75rem;
    line-height: 1rem;
}
</style>
