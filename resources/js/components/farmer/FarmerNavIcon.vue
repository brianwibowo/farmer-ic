<script setup lang="ts">
import type { FarmerNavKey } from './FarmerBottomNav.vue';

defineProps<{ name: FarmerNavKey }>();
</script>

<template>
    <svg
        viewBox="0 0 32 32"
        fill="none"
        stroke="currentColor"
        stroke-width="1.7"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <template v-if="name === 'home'">
            <path d="M3 17a13 13 0 0 1 26 0M16 13v7" />
            <circle cx="16" cy="24" r="4" />
        </template>
        <template v-else-if="name === 'garden'">
            <path d="M16 29c0-10 7-17 12-20M16 29C16 17 9 9 5 5" />
            <path
                d="M12 15C3 18 2 8 2 3c9 0 15 3 13 10M21 20C30 22 30 11 30 7c-8 0-14 3-12 10"
            />
        </template>
        <template v-else-if="name === 'ai'">
            <path
                d="m12 7 2.1 6.2a2 2 0 0 0 1.3 1.3l6.1 2.1-6.1 2.1a2 2 0 0 0-1.3 1.3L12 26.2 9.9 20a2 2 0 0 0-1.3-1.3l-6.1-2.1 6.1-2.1a2 2 0 0 0 1.3-1.3L12 7Z"
            />
            <path
                d="m25 1 1.2 3.2 3.3 1.3-3.3 1.2L25 10l-1.3-3.3-3.2-1.2 3.2-1.3L25 1ZM25 23l1.1 2.9L29 27l-2.9 1.1L25 31l-1.1-2.9L21 27l2.9-1.1L25 23Z"
            />
        </template>
        <template v-else-if="name === 'finance'">
            <path
                d="M9 17a9 9 0 1 1 17 3M19 6v10M22 8.5c-.8-1.4-5-1.7-5 1s5 1 5 3.5-4.2 2.4-5.5 1M2 21l4-1 8 3h4a2 2 0 0 0 0-4h-4M2 28l7 2a5 5 0 0 0 4-.5l13-8a2 2 0 0 0-2-3l-6 3"
            />
        </template>
        <template v-else>
            <rect x="3" y="3" width="26" height="26" rx="7" />
            <path d="m8 19 6-6 4 4 6-6" />
        </template>
    </svg>
</template>
