<script setup lang="ts">
import { ref, type HTMLAttributes } from 'vue';
import { Sprout } from '@lucide/vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
const hasError = ref(false);
</script>

<template>
    <img
        v-if="!hasError"
        src="/images/logo-hijauan.jpeg"
        alt="Farmer IC"
        width="1280"
        height="1280"
        class="shrink-0 rounded-md object-contain"
        :class="className"
        v-bind="$attrs"
        @error="hasError = true"
    />
    <div
        v-else
        class="flex size-full items-center justify-center rounded-md bg-primary/10 text-primary"
        :class="className"
    >
        <Sprout class="size-5" />
    </div>
</template>
