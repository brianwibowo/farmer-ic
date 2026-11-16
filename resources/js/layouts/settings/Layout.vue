<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';

const page = usePage();
</script>

<template>
    <div
        :class="
            page.props.navigation?.admin || page.props.navigation?.owner
                ? 'admin-page-content space-y-6'
                : 'mx-auto w-full max-w-screen-2xl space-y-6 p-4 md:p-6 lg:p-8'
        "
    >
        <slot />
    </div>
</template>
