import seedData from '@/data/commoditySummaries.json';

export type CommoditySummary = {
    id: string;
    name: string;
    image: string;
    statistics: Array<{ label: string; value: string; highlight?: boolean }>;
};

export async function listCommoditySummaries(): Promise<CommoditySummary[]> {
    await new Promise<void>((resolve) => window.setTimeout(resolve, 400));
    return structuredClone(seedData);
}
