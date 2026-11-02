<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Plus } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import BlockLayout from '@/layouts/BlockLayout.vue';
import type { BlockFarm, FarmBlock } from '@/types/blocks';
import { hectares } from '@/types/farms';

defineOptions({ layout: BlockLayout });
const props = defineProps<{
    farm: BlockFarm;
    blocks: {
        data: FarmBlock[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    summary: { total: number; active: number; active_area_ha: string };
    filters: { search: string; status: string; per_page: number };
    can: { create: boolean };
}>();
const filters = useForm({ ...props.filters });
function search(): void {
    filters.get(`/kebun/${props.farm.id}/blocks`, {
        preserveState: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`Blok · ${farm.name}`" />
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm text-muted-foreground">{{ farm.name }}</p>
            <h1 class="mt-1 text-2xl font-semibold sm:text-3xl">Blok kebun</h1>
            <p class="mt-2 text-sm text-muted-foreground">
                Kelola blok permanen dan pembagian batch penanamannya.
            </p>
        </div>
        <Button v-if="can.create" as-child class="h-12 rounded-xl"
            ><Link :href="`/kebun/${farm.id}/blocks/create`"
                ><Plus />Tambah blok</Link
            ></Button
        >
    </header>
    <div class="grid gap-3 sm:grid-cols-3">
        <section
            v-for="metric in [
                { label: 'Total blok', value: summary.total },
                { label: 'Blok aktif', value: summary.active },
                {
                    label: 'Luas blok aktif / kebun (ha)',
                    value: `${hectares(summary.active_area_ha)} / ${hectares(farm.total_area_ha)}`,
                },
            ]"
            :key="metric.label"
            class="rounded-xl border border-border bg-card p-5"
        >
            <h2 class="text-sm text-muted-foreground">{{ metric.label }}</h2>
            <p class="mt-2 text-2xl font-semibold">{{ metric.value }}</p>
        </section>
    </div>
    <form
        class="flex flex-wrap items-end gap-3 rounded-xl border border-border bg-card p-4"
        @submit.prevent="search"
    >
        <div class="min-w-0 flex-1 basis-48">
            <label for="block-search" class="mb-2 block text-sm font-medium"
                >Cari blok</label
            ><input
                id="block-search"
                v-model="filters.search"
                class="h-12 w-full rounded-xl border border-border bg-background px-3 focus-visible:outline-2 focus-visible:outline-ring"
                maxlength="150"
                placeholder="Kode atau nama blok"
            />
            <p
                v-if="filters.errors.search"
                role="alert"
                class="mt-1 text-xs text-error"
            >
                {{ filters.errors.search }}
            </p>
        </div>
        <div>
            <label for="block-status" class="mb-2 block text-sm font-medium"
                >Status</label
            ><select
                id="block-status"
                v-model="filters.status"
                class="h-12 rounded-xl border border-border bg-background px-3 focus-visible:outline-2 focus-visible:outline-ring"
            >
                <option value="all">Semua</option>
                <option value="active">Aktif</option>
                <option value="inactive">Nonaktif</option>
            </select>
        </div>
        <Button
            type="submit"
            class="h-12 rounded-xl"
            :disabled="filters.processing"
            ><LoaderCircle v-if="filters.processing" class="animate-spin" />{{
                filters.processing ? 'Memuat…' : 'Terapkan'
            }}</Button
        >
    </form>
    <div
        v-if="blocks.data.length"
        class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
    >
        <Link
            v-for="block in blocks.data"
            :key="block.id"
            :href="`/kebun/${farm.id}/blocks/${block.id}`"
            class="rounded-xl border border-border bg-card p-5 transition hover:border-primary focus-visible:outline-2 focus-visible:outline-ring"
            ><div class="flex flex-wrap items-center justify-between gap-2">
                <span
                    class="text-xs font-semibold tracking-wide text-muted-foreground"
                    >{{ block.code }}</span
                ><span class="rounded-full bg-muted px-3 py-1 text-xs">{{
                    block.is_active ? 'Aktif' : 'Nonaktif'
                }}</span>
            </div>
            <h2 class="mt-3 text-xl font-semibold">{{ block.name }}</h2>
            <p class="mt-2 text-sm">{{ hectares(block.area_ha) }} ha</p>
            <p class="mt-4 text-xs text-muted-foreground">
                {{
                    block.land_clearing_completed_at
                        ? 'Land Clearing selesai · Lihat timeline batch'
                        : 'Land Clearing berlangsung'
                }}
            </p></Link
        >
    </div>
    <section
        v-else
        class="rounded-xl border border-dashed border-border bg-card p-8 text-center"
    >
        <h2 class="text-lg font-semibold">Belum ada blok yang ditampilkan</h2>
        <p class="mt-2 text-sm text-muted-foreground">
            {{
                summary.total
                    ? 'Coba ubah pencarian atau filter status.'
                    : can.create
                      ? 'Tambahkan blok pertama untuk mulai mencatat persiapan lahan.'
                      : 'Admin belum menambahkan blok pada kebun ini.'
            }}
        </p>
    </section>
    <nav
        v-if="blocks.last_page > 1"
        aria-label="Halaman daftar blok"
        class="flex flex-wrap items-center justify-between gap-3"
    >
        <span class="text-sm text-muted-foreground"
            >Halaman {{ blocks.current_page }} dari {{ blocks.last_page }} ·
            {{ blocks.total }} blok</span
        >
        <div class="flex gap-2">
            <Button v-if="blocks.prev_page_url" as-child variant="outline"
                ><Link :href="blocks.prev_page_url" preserve-scroll
                    >Sebelumnya</Link
                ></Button
            ><Button v-if="blocks.next_page_url" as-child variant="outline"
                ><Link :href="blocks.next_page_url" preserve-scroll
                    >Berikutnya</Link
                ></Button
            >
        </div>
    </nav>
</template>
