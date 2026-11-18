import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type {
    TransactionBlockOption,
    TransactionFarmOption,
    TransactionVarietyOption,
} from '@/components/transactions/TransactionFiltersPopover.vue';

export type TransactionFilterValues = {
    search?: string;
    farm_id?: string;
    block_id?: string;
    variety_id?: string;
    from_date?: string;
    to_date?: string;
    per_page?: number;
};

export type TransactionFilterChipKey =
    | 'search'
    | 'farm_id'
    | 'block_id'
    | 'variety_id'
    | 'from_date'
    | 'to_date';

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

function formatFilterDate(value: string): string {
    const [year, month, day] = value.split('-').map(Number);
    if (!year || !month || !day) {
        return value;
    }

    return `${String(day).padStart(2, '0')} ${monthLabels[month - 1] ?? ''} ${year}`;
}

export function useTransactionFilters(options: {
    url: string;
    initialFilters: () => TransactionFilterValues;
    farms: () => TransactionFarmOption[];
    blocks: () => TransactionBlockOption[];
    varieties: () => TransactionVarietyOption[];
}) {
    const initial = options.initialFilters();
    const initialBlock = options
        .blocks()
        .find((block) => block.id === initial.block_id);

    const showFilters = ref(false);

    const filters = useForm({
        search: initial.search ?? '',
        farm_id: initial.farm_id ?? initialBlock?.farm_id ?? '',
        block_id: initial.block_id ?? '',
        variety_id: initial.variety_id ?? '',
        from_date: initial.from_date ?? '',
        to_date: initial.to_date ?? '',
        per_page: Number(initial.per_page ?? 15),
    });

    const appliedFilters = computed(() => {
        const current = options.initialFilters();
        const chips: { key: TransactionFilterChipKey; label: string }[] = [];

        if (current.search) {
            chips.push({
                key: 'search',
                label: `Pencarian: ${current.search}`,
            });
        }

        if (current.farm_id) {
            const farmName =
                options.farms().find((farm) => farm.id === current.farm_id)
                    ?.name ?? 'Tidak tersedia';
            chips.push({ key: 'farm_id', label: `Lahan: ${farmName}` });
        }

        if (current.block_id) {
            const block = options
                .blocks()
                .find((item) => item.id === current.block_id);
            chips.push({
                key: 'block_id',
                label: block
                    ? `Blok: ${block.name}`
                    : 'Blok: Tidak tersedia',
            });
        }

        if (current.variety_id) {
            const variety = options
                .varieties()
                .find((item) => item.id === current.variety_id);
            const label = variety
                ? variety.commodity
                    ? `${variety.name} · ${variety.commodity.name}`
                    : variety.name
                : 'Tidak tersedia';
            chips.push({ key: 'variety_id', label: `Varietas: ${label}` });
        }

        if (current.from_date) {
            chips.push({
                key: 'from_date',
                label: `Dari: ${formatFilterDate(current.from_date)}`,
            });
        }

        if (current.to_date) {
            chips.push({
                key: 'to_date',
                label: `Sampai: ${formatFilterDate(current.to_date)}`,
            });
        }

        return chips;
    });

    const filterCount = computed(
        () =>
            appliedFilters.value.filter((item) => item.key !== 'search').length,
    );

    const hasFilters = computed(() => appliedFilters.value.length > 0);

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
            showFilters.value = true;
            return;
        }

        filters.clearErrors();
        filters.get(options.url, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onSuccess: () => {
                showFilters.value = false;
            },
            onError: (errors) => {
                if (
                    errors.farm_id ||
                    errors.block_id ||
                    errors.variety_id ||
                    errors.from_date ||
                    errors.to_date
                ) {
                    showFilters.value = true;
                }
            },
        });
    }

    function clearFilter(key: TransactionFilterChipKey): void {
        filters[key] = '';
        if (key === 'farm_id') {
            filters.block_id = '';
        }
        apply();
    }

    function reset(): void {
        filters.search = '';
        filters.farm_id = '';
        filters.block_id = '';
        filters.variety_id = '';
        filters.from_date = '';
        filters.to_date = '';
        filters.per_page = 15;
        filters.clearErrors();
        apply();
    }

    return {
        filters,
        showFilters,
        appliedFilters,
        filterCount,
        hasFilters,
        apply,
        clearFilter,
        reset,
    };
}
