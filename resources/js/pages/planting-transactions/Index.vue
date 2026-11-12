<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ChevronDown,
    ClipboardList,
    LoaderCircle,
    Ruler,
    Search,
    Sprout,
    Wheat,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import FarmField from '@/components/farms/FarmField.vue';
import LogbookPagination from '@/components/logbook/LogbookPagination.vue';
import PlantingReportList from '@/components/planting/PlantingReportList.vue';
import { Button } from '@/components/ui/button';
import {
    type PlantingTransactionBlockOption,
    type PlantingTransactionFarmOption,
    type PlantingTransactionFilters,
    type PlantingTransactionPage,
    type PlantingTransactionRow,
    type PlantingTransactionSummary,
    type PlantingTransactionVarietyOption,
} from '@/types/planting-transactions';

const props = defineProps<{
    transactions: PlantingTransactionPage;
    summary: PlantingTransactionSummary;
    filters: PlantingTransactionFilters;
    farms: PlantingTransactionFarmOption[];
    blocks: PlantingTransactionBlockOption[];
    varieties: PlantingTransactionVarietyOption[];
}>();

const initialBlock = props.blocks.find(
    (block) => block.id === props.filters.block_id,
);
const filters = useForm({
    search: props.filters.search ?? '',
    farm_id: props.filters.farm_id ?? initialBlock?.farm_id ?? '',
    block_id: props.filters.block_id ?? '',
    variety_id: props.filters.variety_id ?? '',
    from_date: props.filters.from_date ?? '',
    to_date: props.filters.to_date ?? '',
    per_page: Number(props.filters.per_page ?? 15),
});

const blockOptions = computed(() =>
    filters.farm_id
        ? props.blocks.filter((block) => block.farm_id === filters.farm_id)
        : [],
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
        label: 'Subblok tertanam',
        value: String(props.summary.subblocks),
        hint: 'Subblok dengan realisasi tanam',
        icon: ClipboardList,
    },
    {
        label: 'Luas tanam',
        value: `${formatDecimal(props.summary.area_ha)} ha`,
        hint: 'Akumulasi luas laporan',
        icon: Ruler,
    },
    {
        label: 'Benih',
        value: `${formatDecimal(props.summary.seed_kg)} kg`,
        hint: 'Akumulasi jumlah benih',
        icon: Wheat,
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
    filters.get('/admin/transaksi-penanaman', {
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
    filters.block_id = '';
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

function varietyLabel(variety: PlantingTransactionVarietyOption): string {
    return variety.commodity
        ? `${variety.name} · ${variety.commodity.name}`
        : variety.name;
}

const expandedKeys = ref<string[]>([]);

function isExpanded(key: string): boolean {
    return expandedKeys.value.includes(key);
}

function toggleExpanded(key: string): void {
    expandedKeys.value = isExpanded(key)
        ? expandedKeys.value.filter((item) => item !== key)
        : [...expandedKeys.value, key];
}

function activityDateRange(row: PlantingTransactionRow): string {
    if (!row.first_activity_date) {
        return '-';
    }
    if (
        !row.last_activity_date ||
        row.first_activity_date === row.last_activity_date
    ) {
        return formatDate(row.first_activity_date);
    }

    return `${formatDate(row.first_activity_date)} – ${formatDate(row.last_activity_date)}`;
}

function rowSubblock(row: PlantingTransactionRow): string {
    if (!row.cycle) {
        return 'Subblok belum terhubung';
    }
    if (row.cycle.subblock_label) {
        return `Subblok ${row.cycle.subblock_label}`;
    }
    if (row.cycle.batch_number !== null) {
        return `Batch ${row.cycle.batch_number}`;
    }

    return 'Subblok';
}
</script>

<template>
    <Head title="Transaksi Penanaman" />
    <div class="space-y-[18px]" :aria-busy="filters.processing">
        <section
            class="grid grid-cols-2 gap-3 lg:grid-cols-3"
            aria-label="Ringkasan transaksi penanaman"
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
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ item.hint }}
                </p>
            </article>
        </section>

        <section class="admin-card overflow-hidden rounded-xl bg-card">
            <div class="border-b border-border p-4 md:p-5">
                <div
                    class="mb-4 flex flex-wrap items-center justify-between gap-3"
                >
                    <div class="flex items-center gap-2">
                        <h2 class="admin-section-title">Daftar subblok</h2>
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
                        id="planting-transaction-search"
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
                                placeholder="Kebun, blok, varietas, benih, atau petugas"
                                :aria-invalid="field.invalid"
                                :aria-describedby="field.description"
                            />
                        </div>
                    </FarmField>

                    <FarmField
                        id="planting-transaction-farm"
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
                            <option
                                v-for="farm in farms"
                                :key="farm.id"
                                :value="farm.id"
                            >
                                {{ farm.name }}
                            </option>
                        </select>
                    </FarmField>

                    <FarmField
                        id="planting-transaction-block"
                        label="Blok"
                        :error="filters.errors.block_id"
                        v-slot="field"
                    >
                        <select
                            :id="field.id"
                            v-model="filters.block_id"
                            class="admin-field"
                            :disabled="!filters.farm_id"
                            :aria-invalid="field.invalid"
                            :aria-describedby="field.description"
                        >
                            <option value="">
                                {{
                                    filters.farm_id
                                        ? 'Semua blok'
                                        : 'Pilih kebun terlebih dahulu'
                                }}
                            </option>
                            <option
                                v-for="block in blockOptions"
                                :key="block.id"
                                :value="block.id"
                            >
                                {{ block.code }} · {{ block.name }}
                            </option>
                        </select>
                    </FarmField>

                    <FarmField
                        id="planting-transaction-variety"
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
                            <option
                                v-for="variety in varieties"
                                :key="variety.id"
                                :value="variety.id"
                            >
                                {{ varietyLabel(variety) }}
                            </option>
                        </select>
                    </FarmField>

                    <FarmField
                        id="planting-transaction-from"
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
                        id="planting-transaction-to"
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
                aria-label="Memuat transaksi penanaman"
            >
                <div
                    v-for="row in 3"
                    :key="row"
                    class="h-12 animate-pulse rounded-xl bg-muted"
                />
                <p class="text-sm text-muted-foreground">
                    Memuat transaksi penanaman.
                </p>
            </div>

            <div
                v-else-if="!transactions.data.length"
                class="px-5 py-16 text-center"
            >
                <div
                    class="mx-auto flex size-14 items-center justify-center rounded-xl bg-accent text-primary"
                >
                    <Sprout class="size-7" />
                </div>
                <h3 class="mt-4 font-semibold">
                    {{
                        hasFilters
                            ? 'Subblok tidak ditemukan'
                            : 'Belum ada subblok ditanam'
                    }}
                </h3>
                <p
                    class="mx-auto mt-2 max-w-md text-sm leading-6 text-muted-foreground"
                >
                    {{
                        hasFilters
                            ? 'Coba kata kunci, tanggal, atau filter lain.'
                            : 'Subblok akan muncul setelah Petugas mencatat realisasi penanaman.'
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
                    aria-label="Tabel subblok penanaman"
                    tabindex="0"
                >
                    <table class="w-full table-fixed text-left text-sm">
                        <thead
                            class="bg-muted/50 text-xs text-muted-foreground"
                        >
                            <tr>
                                <th
                                    scope="col"
                                    class="w-[20%] px-4 py-3 font-semibold"
                                >
                                    Tanam
                                </th>
                                <th
                                    scope="col"
                                    class="w-[24%] px-4 py-3 font-semibold"
                                >
                                    Lokasi
                                </th>
                                <th
                                    scope="col"
                                    class="w-[18%] px-4 py-3 font-semibold"
                                >
                                    Varietas
                                </th>
                                <th
                                    scope="col"
                                    class="w-[20%] px-4 py-3 font-semibold"
                                >
                                    Luas &amp; benih
                                </th>
                                <th
                                    scope="col"
                                    class="w-[18%] px-4 py-3 text-right font-semibold"
                                >
                                    Laporan
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <template
                                v-for="row in transactions.data"
                                :key="row.key"
                            >
                                <tr class="align-top hover:bg-muted/40">
                                    <td class="px-4 py-4">
                                        <p class="font-medium">
                                            {{ activityDateRange(row) }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-4">
                                        <p class="font-medium">
                                            {{ row.farm?.name ?? '-' }}
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{ row.block?.code ?? '-' }} ·
                                            {{
                                                row.block?.name ??
                                                'Blok belum diisi'
                                            }}
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{ rowSubblock(row) }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-4">
                                        <p class="font-medium">
                                            {{ row.variety?.name ?? '-' }}
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{
                                                row.commodity?.name ??
                                                'Komoditas belum diisi'
                                            }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-4">
                                        <p class="font-medium">
                                            {{ formatDecimal(row.area_ha) }} ha
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{ formatDecimal(row.seed_kg) }} kg
                                        </p>
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <button
                                            type="button"
                                            class="inline-flex min-h-9 items-center gap-1 rounded-xl px-2 text-xs font-medium text-primary hover:bg-primary/10 focus-visible:outline-2 focus-visible:outline-ring"
                                            :aria-expanded="isExpanded(row.key)"
                                            :aria-controls="`planting-reports-${row.key}`"
                                            @click="toggleExpanded(row.key)"
                                        >
                                            {{ row.report_count }} laporan
                                            <ChevronDown
                                                class="size-4 transition-transform motion-reduce:transition-none"
                                                :class="{
                                                    'rotate-180': isExpanded(
                                                        row.key,
                                                    ),
                                                }"
                                                aria-hidden="true"
                                            />
                                        </button>
                                    </td>
                                </tr>
                                <tr
                                    v-if="isExpanded(row.key)"
                                    :id="`planting-reports-${row.key}`"
                                    class="bg-muted/30"
                                >
                                    <td colspan="5" class="px-4 py-4">
                                        <div class="mb-3">
                                            <h3 class="text-sm font-semibold">
                                                Laporan penanaman
                                            </h3>
                                            <p
                                                class="mt-1 text-xs text-muted-foreground"
                                            >
                                                {{ rowSubblock(row) }}
                                            </p>
                                        </div>
                                        <PlantingReportList
                                            :reports="row.reports"
                                        />
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="divide-y divide-border md:hidden">
                    <article
                        v-for="row in transactions.data"
                        :key="row.key"
                        class="p-4"
                    >
                        <div>
                            <p class="font-semibold">
                                {{ row.farm?.name ?? '-' }} ·
                                {{ row.block?.code ?? '-' }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ rowSubblock(row) }}
                            </p>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Varietas
                                </p>
                                <p class="font-medium">
                                    {{ row.variety?.name ?? '-' }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        row.commodity?.name ??
                                        'Komoditas belum diisi'
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Tanam
                                </p>
                                <p class="font-medium">
                                    {{ activityDateRange(row) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Luas
                                </p>
                                <p class="font-medium">
                                    {{ formatDecimal(row.area_ha) }} ha
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Benih
                                </p>
                                <p class="font-medium">
                                    {{ formatDecimal(row.seed_kg) }} kg
                                </p>
                            </div>
                        </div>
                        <div class="mt-3 flex justify-end">
                            <button
                                type="button"
                                class="inline-flex min-h-11 items-center gap-1 rounded-xl px-2 text-sm font-medium text-primary hover:bg-primary/10 focus-visible:outline-2 focus-visible:outline-ring"
                                :aria-expanded="isExpanded(row.key)"
                                :aria-controls="`planting-reports-mobile-${row.key}`"
                                @click="toggleExpanded(row.key)"
                            >
                                {{ row.report_count }} laporan
                                <ChevronDown
                                    class="size-4 transition-transform motion-reduce:transition-none"
                                    :class="{
                                        'rotate-180': isExpanded(row.key),
                                    }"
                                    aria-hidden="true"
                                />
                            </button>
                        </div>
                        <div
                            v-if="isExpanded(row.key)"
                            :id="`planting-reports-mobile-${row.key}`"
                            class="mt-3"
                        >
                            <PlantingReportList :reports="row.reports" />
                        </div>
                    </article>
                </div>

                <LogbookPagination
                    v-model:size="filters.per_page"
                    :page="transactions"
                    :loading="filters.processing"
                    label="subblok"
                    @change="apply"
                />
            </template>
        </section>
    </div>
</template>
