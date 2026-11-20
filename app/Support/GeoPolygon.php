<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Validates and measures single-ring GeoJSON Polygon boundaries (RFC 7946).
 */
final class GeoPolygon
{
    private const EARTH_RADIUS_M = 6378137.0;

    private const MAX_VERTICES = 200;

    private const MAX_AREA_SQUARE_METRES = 999999999999;

    /**
     * Validate and normalize a GeoJSON Polygon into a closed 7-decimal coordinate ring.
     *
     * @return array{type: 'Polygon', coordinates: list<list<array{0: float, 1: float}>>}
     */
    public static function normalize(mixed $geojson): array
    {
        if (! is_array($geojson) || ($geojson['type'] ?? null) !== 'Polygon') {
            throw new InvalidArgumentException('Format batas blok harus berupa poligon peta yang valid.');
        }

        $rings = $geojson['coordinates'] ?? null;
        if (! is_array($rings) || count($rings) !== 1 || ! is_array($rings[0])) {
            throw new InvalidArgumentException('Batas blok harus berupa satu bidang poligon tertutup.');
        }

        $rawPoints = $rings[0];
        if (! array_is_list($rawPoints) || count($rawPoints) < 3) {
            throw new InvalidArgumentException('Tandai minimal 3 titik sudut untuk membentuk batas blok.');
        }

        $vertices = [];
        foreach ($rawPoints as $point) {
            if (! is_array($point) || count($point) < 2 || ! is_numeric($point[0]) || ! is_numeric($point[1])) {
                throw new InvalidArgumentException('Koordinat batas blok tidak valid.');
            }

            $lng = (float) $point[0];
            $lat = (float) $point[1];
            if (! is_finite($lng) || ! is_finite($lat) || $lng < -180.0 || $lng > 180.0 || $lat < -90.0 || $lat > 90.0) {
                throw new InvalidArgumentException('Koordinat batas blok berada di luar rentang peta yang valid.');
            }

            $normalized = [round($lng, 7), round($lat, 7)];
            $previous = $vertices[array_key_last($vertices)] ?? null;
            if ($previous !== null && $previous[0] === $normalized[0] && $previous[1] === $normalized[1]) {
                continue;
            }

            $vertices[] = $normalized;
        }

        if (count($vertices) > 1 && $vertices[0][0] === $vertices[array_key_last($vertices)][0] && $vertices[0][1] === $vertices[array_key_last($vertices)][1]) {
            array_pop($vertices);
        }

        if (count($vertices) < 3) {
            throw new InvalidArgumentException('Tandai minimal 3 titik sudut berbeda untuk membentuk batas blok.');
        }

        if (count($vertices) > self::MAX_VERTICES) {
            throw new InvalidArgumentException('Jumlah titik batas blok maksimal '.self::MAX_VERTICES.' titik.');
        }

        if (self::hasSelfIntersection($vertices)) {
            throw new InvalidArgumentException('Garis batas blok saling bersilangan. Tandai titik sudut secara berurutan mengelilingi lahan.');
        }

        $closedRing = [...$vertices, $vertices[0]];
        $areaSqMetres = self::squareMetres($closedRing);
        if ($areaSqMetres < 1) {
            throw new InvalidArgumentException('Area batas blok terlalu kecil (minimal 1 m²).');
        }

        if ($areaSqMetres > self::MAX_AREA_SQUARE_METRES) {
            throw new InvalidArgumentException('Area batas blok melebihi batas penyimpanan.');
        }

        return [
            'type' => 'Polygon',
            'coordinates' => [$closedRing],
        ];
    }

    /**
     * Compute geodesic spherical excess area in integer square metres.
     *
     * @param  list<array{0: float, 1: float}>  $closedRing
     */
    public static function squareMetres(array $closedRing): int
    {
        $count = count($closedRing);
        if ($count < 4) {
            return 0;
        }

        $vertices = $closedRing;
        if ($vertices[0][0] === $vertices[$count - 1][0] && $vertices[0][1] === $vertices[$count - 1][1]) {
            array_pop($vertices);
        }

        $m = count($vertices);
        if ($m < 3) {
            return 0;
        }

        $total = 0.0;
        for ($i = 0; $i < $m; $i++) {
            $p1 = $vertices[$i];
            $p2 = $vertices[($i + 1) % $m];
            $p3 = $vertices[($i + 2) % $m];

            $total += (deg2rad($p3[0]) - deg2rad($p1[0])) * sin(deg2rad($p2[1]));
        }

        $area = abs($total * self::EARTH_RADIUS_M * self::EARTH_RADIUS_M / 2.0);

        return (int) round($area);
    }

    /**
     * Compute geodesic area in hectares formatted to decimal(12,4).
     *
     * @param  array{type: 'Polygon', coordinates: list<list<array{0: float, 1: float}>>}  $normalizedPolygon
     */
    public static function areaHectares(array $normalizedPolygon): string
    {
        return LandArea::hectares(self::squareMetres($normalizedPolygon['coordinates'][0]));
    }

    /**
     * Detect whether any two non-adjacent edges of the polygon cross or touch.
     *
     * @param  list<array{0: float, 1: float}>  $vertices
     */
    public static function hasSelfIntersection(array $vertices): bool
    {
        $n = count($vertices);
        if ($n < 4) {
            return false;
        }

        for ($i = 0; $i < $n; $i++) {
            $a1 = $vertices[$i];
            $a2 = $vertices[($i + 1) % $n];

            for ($j = $i + 2; $j < $n; $j++) {
                if ($i === 0 && $j === $n - 1) {
                    continue;
                }

                $b1 = $vertices[$j];
                $b2 = $vertices[($j + 1) % $n];

                if (self::segmentsIntersect($a1, $a2, $b1, $b2)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array{0: float, 1: float}  $a1
     * @param  array{0: float, 1: float}  $a2
     * @param  array{0: float, 1: float}  $b1
     * @param  array{0: float, 1: float}  $b2
     */
    private static function segmentsIntersect(array $a1, array $a2, array $b1, array $b2): bool
    {
        $o1 = self::orientation($a1, $a2, $b1);
        $o2 = self::orientation($a1, $a2, $b2);
        $o3 = self::orientation($b1, $b2, $a1);
        $o4 = self::orientation($b1, $b2, $a2);

        if ($o1 !== $o2 && $o3 !== $o4) {
            return true;
        }

        return ($o1 === 0 && self::onSegment($a1, $b1, $a2))
            || ($o2 === 0 && self::onSegment($a1, $b2, $a2))
            || ($o3 === 0 && self::onSegment($b1, $a1, $b2))
            || ($o4 === 0 && self::onSegment($b1, $a2, $b2));
    }

    /**
     * @param  array{0: float, 1: float}  $p
     * @param  array{0: float, 1: float}  $q
     * @param  array{0: float, 1: float}  $r
     */
    private static function orientation(array $p, array $q, array $r): int
    {
        $val = ($q[1] - $p[1]) * ($r[0] - $q[0]) - ($q[0] - $p[0]) * ($r[1] - $q[1]);
        if (abs($val) < 1e-12) {
            return 0;
        }

        return $val > 0 ? 1 : 2;
    }

    /**
     * @param  array{0: float, 1: float}  $p
     * @param  array{0: float, 1: float}  $q
     * @param  array{0: float, 1: float}  $r
     */
    private static function onSegment(array $p, array $q, array $r): bool
    {
        return $q[0] <= max($p[0], $r[0]) + 1e-12
            && $q[0] >= min($p[0], $r[0]) - 1e-12
            && $q[1] <= max($p[1], $r[1]) + 1e-12
            && $q[1] >= min($p[1], $r[1]) - 1e-12;
    }
}
