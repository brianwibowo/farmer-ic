<script setup lang="ts">
import { Sprout, Wheat } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{ name: string }>();
const normalizedName = computed(() =>
    props.name.trim().toLocaleLowerCase('id-ID'),
);
const imageSource = computed(() => {
    if (normalizedName.value === 'jagung')
        return '/images/commodities/jagung.svg';
    if (normalizedName.value === 'rumput')
        return '/images/commodities/rumput.png';

    return null;
});
const iconColor = computed(() => {
    if (normalizedName.value === 'jagung') return 'text-warning';
    if (normalizedName.value === 'rumput') return 'text-success';
    if (normalizedName.value === 'sorgum') return 'text-secondary';

    return 'text-muted-foreground';
});
</script>

<template>
    <span
        class="flex size-7 shrink-0 items-center justify-center overflow-hidden"
        :class="iconColor"
        aria-hidden="true"
    >
        <img
            v-if="imageSource"
            :src="imageSource"
            :alt="''"
            class="size-full object-cover"
        />
        <Wheat v-else-if="normalizedName === 'sorgum'" class="size-4" />
        <Sprout v-else class="size-4" />
    </span>
</template>
