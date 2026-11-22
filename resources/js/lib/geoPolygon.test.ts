import { describe, expect, it } from 'vitest';
import {
    centroidOfPolygons,
    formatAreaHa,
    hasSelfIntersection,
    latLngsToGeoJson,
    polygonAreaHectaresString,
    polygonAreaSquareMetres,
    ringToLatLngs,
    type LatLngPoint,
} from './geoPolygon';

describe('geoPolygon helpers', () => {
    const squareOneHa: LatLngPoint[] = [
        [-6.5965, 106.9502],
        [-6.5965, 106.9511043],
        [-6.5956017, 106.9511043],
        [-6.5956017, 106.9502],
    ];

    it('converts between Leaflet lat/lng points and closed GeoJSON Polygon', () => {
        const geojson = latLngsToGeoJson(squareOneHa);
        expect(geojson).not.toBeNull();
        expect(geojson?.type).toBe('Polygon');
        expect(geojson?.coordinates[0]).toHaveLength(5);
        expect(geojson?.coordinates[0][0]).toEqual(geojson?.coordinates[0][4]);
        expect(geojson?.coordinates[0][0]).toEqual([106.9502, -6.5965]);

        const restored = ringToLatLngs(geojson);
        expect(restored).toHaveLength(4);
        expect(restored[0]).toEqual([-6.5965, 106.9502]);
    });

    it('rejects polygons with fewer than 3 vertices', () => {
        expect(latLngsToGeoJson([[-6.59, 106.95], [-6.58, 106.96]])).toBeNull();
        expect(polygonAreaSquareMetres([[-6.59, 106.95]])).toBe(0);
    });

    it('computes geodesic area close to 1.0000 ha for a 100m x 100m plot', () => {
        const sqMetres = polygonAreaSquareMetres(squareOneHa);
        expect(sqMetres).toBeGreaterThan(9500);
        expect(sqMetres).toBeLessThan(10500);

        const ha = polygonAreaHectaresString(squareOneHa);
        expect(ha).toMatch(/^\d+\.\d{4}$/);
        expect(formatAreaHa(ha)).toContain(',');
    });

    it('detects self-intersecting bowtie polygons while accepting valid rings', () => {
        expect(hasSelfIntersection(squareOneHa)).toBe(false);

        const bowtie: LatLngPoint[] = [
            [-6.5965, 106.9502],
            [-6.5956, 106.9511],
            [-6.5965, 106.9511],
            [-6.5956, 106.9502],
        ];
        expect(hasSelfIntersection(bowtie)).toBe(true);
    });

    it('computes centroid of multiple polygons as the average vertex', () => {
        const poly1 = latLngsToGeoJson(squareOneHa);
        const poly2 = latLngsToGeoJson([
            [-6.5950, 106.9520],
            [-6.5950, 106.9530],
            [-6.5940, 106.9530],
            [-6.5940, 106.9520],
        ]);

        const center = centroidOfPolygons([poly1, poly2]);
        expect(center).not.toBeNull();
        expect(center![0]).toBeGreaterThan(-6.60);
        expect(center![0]).toBeLessThan(-6.59);
        expect(center![1]).toBeGreaterThan(106.95);
        expect(center![1]).toBeLessThan(106.96);
    });

    it('returns null centroid when fewer than 3 total vertices', () => {
        expect(centroidOfPolygons([])).toBeNull();
        expect(centroidOfPolygons([null, undefined])).toBeNull();
    });
});
