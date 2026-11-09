import { describe, expect, it } from 'vitest';
import { ownerFarmOrigin, withOwnerFarmOrigin } from './ownerFarmNavigation';

describe('Owner farm navigation context', () => {
    it('keeps Beranda as the destination through a farm and block round trip', () => {
        const farm = withOwnerFarmOrigin('/kebun/farm-id', 'home');
        const block = withOwnerFarmOrigin(
            '/kebun/farm-id/blok/block-id',
            ownerFarmOrigin(farm),
        );
        const backToFarm = withOwnerFarmOrigin(
            '/kebun/farm-id',
            ownerFarmOrigin(block),
        );

        expect(backToFarm).toBe('/kebun/farm-id?from=home');
        expect(ownerFarmOrigin(backToFarm)).toBe('home');
    });

    it.each([
        '/kebun/farm-id',
        '/kebun/farm-id?from=farms',
        '/kebun/farm-id?from=https%3A%2F%2Fexample.com',
        '/kebun/farm-id?from[]=home',
        '/kebun/farm-id#from=home',
    ])('uses the farm list for a direct or unrecognized origin: %s', (url) => {
        expect(ownerFarmOrigin(url)).toBe('farms');
        expect(
            withOwnerFarmOrigin('/kebun/farm-id', ownerFarmOrigin(url)),
        ).toBe('/kebun/farm-id');
    });

    it('preserves other query parameters and the fragment without duplicating origin', () => {
        const target = '/kebun/farm-id?from=farms&batch=2#blok';
        const href = withOwnerFarmOrigin(target, 'home');
        expect(href).toBe('/kebun/farm-id?batch=2&from=home#blok');
        expect(withOwnerFarmOrigin(href, 'home')).toBe(href);
        expect(withOwnerFarmOrigin(href, 'farms')).toBe(
            '/kebun/farm-id?batch=2#blok',
        );
    });
});
