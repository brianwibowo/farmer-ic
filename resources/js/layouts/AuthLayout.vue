<script setup lang="ts">
import AuthLoginLayout from '@/layouts/auth/AuthLoginLayout.vue';
import AuthSimpleLayout from '@/layouts/auth/AuthSimpleLayout.vue';

const {
    title = '',
    description = '',
    variant = 'simple',
} = defineProps<{
    title?: string;
    description?: string;
    variant?: 'simple' | 'login';
}>();
</script>

<template>
    <component
        :is="variant === 'login' ? AuthLoginLayout : AuthSimpleLayout"
        :title="title"
        :description="description"
    >
        <slot />
    </component>
</template>
