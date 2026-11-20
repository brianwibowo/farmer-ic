export type GeoJsonPolygon = {
    type: 'Polygon';
    coordinates: [number, number][][];
};

export type LatLngPoint = [number, number];

const EARTH_RADIUS_M = 6378137;

function degToRad(degrees: number): number {
    return (degrees * Math.PI) / 180;
}

export function ringToLatLngs(
    geojson: GeoJsonPolygon | null | undefined,
): LatLngPoint[] {
    if (
        !geojson ||
        geojson.type !== 'Polygon' ||
        !Array.isArray(geojson.coordinates) ||
        !Array.isArray(geojson.coordinates[0])
    ) {
        return [];
    }

    const ring = geojson.coordinates[0];
    const points: LatLngPoint[] = [];

    for (const pair of ring) {
        if (!Array.isArray(pair) || pair.length < 2) continue;
        const lng = Number(pair[0]);
        const lat = Number(pair[1]);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) continue;
        points.push([lat, lng]);
    }

    if (
        points.length > 1 &&
        points[0][0] === points[points.length - 1][0] &&
        points[0][1] === points[points.length - 1][1]
    ) {
        points.pop();
    }

    return points;
}

export function latLngsToGeoJson(points: LatLngPoint[]): GeoJsonPolygon | null {
    if (points.length < 3) return null;

    const ring: [number, number][] = points.map(([lat, lng]) => [
        Number(lng.toFixed(7)),
        Number(lat.toFixed(7)),
    ]);
    ring.push([ring[0][0], ring[0][1]]);

    return {
        type: 'Polygon',
        coordinates: [ring],
    };
}

/**
 * Geodesic spherical excess area in integer square metres (matches PHP GeoPolygon).
 */
export function polygonAreaSquareMetres(points: LatLngPoint[]): number {
    if (points.length < 3) return 0;

    let total = 0;
    const count = points.length;
    for (let i = 0; i < count; i++) {
        const p1 = points[i];
        const p2 = points[(i + 1) % count];
        const p3 = points[(i + 2) % count];

        total += (degToRad(p3[1]) - degToRad(p1[1])) * Math.sin(degToRad(p2[0]));
    }

    return Math.round(
        Math.abs((total * EARTH_RADIUS_M * EARTH_RADIUS_M) / 2),
    );
}

export function polygonAreaHectaresString(points: LatLngPoint[]): string {
    const sqMetres = polygonAreaSquareMetres(points);
    const whole = Math.floor(sqMetres / 10000);
    const fraction = String(sqMetres % 10000).padStart(4, '0');
    return `${whole}.${fraction}`;
}

export function formatAreaHa(value: string | number): string {
    const parsed = Number(value);
    if (!Number.isFinite(parsed)) return '0';
    return parsed.toLocaleString('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function orientation(p: LatLngPoint, q: LatLngPoint, r: LatLngPoint): number {
    const val = (q[0] - p[0]) * (r[1] - q[1]) - (q[1] - p[1]) * (r[0] - q[0]);
    if (Math.abs(val) < 1e-12) return 0;
    return val > 0 ? 1 : 2;
}

function onSegment(p: LatLngPoint, q: LatLngPoint, r: LatLngPoint): boolean {
    return (
        q[1] <= Math.max(p[1], r[1]) + 1e-12 &&
        q[1] >= Math.min(p[1], r[1]) - 1e-12 &&
        q[0] <= Math.max(p[0], r[0]) + 1e-12 &&
        q[0] >= Math.min(p[0], r[0]) - 1e-12
    );
}

function segmentsIntersect(
    a1: LatLngPoint,
    a2: LatLngPoint,
    b1: LatLngPoint,
    b2: LatLngPoint,
): boolean {
    const o1 = orientation(a1, a2, b1);
    const o2 = orientation(a1, a2, b2);
    const o3 = orientation(b1, b2, a1);
    const o4 = orientation(b1, b2, a2);

    if (o1 !== o2 && o3 !== o4) return true;

    return (
        (o1 === 0 && onSegment(a1, b1, a2)) ||
        (o2 === 0 && onSegment(a1, b2, a2)) ||
        (o3 === 0 && onSegment(b1, a1, b2)) ||
        (o4 === 0 && onSegment(b1, a2, b2))
    );
}

/**
 * Returns true if any two non-adjacent edges of the polygon cross or touch.
 */
/**
 * Compute the average lat/lng centroid from all given polygon rings.
 * Returns null when fewer than 3 total vertices are provided.
 */
export function centroidOfPolygons(
    polygons: (GeoJsonPolygon | null | undefined)[],
): LatLngPoint | null {
    let sumLat = 0;
    let sumLng = 0;
    let count = 0;

    for (const geo of polygons) {
        for (const pt of ringToLatLngs(geo)) {
            sumLat += pt[0];
            sumLng += pt[1];
            count++;
        }
    }

    if (count < 3) return null;

    return [
        Number((sumLat / count).toFixed(7)),
        Number((sumLng / count).toFixed(7)),
    ];
}

export function hasSelfIntersection(points: LatLngPoint[]): boolean {
    const n = points.length;
    if (n < 4) return false;

    for (let i = 0; i < n; i++) {
        const a1 = points[i];
        const a2 = points[(i + 1) % n];

        for (let j = i + 2; j < n; j++) {
            if (i === 0 && j === n - 1) continue;

            const b1 = points[j];
            const b2 = points[(j + 1) % n];

            if (segmentsIntersect(a1, a2, b1, b2)) return true;
        }
    }

    return false;
}
