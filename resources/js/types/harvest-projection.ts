export type ProjectionValue = {
    projection_ton: number | null;
    missing_count: number;
    batch_count: number;
};

export type HarvestProjection = {
    period: 'cycle' | 'year';
    farm_id: string | null;
    farm_count: number;
    unidentified_batches: number;
    cards: (ProjectionValue & {
        key: string;
        name: string;
        ton_per_ha: number;
        cycles_per_year: number;
    })[];
    rows: (ProjectionValue & {
        key: string;
        farm_id: string;
        farm_name: string;
        crop_name: string | null;
    })[];
};

export const projectionNumber = (value: number | null): string =>
    value === null
        ? '—'
        : new Intl.NumberFormat('id-ID', { maximumFractionDigits: 3 }).format(
              value,
          );
