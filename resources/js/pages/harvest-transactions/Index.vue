<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    ClipboardList,
    LandPlot,
    LoaderCircle,
    Ruler,
    Search,
    Users,
    Wheat,
} from '@lucide/vue';
import { computed } from 'vue';
import FarmField from '@/components/farms/FarmField.vue';
import LogbookPagination from '@/components/logbook/LogbookPagination.vue';
import { Button } from '@/components/ui/button';
import {
    type HarvestTransactionBlockOption,
    type HarvestTransactionFarmOption,
    type HarvestTransactionFilters,
    type HarvestTransactionPage,
    type HarvestTransactionRow,
    type HarvestTransactionSummary,
    type HarvestTransactionVarietyOption,
} from '@/types/harvest-transactions';

const props = defineProps<{
    transactions: HarvestTransactionPage;
    summary: HarvestTransactionSummary;
    filters: HarvestTransactionFilters;
    farms: HarvestTransactionFarmOption[];
    blocks: HarvestTransactionBlockOption[];
    varieties: HarvestTransactionVarietyOption[];
}>();

const filters = useForm({
    search: props.filters.search ?? '',
    farm_id: props.filters.farm_id ?? '',
    block_id: props.filters.block_id ?? '',
    variety_id: props.filters.variety_id ?? '',
    from_date: props.filters.from_date ?? '',
    to_date: props.filters.to_date ?? '',
    per_page: Number(props.filters.per_page ?? 15),
});

const blockOptions = computed(() =>
    props.blocks.filter(
        (block) => !filters.farm_id || block.farm_id === filters.farm_id,
    ),
);
const hasFilters = computed(() =>
    Boolean(
        filters.search ||
            filters.farm_id ||
            filters.block_id ||
            filters.variety_id ||
            filters.from_date ||
            filters.to_date,
    ),
);
const summaryCards = computed(() => [
    {
        label: 'Laporan panen',
        value: String(props.summary.reports),
        hint: 'Laporan realisasi panen',
        icon: ClipboardList,
    },
    {
        label: 'Luas panen',
        value: `${formatDecimal(props.summary.area_ha)} ha`,
        hint: 'Akumulasi luas laporan',
        icon: Ruler,
    },
    {
        label: 'Berat panen',
        value: `${formatDecimal(props.summary.weight_ton)} ton`,
        hint: 'Akumulasi berat kotor',
        icon: Wheat,
    },
    {
        label: 'Pekerja',
        value: String(props.summary.workers),
        hint: 'Jumlah pekerja tercatat',
        icon: Users,
    },
    {
        label: 'Kebun',
        value: String(props.summary.farms),
        hint: 'Kebun dengan transaksi',
        icon: LandPlot,
    },
]);

function apply(): void {
    if (
        filters.from_date &&
        filters.to_date &&
        filters.to_date < filters.from_date
    ) {
        filters.setError(
            'to_date',
            'Tanggal akhir tidak boleh sebelum tanggal awal.',
        );
        return;
    }
    filters.clearErrors();
    filters.get('/admin/transaksi-panen', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function reset(): void {
    filters.search = '';
    filters.farm_id = '';
    filters.block_id = '';
    filters.variety_id = '';
    filters.from_date = '';
    filters.to_date = '';
    filters.per_page = 15;
    apply();
}

function changeFarm(): void {
    if (
        filters.block_id &&
        !blockOptions.value.some((block) => block.id === filters.block_id)
    ) {
        filters.block_id = '';
    }
}

const monthLabels = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Agu',
    'Sep',
    'Okt',
    'Nov',
    'Des',
];

function formatDecimal(value: string | null, digits = 2): string {
    if (value === null || value === '') {
        return '0';
    }
    const [whole, fraction] = Number(value).toFixed(digits).split('.');
    const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return fraction ? `${grouped},${fraction}` : grouped;
}

function formatDate(value: string): string {
    const [year, month, day] = value.split('-').map(Number);
    return `${String(day).padStart(2, '0')} ${monthLabels[month - 1] ?? ''} ${year}`;
}

function varietyLabel(variety: HarvestTransactionVarietyOption): string {
    return variety.commodity
        ? `${variety.name} · ${variety.commodity.name}`
        : variety.name;
}

function rowSubblock(row: HarvestTransactionRow): string {
    if (!row.cycle) {
        return 'Subblok belum terhubung';
    }
    const label = row.cycle.subblock_label
        ? `Subblok ${row.cycle.subblock_label}`
        : 'Subblok';
    return `${label} · Batch ${row.cycle.batch_number ?? '-'}`;
}

function qualityLabel(row: HarvestTransactionRow): string {
    return [row.ripeness, row.cleanliness].filter(Boolean).join(' · ') || '-';
}
</script>

<template>
    <Head title="Transaksi Panen" />
    <div class="space-y-[18px]" :aria-busy="filters.processing">
        <section
            class="grid grid-cols-2 gap-3 lg:grid-cols-5"
            aria-label="Ringkasan transaksi panen"
        >
            <article
                v-for="item in summaryCards"
                :key="item.label"
                class="admin-panel"
            >
                <div class="flex items-start justify-between gap-2">
                    <h2 class="text-sm font-semibold">{{ item.label }}</h2>
                    <component
                        :is="item.icon"
                        class="hidden size-5 shrink-0 text-primary sm:block"
                        aria-hidden="true"
                    />
                </div>
                <p class="admin-metric-value mt-3">{{ item.value }}</p>
                <p class="mt-1 text-xs text-muted-foreground">{{ item.hint }}</p>
            </article>
        </section>

        <section class="admin-card overflow-hidden rounded-xl bg-card">
            <div class="border-b border-border p-4 md:p-5">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <h2 class="admin-section-title">Daftar transaksi</h2>
                        <span
                            class="rounded-full bg-accent px-2 py-0.5 text-xs font-medium text-accent-foreground"
                        >
                            {{ transactions.total }}
                        </span>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Tanggal mengikuti waktu kerja WIB
                    </p>
                </div>

                <form
                    class="grid items-start gap-3 sm:grid-cols-2 xl:grid-cols-4"
                    @submit.prevent="apply"
                >
                    <FarmField
                        id="harvest-transaction-search"
                        label="Cari"
                        :error="filters.errors.search"
                        class="sm:col-span-2"
                        v-slot="field"
                    >
                        <div class="relative">
                            <Search
                                class="pointer-events-none absolute top-3 left-3 size-4 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <input
                                :id="field.id"
                                v-model="filters.search"
                                class="admin-field pl-9!"
                                type="search"
                                maxlength="100"
                                placeholder="Kebun, blok, varietas, kualitas, atau petugas"
                                :aria-invalid="field.invalid"
                                :aria-describedby="field.description"
                            />
                        </div>
                    </FarmField>

                    <FarmField
                        id="harvest-transaction-farm"
                        label="Kebun"
                        :error="filters.errors.farm_id"
                        v-slot="field"
                    >
                        <select
                            :id="field.id"
                            v-model="filters.farm_id"
                            class="admin-field"
                            :aria-invalid="field.invalid"
                            :aria-describedby="field.description"
                            @change="changeFarm"
                        >
                            <option value="">Semua kebun</option>
                            <option v-for="farm in farms" :key="farm.id" :value="farm.id">
                                {{ farm.name }}
                            </option>
                        </select>
                    </FarmField>

                    <FarmField
                        id="harvest-transaction-block"
                        label="Blok"
                        :error="filters.errors.block_id"
                        v-slot="field"
                    >
                        <select
                            :id="field.id"
                            v-model="filters.block_id"
                            class="admin-field"
                            :aria-invalid="field.invalid"
                            :aria-describedby="field.description"
                        >
                            <option value="">Semua blok</option>
                            <option v-for="block in blockOptions" :key="block.id" :value="block.id">
                                {{ block.code }} · {{ block.name }}
                                <template v-if="!filters.farm_id && block.farm">
                                    · {{ block.farm.name }}
                                </template>
                            </option>
                        </select>
                    </FarmField>

                    <FarmField
                        id="harvest-transaction-variety"
                        label="Varietas"
                        :error="filters.errors.variety_id"
                        v-slot="field"
                    >
                        <select
                            :id="field.id"
                            v-model="filters.variety_id"
                            class="admin-field"
                            :aria-invalid="field.invalid"
                            :aria-describedby="field.description"
                        >
                            <option value="">Semua varietas</option>
                            <option v-for="variety in varieties" :key="variety.id" :value="variety.id">
                                {{ varietyLabel(variety) }}
                            </option>
                        </select>
                    </FarmField>

                    <FarmField
                        id="harvest-transaction-from"
                        label="Dari tanggal"
                        :error="filters.errors.from_date"
                        v-slot="field"
                    >
                        <input
                            :id="field.id"
                            v-model="filters.from_date"
                            type="date"
                            class="admin-field"
                            :aria-invalid="field.invalid"
                            :aria-describedby="field.description"
                        />
                    </FarmField>

                    <FarmField
                        id="harvest-transaction-to"
                        label="Sampai tanggal"
                        :error="filters.errors.to_date"
                        v-slot="field"
                    >
                        <input
                            :id="field.id"
                            v-model="filters.to_date"
                            type="date"
                            class="admin-field"
                            :aria-invalid="field.invalid"
                            :aria-describedby="field.description"
                        />
                    </FarmField>

                    <div class="flex flex-wrap items-center gap-2 pt-0 sm:pt-7">
                        <Button
                            type="submit"
                            class="h-10 rounded-xl"
                            :disabled="filters.processing"
                        >
                            <LoaderCircle
                                v-if="filters.processing"
                                class="animate-spin motion-reduce:animate-none"
                            />
                            <Search v-else />Terapkan
                        </Button>
                        <Button
                            v-if="hasFilters"
                            type="button"
                            variant="ghost"
                            :disabled="filters.processing"
                            @click="reset"
                        >
                            Reset filter
                        </Button>
                    </div>
                </form>
            </div>

            <div
                v-if="filters.processing"
                class="space-y-4 p-6"
                role="status"
                aria-label="Memuat transaksi panen"
            >
                <div
                    v-for="row in 3"
                    :key="row"
                    class="h-12 animate-pulse rounded-xl bg-muted"
                />
                <p class="text-sm text-muted-foreground">
                    Memuat transaksi panen.
                </p>
            </div>

            <div v-else-if="!transactions.data.length" class="px-5 py-16 text-center">
                <div
                    class="mx-auto flex size-14 items-center justify-center rounded-xl bg-accent text-primary"
                >
                    <Wheat class="size-7" />
                </div>
                <h3 class="mt-4 font-semibold">
                    {{ hasFilters ? 'Transaksi tidak ditemukan' : 'Belum ada transaksi panen' }}
                </h3>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-muted-foreground">
                    {{
                        hasFilters
                            ? 'Coba kata kunci, tanggal, atau filter lain.'
                            : 'Transaksi akan muncul setelah Petugas mencatat realisasi panen.'
                    }}
                </p>
                <Button
                    v-if="hasFilters"
                    variant="outline"
                    class="mt-5 rounded-xl"
                    @click="reset"
                >
                    Reset filter
                </Button>
            </div>

            <template v-else>
                <div
                    class="hidden overflow-x-auto md:block"
                    role="region"
                    aria-label="Tabel transaksi panen"
                    tabindex="0"
                >
                    <table class="w-full min-w-[1100px] text-left text-sm">
                        <thead class="bg-muted/50 text-xs text-muted-foreground">
                            <tr>
                                <th scope="col" class="px-5 py-3 font-semibold">Tanggal</th>
                                <th scope="col" class="px-5 py-3 font-semibold">Lokasi</th>
                                <th scope="col" class="px-5 py-3 font-semibold">Varietas</th>
                                <th scope="col" class="px-5 py-3 font-semibold">Luas &amp; berat</th>
                                <th scope="col" class="px-5 py-3 font-semibold">Kualitas</th>
                                <th scope="col" class="px-5 py-3 font-semibold">Pelaksana</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">Detail</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="row in transactions.data"
                                :key="row.id"
                                class="align-top hover:bg-muted/40"
                            >
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <p class="font-medium">{{ formatDate(row.activity_date) }}</p>
                                    <span
                                        class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium"
                                        :class="
                                            row.source === 'Tugas SOP'
                                                ? 'bg-primary/10 text-primary'
                                                : 'bg-accent text-accent-foreground'
                                        "
                                    >
                                        {{ row.source }}
                                    </span>
                                    <p v-if="row.log_url" class="mt-2 text-xs">
                                        <Link
                                            :href="row.log_url"
                                            class="inline-flex items-center gap-1 text-primary underline underline-offset-2"
                                        >
                                            Logbook <ArrowUpRight class="size-3" />
                                        </Link>
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="font-medium">{{ row.farm?.name ?? '-' }}</p>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        {{ row.block?.code ?? '-' }} · {{ row.block?.name ?? 'Blok belum diisi' }}
                                    </p>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        {{ rowSubblock(row) }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="font-medium">{{ row.variety?.name ?? '-' }}</p>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        {{ row.commodity?.name ?? 'Komoditas belum diisi' }}
                                    </p>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <p class="font-medium">{{ formatDecimal(row.harvested_area_ha, 4) }} ha</p>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        {{ formatDecimal(row.gross_weight_ton, 3) }} ton
                                    </p>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        Tanam {{ formatDecimal(row.cycle?.planted_area_ha ?? null, 4) }} ha
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="font-medium">
                                        {{ row.moisture_percentage === null ? '-' : formatDecimal(row.moisture_percentage, 2) + '%' }}
                                    </p>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        {{ qualityLabel(row) }}
                                    </p>
                                    <p v-if="row.color" class="mt-1 text-xs text-muted-foreground">
                                        {{ row.color }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="font-medium">{{ row.performer_name ?? '-' }}</p>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        {{ row.worker_count === null ? '-' : row.worker_count + ' pekerja' }}
                                    </p>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex justify-end gap-1">
                                        <a
                                            v-for="photo in row.photos"
                                            :key="photo.id"
                                            :href="photo.url"
                                            target="_blank"
                                            rel="noopener"
                                            class="overflow-hidden rounded-md border border-border"
                                            :aria-label="photo.caption ?? photo.label ?? 'Foto panen'"
                                        >
                                            <img
                                                :src="photo.url"
                                                :alt="photo.caption ?? photo.label ?? 'Foto panen'"
                                                class="size-10 object-cover"
                                                loading="lazy"
                                            />
                                        </a>
                                    </div>
                                    <p v-if="!row.photos.length" class="text-xs text-muted-foreground">
                                        Tanpa foto
                                    </p>
                                    <p
                                        v-if="row.notes"
                                        class="mt-2 max-w-52 text-xs leading-5 text-muted-foreground md:ml-auto"
                                        :title="row.notes"
                                    >
                                        {{ row.notes }}
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="divide-y divide-border md:hidden">
                    <article
                        v-for="row in transactions.data"
                        :key="row.id"
                        class="space-y-3 p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold">{{ formatDate(row.activity_date) }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ row.farm?.name ?? '-' }} · {{ row.block?.code ?? '-' }}
                                </p>
                            </div>
                            <span
                                class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium"
                                :class="
                                    row.source === 'Tugas SOP'
                                        ? 'bg-primary/10 text-primary'
                                        : 'bg-accent text-accent-foreground'
                                "
                            >
                                {{ row.source }}
                            </span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div>
                                <p class="text-xs text-muted-foreground">Varietas</p>
                                <p class="font-medium">{{ row.variety?.name ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Luas panen</p>
                                <p class="font-medium">{{ formatDecimal(row.harvested_area_ha, 4) }} ha</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Berat</p>
                                <p class="font-medium">{{ formatDecimal(row.gross_weight_ton, 3) }} ton</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Kadar air</p>
                                <p class="font-medium">
                                    {{ row.moisture_percentage === null ? '-' : formatDecimal(row.moisture_percentage, 2) + '%' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Pekerja</p>
                                <p class="font-medium">{{ row.worker_count ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Pelaksana</p>
                                <p class="font-medium">{{ row.performer_name ?? '-' }}</p>
                            </div>
                        </div>
                        <p class="text-xs text-muted-foreground">{{ rowSubblock(row) }}</p>
                        <p class="text-xs text-muted-foreground">{{ qualityLabel(row) }}</p>
                        <p v-if="row.notes" class="text-xs leading-5 text-muted-foreground">
                            {{ row.notes }}
                        </p>
                        <div class="flex flex-wrap items-center gap-3">
                            <Link
                                v-if="row.log_url"
                                :href="row.log_url"
                                class="inline-flex items-center gap-1 text-xs font-medium text-primary"
                            >
                                Buka logbook <ArrowUpRight class="size-3" />
                            </Link>
                            <a
                                v-for="photo in row.photos"
                                :key="photo.id"
                                :href="photo.url"
                                target="_blank"
                                rel="noopener"
                                class="overflow-hidden rounded-md border border-border"
                                :aria-label="photo.caption ?? photo.label ?? 'Foto panen'"
                            >
                                <img
                                    :src="photo.url"
                                    :alt="photo.caption ?? photo.label ?? 'Foto panen'"
                                    class="size-10 object-cover"
                                    loading="lazy"
                                />
                            </a>
                        </div>
                    </article>
                </div>

                <LogbookPagination
                    v-model:size="filters.per_page"
                    :page="transactions"
                    :loading="filters.processing"
                    label="transaksi"
                    @change="apply"
                />
            </template>
        </section>
    </div>
</template>
