import { effectScope, nextTick, reactive } from 'vue';
import { describe, expect, it, vi } from 'vitest';
import { useVoluntaryLocation } from './useVoluntaryLocation';

const blocks = [
    { id: 'north', area_limit_m2: 50000, blocked_reason: null },
    { id: 'south', area_limit_m2: 30000, blocked_reason: null },
    { id: 'empty', area_limit_m2: 10000, blocked_reason: null },
];
const cycles = [
    {
        id: 'n1',
        block: { id: 'north' },
        area_limit_m2: 20000,
        blocked_reason: null,
    },
    {
        id: 'n2',
        block: { id: 'north' },
        area_limit_m2: 12000,
        blocked_reason: null,
    },
    {
        id: 's1',
        block: { id: 'south' },
        area_limit_m2: 10000,
        blocked_reason: null,
    },
];

describe.each(['incident', 'other'])('optional location for %s', () => {
    it('filters by block, retains both selections, and synchronously clears stale subblocks', async () => {
        const clearErrors = vi.fn();
        const form = reactive({ block_id: '', cycle_id: '', clearErrors });
        const scope = effectScope();
        const location = scope.run(() =>
            useVoluntaryLocation(
                form,
                () => true,
                () => blocks,
                () => cycles,
            ),
        )!;

        expect(location.availableCycles.value).toEqual([]);
        expect(location.areaLimitM2.value).toBe(null);
        form.block_id = 'north';
        expect(location.availableCycles.value.map((cycle) => cycle.id)).toEqual(
            ['n1', 'n2'],
        );
        expect(location.areaLimitM2.value).toBe(50000);
        form.cycle_id = 'n1';
        await nextTick();
        expect(form.block_id).toBe('north');
        expect(form.cycle_id).toBe('n1');
        expect(location.areaLimitM2.value).toBe(20000);

        form.block_id = 'south';
        expect(form.cycle_id).toBe('');
        expect(location.availableCycles.value.map((cycle) => cycle.id)).toEqual(
            ['s1'],
        );
        expect(location.areaLimitM2.value).toBe(30000);
        form.cycle_id = 's1';
        form.block_id = '';
        expect(form.cycle_id).toBe('');
        expect(location.availableCycles.value).toEqual([]);
        expect(location.areaLimitM2.value).toBe(null);
        expect(clearErrors).toHaveBeenCalledWith('cycle_id');
        expect(clearErrors).toHaveBeenCalledWith('block_id');

        form.block_id = 'empty';
        expect(location.availableCycles.value).toEqual([]);
        expect(location.areaLimitM2.value).toBe(10000);
        scope.stop();
    });

    it('sends a block only when no subblock was selected', () => {
        const form = reactive({
            block_id: '',
            cycle_id: '',
            clearErrors: vi.fn(),
        });
        const scope = effectScope();
        const location = scope.run(() =>
            useVoluntaryLocation(
                form,
                () => true,
                () => blocks,
                () => cycles,
            ),
        )!;
        expect(location.submittedBlockId('', '')).toBe('');
        expect(location.submittedBlockId('', 'north')).toBe('north');
        expect(location.submittedBlockId('n1', 'north')).toBe('');
        scope.stop();
    });
});

it('prioritizes the selected subblock warning and area', () => {
    const form = reactive({
        block_id: 'north',
        cycle_id: 'n1',
        clearErrors: vi.fn(),
    });
    const scope = effectScope();
    const location = scope.run(() =>
        useVoluntaryLocation(
            form,
            () => true,
            () => [{ ...blocks[0], blocked_reason: 'Blok belum siap' }],
            () => [{ ...cycles[0], blocked_reason: 'Subblok belum siap' }],
        ),
    )!;
    expect(location.blockedReason.value).toBe('Subblok belum siap');
    expect(location.areaLimitM2.value).toBe(20000);
    form.cycle_id = '';
    expect(location.blockedReason.value).toBe('Blok belum siap');
    expect(location.areaLimitM2.value).toBe(50000);
    scope.stop();
});

it('keeps non-optional categories independent of the selected block', async () => {
    const form = reactive({ block_id: '', cycle_id: '', clearErrors: vi.fn() });
    const scope = effectScope();
    const location = scope.run(() =>
        useVoluntaryLocation(
            form,
            () => false,
            () => blocks,
            () => cycles,
        ),
    )!;
    expect(location.availableCycles.value.map((cycle) => cycle.id)).toEqual([
        'n1',
        'n2',
        's1',
    ]);
    form.cycle_id = 's1';
    await nextTick();
    expect(location.areaLimitM2.value).toBe(10000);
    expect(location.submittedBlockId('s1', '')).toBe('');
    scope.stop();
});
