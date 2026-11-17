<script setup lang="ts">
import InertiaPagination from '@/components/InertiaPagination.vue';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { CheckCircle2, LandPlot, LoaderCircle, Ruler } from '@lucide/vue';
import SummaryStatCard from '@/components/SummaryStatCard.vue';
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
                Blok lahan
            </h1>
            <p class="mt-2 text-sm text-muted-foreground">
                {{ farm.name }} · Kelola blok permanen dan pembagian batch
                penanamannya.
            </p>
        </div>
    </header>
    <section
        class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4"
        aria-label="Ringkasan blok lahan"
    >
        <SummaryStatCard
            label="Total blok"
            :value="summary.total"
            :icon="LandPlot"
        />
        <SummaryStatCard
            label="Blok aktif"
            :value="summary.active"
            :icon="CheckCircle2"
        />
        <SummaryStatCard
            label="Luas blok aktif (ha)"
            :value="hectares(summary.active_area_ha)"
            :icon="Ruler"
        />
    </section>
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
    <footer
        v-if="blocks.last_page > 1"
        class="flex flex-wrap items-center justify-between gap-3"
    >
        <p class="text-xs text-muted-foreground">{{ blocks.total }} blok</p>
        <InertiaPagination
            :paginator="blocks"
            :disabled="filters.processing"
            label="Pagination blok"
            class="ml-auto justify-end"
        />
    </footer>
</template>
