import {
    computed,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from 'vue';
import { toast } from '@/lib/toast';
import {
    executiveMoney as money,
    executiveNumber as number,
    executiveCommodities,
    executiveClients,
    executiveLandCategories,
    executiveRows,
    executiveYears,
    executiveCycleForYear,
    executiveSnapshotDate,
    executiveTotals,
    loadExecutiveDemo,
} from '@/services/ownerExecutiveDemoService';
import type {
    ExecutiveDemoData,
    ExecutiveFilters,
} from '@/types/owner-executive';

export function useExecutiveSummary() {
    const data = ref<ExecutiveDemoData>();
    const loading = ref(true);
    const error = ref('');
    const feedback = ref('');
    const filters = reactive<ExecutiveFilters>({
        year: 2026,
        cycle: 1,
        partner: '',
        region: '',
        farm: '',
        commodity: '',
    });
    watch(
        filters,
        () => {
            feedback.value = '';
        },
        { flush: 'sync' },
    );
    let disposed = false;
    async function load(refresh = false) {
        loading.value = true;
        error.value = '';
        feedback.value = '';
        try {
            const result = await loadExecutiveDemo();
            if (disposed) return;
            data.value = result;
            if (!refresh) {
                filters.year = result.defaultYear;
                filters.cycle = result.defaultCycle;
            }
            if (refresh)
                void toast.success(
                    'Data contoh berhasil dimuat ulang. Filter tetap diterapkan.',
                );
        } catch {
            if (!disposed) {
                const message =
                    'Data contoh belum berhasil dimuat. Periksa koneksi lalu coba lagi.';
                error.value = message;
                void toast.error(message);
            }
        } finally {
            if (!disposed) loading.value = false;
        }
    }
    onMounted(() => void load());
    onBeforeUnmount(() => {
        disposed = true;
    });
    const rows = computed(() =>
        data.value ? executiveRows(data.value, filters) : [],
    );
    const totals = computed(() => executiveTotals(rows.value));
    const years = computed(() =>
        data.value ? executiveYears(data.value) : [],
    );
    const cycles = computed(() =>
        [
            ...new Set(
                data.value?.operations
                    .filter((row) => row.year === filters.year)
                    .map((row) => row.cycle),
            ),
        ].sort((a, b) => a - b),
    );
    const regions = computed(() =>
        [...new Set(data.value?.farms.map((farm) => farm.region))].sort(
            (a, b) => a.localeCompare(b, 'id'),
        ),
    );
    watch(cycles, (available) => {
        if (data.value)
            filters.cycle = executiveCycleForYear(filters.cycle, available);
    });
    const farmOptions = computed(
        () =>
            data.value?.farms.filter(
                (farm) =>
                    (!filters.partner || farm.partnerId === filters.partner) &&
                    (!filters.region || farm.region === filters.region) &&
                    (!filters.commodity ||
                        farm.commodityId === filters.commodity),
            ) ?? [],
    );
    watch(farmOptions, (farms) => {
        if (filters.farm && !farms.some((farm) => farm.id === filters.farm))
            filters.farm = '';
    });
    const partnerSummary = computed(() =>
        (data.value?.partners ?? [])
            .map((partner) => {
                const group = rows.value.filter(
                    (row) => row.partner.id === partner.id,
                );
                const summary = executiveTotals(group);
                return {
                    ...partner,
                    area: summary.area,
                    planted: summary.planted,
                    utilization: summary.utilization,
                    farmCount: group.length,
                };
            })
            .filter((partner) => partner.area > 0)
            .sort((a, b) => b.area - a.area),
    );
    const commodities = computed(() => executiveCommodities(rows.value));
    const landCategories = computed(() => executiveLandCategories(rows.value));
    const clients = computed(() =>
        executiveClients(rows.value, data.value?.clients ?? []),
    );
    const mapFarms = computed(() =>
        rows.value.map(({ farm }) => ({
            ...farm,
            location: `${farm.city}, ${farm.region}`,
            area: number(farm.areaHa),
        })),
    );
    function reset() {
        Object.assign(filters, {
            year: data.value?.defaultYear ?? 2026,
            cycle: data.value?.defaultCycle ?? 1,
            partner: '',
            region: '',
            farm: '',
            commodity: '',
        });
        void toast.info('Filter dikembalikan ke pilihan awal.');
    }
    const periodLabel = computed(() =>
        filters.cycle === 'all'
            ? `1 Januari–31 Desember ${filters.year}`
            : `Cycle ${filters.cycle} · ${filters.year}`,
    );
    const snapshotLabel = computed(() => {
        const date =
            data.value && executiveSnapshotDate(data.value, filters.year);
        return date
            ? new Intl.DateTimeFormat('id-ID', {
                  dateStyle: 'medium',
                  timeZone: 'Asia/Jakarta',
              }).format(new Date(date))
            : 'belum tersedia';
    });
    const monthlyLabel = computed(() =>
        new Intl.DateTimeFormat('id-ID', {
            month: 'long',
            year: 'numeric',
        }).format(
            new Date(filters.year, (data.value?.referenceMonth ?? 11) - 1, 1),
        ),
    );
    const updatedLabel = computed(() =>
        data.value
            ? new Intl.DateTimeFormat('id-ID', {
                  dateStyle: 'medium',
                  timeStyle: 'short',
                  timeZone: 'Asia/Jakarta',
              }).format(new Date(data.value.updatedAt)) + ' WIB'
            : '—',
    );
    return {
        data,
        loading,
        error,
        feedback,
        filters,
        rows,
        totals,
        years,
        cycles,
        regions,
        farmOptions,
        partnerSummary,
        landCategories,
        commodities,
        clients,
        mapFarms,
        reset,
        load,
        periodLabel,
        snapshotLabel,
        monthlyLabel,
        updatedLabel,
        number,
        money,
    };
}
