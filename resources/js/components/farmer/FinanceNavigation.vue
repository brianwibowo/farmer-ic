<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { financeLabels } from '@/lib/finance';
import type { FinanceResource } from '@/types/finance';

defineProps<{ active?: FinanceResource | 'overview' }>();
const page = usePage<{
    navigation: { finance: Partial<Record<FinanceResource, boolean>> };
}>();
</script>

<template>
    <nav aria-label="Menu keuangan" class="flex flex-wrap gap-2">
        <Link
            v-if="page.props.navigation.finance.transactions"
            href="/finance"
            :aria-current="active === 'overview' ? 'page' : undefined"
            class="rounded-xl border border-border px-3 py-2 text-xs font-medium hover:bg-muted focus-visible:outline-2 focus-visible:outline-ring"
            :class="
                active === 'overview'
                    ? 'bg-primary text-primary-foreground'
                    : 'text-foreground'
            "
            >Ringkasan</Link
        >
        <template v-for="(label, key) in financeLabels" :key="key">
            <Link
                v-if="page.props.navigation.finance[key]"
                :href="`/finance/${key}`"
                :aria-current="active === key ? 'page' : undefined"
                class="rounded-xl border border-border px-3 py-2 text-xs font-medium hover:bg-muted focus-visible:outline-2 focus-visible:outline-ring"
                :class="
                    active === key
                        ? 'bg-primary text-primary-foreground'
                        : 'text-foreground'
                "
                >{{ label }}</Link
            >
        </template>
    </nav>
</template>
