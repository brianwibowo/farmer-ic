<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue';
import {
    Gauge,
    HandCoins,
    Sparkles,
    SquareActivity,
    Sprout,
} from '@lucide/vue';
import { computed } from 'vue';

export type FarmerNavKey = 'home' | 'garden' | 'ai' | 'finance' | 'activity';

type FarmerNavItem = {
    key: FarmerNavKey;
    label: string;
    icon: LucideIcon;
    emphasis?: boolean;
};

type Props = {
    activeKey?: FarmerNavKey;
};

const props = withDefaults(defineProps<Props>(), {
    activeKey: 'ai',
});

const emit = defineEmits<{
    select: [key: FarmerNavKey];
}>();

const items: FarmerNavItem[] = [
    { key: 'home', label: 'Beranda', icon: Gauge },
    { key: 'garden', label: 'Kebun', icon: Sprout },
    { key: 'ai', label: 'AI', icon: Sparkles, emphasis: true },
    { key: 'finance', label: 'Keuangan', icon: HandCoins },
    { key: 'activity', label: 'Aktivitas', icon: SquareActivity },
];

const active = computed(() => props.activeKey);

function select(key: FarmerNavKey): void {
    emit('select', key);
}
</script>

<template>
    <nav
        aria-label="Navigasi utama"
        class="absolute inset-x-0 bottom-0 z-10 h-20 border-t border-[#d9d9d9] bg-white px-2 shadow-[0_-3px_12px_rgba(7,8,7,0.08)]"
    >
        <ul class="grid h-full grid-cols-5 items-end">
            <li
                v-for="item in items"
                :key="item.key"
                class="flex h-full items-end justify-center"
            >
                <button
                    type="button"
                    :aria-current="active === item.key ? 'page' : undefined"
                    :aria-label="item.label"
                    :class="[
                        'flex w-full flex-col items-center justify-center gap-1 pb-2 text-[10px] leading-none font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                        item.emphasis
                            ? active === item.key
                                ? 'h-[72px] max-w-[72px] -translate-y-5 gap-1 rounded-full bg-[#68bb6c] pb-2 text-white shadow-sm'
                                : 'h-[72px] max-w-[72px] -translate-y-5 gap-1 rounded-full bg-white pb-2 text-[#070807] shadow-sm ring-1 ring-[#d9d9d9]'
                            : active === item.key
                              ? 'text-[#1c5e21]'
                              : 'text-[#070807]',
                    ]"
                    @click="select(item.key)"
                >
                    <component
                        :is="item.icon"
                        :size="item.emphasis ? 28 : 24"
                        :stroke-width="item.emphasis ? 1.5 : 1.6"
                        aria-hidden="true"
                    />
                    <span>{{ item.label }}</span>
                </button>
            </li>
        </ul>
    </nav>
</template>
