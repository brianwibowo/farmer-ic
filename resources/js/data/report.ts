export type ReportPeriod = 'minggu' | 'bulan' | 'kuartal';
export type ReportCommodity = 'Semua' | 'Jagung' | 'Kedelai' | 'Rumput Pakan';

export type ReportData = {
    status: 'success';
    message: string;
    data: {
        quarterly_report: {
            period_label: 'Q2 (Apr - Jun)',
            total_opex_idr: 4850000000,
            estimated_revenue_idr: 7200000000,
            farms: Array<{
                id: string;
                name: string;
                province: string;
                commodity: string;
                status: string;
                progress_percentage: number;
                estimated_revenue_idr: number;
                opex_idr: number;
                roi_percentage: number;
                area_hectares: number;
                phase: string;
                hst: number;
                status_label: 'Optimal' | 'Siap Panen';
            }>;
            farm_recap: Array<{
                id: string;
                name: string;
                province: string;
                commodity_summary: string;
                area_hectares: number;
                phase: string;
                hst: number;
                opex_idr: number;
                estimated_revenue_idr: number;
                status_label: 'Optimal' | 'Siap Panen';
                icon: string;
            }>;
        };
        monthly_commodity_reports: Array<{
            id: string;
            order: number;
            commodity_name: string;
            farm_count: number;
            total_area_hectares: number;
            period_label: string;
            stages: Array<{
                label: string;
                completed_area_hectares: number;
                farm_count: number;
                progress_percentage: number;
                value_label: string;
            }>;
        }>;
        filter_metadata: {
            week: number;
            month: string;
            year: number;
            generated_at: string;
        };
        summary_cards: {
            total_area: {
                total_hectares: number;
                unit: 'Ha';
                total_commodities: number;
                commodities_progress_percentage: number;
            };
            weekly_opex: {
                current_week_opex_idr: number;
                current_month_opex_idr: number;
                monthly_budget_idr: number;
                monthly_budget_progress_percentage: number;
            };
            estimated_harvest: {
                total_estimated_revenue_idr: number;
                harvesting_commodities_count: number;
                total_commodities_count: number;
                harvest_progress_percentage: number;
                harvesting_commodities: string[];
            };
            budget_efficiency: {
                efficiency_percentage: number;
                weekly_ceiling_idr: number;
                ceiling_compliance_status: string;
                ceiling_usage_percentage: number;
            };
        };
        land_and_cash_proportion: {
            donut_chart_data: Array<{
                commodity_name: string;
                area_hectares: number;
                area_percentage: number;
                weekly_opex_idr: number;
                estimated_revenue_idr: number;
            }>;
        };
        operational_targets: Array<{
            id: string;
            commodity_name: string;
            icon: string;
            farm_name: string;
            area_hectares: number;
            status_description: string;
            fase: {
                code: string;
                category: string;
                description: string;
                current_stage_index: number;
                total_stages_in_cycle: number;
                is_active: boolean;
            };
            operational_task: {
                task_name: string;
                target_value: number;
                completed_value: number;
                progress_percentage: number;
                target_unit: string;
            };
        }>;
    };
};

export const reportSeedData: ReportData = {
    status: 'success',
    message: 'Page Report Data Retrieved Successfully',
    data: {
        quarterly_report: {
            period_label: 'Q2 (Apr - Jun)',
            total_opex_idr: 4850000000,
            estimated_revenue_idr: 7200000000,
            farms: [
                { id: 'farm-001', name: 'Pangan Nusantara', province: 'Jawa Barat', commodity: 'Jagung', status: 'Panen Q2', progress_percentage: 82, estimated_revenue_idr: 2000000000, opex_idr: 710000000, roi_percentage: 69, area_hectares: 200, phase: 'Panen Q2', hst: 105, status_label: 'Siap Panen' },
                { id: 'farm-002', name: 'Subur Makmur', province: 'Jawa Tengah', commodity: 'Jagung', status: 'Isi Biji Q2', progress_percentage: 78, estimated_revenue_idr: 1750000000, opex_idr: 680000000, roi_percentage: 69, area_hectares: 180, phase: 'Isi Biji', hst: 80, status_label: 'Optimal' },
                { id: 'farm-003', name: 'Maju Jaya', province: 'Lampung', commodity: 'Kedelai', status: 'Isi Polong', progress_percentage: 64, estimated_revenue_idr: 800000000, opex_idr: 520000000, roi_percentage: 54, area_hectares: 150, phase: 'Isi Polong', hst: 62, status_label: 'Optimal' },
                { id: 'farm-004', name: 'Makmur Abadi', province: 'Lampung', commodity: 'Rumput Pakan', status: 'Babat Q2', progress_percentage: 71, estimated_revenue_idr: 750000000, opex_idr: 450000000, roi_percentage: 67, area_hectares: 130, phase: 'Babat', hst: 38, status_label: 'Siap Panen' },
                { id: 'farm-005', name: 'Tunas Baru', province: 'Jawa Timur', commodity: 'Kedelai', status: 'Berbunga', progress_percentage: 58, estimated_revenue_idr: 590000000, opex_idr: 380000000, roi_percentage: 55, area_hectares: 100, phase: 'Berbunga', hst: 45, status_label: 'Optimal' },
                { id: 'farm-006', name: 'Hijau Mandiri', province: 'Banten', commodity: 'Rumput Pakan', status: 'Ratoon 2', progress_percentage: 49, estimated_revenue_idr: 450000000, opex_idr: 290000000, roi_percentage: 55, area_hectares: 90, phase: 'Ratoon 2', hst: 30, status_label: 'Optimal' },
            ],
            farm_recap: [
                { id: 'recap-001', name: 'Kebun Subur Makmur', province: 'Jatim', commodity_summary: 'JAGUNG', area_hectares: 200, phase: 'Fase Q2', hst: 85, opex_idr: 680000000, estimated_revenue_idr: 1150000000, status_label: 'Optimal', icon: '🌽' },
                { id: 'recap-002', name: 'Kebun Maju Jaya', province: 'Lampung', commodity_summary: 'KEDELAI', area_hectares: 150, phase: 'Isi Polong', hst: 62, opex_idr: 520000000, estimated_revenue_idr: 800000000, status_label: 'Optimal', icon: '🫘' },
                { id: 'recap-003', name: 'Kebun Makmur Abadi', province: 'Lampung', commodity_summary: 'RUMPUT', area_hectares: 130, phase: 'Babat', hst: 38, opex_idr: 450000000, estimated_revenue_idr: 750000000, status_label: 'Siap Panen', icon: '🌿' },
                { id: 'recap-004', name: '7 Kebun Lainnya', province: '', commodity_summary: '4 Jagung, 2 Kedelai, 1 Rumput Pakan', area_hectares: 770, phase: 'Rekapitulasi', hst: 0, opex_idr: 3200000000, estimated_revenue_idr: 4500000000, status_label: 'Optimal', icon: '🌱' },
            ],
        },
        monthly_commodity_reports: [
            {
                id: 'monthly-jagung',
                order: 1,
                commodity_name: 'Tanaman Jagung',
                farm_count: 5,
                total_area_hectares: 650,
                period_label: 'Bulan: Mei 2026',
                stages: [
                    { label: 'Bibit (V1-V3)', completed_area_hectares: 90, farm_count: 1, progress_percentage: 32, value_label: '90 Ha (1 Kebun)' },
                    { label: 'Vegetatif (V4-VT)', completed_area_hectares: 180, farm_count: 2, progress_percentage: 62, value_label: '180 Ha (2 Kebun)' },
                    { label: 'Bunga & Isi Biji', completed_area_hectares: 200, farm_count: 2, progress_percentage: 70, value_label: '200 Ha (2 Kebun)' },
                    { label: 'Panen (>100 HST)', completed_area_hectares: 180, farm_count: 1, progress_percentage: 64, value_label: '180 Ha (1 Kebun)' },
                ],
            },
            {
                id: 'monthly-kedelai',
                order: 2,
                commodity_name: 'Tanaman Kedelai',
                farm_count: 3,
                total_area_hectares: 350,
                period_label: 'Siklus 85 HST',
                stages: [
                    { label: 'Vegetatif & Bakal Akar', completed_area_hectares: 90, farm_count: 1, progress_percentage: 42, value_label: '90 Ha (Kebun Agro)' },
                    { label: 'Pembungaan (Fase R1-R2)', completed_area_hectares: 110, farm_count: 1, progress_percentage: 50, value_label: '110 Ha (Kebun Tunas)' },
                    { label: 'Pengisian Polong (R3-R6)', completed_area_hectares: 150, farm_count: 2, progress_percentage: 70, value_label: '150 Ha (Kebun Makmur)' },
                    { label: 'Masak Polong / Panen (R8)', completed_area_hectares: 0, farm_count: 0, progress_percentage: 100, value_label: 'Panen dalam 14 Hari' },
                ],
            },
            {
                id: 'monthly-rumput',
                order: 3,
                commodity_name: 'Rumput Pakan',
                farm_count: 2,
                total_area_hectares: 250,
                period_label: 'Panen Tiap 40 Hari',
                stages: [
                    { label: 'Tunas Baru / Stek Awal', completed_area_hectares: 0, farm_count: 0, progress_percentage: 14, value_label: '0 Ha (Selesai Tanam)' },
                    { label: 'Pertumbuhan Anakan (Vegetatif)', completed_area_hectares: 20, farm_count: 1, progress_percentage: 48, value_label: '20 Ha (Kebun Hijau)' },
                    { label: 'Siap Babat / Panen Susuan', completed_area_hectares: 190, farm_count: 2, progress_percentage: 88, value_label: '190 Ha (Kebun Makmur)' },
                    { label: 'Regenerasi (Rotasi ke-3)', completed_area_hectares: 150, farm_count: 1, progress_percentage: 78, value_label: 'Pupuk Urea Pasca-Babat' },
                ],
            },
        ],
        filter_metadata: {
            week: 3,
            month: 'Januari',
            year: 2026,
            generated_at: '2026-10-17T20:55:00Z',
        },
        summary_cards: {
            total_area: {
                total_hectares: 450.5,
                unit: 'Ha',
                total_commodities: 3,
                commodities_progress_percentage: 100,
            },
            weekly_opex: {
                current_week_opex_idr: 125000000,
                current_month_opex_idr: 450000000,
                monthly_budget_idr: 500000000,
                monthly_budget_progress_percentage: 90,
            },
            estimated_harvest: {
                total_estimated_revenue_idr: 350000000,
                harvesting_commodities_count: 1,
                total_commodities_count: 3,
                harvest_progress_percentage: 33.33,
                harvesting_commodities: ['Rumput Pakan'],
            },
            budget_efficiency: {
                efficiency_percentage: 93.5,
                weekly_ceiling_idr: 133700000,
                ceiling_compliance_status: 'Sesuai Plafon Mingguan',
                ceiling_usage_percentage: 93.49,
            },
        },
        land_and_cash_proportion: {
            donut_chart_data: [
                {
                    commodity_name: 'Jagung',
                    area_hectares: 170,
                    area_percentage: 37.74,
                    weekly_opex_idr: 45000000,
                    estimated_revenue_idr: 120000000,
                },
                {
                    commodity_name: 'Kedelai',
                    area_hectares: 130,
                    area_percentage: 28.85,
                    weekly_opex_idr: 35000000,
                    estimated_revenue_idr: 80000000,
                },
                {
                    commodity_name: 'Rumput Pakan',
                    area_hectares: 150.5,
                    area_percentage: 33.41,
                    weekly_opex_idr: 45000000,
                    estimated_revenue_idr: 150000000,
                },
            ],
        },
        operational_targets: [
            {
                id: 'target-001',
                commodity_name: 'Jagung',
                icon: '🌽',
                farm_name: 'Kebun Makmur',
                area_hectares: 170,
                status_description: 'Jagung masa pemupukan NPK ke-2',
                fase: {
                    code: 'V3',
                    category: 'Vegetatif',
                    description: '3 daun terbuka sempurna',
                    current_stage_index: 3,
                    total_stages_in_cycle: 12,
                    is_active: true,
                },
                operational_task: {
                    task_name: 'Spreading Pupuk NPK ke-2',
                    target_value: 170,
                    completed_value: 150,
                    progress_percentage: 88.24,
                    target_unit: 'Ha',
                },
            },
            {
                id: 'target-002',
                commodity_name: 'Kedelai',
                icon: '🫘',
                farm_name: 'Kebun Makmur',
                area_hectares: 130,
                status_description: 'Kedelai masa monitor polong fase R3',
                fase: {
                    code: 'R3',
                    category: 'Generatif',
                    description: 'Beginning Pod (pembentukan polong 5 mm)',
                    current_stage_index: 3,
                    total_stages_in_cycle: 6,
                    is_active: true,
                },
                operational_task: {
                    task_name: 'Kontrol kelembaban & bedengan',
                    target_value: 130,
                    completed_value: 130,
                    progress_percentage: 100,
                    target_unit: 'Ha',
                },
            },
            {
                id: 'target-003',
                commodity_name: 'Rumput Pakan',
                icon: '🌿',
                farm_name: 'Kebun Makmur',
                area_hectares: 150.5,
                status_description: 'Rumput pakan masa panen babat ke-3',
                fase: {
                    code: 'Babat ke-3',
                    category: 'Panen Rotasi',
                    description: 'Siklus pemotongan/pemanenan ke-3',
                    current_stage_index: 3,
                    total_stages_in_cycle: 6,
                    is_active: true,
                },
                operational_task: {
                    task_name: 'Panen Babat ke-3',
                    target_value: 1500,
                    completed_value: 1200,
                    progress_percentage: 80,
                    target_unit: 'Ton',
                },
            },
        ],
    },
};
