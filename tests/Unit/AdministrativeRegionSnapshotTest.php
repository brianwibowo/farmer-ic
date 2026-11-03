<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AdministrativeRegionSnapshotTest extends TestCase
{
    public function test_committed_snapshot_matches_its_checksum_and_declared_hierarchy(): void
    {
        $directory = dirname(__DIR__, 2).'/database/data/wilayah';
        $source = json_decode(file_get_contents($directory.'/source.json'), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame($source['csv_sha256'], hash_file('sha256', $directory.'/regions.csv'));

        $file = fopen($directory.'/regions.csv', 'r');
        $this->assertNotFalse($file);
        $regions = [];
        $counts = [1 => 0, 2 => 0, 3 => 0];
        try {
            $this->assertSame(['code', 'name', 'level', 'parent_code'], fgetcsv($file, escape: ''));
            while (($row = fgetcsv($file, escape: '')) !== false) {
                $this->assertCount(4, $row);
                [$code, $name, $level, $parent] = $row;
                $level = (int) $level;
                $this->assertArrayHasKey($level, $counts);
                $this->assertArrayNotHasKey($code, $regions);
                $this->assertNotSame('', $name);
                $regions[$code] = ['level' => $level, 'parent' => $parent];
                $counts[$level]++;
            }
        } finally {
            fclose($file);
        }

        $this->assertSame($source['counts'], $counts);
        foreach ($regions as $region) {
            if ($region['level'] === 1) {
                $this->assertSame('', $region['parent']);
            } else {
                $this->assertArrayHasKey($region['parent'], $regions);
                $this->assertSame($region['level'] - 1, $regions[$region['parent']]['level']);
            }
        }
    }
}
