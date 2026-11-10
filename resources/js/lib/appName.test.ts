import { afterEach, describe, expect, it, vi } from 'vitest';
import { createSSRApp } from 'vue';
import { renderToString } from '@vue/server-renderer';
import BrandLogo from '@/components/BrandLogo.vue';
import AppLogo from '@/components/AppLogo.vue';
import { getAppName, resolveAppName } from './appName';

const page = vi.hoisted(() => ({ props: { name: '' } }));
vi.mock('@inertiajs/vue3', () => ({ usePage: () => page }));

afterEach(() => {
    vi.unstubAllGlobals();
    page.props.name = '';
});

describe('application name from Laravel', () => {
    it('defaults for missing or empty names', () => {
        for (const value of [undefined, null, '', '   ']) {
            expect(resolveAppName(value)).toBe('Bumi Hijauan');
        }
        expect(getAppName()).toBe('Bumi Hijauan');
    });

    it('uses server metadata for titles and plain services', () => {
        vi.stubGlobal('document', {
            querySelector: () => ({ content: 'Kebun Nusantara' }),
        });
        expect(getAppName()).toBe('Kebun Nusantara');
    });

    it('renders the configured name safely in both shared logos', async () => {
        page.props.name = '<Kebun & Nusantara>';
        for (const component of [BrandLogo, AppLogo]) {
            const html = await renderToString(createSSRApp(component));
            expect(html).toContain('&lt;Kebun &amp; Nusantara&gt;');
            expect(html).not.toContain('<Kebun & Nusantara>');
        }
    });
});
