export type FarmCycleDetail = {
    id: string;
    cycle_number: number;
    batch_count: number;
    harvested_ton: string;
    created_at: string | null;
};

export type FarmCyclesPageProps = {
    farm: { id: string; name: string };
    cycles: {
        data: FarmCycleDetail[];
        total: number;
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
    };
    backUrl: string;
};
