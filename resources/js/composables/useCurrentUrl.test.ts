import { describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import { useCurrentUrl } from './useCurrentUrl';

const { mockPage } = vi.hoisted(() => ({
    mockPage: { current: null as unknown },
}));
vi.mock('@inertiajs/vue3', () => ({ usePage: () => mockPage.current }));

describe('navigation URL matching', () => {
    function navigation(url: string) {
        const page = reactive({ url });
        mockPage.current = page;
        return { page, ...useCurrentUrl() };
    }

    it.each(['create', 'farm-id', 'farm-id/edit', 'farm-id/force-delete'])(
        'retains the farm section on %s without also selecting Dashboard',
        (suffix) => {
            const nav = navigation(`/admin/farm-management/${suffix}?page=2`);
            expect(nav.isCurrentOrParentUrl('/admin/farm-management')).toBe(
                true,
            );
            expect(nav.isCurrentUrl('/admin/dashboard')).toBe(false);
        },
    );

    it('does not match sibling prefixes or treat the root as every section', () => {
        const nav = navigation('/admin/user-management-archive');
        expect(nav.isCurrentOrParentUrl('/admin/user-management')).toBe(false);
        expect(nav.isCurrentOrParentUrl('/')).toBe(false);
        expect(nav.isCurrentUrl('/admin')).toBe(false);
    });

    it('normalizes query, hash, trailing slash, and Wayfinder hrefs', () => {
        const nav = navigation('/admin/user-management/?page=2#results');
        expect(
            nav.isCurrentUrl({
                url: '/admin/user-management?role=admin',
                method: 'get',
            }),
        ).toBe(true);
        expect(
            nav.isCurrentUrl('https://example.test/admin/user-management/'),
        ).toBe(true);
        expect(
            nav.isCurrentOrParentUrl(
                '/admin/user-management/',
                '/admin/user-management/account/edit',
            ),
        ).toBe(true);
    });

    it('reacts to Inertia navigation and keeps separate page contexts independent', () => {
        const farms = navigation('/admin/farm-management');
        const users = navigation('/admin/user-management');
        farms.page.url = '/admin/dashboard';
        expect(farms.isCurrentUrl('/admin/dashboard')).toBe(true);
        expect(farms.isCurrentOrParentUrl('/admin/farm-management')).toBe(
            false,
        );
        expect(users.isCurrentUrl('/admin/user-management')).toBe(true);
    });
});
