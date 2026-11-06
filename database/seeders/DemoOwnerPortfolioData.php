<?php

namespace Database\Seeders;

use App\Models\Team;
use Ramsey\Uuid\Uuid;

/** Fixed presentation scenario; these are fixtures, not current agronomic guidance. */
final class DemoOwnerPortfolioData
{
    public const SNAPSHOT_DATE = '2026-11-05';

    public static function id(Team $team, string $key): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'farmer-ic:owner-portfolio:v1:'.$team->id.':'.$key)->toString();
    }

    /** @return list<array<string, mixed>> */
    public static function sites(): array
    {
        return [
            [
                'key' => 'sukamaju', 'name' => 'Kebun Sukamaju', 'commodity' => 'Padi', 'variety' => 'Ciherang',
                'area_ha' => '8.0000', 'land_tenure' => 'owned', 'latitude' => '-6.5730000', 'longitude' => '106.8990000',
                'address' => 'Lahan percontohan Sukamaju, skenario presentasi Farm IC', 'city' => 'Kabupaten Bogor',
                'district' => 'Sukamakmur', 'province' => 'Jawa Barat', 'target_volume_ton' => '44.000', 'harvest_offset' => 107,
                'blocks' => [
                    ['code' => 'B01', 'name' => 'Blok Sawah Utara', 'area_ha' => '5.0000'],
                    ['code' => 'B02', 'name' => 'Blok Sawah Selatan', 'area_ha' => '3.0000'],
                ],
                'batches' => [
                    self::batch('active', 'B01', 'A', '3.2500', '2026-10-01', '2027-01-16', '18.200', 'maintenance'),
                    self::batch('harvested', 'B02', 'H', '1.7500', '2026-07-10', '2026-10-25', '9.800', 'harvest', '2026-10-25'),
                ],
            ],
            [
                'key' => 'cibogo', 'name' => 'Kebun Cibogo', 'commodity' => 'Kedelai', 'variety' => 'Anjasmoro',
                'area_ha' => '6.5000', 'land_tenure' => 'partnership', 'latitude' => '-6.6260000', 'longitude' => '106.9470000',
                'address' => 'Lahan kemitraan Cibogo, skenario presentasi Farm IC', 'city' => 'Kabupaten Bogor',
                'district' => 'Megamendung', 'province' => 'Jawa Barat', 'target_volume_ton' => '12.000', 'harvest_offset' => 80,
                'blocks' => [
                    ['code' => 'B01', 'name' => 'Blok Kedelai Timur', 'area_ha' => '4.0000'],
                    ['code' => 'B02', 'name' => 'Blok Rotasi Barat', 'area_ha' => '2.5000'],
                ],
                'batches' => [self::batch('active', 'B01', 'A', '3.0000', '2026-10-20', '2027-01-08', '6.000', 'maintenance')],
            ],
            [
                'key' => 'mekarsari', 'name' => 'Kebun Mekarsari', 'commodity' => 'Cabai keriting', 'variety' => 'Kencana',
                'area_ha' => '4.0000', 'land_tenure' => 'rented', 'latitude' => '-6.5280000', 'longitude' => '106.9940000',
                'address' => 'Lahan sewa hortikultura Mekarsari, skenario presentasi Farm IC', 'city' => 'Kabupaten Bogor',
                'district' => 'Cileungsi', 'province' => 'Jawa Barat', 'target_volume_ton' => '28.000', 'harvest_offset' => 90,
                'blocks' => [
                    ['code' => 'B01', 'name' => 'Blok Cabai Bedengan', 'area_ha' => '2.5000'],
                    ['code' => 'B02', 'name' => 'Blok Cadangan Hortikultura', 'area_ha' => '1.5000'],
                ],
                'batches' => [self::batch('active', 'B01', 'A', '1.5000', '2026-09-01', '2026-11-30', '10.500', 'maintenance')],
            ],
            [
                'key' => 'cikeas', 'name' => 'Kebun Cikeas', 'commodity' => 'Jagung', 'variety' => 'Bisi 18',
                'area_ha' => '10.0000', 'land_tenure' => 'partnership', 'latitude' => '-6.4920000', 'longitude' => '106.9380000',
                'address' => 'Lahan kemitraan Cikeas, skenario presentasi Farm IC', 'city' => 'Kabupaten Bogor',
                'district' => 'Gunung Putri', 'province' => 'Jawa Barat', 'target_volume_ton' => '64.000', 'harvest_offset' => 110,
                'blocks' => [
                    ['code' => 'B01', 'name' => 'Blok Jagung Utama', 'area_ha' => '6.0000'],
                    ['code' => 'B02', 'name' => 'Blok Tanam Berikutnya', 'area_ha' => '4.0000'],
                ],
                'batches' => [
                    self::batch('active', 'B01', 'A', '4.0000', '2026-09-15', '2027-01-03', '25.600', 'maintenance'),
                    self::batch('planned', 'B02', 'P', '2.0000', '2026-11-15', null, '12.800', 'soil_preparation'),
                ],
            ],
            [
                'key' => 'cisarua', 'name' => 'Kebun Cisarua', 'commodity' => 'Tomat', 'variety' => 'Servo',
                'area_ha' => '3.5000', 'land_tenure' => 'owned', 'latitude' => '-6.6800000', 'longitude' => '106.9510000',
                'address' => 'Lahan percontohan sayuran Cisarua, skenario presentasi Farm IC', 'city' => 'Kabupaten Bogor',
                'district' => 'Cisarua', 'province' => 'Jawa Barat', 'target_volume_ton' => '70.000', 'harvest_offset' => 107,
                'blocks' => [
                    ['code' => 'B01', 'name' => 'Blok Tomat Selesai Panen', 'area_ha' => '2.0000'],
                    ['code' => 'B02', 'name' => 'Blok Persiapan Tomat', 'area_ha' => '1.5000'],
                ],
                'batches' => [
                    self::batch('harvested', 'B01', 'H', '1.2500', '2026-07-15', '2026-10-30', '25.000', 'harvest', '2026-10-30'),
                    self::batch('planned', 'B02', 'P', '0.7500', '2026-11-20', null, '15.000', 'basic_fertilizer'),
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function batch(string $status, string $block, string $label, string $area, string $plantingDate, ?string $harvestDate, string $weight, string $stage, ?string $harvestedAt = null): array
    {
        return [
            'key' => $status, 'block_code' => $block, 'subblock_label' => $label, 'reserved_area_ha' => $area,
            'planted_area_ha' => $status === 'planned' ? '0.0000' : $area, 'status' => $status,
            'planted_at' => $status === 'planned' ? null : $plantingDate, 'planned_planting_at' => $plantingDate,
            'estimated_harvest_at' => $harvestDate, 'harvested_at' => $harvestedAt,
            'estimated_weight_ton' => $weight, 'current_stage' => $stage,
        ];
    }
}
