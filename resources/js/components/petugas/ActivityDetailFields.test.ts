import { renderToString } from '@vue/server-renderer';
import { h } from 'vue';
import { describe, expect, it } from 'vitest';
import { activityDetailMaximumErrors } from '@/lib/activityInputLimits';
import ActivityDetailFields from './ActivityDetailFields.vue';

describe('activity form numeric feedback', () => {
    const fields = [
        {
            key: 'area',
            label: 'Luas area',
            type: 'number',
            max: 999999,
            step: '0.0001',
        },
    ];

    it('renders the current maximum and associates an immediate inline error', async () => {
        const values = { area: '8', area_unit: 'Ha' };
        const errors = activityDetailMaximumErrors(
            fields,
            values,
            70000,
            999999999.999,
        );
        const html = await renderToString(
            h(ActivityDetailFields, {
                fields,
                modelValue: values,
                errors,
                areaLimitM2: 70000,
            }),
        );
        expect(html).toContain('max="7"');
        expect(html).toContain('Maksimal 7 Ha');
        expect(html).toContain('Luas area maksimal 7 Ha.');
        expect(html).toContain('aria-invalid="true"');
        expect(html).toContain('detail-error-area');
    });

    it('updates the hint and removes the error after correction or a unit change', async () => {
        const values = { area: '70000', area_unit: 'm²' };
        const errors = activityDetailMaximumErrors(
            fields,
            values,
            70000,
            999999999.999,
        );
        const html = await renderToString(
            h(ActivityDetailFields, {
                fields,
                modelValue: values,
                errors,
                areaLimitM2: 70000,
            }),
        );
        expect(html).toContain('max="70000"');
        expect(html).toContain('Maksimal 70.000 m²');
        expect(html).not.toContain('id="detail-error-area"');
    });
});
