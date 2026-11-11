export type ExecutiveFilters = {
    year: number;
    cycle: number;
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
    operations: {
        id: string;
        farmId: string;
        year: number;
        cycle: number;
        plantedHa: number;
        harvestedTon: number;
        dispatchedTon: number;
        adjustmentTon: number;
        monthlyDeliveredTon: number;
        status: string;
    }[];
};

export type ExecutiveRow = ExecutiveDemoData['operations'][number] & {
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
