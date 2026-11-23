import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export type FarmCycleOption = {
    id: string;
    cycle_number: number;
    batch_count: number;
    harvested_ton: string;
};

export function useFarmCycles() {
    const page = usePage<{
        farmCycleOptions?: FarmCycleOption[];
        canCreateFarmCycle?: boolean;
        farmCyclesUrl?: string | null;
    }>();
    const cycles = computed(() => page.props.farmCycleOptions ?? []);
    const canCreate = computed(() => page.props.canCreateFarmCycle === true);
    const detailUrl = computed(() => page.props.farmCyclesUrl ?? null);
    const defaults = () => ({
        farm_cycle_id: cycles.value[0]?.id ?? (null as string | null),
        create_farm_cycle: cycles.value.length === 0 && canCreate.value,
    });
    return { cycles, canCreate, defaults, detailUrl };
}
