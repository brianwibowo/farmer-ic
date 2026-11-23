import { describe, expect, it } from 'vitest';
import { paginationUrl } from './pagination';

describe('Pagination route context', () => {
    it('preserves filters, encoded search, tenant, page size and fragment', () => {
        const href = paginationUrl(
            '/admin/farm-management?search=Jagung+%26+Padi&team_id=7&status=active&per_page=30&page=2#daftar',
            4,
        );
        const target = new URL(href, 'http://inertia.local');
        expect(target.pathname).toBe('/admin/farm-management');
        expect(Object.fromEntries(target.searchParams)).toEqual({
            search: 'Jagung & Padi',
            team_id: '7',
            status: 'active',
            per_page: '30',
            page: '4',
        });
        expect(target.hash).toBe('#daftar');
        expect(target.searchParams.getAll('page')).toEqual(['4']);
    });

    it('keeps the selected petugas block when navigating directly to a page', () => {
        expect(paginationUrl('/petugas/kebun?block=blok-b', 3)).toBe(
            '/petugas/kebun?block=blok-b&page=3',
        );
    });

    it('supports independent page parameters without resetting other sections', () => {
        expect(
            paginationUrl(
                '/admin/dashboard?page=2&activity_page=1',
                5,
                'activity_page',
            ),
        ).toBe('/admin/dashboard?page=2&activity_page=5');
        expect(paginationUrl('/owner/users', 2)).toBe('/owner/users?page=2');
    });
});
