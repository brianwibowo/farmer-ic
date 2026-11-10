<?php

namespace Database\Seeders;

use App\Models\Team;
use Ramsey\Uuid\Uuid;

/**
 * Additive fixtures for exercising the Owner dashboard's regional table.
 * These records are deliberately separate from the six baseline demo farms.
 */
final class DemoOwnerDashboardCoverageData
{
    public static function id(Team $team, string $key): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'farmer-ic:owner-dashboard-coverage:v1:'.$team->id.':'.$key)->toString();
    }

    /** @return list<array<string, string|int|null>> */
    public static function farms(): array
    {
        $farms = [
            ['key' => 'pabuaran', 'name' => 'Kebun Pabuaran', 'city' => 'Kabupaten Bogor', 'district' => 'Kemang', 'area' => '5.5000', 'latitude' => '-6.4862000', 'longitude' => '106.7269000', 'tenure' => 'owned', 'commodity' => 'Jagung', 'variety' => 'Bisi 18', 'status' => 'active', 'planted_area' => '3.5000', 'weight' => '140.000', 'completed' => 4],
            ['key' => 'leuwiliang', 'name' => 'Kebun Leuwiliang', 'city' => 'Kabupaten Bogor', 'district' => 'Leuwiliang', 'area' => '4.0000', 'latitude' => '-6.6110000', 'longitude' => '106.6314000', 'tenure' => 'rented', 'commodity' => 'Rumput', 'variety' => 'Pakchong', 'status' => 'active', 'planted_area' => '2.7500', 'weight' => '275.000', 'completed' => 3],
            ['key' => 'pacet', 'name' => 'Kebun Pacet', 'city' => 'Kabupaten Cianjur', 'district' => 'Pacet', 'area' => '7.0000', 'latitude' => '-6.7371000', 'longitude' => '107.0498000', 'tenure' => 'partnership', 'commodity' => 'Sorgum', 'variety' => 'Bioguma 1', 'status' => 'active', 'planted_area' => '4.0000', 'weight' => '315.000', 'completed' => 5],
            ['key' => 'cugenang', 'name' => 'Kebun Cugenang', 'city' => 'Kabupaten Cianjur', 'district' => 'Cugenang', 'area' => '6.0000', 'latitude' => '-6.8241000', 'longitude' => '107.0872000', 'tenure' => 'owned', 'commodity' => 'Jagung', 'variety' => 'Bisi 18', 'status' => 'active', 'planted_area' => '4.2500', 'weight' => '240.000', 'completed' => 2],
            ['key' => 'kadupandak', 'name' => 'Kebun Kadupandak', 'city' => 'Kabupaten Cianjur', 'district' => 'Kadupandak', 'area' => '5.0000', 'latitude' => '-7.1030000', 'longitude' => '107.1695000', 'tenure' => 'rented', 'commodity' => 'Rumput', 'variety' => 'Pakchong', 'status' => 'harvested', 'planted_area' => '3.0000', 'weight' => '300.000', 'completed' => 6],
            ['key' => 'cikembar', 'name' => 'Kebun Cikembar', 'city' => 'Kabupaten Sukabumi', 'district' => 'Cikembar', 'area' => '8.0000', 'latitude' => '-6.9468000', 'longitude' => '106.7897000', 'tenure' => 'partnership', 'commodity' => 'Rumput', 'variety' => 'Pakchong', 'status' => 'active', 'planted_area' => '5.0000', 'weight' => '800.000', 'completed' => 4],
            ['key' => 'jampang', 'name' => 'Kebun Jampang', 'city' => 'Kabupaten Sukabumi', 'district' => 'Jampang Tengah', 'area' => '5.0000', 'latitude' => '-7.0392000', 'longitude' => '106.7355000', 'tenure' => 'owned', 'commodity' => 'Jagung', 'variety' => 'Bisi 18', 'status' => 'active', 'planted_area' => '3.5000', 'weight' => '200.000', 'completed' => 3],
            ['key' => 'pelabuhanratu', 'name' => 'Kebun Pelabuhanratu', 'city' => 'Kabupaten Sukabumi', 'district' => 'Pelabuhanratu', 'area' => '4.0000', 'latitude' => '-6.9875000', 'longitude' => '106.5511000', 'tenure' => 'rented', 'commodity' => 'Sorgum', 'variety' => 'Bioguma 1', 'status' => 'active', 'planted_area' => '2.5000', 'weight' => '180.000', 'completed' => 2],
            ['key' => 'lembang', 'name' => 'Kebun Lembang', 'city' => 'Kabupaten Bandung Barat', 'district' => 'Lembang', 'area' => '3.5000', 'latitude' => '-6.8161000', 'longitude' => '107.6171000', 'tenure' => 'owned', 'commodity' => 'Rumput', 'variety' => 'Pakchong', 'status' => 'active', 'planted_area' => '2.2500', 'weight' => '350.000', 'completed' => 5],
            ['key' => 'padalarang', 'name' => 'Kebun Padalarang', 'city' => 'Kabupaten Bandung Barat', 'district' => 'Padalarang', 'area' => '4.5000', 'latitude' => '-6.8377000', 'longitude' => '107.4832000', 'tenure' => 'partnership', 'commodity' => 'Jagung', 'variety' => 'Bisi 18', 'status' => 'active', 'planted_area' => '3.0000', 'weight' => '180.000', 'completed' => 1],
            ['key' => 'cikalong-wetan', 'name' => 'Kebun Cikalong Wetan', 'city' => 'Kabupaten Bandung Barat', 'district' => 'Cikalong Wetan', 'area' => '6.0000', 'latitude' => '-6.7670000', 'longitude' => '107.3991000', 'tenure' => 'rented', 'commodity' => 'Sorgum', 'variety' => 'Bioguma 1', 'status' => 'active', 'planted_area' => '3.5000', 'weight' => '270.000', 'completed' => 4],
            ['key' => 'tarogong', 'name' => 'Kebun Tarogong', 'city' => 'Kabupaten Garut', 'district' => 'Tarogong Kidul', 'area' => '4.0000', 'latitude' => '-7.2181000', 'longitude' => '107.9013000', 'tenure' => 'owned', 'commodity' => 'Jagung', 'variety' => 'Bisi 18', 'status' => 'active', 'planted_area' => '2.7500', 'weight' => '160.000', 'completed' => 5],
            ['key' => 'bayongbong', 'name' => 'Kebun Bayongbong', 'city' => 'Kabupaten Garut', 'district' => 'Bayongbong', 'area' => '5.5000', 'latitude' => '-7.3178000', 'longitude' => '107.7859000', 'tenure' => 'partnership', 'commodity' => 'Rumput', 'variety' => 'Pakchong', 'status' => 'active', 'planted_area' => '4.0000', 'weight' => '550.000', 'completed' => 2],
            ['key' => 'cikajang', 'name' => 'Kebun Cikajang', 'city' => 'Kabupaten Garut', 'district' => 'Cikajang', 'area' => '7.0000', 'latitude' => '-7.3853000', 'longitude' => '107.7810000', 'tenure' => 'rented', 'commodity' => 'Sorgum', 'variety' => 'Bioguma 1', 'status' => 'harvested', 'planted_area' => '4.5000', 'weight' => '315.000', 'completed' => 6],
        ];

        $relatedParties = ['TNI AL', 'TNI AU', 'TNI AD', 'Polri', 'PT Bumi Hijauan', 'Kementan'];

        return array_map(function (array $farm, int $index) use ($relatedParties): array {
            $farm['tenure_party'] = match ($farm['tenure']) {
                // The current Farm contract stores a related party only for
                // rented or partnership land. Owned land intentionally keeps
                // this empty instead of introducing legacy-style data.
                'owned' => null,
                // A deterministic rotation gives the demo a realistic mix,
                // guarantees every listed institution appears, and keeps each
                // local seed/test run reproducible.
                'partnership', 'rented' => $relatedParties[$index % count($relatedParties)],
            };

            return $farm;
        }, $farms, array_keys($farms));
    }
}
