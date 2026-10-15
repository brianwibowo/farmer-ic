<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import FarmerMobileShell from '@/components/farmer/FarmerMobileShell.vue';
</script>

<template>
    <Head title="Beranda" />

    <FarmerMobileShell active-key="home">
        <!-- Reserved for the Beranda dashboard. -->
    </FarmerMobileShell>
</template>
