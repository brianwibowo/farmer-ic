<script setup lang="ts">
import { BanknoteArrowDown, Coins } from '@lucide/vue';

defineProps<{
    title: string;
    value: string;
    projectionTitle: string;
    projectionValue: string;
    type: 'income' | 'expense';
}>();
</script>

<template>
    <article
        :aria-label="title"
        :class="[
            'flex min-w-0 flex-col gap-3 rounded-2xl p-3 text-secondary min-[390px]:gap-3.5 min-[390px]:p-3.5 dark:text-surface-foreground',
            type === 'income' ? 'bg-surface' : 'bg-expense-surface',
        ]"
    >
        <component
            :is="type === 'income' ? Coins : BanknoteArrowDown"
            :size="25"
            :stroke-width="1.7"
            class="shrink-0 text-secondary-foreground"
            aria-hidden="true"
        />
        <dl class="space-y-3 min-[390px]:space-y-3.5">
            <div>
                <dt
                    class="text-[11px] leading-4 font-normal min-[390px]:text-xs"
                >
                    {{ title }}
                </dt>
                <dd
                    class="mt-0.5 text-sm leading-5 font-bold tabular-nums min-[390px]:text-base"
                >
                    {{ value }}
                </dd>
            </div>
            <div>
                <dt
                    class="text-[11px] leading-4 font-normal min-[390px]:text-xs"
                >
                    {{ projectionTitle }}
                </dt>
                <dd
                    class="mt-0.5 text-sm leading-5 font-bold tabular-nums min-[390px]:text-base"
                >
                    {{ projectionValue }}
                </dd>
            </div>
        </dl>
    </article>
</template>
