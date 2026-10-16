<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import FarmerMobileShell from '@/components/farmer/FarmerMobileShell.vue';
import { useFarmerNavigation } from '@/composables/useFarmerNavigation';

const { navigate } = useFarmerNavigation();
</script>

<template>
    <Head title="Beranda" />

    <FarmerMobileShell active-key="home">
        <!-- Reserved for the Beranda dashboard. -->

        <!-- dari dev alwi -->
    <!-- <FarmerMobileShell active-key="ai" @select="navigate"> -->
        <!-- Reserved for farmer dashboard and AI content. -->
    </FarmerMobileShell>
</template>
