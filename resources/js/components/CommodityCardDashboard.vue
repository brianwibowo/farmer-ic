<script setup lang="ts">
import { Sprout } from '@lucide/vue';
import { ref, watch } from 'vue';
import type { CommoditySummary } from '@/services/commoditySummaryService';

const props = defineProps<{ item: CommoditySummary }>();
const imageFailed = ref(false);
watch(
    () => props.item.image,
    () => {
        imageFailed.value = false;
    },
);
</script>

<template>
    <article
        class="flex h-full min-h-[150px] items-center gap-3 rounded-2xl border border-border bg-card p-3 text-card-foreground min-[375px]:gap-4 min-[375px]:p-4"
    >
        <div
            class="flex size-16 shrink-0 items-center justify-center min-[375px]:size-20"
        >
            <img
                v-if="!imageFailed"
                :src="item.image"
                :alt="`Ilustrasi ${item.name}`"
                width="80"
                height="80"
                class="size-full object-contain"
                draggable="false"
                decoding="async"
                @error="imageFailed = true"
            />
            <Sprout v-else :size="40" class="text-primary" aria-hidden="true" />
        </div>
        <div class="min-w-0 flex-1">
            <h3
                class="text-sm leading-5 font-semibold text-secondary dark:text-surface-foreground"
            >
                {{ item.name }}
            </h3>
            <dl
                class="mt-2 space-y-1.5 text-[11px] leading-4 min-[375px]:text-xs"
            >
                <div
                    v-for="statistic in item.statistics"
                    :key="statistic.label"
                    class="flex flex-wrap gap-x-1"
                >
                    <dt>{{ statistic.label }} :</dt>
                    <dd
                        :class="
                            statistic.highlight
                                ? 'font-semibold text-secondary dark:text-surface-foreground'
                                : ''
                        "
                    >
                        {{ statistic.value }}
                    </dd>
                </div>
            </dl>
        </div>
    </article>
</template>
