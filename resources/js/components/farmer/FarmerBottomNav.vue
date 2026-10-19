<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import FarmerNavIcon from './FarmerNavIcon.vue';

export type FarmerNavKey =
    | 'home'
    | 'garden'
    | 'ai'
    | 'finance'
    | 'activity';
    // | 'report';

type FarmerNavItem = {
    key: FarmerNavKey;
    label: string;
    href?: string;
    emphasis?: boolean;
};

type Props = {
    activeKey?: FarmerNavKey;
};

const props = withDefaults(defineProps<Props>(), {
    activeKey: 'home',
});

const emit = defineEmits<{
    select: [key: FarmerNavKey];
}>();

const items: FarmerNavItem[] = [
    { key: 'home', label: 'Beranda', href: '/' },
    { key: 'garden', label: 'Kebun', href: '/kebun' },
    { key: 'ai', label: 'AI', emphasis: true, href: '/kebun-petani/ai' },
    { key: 'finance', label: 'Keuangan', href: '/finance' },
    { key: 'activity', label: 'Aktivitas', href: '/aktivitas' },
    // { key: 'report', label: 'Laporan', href: '/laporan' },
];

const active = computed(() => props.activeKey);

function select(key: FarmerNavKey): void {
    emit('select', key);
}
</script>

<template>
    <nav
        aria-label="Navigasi utama"
        class="farmer-bottom-nav relative z-10 shrink-0 rounded-t-[18px] bg-card px-2"
    >
        <ul class="grid h-[76px] grid-cols-5 items-end">
            <li
                v-for="item in items"
                :key="item.key"
                class="flex h-full items-end justify-center"
            >
                <component
                    :is="item.href ? Link : 'button'"
                    :href="item.href"
                    :type="item.href ? undefined : 'button'"
                    :aria-current="active === item.key ? 'page' : undefined"
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
                    <FarmerNavIcon
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
</style>
