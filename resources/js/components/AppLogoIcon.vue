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
        src="/images/logo-hijauan.jpeg"
        alt=""
        width="1280"
        height="1280"
        class="shrink-0 rounded-md object-contain"
        :class="className"
        v-bind="$attrs"
    />
</template>
