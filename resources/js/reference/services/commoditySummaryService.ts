// Historical source: 73f2555d91d0fb2d6acb7bc70813921edd479ea4 / resources/js/services/commoditySummaryService.ts
import {
    getFarmCommoditySummaries,
    type CommoditySummary,
} from '@/reference/services/farmDemoService';

export type { CommoditySummary } from '@/reference/services/farmDemoService';

export async function listCommoditySummaries(): Promise<CommoditySummary[]> {
    return getFarmCommoditySummaries();
}
