<script setup lang="ts">
import { computed } from 'vue';
import { Crown, ShieldCheck, UserRound, Users } from '@lucide/vue';
import { roleLabel } from '@/types/user-management';

const props = defineProps<{
    total: number;
    roles: { role: string; count: number }[];
}>();
const metrics = computed(() => [
    {
        key: 'total',
        label: 'Total pengguna',
        count: props.total,
        icon: Users,
    },
    ...props.roles.map((item) => ({
        key: item.role,
        label: roleLabel(item.role),
        count: item.count,
        icon:
            item.role === 'owner'
                ? Crown
                : item.role === 'petugas'
                  ? UserRound
                  : ShieldCheck,
    })),
]);
const number = new Intl.NumberFormat('id-ID');
</script>

<template>
    <section
        class="user-summary-grid"
        :style="{ '--user-summary-columns': metrics.length }"
        aria-label="Statistik pengguna"
    >
        <article
            v-for="metric in metrics"
            :key="metric.key"
            class="user-summary-card rounded-xl bg-card"
        >
            <div class="flex items-center justify-between gap-3">
                <h2 class="min-w-0 text-sm font-semibold break-words">
                    {{ metric.label }}
                </h2>
                <component
                    :is="metric.icon"
                    class="hidden size-5 shrink-0 text-primary sm:block"
                    aria-hidden="true"
                />
            </div>
            <p class="admin-metric-value mt-3 tracking-tight">
                {{ number.format(metric.count) }}
            </p>
        </article>
    </section>
</template>
