import {
    getFarmCommoditySummaries,
    type CommoditySummary,
} from '@/services/farmDemoService';

export type { CommoditySummary } from '@/services/farmDemoService';

export async function listCommoditySummaries(): Promise<CommoditySummary[]> {
    return getFarmCommoditySummaries();
}
