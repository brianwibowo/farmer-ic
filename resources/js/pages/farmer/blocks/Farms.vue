<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import BlockLayout from '@/layouts/BlockLayout.vue';
import type { BlockFarm } from '@/types/blocks';
import { hectares } from '@/types/farms';

defineOptions({ layout: BlockLayout });
defineProps<{
    farms: {
        data: BlockFarm[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    team: { name: string };
}>();
</script>

<template>
    <Head title="Blok kebun" />
    <header>
        <h1 class="text-2xl font-semibold sm:text-3xl">Blok kebun</h1>
        <p class="mt-2 text-sm text-muted-foreground">
            Pilih kebun dalam tim {{ team.name }} untuk melihat blok dan batch.
        </p>
    </header>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <Link
            v-for="farm in farms.data"
            :key="farm.id"
            :href="`/kebun/${farm.id}/blocks`"
            class="rounded-xl border border-border bg-card p-5 hover:border-primary focus-visible:outline-2 focus-visible:outline-ring"
            ><h2 class="text-lg font-semibold">{{ farm.name }}</h2>
            <p class="mt-2 text-sm text-muted-foreground">
                {{ hectares(farm.total_area_ha) }} ha
            </p>
            <p class="mt-4 text-sm font-medium">Lihat blok →</p></Link
        >
    </div>
    <p
        v-if="!farms.data.length"
        class="rounded-xl border border-dashed border-border bg-card p-6 text-sm text-muted-foreground"
    >
        Belum ada kebun yang dapat diakses. Petugas perlu mendapat penugasan
        kebun dari Admin.
    </p>
    <nav
        v-if="farms.prev_page_url || farms.next_page_url"
        aria-label="Halaman daftar kebun"
        class="flex flex-wrap gap-4 text-sm"
    >
        <Link v-if="farms.prev_page_url" :href="farms.prev_page_url"
            >← Sebelumnya</Link
        ><Link v-if="farms.next_page_url" :href="farms.next_page_url"
            >Berikutnya →</Link
        >
    </nav>
</template>
