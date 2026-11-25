<script setup lang="ts">
import PageBackButton from '@/components/PageBackButton.vue';
defineProps<{ title: string; backHref: string; backLabel: string }>();
</script>

<template>
    <header class="flex items-center gap-2">
        <PageBackButton :href="backHref" :label="backLabel" />
        <h1 class="min-w-0 break-words">{{ title }}</h1>
    </header>
</template>
