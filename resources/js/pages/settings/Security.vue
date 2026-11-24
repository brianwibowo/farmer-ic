<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import FarmerMobileShell from '@/components/farmer/FarmerMobileShell.vue';
import MobileSecurityForm from '@/components/settings/MobileSecurityForm.vue';

defineProps<{
    passwordRules: string;
    securityPresentation: { returnUrl: string };
}>();
</script>

<template>
    <Head title="Keamanan" />
    <FarmerMobileShell :show-navigation="false">
        <MobileSecurityForm
            :password-rules="passwordRules"
            :return-url="securityPresentation.returnUrl"
        />
    </FarmerMobileShell>
</template>
