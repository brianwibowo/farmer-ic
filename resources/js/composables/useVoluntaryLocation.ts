import { computed, watch } from 'vue';

type LocationForm = {
    block_id: string;
    cycle_id: string;
    clearErrors: (...fields: ('block_id' | 'cycle_id')[]) => unknown;
};

type LocationBlock = {
    id: string;
    area_limit_m2: number;
    blocked_reason: string | null;
};

type LocationCycle = LocationBlock & {
    block: { id: string };
};

export function useVoluntaryLocation<Cycle extends LocationCycle>(
    form: LocationForm,
    optionalLocation: () => boolean,
    blocks: () => readonly LocationBlock[],
    cycles: () => readonly Cycle[],
) {
    watch(
        () => form.cycle_id,
        (value) => {
            form.clearErrors('cycle_id');
            if (value && !optionalLocation()) {
                form.block_id = '';
                form.clearErrors('block_id');
            }
        },
    );
    watch(
        () => form.block_id,
        (value) => {
            form.clearErrors('block_id');
            if (optionalLocation() || value) {
                form.cycle_id = '';
                form.clearErrors('cycle_id');
            }
        },
        { flush: 'sync' },
    );

    const availableCycles = computed(() =>
        optionalLocation()
            ? cycles().filter(
                  (cycle) => form.block_id && cycle.block.id === form.block_id,
              )
            : cycles(),
    );
    const selectedCycle = computed(() =>
        availableCycles.value.find((cycle) => cycle.id === form.cycle_id),
    );
    const selectedBlock = computed(() =>
        blocks().find((block) => block.id === form.block_id),
    );
    const areaLimitM2 = computed(
        () =>
            selectedCycle.value?.area_limit_m2 ??
            selectedBlock.value?.area_limit_m2 ??
            null,
    );
    const blockedReason = computed(
        () =>
            selectedCycle.value?.blocked_reason ??
            selectedBlock.value?.blocked_reason ??
            null,
    );

    // The server derives the block from the selected persisted cycle.
    function submittedBlockId(cycleId: string, blockId: string): string {
        return optionalLocation() && cycleId ? '' : blockId;
    }

    return { availableCycles, areaLimitM2, blockedReason, submittedBlockId };
}
