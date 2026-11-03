<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <img
        src="/images/logo.png"
        alt=""
        width="2000"
        height="2000"
        class="object-contain dark:brightness-0 dark:invert"
        :class="className"
        v-bind="$attrs"
    />
</template>
