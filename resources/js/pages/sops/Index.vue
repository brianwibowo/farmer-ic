<script setup lang="ts">
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowDownWideNarrow,
    ArrowUpRight,
    ArrowUpWideNarrow,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    ClipboardList,
    LoaderCircle,
    Pencil,
    Plus,
    Search,
    SlidersHorizontal,
    Sprout,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import FarmField from '@/components/farms/FarmField.vue';
import DeleteSopDialog from '@/components/sops/DeleteSopDialog.vue';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type {
    SopCommodity,
    SopFilters,
    SopPage,
    SopSummary,
    SopTeam,
    SopTemplateRow,
} from '@/types/sop';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    sops: SopPage;
    summary?: SopSummary;
    filters: SopFilters;
    commodities: SopCommodity[];
    team: SopTeam;
    can: { create: boolean };
}>();

const selectedSop = ref<SopTemplateRow | null>(null);
const showFilters = ref(false);
const createUrl = '/admin/sops/create';

const form = useForm({
    search: props.filters.search ?? '',
    commodity_id: props.filters.commodity_id ?? '',
    is_active:
        props.filters.is_active !== undefined &&
        props.filters.is_active !== null &&
        props.filters.is_active !== ''
            ? String(props.filters.is_active)
            : '',
    sort: props.filters.sort ?? 'name',
    direction: props.filters.direction ?? 'asc',
    per_page: Number(props.filters.per_page ?? 15),
});

const hasFilters = computed(
    () =>
        !!(
            form.search ||
            form.commodity_id ||
            form.is_active !== '' ||
            form.sort !== 'name' ||
            form.direction !== 'asc' ||
            form.per_page !== 15
        ),
);

const activeExtraFilterCount = computed(
    () =>
        [
            props.filters.commodity_id,
            props.filters.is_active !== undefined &&
            props.filters.is_active !== null &&
            props.filters.is_active !== ''
                ? String(props.filters.is_active)
                : '',
        ].filter(Boolean).length,
);

const hasSearchCriteria = computed(
    () =>
        !!(
            props.filters.search ||
            props.filters.commodity_id ||
            (props.filters.is_active !== undefined &&
                props.filters.is_active !== null &&
                props.filters.is_active !== '')
        ),
);

const activeChips = computed(() => {
    const chips: {
        key: 'commodity_id' | 'is_active';
        label: string;
        value: string;
    }[] = [];

    if (props.filters.commodity_id) {
        const commodityName =
            props.commodities.find(
                (item) => item.id === props.filters.commodity_id,
            )?.name ?? props.filters.commodity_id;
        chips.push({
            key: 'commodity_id',
            label: 'Komoditas',
            value: commodityName,
        });
    }

    if (
        props.filters.is_active !== undefined &&
        props.filters.is_active !== null &&
        props.filters.is_active !== ''
    ) {
        const isActive =
            String(props.filters.is_active) === '1' ||
            String(props.filters.is_active) === 'true';
        chips.push({
            key: 'is_active',
            label: 'Status',
            value: isActive ? 'Aktif' : 'Nonaktif',
        });
    }

    return chips;
});

const directionDescription = computed(() => {
    const ascending = form.direction === 'asc';
    const labels =
        form.sort === 'created_at'
            ? ['Terlama ke terbaru', 'Terbaru ke terlama']
            : ['A-Z', 'Z-A'];
    return `Urutan ${labels[ascending ? 0 : 1]}. Ubah menjadi ${labels[ascending ? 1 : 0]}.`;
});

const summaryCards = computed(() => [
    {
        label: 'Total template',
        value: props.summary?.total ?? props.sops.total,
        unit: 'template',
        icon: ClipboardList,
    },
    {
        label: 'Template aktif',
        value:
            props.summary?.active ??
            props.sops.data.filter((item) => item.is_active).length,
        unit: 'aktif',
        icon: CheckCircle2,
    },
    {
        label: 'Komoditas terdaftar',
        value: props.commodities.length,
        unit: 'komoditas',
        icon: Sprout,
    },
]);

function apply(): void {
    form.get('/admin/sops', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onError: () => {
            if (form.errors.commodity_id || form.errors.is_active) {
                showFilters.value = true;
            }
        },
    });
}

function toggleDirection(): void {
    form.direction = form.direction === 'asc' ? 'desc' : 'asc';
    apply();
}

function removeFilter(key: 'commodity_id' | 'is_active'): void {
    form[key] = '';
    apply();
}

function reset(): void {
    form.search = '';
    form.commodity_id = '';
    form.is_active = '';
    form.sort = 'name';
    form.direction = 'asc';
    form.per_page = 15;
    apply();
}
</script>

<template>
    <Head title="SOP" />
    <div class="space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="admin-page-title tracking-tight">
                    Kelola template SOP
                </h1>
                <p
                    class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground"
                >
                    Susun langkah budidaya modular per komoditas, lalu tetapkan
                    SOP default pada kebun sebagai jadwal HST otomatis.
                </p>
            </div>
            <Button v-if="can.create" as-child class="h-10 rounded-xl">
                <Link :href="createUrl"><Plus /> Tambah SOP</Link>
            </Button>
        </header>

        <div class="grid grid-cols-3 gap-2 sm:gap-4">
            <div
                v-for="item in summaryCards"
                :key="item.label"
                class="min-w-0 rounded-xl border border-border bg-card p-3 sm:p-5"
            >
                <div class="flex items-center justify-between gap-3">
                    <p
                        class="min-h-10 text-xs leading-5 text-muted-foreground sm:min-h-0 sm:text-sm"
                    >
                        {{ item.label }}
                    </p>
                    <component
                        :is="item.icon"
                        class="hidden size-5 shrink-0 text-primary sm:block"
                        aria-hidden="true"
                    />
                </div>
                <p
                    class="admin-metric-value mt-2 text-xl! tracking-tight sm:mt-4 sm:text-3xl!"
                >
                    {{ item.value }}
                    <span
                        class="ml-1 text-xs font-medium text-muted-foreground sm:ml-2 sm:text-sm"
                        >{{ item.unit }}</span
                    >
                </p>
            </div>
        </div>

        <section
            class="overflow-hidden rounded-xl border border-border bg-card"
        >
            <div class="border-b border-border p-4 md:p-5">
                <div class="mb-4 flex items-center gap-2">
                    <h2 class="admin-section-title">Daftar template</h2>
                    <span
                        class="rounded-full bg-accent px-2 py-0.5 text-xs font-medium text-accent-foreground"
                    >
                        {{ sops.total }}
                    </span>
                </div>

                <form @submit.prevent="apply">
                    <div
                        class="grid grid-cols-[auto_minmax(0,1fr)] items-end gap-3 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,15rem)]"
                    >
                        <FarmField
                            id="sop-search"
                            label="Cari SOP"
                            class="col-span-2 sm:col-span-1"
                            :error="form.errors.search"
                            v-slot="field"
                        >
                            <div class="flex gap-2">
                                <div class="relative min-w-0 flex-1">
                                    <Search
                                        class="pointer-events-none absolute top-3 left-3 size-4 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <input
                                        :id="field.id"
                                        v-model="form.search"
                                        class="admin-field pl-9!"
                                        type="search"
                                        maxlength="100"
                                        placeholder="Nama SOP atau komoditas"
                                        :disabled="form.processing"
                                        :aria-invalid="field.invalid"
                                        :aria-describedby="field.description"
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    variant="outline"
                                    class="size-10 shrink-0 pointer-coarse:min-h-11 pointer-coarse:min-w-11"
                                    :disabled="form.processing"
                                    :aria-label="
                                        form.processing
                                            ? 'Mencari SOP'
                                            : 'Cari SOP'
                                    "
                                    title="Cari SOP"
                                >
                                    <LoaderCircle
                                        v-if="form.processing"
                                        class="animate-spin"
                                        aria-hidden="true"
                                    />
                                    <Search v-else aria-hidden="true" />
                                </Button>
                            </div>
                        </FarmField>

                        <Button
                            type="button"
                            :variant="
                                activeExtraFilterCount ? 'secondary' : 'outline'
                            "
                            class="h-10 pointer-coarse:min-h-11"
                            :disabled="form.processing"
                            :aria-expanded="showFilters"
                            aria-controls="sop-extra-filters"
                            @click="showFilters = !showFilters"
                        >
                            <SlidersHorizontal aria-hidden="true" />Filter
                            <span
                                v-if="activeExtraFilterCount"
                                class="rounded-full bg-accent px-1.5 text-xs text-accent-foreground"
                                :aria-label="
                                    activeExtraFilterCount + ' filter aktif'
                                "
                            >
                                {{ activeExtraFilterCount }}
                            </span>
                        </Button>

                        <FarmField
                            id="sop-sort"
                            label="Urutkan"
                            :error="form.errors.sort || form.errors.direction"
                            v-slot="field"
                        >
                            <div class="flex gap-2">
                                <AppSelect
                                    :id="field.id"
                                    v-model="form.sort"
                                    class="admin-field min-w-0 flex-1"
                                    :disabled="form.processing"
                                    :aria-invalid="field.invalid"
                                    :aria-describedby="field.description"
                                    @change="apply"
                                >
                                    <AppSelectOption value="name">
                                        Nama template
                                    </AppSelectOption>
                                    <AppSelectOption value="created_at">
                                        Waktu dibuat
                                    </AppSelectOption>
                                </AppSelect>
                                <TooltipProvider :delay-duration="150">
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                class="size-10 shrink-0 pointer-coarse:min-h-11 pointer-coarse:min-w-11"
                                                :disabled="form.processing"
                                                :aria-label="
                                                    directionDescription
                                                "
                                                @click="toggleDirection"
                                            >
                                                <ArrowUpWideNarrow
                                                    v-if="
                                                        form.direction === 'asc'
                                                    "
                                                    aria-hidden="true"
                                                />
                                                <ArrowDownWideNarrow
                                                    v-else
                                                    aria-hidden="true"
                                                />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent
                                            class="admin-dashboard-theme max-w-[calc(100vw-2rem)]"
                                            :collision-padding="16"
                                        >
                                            {{ directionDescription }}
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </div>
                        </FarmField>
                    </div>

                    <div
                        v-show="showFilters"
                        id="sop-extra-filters"
                        class="mt-4 space-y-3 border-t border-border pt-4"
                    >
                        <div class="grid gap-3 sm:grid-cols-2">
                            <FarmField
                                id="sop-commodity"
                                label="Komoditas"
                                :error="form.errors.commodity_id"
                                v-slot="field"
                            >
                                <AppSelect
                                    :id="field.id"
                                    v-model="form.commodity_id"
                                    class="admin-field"
                                    :disabled="form.processing"
                                    :aria-invalid="field.invalid"
                                    :aria-describedby="field.description"
                                >
                                    <AppSelectOption value="">
                                        Semua komoditas
                                    </AppSelectOption>
                                    <AppSelectOption
                                        v-for="commodity in commodities"
                                        :key="commodity.id"
                                        :value="commodity.id"
                                    >
                                        {{ commodity.name }}
                                    </AppSelectOption>
                                </AppSelect>
                            </FarmField>

                            <FarmField
                                id="sop-active"
                                label="Status"
                                :error="form.errors.is_active"
                                v-slot="field"
                            >
                                <AppSelect
                                    :id="field.id"
                                    v-model="form.is_active"
                                    class="admin-field"
                                    :disabled="form.processing"
                                    :aria-invalid="field.invalid"
                                    :aria-describedby="field.description"
                                >
                                    <AppSelectOption value="">
                                        Semua status
                                    </AppSelectOption>
                                    <AppSelectOption value="1">
                                        Aktif
                                    </AppSelectOption>
                                    <AppSelectOption value="0">
                                        Nonaktif
                                    </AppSelectOption>
                                </AppSelect>
                            </FarmField>
                        </div>

                        <div class="flex justify-end">
                            <Button
                                type="submit"
                                class="h-10"
                                :disabled="form.processing"
                            >
                                <LoaderCircle
                                    v-if="form.processing"
                                    class="animate-spin"
                                    aria-hidden="true"
                                />
                                Terapkan filter
                            </Button>
                        </div>
                    </div>
                </form>

                <div
                    v-if="hasFilters || activeChips.length"
                    class="mt-3 flex flex-wrap items-center gap-2"
                >
                    <Button
                        v-for="item in activeChips"
                        :key="item.key"
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-auto min-h-8 max-w-full text-left whitespace-normal"
                        :disabled="form.processing"
                        :aria-label="
                            'Hapus filter ' +
                            item.label.toLowerCase() +
                            ' ' +
                            item.value
                        "
                        @click="removeFilter(item.key)"
                    >
                        <span class="min-w-0 break-words">
                            {{ item.label }}: {{ item.value }}
                        </span>
                        <X class="size-3 shrink-0" aria-hidden="true" />
                    </Button>
                    <Button
                        v-if="hasFilters"
                        type="button"
                        variant="ghost"
                        size="sm"
                        :disabled="form.processing"
                        @click="reset"
                    >
                        Reset filter
                    </Button>
                </div>
            </div>

            <div
                v-if="form.processing"
                class="space-y-4 p-6"
                role="status"
                aria-label="Memuat daftar SOP"
            >
                <div
                    v-for="row in 3"
                    :key="row"
                    class="h-12 animate-pulse rounded-xl bg-muted"
                />
                <p class="text-sm text-muted-foreground">Memuat SOP...</p>
            </div>
            <div v-else-if="!sops.data.length" class="px-5 py-16 text-center">
                <div
                    class="mx-auto flex size-14 items-center justify-center rounded-xl bg-accent text-primary"
                >
                    <ClipboardList class="size-7" />
                </div>
                <h3 class="mt-4 font-semibold">
                    {{
                        hasSearchCriteria
                            ? 'SOP tidak ditemukan'
                            : 'Belum ada SOP'
                    }}
                </h3>
                <p
                    class="mx-auto mt-2 max-w-md text-sm leading-6 text-muted-foreground"
                >
                    {{
                        hasSearchCriteria
                            ? 'Coba kata kunci atau komoditas lain untuk menampilkan template SOP.'
                            : 'Buat template SOP pertama untuk komoditas yang ditanam di kebun.'
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
                <Button v-else-if="can.create" as-child class="mt-5 rounded-xl">
                    <Link :href="createUrl"><Plus />Tambah SOP pertama</Link>
                </Button>
            </div>

            <template v-else>
                <div
                    class="hidden overflow-x-auto md:block"
                    role="region"
                    aria-label="Tabel template SOP"
                    tabindex="0"
                >
                    <table class="w-full text-left text-sm">
                        <thead
                            class="bg-muted/50 text-xs text-muted-foreground"
                        >
                            <tr>
                                <th scope="col" class="px-5 py-3 font-semibold">
                                    Template
                                </th>
                                <th scope="col" class="px-5 py-3 font-semibold">
                                    Komoditas
                                </th>
                                <th scope="col" class="px-5 py-3 font-semibold">
                                    Versi
                                </th>
                                <th scope="col" class="px-5 py-3 font-semibold">
                                    Status
                                </th>
                                <th
                                    scope="col"
                                    class="px-5 py-3 text-right font-semibold"
                                >
                                    Tindakan
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="sop in sops.data"
                                :key="sop.id"
                                class="hover:bg-muted/40"
                            >
                                <td class="px-5 py-4">
                                    <Link
                                        :href="`/admin/sops/${sop.id}`"
                                        class="font-semibold hover:text-primary focus-visible:outline-2 focus-visible:outline-ring"
                                    >
                                        {{ sop.name }}
                                    </Link>
                                    <p
                                        class="mt-1 line-clamp-1 text-xs text-muted-foreground"
                                    >
                                        {{
                                            sop.description ||
                                            'Belum ada deskripsi.'
                                        }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    <p>{{ sop.commodity?.name ?? '-' }}</p>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        SOP per komoditas
                                    </p>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <span
                                        class="inline-flex rounded-full bg-accent px-2.5 py-1 text-xs font-medium text-accent-foreground"
                                    >
                                        {{ sop.versions_count }} versi
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="
                                            sop.is_active
                                                ? 'bg-success/10 text-success'
                                                : 'bg-muted text-muted-foreground'
                                        "
                                    >
                                        {{
                                            sop.is_active ? 'Aktif' : 'Nonaktif'
                                        }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-1">
                                        <Button
                                            as-child
                                            variant="ghost"
                                            size="icon"
                                            :aria-label="`Detail ${sop.name}`"
                                        >
                                            <Link
                                                :href="`/admin/sops/${sop.id}`"
                                            >
                                                <ArrowUpRight />
                                            </Link>
                                        </Button>
                                        <Button
                                            v-if="sop.can.update"
                                            as-child
                                            variant="ghost"
                                            size="icon"
                                            :aria-label="`Ubah ${sop.name}`"
                                        >
                                            <Link
                                                :href="`/admin/sops/${sop.id}/edit`"
                                            >
                                                <Pencil />
                                            </Link>
                                        </Button>
                                        <Button
                                            v-if="sop.can.delete"
                                            variant="destructive-ghost"
                                            size="icon"
                                            :aria-label="`Hapus ${sop.name}`"
                                            @click="selectedSop = sop"
                                        >
                                            <Trash2 />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <ul
                    class="divide-y divide-border md:hidden"
                    aria-label="Daftar SOP"
                >
                    <li v-for="sop in sops.data" :key="sop.id" class="p-4">
                        <Link
                            :href="`/admin/sops/${sop.id}`"
                            class="flex items-start justify-between gap-3 font-semibold"
                        >
                            <span>{{ sop.name }}</span>
                            <ArrowUpRight
                                class="size-4 shrink-0 text-primary"
                            />
                        </Link>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span
                                class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="
                                    sop.is_active
                                        ? 'bg-success/10 text-success'
                                        : 'bg-muted text-muted-foreground'
                                "
                            >
                                {{ sop.is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                            <span class="text-xs text-muted-foreground">
                                {{
                                    sop.commodity?.name ??
                                    'Komoditas belum diisi'
                                }}
                            </span>
                        </div>
                        <p
                            v-if="sop.description"
                            class="mt-2 line-clamp-2 text-xs text-muted-foreground"
                        >
                            {{ sop.description }}
                        </p>
                        <div
                            class="mt-3 flex flex-wrap items-center justify-between gap-2"
                        >
                            <span
                                class="rounded-full bg-accent px-2.5 py-1 text-xs font-medium text-accent-foreground"
                            >
                                {{ sop.versions_count }} versi
                            </span>
                            <div class="flex flex-wrap gap-2">
                                <Button
                                    v-if="sop.can.update"
                                    as-child
                                    variant="outline"
                                    size="sm"
                                >
                                    <Link :href="`/admin/sops/${sop.id}/edit`">
                                        <Pencil />Ubah
                                    </Link>
                                </Button>
                                <Button
                                    v-if="sop.can.delete"
                                    variant="destructive-ghost"
                                    size="sm"
                                    @click="selectedSop = sop"
                                >
                                    <Trash2 />Hapus
                                </Button>
                            </div>
                        </div>
                    </li>
                </ul>

                <nav
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-4"
                    aria-label="Pagination SOP"
                >
                    <p class="text-xs text-muted-foreground">
                        Menampilkan {{ sops.from ?? 0 }}-{{ sops.to ?? 0 }} dari
                        {{ sops.total }} template
                    </p>
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2">
                            <label
                                for="sop-per-page"
                                class="flex items-center gap-2 text-xs text-muted-foreground"
                            >
                                Per halaman
                                <AppSelect
                                    id="sop-per-page"
                                    v-model.number="form.per_page"
                                    class="admin-field w-auto!"
                                    :disabled="form.processing"
                                    :aria-invalid="!!form.errors.per_page"
                                    :aria-describedby="
                                        form.errors.per_page
                                            ? 'sop-per-page-error'
                                            : undefined
                                    "
                                    @change="apply"
                                >
                                    <AppSelectOption :value="15">
                                        15
                                    </AppSelectOption>
                                    <AppSelectOption :value="30">
                                        30
                                    </AppSelectOption>
                                    <AppSelectOption :value="50">
                                        50
                                    </AppSelectOption>
                                </AppSelect>
                            </label>
                            <p
                                v-if="form.errors.per_page"
                                id="sop-per-page-error"
                                role="alert"
                                class="text-xs text-error"
                            >
                                {{ form.errors.per_page }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Button
                                v-if="sops.prev_page_url"
                                as-child
                                variant="outline"
                                size="sm"
                            >
                                <Link :href="sops.prev_page_url" preserve-scroll>
                                    <ChevronLeft />Sebelumnya
                                </Link>
                            </Button>
                            <span aria-current="page" class="px-2 text-xs">
                                {{ sops.current_page }} / {{ sops.last_page }}
                            </span>
                            <Button
                                v-if="sops.next_page_url"
                                as-child
                                variant="outline"
                                size="sm"
                            >
                                <Link :href="sops.next_page_url" preserve-scroll>
                                    Berikutnya<ChevronRight />
                                </Link>
                            </Button>
                        </div>
                    </div>
                </nav>
            </template>
        </section>
    </div>
    <DeleteSopDialog :sop="selectedSop" @close="selectedSop = null" />
</template>
