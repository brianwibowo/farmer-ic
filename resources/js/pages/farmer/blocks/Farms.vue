<script setup lang="ts">
import InertiaPagination from '@/components/InertiaPagination.vue';
import { Head, Link } from '@inertiajs/vue3';
import BlockLayout from '@/layouts/BlockLayout.vue';
import type { BlockFarm } from '@/types/blocks';
import { hectares } from '@/types/farms';

defineOptions({ layout: BlockLayout, inheritAttrs: false });
defineProps<{
    farms: {
        data: BlockFarm[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    team: { name: string };
}>();
</script>

<template>
    <Head title="Blok lahan" />
    <header>
        <h1 class="admin-page-title text-2xl font-semibold sm:text-3xl">
            Blok lahan
        </h1>
        <p class="mt-2 text-sm text-muted-foreground">
            Pilih lahan dalam tim {{ team.name }} untuk melihat blok dan batch.
        </p>
    </header>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <Link
            v-for="farm in farms.data"
            :key="farm.id"
            :href="`/kebun/${farm.id}/blocks`"
            class="rounded-xl border border-border bg-card p-5 hover:border-primary focus-visible:outline-2 focus-visible:outline-ring"
            ><h2 class="admin-section-title text-lg font-semibold">
                {{ farm.name }}
            </h2>
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
        Belum ada lahan yang dapat diakses. Petugas perlu mendapat penugasan
        lahan dari Admin.
    </p>
    <InertiaPagination
        v-if="farms.last_page > 1"
        :paginator="farms"
        label="Pagination lahan"
        class="ml-auto justify-end"
    />
</template>
