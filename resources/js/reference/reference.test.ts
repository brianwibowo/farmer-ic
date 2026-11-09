import { describe, expect, it, vi } from 'vitest';
import { createSSRApp } from 'vue';
import { renderToString } from '@vue/server-renderer';
import Dashboard from '@/pages/reference/Dashboard.vue';
import Kebun from '@/pages/reference/Kebun.vue';
import KebunDetail from '@/pages/reference/KebunDetail.vue';
import KebunAktivitas from '@/pages/reference/KebunAktivitas.vue';
import Aktivitas from '@/pages/reference/Aktivitas.vue';
import RincianAktivitas from '@/pages/reference/RincianAktivitas.vue';
import Laporan from '@/pages/reference/Laporan.vue';
import Finance from '@/pages/reference/Finance.vue';
import Index from '@/pages/reference/Index.vue';
import { usePage } from './inertia';
import DashboardHeader from './components/farmer/DashboardHeader.vue';
import { referenceAsset } from './assets';
import { loadFarmDemoState } from './services/farmDemoService';
import { referenceUrl } from './url.mjs';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<span />' },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    router: { visit: vi.fn() },
    usePage: () => ({ url: '/referensi', props: { auth: { user: { name: 'REAL DATABASE IDENTITY' } }, farm: { name: 'REAL DATABASE FARM' } } }),
}));

describe('isolated historical mobile reference', () => {
    const pages = { Dashboard, Kebun, KebunDetail, KebunAktivitas, Aktivitas, RincianAktivitas, Laporan, Finance, Index };
    for (const [name, component] of Object.entries(pages)) {
        it(`${name} opts out of the live layout and renders the historical banner`, async () => {
            expect(component.layout).toEqual([]);
            const html = await renderToString(createSSRApp(component));
            expect(html).toContain('Referensi historis');
            expect(html).toContain('29 Oktober 2026');
            expect(html).toContain('Kembali ke aplikasi');
            expect(html).not.toContain('REAL DATABASE');
            expect(html).not.toContain('/bypass');
        });
    }

    it('header uses the historical logo instead of the missing-image fallback', async () => {
        const html = await renderToString(createSSRApp(DashboardHeader));
        expect(html).toContain(`src="${referenceAsset('/images/logo-dashboard.svg')}"`);
        expect(html).not.toContain(`src="${referenceAsset('/images/kebun-placeholder.svg')}"`);
    });

    it('never exposes operational page props to historical components', () => {
        const page = usePage();
        expect(page.props.auth.user.name).toBe('Petani IC');
        expect('farm' in page.props).toBe(false);
        expect(page.props.reference.sourceCommit).toBe('73f2555d91d0fb2d6acb7bc70813921edd479ea4');
    });

    it('AI has a visibly explained disabled fieldset and no assistant replies', async () => {
        const html = await renderToString(createSSRApp(Index));
        expect(html).toMatch(/<fieldset[^>]*disabled/);
        expect(html).toContain('Tidak ada panggilan AI maupun jawaban buatan.');
        expect(html).toContain('Halo, ada yang bisa saya bantu?');
        expect(html).toContain('Pesan untuk asisten kebun');
    });

    it('URL helper confines historical paths and rejects unknown operations', () => {
        expect(referenceUrl('/kebun/detail?id=kebun-001')).toBe('/referensi/kebun/detail?id=kebun-001');
        expect(referenceUrl('/finance/export')).toBe('/referensi');
        expect(referenceUrl('/bypass')).toBe('/referensi/ai');
    });

    it('loads fresh immutable-by-isolation historical fixtures without browser storage', async () => {
        // This Node test has no window/localStorage. Only the display delay needs a timer.
        vi.stubGlobal('window', { setTimeout });
        try {
            const first = await loadFarmDemoState();
            expect(first.kebun[0].name).toBe('Kebun Sumber Makmur');
            first.kebun[0].name = 'changed only in this test';
            const second = await loadFarmDemoState();
            expect(second.kebun[0].name).toBe('Kebun Sumber Makmur');
            expect(second.kebun[0].image).not.toMatch(/^\/images\//);
        } finally {
            vi.unstubAllGlobals();
        }
    });
});
