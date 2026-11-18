import { renderToString } from '@vue/server-renderer';
import { h } from 'vue';
import { describe, expect, it } from 'vite-plus/test';
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

    it('keeps area and its unit together in the compact task form', async () => {
        const html = await renderToString(
            h(ActivityDetailFields, {
                fields: [
                    ...fields,
                    {
                        key: 'area_unit',
                        label: 'Satuan luas',
                        type: 'select',
                        options: ['m²', 'Ha'],
                    },
                    { key: 'description', label: 'Keterangan', type: 'text' },
                ],
                modelValue: {
                    area: '500',
                    area_unit: 'm²',
                    description: '',
                },
                errors: { 'details.area_unit': 'Pilih satuan luas.' },
                areaLimitM2: 70000,
                compact: true,
            }),
        );

        expect(html).toContain(
            'grid-cols-[minmax(0,1fr)_minmax(5.75rem,0.55fr)]',
        );
        expect(html).toContain('id="detail-area"');
        expect(html).toContain('step="1"');
        expect(html).toContain('Maksimal 70.000 m²');
        expect(html).toContain('detail-error-area_unit');
        expect(html).toContain('col-span-2');
    });

    it('opens optional details when the server returns a field error', async () => {
        const html = await renderToString(
            h(ActivityDetailFields, {
                fields: [
                    {
                        key: 'description',
                        label: 'Deskripsi',
                        type: 'text',
                        optional: true,
                    },
                ],
                modelValue: { description: '' },
                errors: { 'details.description': 'Periksa deskripsi.' },
                areaLimitM2: null,
                compact: true,
            }),
        );

        expect(html).toMatch(/<details[^>]*\sopen/);
        expect(html).toContain('detail-error-description');
    });
});
