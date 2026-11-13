<script setup lang="ts">
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { computed } from 'vue';
import FarmBlocksTable from '@/components/blocks/FarmBlocksTable.vue';
import { Button } from '@/components/ui/button';
import BlockLayout from '@/layouts/BlockLayout.vue';
import type {
    BlockFarm,
    ManagedBlock,
    BlockFormCapacity,
} from '@/types/blocks';
import { hectares } from '@/types/farms';

defineOptions({ layout: BlockLayout, inheritAttrs: false });
const props = defineProps<{
    farm: BlockFarm;
    blocks: {
        data: ManagedBlock[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    blockCapacity: BlockFormCapacity;
    batchPermissions: Record<string, boolean>;
    harvestPermissions: Record<string, boolean>;
    summary: { total: number; active: number; active_area_ha: string };
    filters: { search: string; status: string; per_page: number };
    can: { create: boolean };
}>();
const page = usePage();
const theme = computed(() =>
    page.props.navigation?.farms ? 'admin-dashboard-theme' : 'farm-block-theme',
);
const filters = useForm({ ...props.filters });
function reset(): void {
    filters.search = '';
    filters.status = 'all';
    search();
}
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
            <h1
                class="admin-page-title mt-1 text-2xl font-semibold sm:text-3xl"
            >
                Blok kebun
            </h1>
            <p class="mt-2 text-sm text-muted-foreground">
                {{ farm.name }} · Kelola blok permanen dan pembagian batch
                penanamannya.
            </p>
        </div>
    </header>
    <div class="grid grid-cols-3 gap-2 sm:gap-4">
        <section
            v-for="metric in [
                { label: 'Total blok', value: summary.total, unit: 'blok' },
                { label: 'Blok aktif', value: summary.active, unit: 'blok' },
                {
                    label: 'Luas blok aktif',
                    value: hectares(summary.active_area_ha),
                    unit: 'ha',
                    hint: `dari ${hectares(farm.total_area_ha)} ha kebun`,
                },
            ]"
            :key="metric.label"
            class="min-w-0 rounded-xl border border-border bg-card p-3 sm:p-5"
        >
            <h2
                class="min-h-10 text-xs leading-5 text-muted-foreground sm:min-h-0 sm:text-sm"
            >
                {{ metric.label }}
            </h2>
            <p
                class="admin-metric-value mt-2 text-xl! tracking-tight break-words sm:text-3xl!"
            >
                {{ metric.value }}
                <span class="text-xs font-medium text-muted-foreground">{{
                    metric.unit
                }}</span>
            </p>
            <p
                v-if="metric.hint"
                class="mt-1 text-xs leading-5 text-muted-foreground"
            >
                {{ metric.hint }}
            </p>
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
                class="admin-field block-field"
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
            ><AppSelect
                id="block-status"
                v-model="filters.status"
                class="admin-field block-field"
            >
                <AppSelectOption value="all">Semua</AppSelectOption>
                <AppSelectOption value="active">Aktif</AppSelectOption>
                <AppSelectOption value="inactive">Nonaktif</AppSelectOption>
            </AppSelect>
        </div>
        <Button
            type="submit"
            class="block-action h-10"
            :disabled="filters.processing"
            ><LoaderCircle v-if="filters.processing" class="animate-spin" />{{
                filters.processing ? 'Memuat…' : 'Terapkan'
            }}</Button
        >
        <Button
            v-if="filters.search || filters.status !== 'all'"
            type="button"
            variant="outline"
            class="block-action h-10"
            :disabled="filters.processing"
            @click="reset"
            >Reset filter</Button
        >
    </form>
    <FarmBlocksTable
        :farm="farm"
        :blocks="blocks.data"
        :batch-permissions="batchPermissions"
        :harvest-permissions="harvestPermissions"
        :capacity="blockCapacity"
        :can-create="can.create"
        :theme="theme"
        :total="blocks.total"
        :client-pagination="false"
        :searchable="false"
        return-to="blocks"
    />
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
