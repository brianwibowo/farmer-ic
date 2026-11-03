<script setup lang="ts">
import {
    AlertTriangle,
    ChevronDown,
    LandPlot,
    ListChecks,
    Sprout,
    Users,
    Wheat,
} from '@lucide/vue';
import { ref } from 'vue';
import type { DashboardData } from '@/types/dashboard';
defineProps<{ items: DashboardData['attention'] }>();
const expanded = ref<string | null>(null);
const icons = {
    risks: AlertTriangle,
    harvest: Wheat,
    clearing: LandPlot,
    maintenance: Sprout,
    officers: Users,
};
</script>

<template>
    <aside
        class="dashboard-panel dashboard-attention"
        aria-labelledby="attention-heading"
    >
        <div class="mb-3 flex items-center justify-between gap-3">
            <h2 id="attention-heading" class="admin-section-title">
                Perlu perhatian
            </h2>
            <ListChecks
                :size="18"
                class="text-muted-foreground"
                aria-hidden="true"
            />
        </div>
        <div class="divide-y divide-border/60">
            <div v-for="item in items" :key="item.key">
                <button
                    type="button"
                    class="flex min-h-12 w-full items-center gap-2.5 rounded-md py-3 text-left text-sm font-medium focus-visible:outline-2 focus-visible:outline-ring"
                    :aria-expanded="expanded === item.key"
                    :aria-controls="`attention-${item.key}`"
                    @click="expanded = expanded === item.key ? null : item.key"
                >
                    <component
                        :is="icons[item.key as keyof typeof icons]"
                        :size="17"
                        class="shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <span class="flex-1">{{ item.label }}</span
                    ><span
                        class="grid min-w-6 place-items-center rounded-full bg-muted px-1.5 py-1 text-sm font-bold tabular-nums"
                        >{{ item.count }}</span
                    >
                    <ChevronDown
                        :size="13"
                        class="shrink-0 text-muted-foreground transition-transform"
                        :class="{ 'rotate-180': expanded === item.key }"
                        aria-hidden="true"
                    />
                </button>
                <p
                    v-if="expanded === item.key"
                    :id="`attention-${item.key}`"
                    class="pb-4 pl-7 text-xs leading-5 text-muted-foreground"
                >
                    {{ item.description }}
                </p>
            </div>
        </div>
    </aside>
</template>
