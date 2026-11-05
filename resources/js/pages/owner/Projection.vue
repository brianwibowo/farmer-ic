<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import OwnerPageHeader from '@/components/owner/OwnerPageHeader.vue';
</script>

<template>
    <Head title="Proyeksi" />
    <div class="space-y-5">
        <OwnerPageHeader title="Proyeksi" />
        <p
            role="status"
            class="rounded-xl border border-dashed border-border bg-card px-5 py-9 text-center text-sm leading-6 text-muted-foreground"
        >
            Belum ada proyeksi yang ditampilkan.
        </p>
    </div>
</template>
