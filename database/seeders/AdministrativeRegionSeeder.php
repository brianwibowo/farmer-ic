<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AdministrativeRegionSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/wilayah/regions.csv');
        $metadata = file_get_contents(database_path('data/wilayah/source.json'));
        if ($metadata === false) {
            throw new RuntimeException('Metadata snapshot wilayah belum tersedia.');
        }
        $source = json_decode($metadata, true, flags: JSON_THROW_ON_ERROR);
        $csv = file_get_contents($path);
        if ($csv === false) {
            throw new RuntimeException('Snapshot wilayah belum dapat dibaca.');
        }
        $normalizedCsv = str_replace("\r\n", "\n", $csv);
        if (hash('sha256', $normalizedCsv) !== $source['csv_sha256']) {
            throw new RuntimeException('Snapshot wilayah tidak sesuai checksum.');
        }
        DB::transaction(function () use ($path): void {
            $file = fopen($path, 'r');
            if ($file === false) {
                throw new RuntimeException('Snapshot wilayah belum dapat dibaca.');
            }
            try {
                fgetcsv($file, escape: '');
                $rows = [];
                while (($row = fgetcsv($file, escape: '')) !== false) {
                    $rows[] = ['code' => $row[0], 'name' => $row[1], 'level' => (int) $row[2], 'parent_code' => $row[3] ?: null];
                }
                // Insert parents before children; never remove existing reference codes.
                foreach ([1, 2, 3] as $level) {
                    foreach (array_chunk(array_values(array_filter($rows, fn ($row) => $row['level'] === $level)), 500) as $chunk) {
                        DB::table('administrative_regions')->upsert($chunk, ['code'], ['name', 'level', 'parent_code']);
                    }
                }
            } finally {
                fclose($file);
            }
        });
    }
}
