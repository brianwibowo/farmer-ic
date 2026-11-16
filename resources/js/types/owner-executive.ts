export type ExecutiveFilters = {
    year: number;
    cycle: number | 'all';
    partner: string;
    region: string;
    farm: string;
    commodity: string;
};
export type ExecutivePricing = {
    sellingPrice: number;
    hpp: number;
    transport: number;
};
export type ExecutiveDemoData = {
    updatedAt: string;
    defaultYear: number;
    defaultCycle: number;
    referenceMonth: number;
    commodities: {
        id: string;
        name: string;
        tonPerHa: number;
        pricing: ExecutivePricing;
    }[];
    partners: { id: string; name: string }[];
    clients: { id: string; name: string }[];
    farms: {
        id: string;
        name: string;
        city: string;
        region: string;
        partnerId: string;
        landCategory: string;
        areaHa: number;
        commodityId: string;
        latitude: number;
        longitude: number;
        partnershipStatus: string;
    }[];
    contracts: {
        id: string;
        farmId: string;
        clientId: string;
        monthlyTon: number;
        status: string;
        startYear: number;
        endYear: number;
    }[];
    // Illustrative positions, separate from production accumulated over cycles.
    snapshots: {
        farmId: string;
        year: number;
        capturedAt: string;
        plantings: { status: string; plantedHa: number }[];
        stockTon: number;
        monthlyDeliveredTon: number;
    }[];
    operations: {
        id: string;
        farmId: string;
        year: number;
        cycle: number;
        harvestedAt: string;
        projectedHarvestAt: string;
        plantedHa: number;
        harvestedTon: number;
        dispatchedTon: number;
        adjustmentTon: number;
        monthlyDeliveredTon: number;
        status: string;
    }[];
};

export type ExecutiveRow = Omit<
    ExecutiveDemoData['operations'][number],
    | 'cycle'
    | 'harvestedAt'
    | 'projectedHarvestAt'
    | 'dispatchedTon'
    | 'adjustmentTon'
> & {
    cycle: ExecutiveFilters['cycle'];
    farm: ExecutiveDemoData['farms'][number];
    partner: ExecutiveDemoData['partners'][number];
    commodity: ExecutiveDemoData['commodities'][number];
    contract: ExecutiveDemoData['contracts'][number] | undefined;
    client: ExecutiveDemoData['clients'][number] | undefined;
    projectedTon: number;
    stockTon: number;
};

export type ExecutiveColumn = { key: string; label: string; numeric?: boolean };
export type ExecutiveCellRow = Record<string, string | number>;
