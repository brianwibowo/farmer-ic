<!-- Historical source: 73f2555d91d0fb2d6acb7bc70813921edd479ea4 / resources/js/components/farmer/DashboardHeader.vue -->
<script setup lang="ts">
import { referenceAsset } from '@/reference/assets';

import { Bell, User } from '@lucide/vue';
</script>
<template>
    <header class="flex items-center justify-between gap-3 py-1">
        <img :src="referenceAsset('/images/logo-dashboard.svg')" alt="Farm IC" class="h-9 w-auto min-w-0 object-contain" />
        <div class="flex shrink-0 items-center gap-2">
            <button disabled aria-label="Notifikasi dinonaktifkan pada referensi" title="Notifikasi dinonaktifkan pada referensi" class="flex size-11 items-center justify-center rounded-full bg-muted/40 text-primary"><Bell class="size-5" :stroke-width="1.75" /></button>
            <span aria-label="Profil contoh historis" class="flex size-11 items-center justify-center rounded-full bg-primary/15 text-secondary"><User class="size-5" /></span>
        </div>
    </header>
</template>
