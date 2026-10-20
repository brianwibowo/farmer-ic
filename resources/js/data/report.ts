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
            commodity_icon_url: string;
            icon_bg_color: string;
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
                    target_area_hectares?: number;
                    completed_area_hectares?: number;
                    target_tonnage?: number;
                    completed_tonnage?: number;
                progress_percentage: number;
                target_unit: string;
            };
        }>;
    };
};
