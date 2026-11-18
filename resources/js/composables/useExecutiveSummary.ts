import { router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { toast } from '@/lib/toast';
import {
    executiveMoney as money,
    executiveNumber as number,
    executiveCommodities,
    executiveClients,
    executiveLandCategories,
    executiveTotals,
} from '@/services/ownerExecutiveSummary';
import type { ExecutiveSummaryData } from '@/types/owner-executive';

export function useExecutiveSummary(source: () => ExecutiveSummaryData) {
    const data = computed(source);
    const loading = ref(false);
    const error = ref('');
    const feedback = ref('');
    const filters = reactive({ ...data.value.filters });
    watch(data, (value) => Object.assign(filters, value.filters));
    function load(refresh = false) {
        if (loading.value) return;
        error.value = '';
        feedback.value = '';
        const fail = () => {
            error.value =
                'Data belum berhasil dimuat. Periksa koneksi lalu coba lagi.';
            void toast.error(error.value);
            return false;
        };
        router.get(
            '/ringkasan-eksekutif',
            { ...filters },
            {
                only: ['summary'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => {
                    loading.value = true;
                },
                onSuccess: () => {
                    if (refresh)
                        void toast.success(
                            'Data database berhasil diperbarui. Filter tetap diterapkan.',
                        );
                },
                onError: (errors) => {
                    error.value =
                        Object.values(errors).join(' ') ||
                        'Periksa filter lalu coba lagi.';
                    void toast.error(error.value);
                },
                onHttpException: fail,
                onNetworkError: fail,
                onFinish: () => {
                    loading.value = false;
                },
            },
        );
    }
    const rows = computed(() => data.value.rows);
    const totals = computed(() => executiveTotals(rows.value));
    const years = computed(() => data.value.years);
    const cycles = computed(() => data.value.cycles);
    const regions = computed(() =>
        [
            ...new Set(
                data.value.farms.map((farm) => farm.region).filter(Boolean),
            ),
        ].sort((a, b) => a.localeCompare(b, 'id')),
    );
    const farmOptions = computed(() =>
        data.value.farms.filter(
            (farm) =>
                (!filters.partner || farm.partnerId === filters.partner) &&
                (!filters.region || farm.region === filters.region) &&
                (!filters.commodity ||
                    farm.commodityIds.includes(filters.commodity)),
        ),
    );
    watch(filters, () => {
        if (
            filters.farm &&
            !farmOptions.value.some((farm) => farm.id === filters.farm)
        ) {
            filters.farm = '';
        }
        if (JSON.stringify(filters) !== JSON.stringify(data.value.filters))
            load();
    });
    const partnerSummary = computed(() => {
        const partners = [
            ...new Map(
                rows.value.map((row) => [row.partner.id, row.partner]),
            ).values(),
        ];
        return partners
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
                    farmCount: new Set(group.map((row) => row.farm.id)).size,
                };
            })
            .filter((partner) => partner.area > 0)
            .sort((a, b) => b.area - a.area);
    });
    const commodities = computed(() => executiveCommodities(rows.value));
    const landCategories = computed(() => executiveLandCategories(rows.value));
    const clients = computed(() =>
        executiveClients(rows.value, data.value.clients),
    );
    const mapFarms = computed(() => [
        ...new Map(
            rows.value.map(({ farm }) => [
                farm.id,
                {
                    ...farm,
                    location:
                        [farm.city, farm.region].filter(Boolean).join(', ') ||
                        'Lokasi belum tercatat',
                    area: number(farm.areaHa),
                },
            ]),
        ).values(),
    ]);
    function reset() {
        Object.assign(filters, {
            year: data.value.defaultYear,
            cycle: 'all',
            partner: '',
            region: '',
            farm: '',
            commodity: '',
        });
    }
    const periodLabel = computed(() =>
        data.value.filters.cycle === 'all'
            ? `1 Januari–31 Desember ${data.value.filters.year}`
            : `${data.value.cycles.find((cycle) => cycle.id === data.value.filters.cycle)?.name ?? 'Cycle terpilih'} · ${data.value.filters.year}`,
    );
    const snapshotLabel = computed(() =>
        new Intl.DateTimeFormat('id-ID', {
            dateStyle: 'medium',
            timeZone: 'Asia/Jakarta',
        }).format(new Date(data.value.snapshotAt)),
    );
    const monthlyLabel = computed(() =>
        new Intl.DateTimeFormat('id-ID', {
            month: 'long',
            year: 'numeric',
        }).format(
            new Date(data.value.defaultYear, data.value.referenceMonth - 1, 1),
        ),
    );
    const updatedLabel = computed(
        () =>
            new Intl.DateTimeFormat('id-ID', {
                dateStyle: 'medium',
                timeStyle: 'short',
                timeZone: 'Asia/Jakarta',
            }).format(new Date(data.value.updatedAt)) + ' WIB',
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
