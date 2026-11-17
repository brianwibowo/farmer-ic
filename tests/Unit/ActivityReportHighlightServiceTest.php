<?php

namespace Tests\Unit;

use App\Services\Activity\ActivityReportHighlightService;
use PHPUnit\Framework\TestCase;

class ActivityReportHighlightServiceTest extends TestCase
{
    public function test_formats_recorded_values_without_losing_units_or_precision(): void
    {
        $service = new ActivityReportHighlightService;
        $cases = [
            ['maintenance', ['area' => '1200', 'area_unit' => 'm²'], 'Luas dikerjakan: 1.200 m²'],
            ['soil_preparation', ['area' => '.5000', 'area_unit' => 'Ha'], 'Luas dikerjakan: 0,5 ha'],
            ['land_cleaning', ['area_ha' => '0.0005'], 'Luas dikerjakan: 0,0005 ha'],
            ['planting', ['seed_name' => 'Bisi 18', 'quantity' => '1e3', 'quantity_unit' => 'butir'], 'Benih: Bisi 18 · 1.000 butir'],
            ['planting', ['quantity_kg' => '12.500'], 'Jumlah benih: 12,5 kg'],
            ['harvest', ['weight' => '2.5000E+3', 'weight_unit' => 'kg'], 'Berat panen: 2.500 kg'],
            ['harvest', ['gross_weight_ton' => '2.750'], 'Berat panen: 2,75 ton'],
            ['incident', ['incident_category' => 'Hama', 'severity' => 'low'], 'Insiden: Hama · Ringan'],
            ['incident', ['severity' => 'Darurat'], 'Tingkat insiden: Darurat'],
        ];
        foreach ($cases as [$code, $values, $expected]) {
            $this->assertSame($expected, $service->format($code, $values));
        }
    }

    public function test_missing_invalid_and_unsupported_values_are_not_invented(): void
    {
        $service = new ActivityReportHighlightService;
        $this->assertNull($service->format('maintenance', []));
        $this->assertNull($service->format('planting', ['quantity' => 10]));
        $this->assertNull($service->format('harvest', ['weight' => '1e99999', 'weight_unit' => 'kg']));
        $this->assertNull($service->format('incident', ['incident_category' => ' ', 'severity' => null]));
        $this->assertNull($service->format('other', ['description' => 'Kondisi lahan']));
        $this->assertNull($service->format('unknown', ['area_ha' => '1.0000']));
    }
}
