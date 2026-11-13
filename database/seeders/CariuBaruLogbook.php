<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use RuntimeException;

/** Read the frozen spreadsheet exports; the database, not the CSV, is the live record. */
final class CariuBaruLogbook
{
    /** @return list<array<string, mixed>> */
    public static function rows(): array
    {
        $rows = [];
        // Typed areas are curated against these snapshots. A changed CSV
        // must be reviewed together with the mapping, not imported silently.
        foreach ([
            'oktober' => '2ab1aab93a5c2bdc22ee1cfb5531a4b91c18fb4080a0d092c6d33d76c104216c',
            'november' => 'b5f95093af09e24f7c9b61be3332e6f9c19c569898f0d2eb90fdd97744704be8',
        ] as $month => $checksum) {
            $path = database_path("seeders/fixtures/cariu-baru/{$month}-2026.csv");
            $snapshot = is_file($path) ? file_get_contents($path) : false;
            if ($snapshot === false || ! hash_equals($checksum, hash('sha256', str_replace("\r\n", "\n", $snapshot)))) {
                throw new RuntimeException('Snapshot logbook Cariu berubah atau tidak tersedia; tinjau pemetaan sebelum impor.');
            }
            $file = fopen($path, 'rb');
            if ($file === false) {
                throw new RuntimeException('Snapshot logbook Cariu tidak tersedia: '.$path);
            }
            try {
                $header = fgetcsv($file, 0, ',', '"', '');
                if ($header === false || count($header) !== 12 || $header[4] !== 'Kegiatan') {
                    throw new RuntimeException('Format snapshot logbook Cariu tidak dikenali.');
                }
                $date = $time = '';
                $number = 1;
                while (($values = fgetcsv($file, 0, ',', '"', '')) !== false) {
                    $number++;
                    if (count($values) !== 12) {
                        throw new RuntimeException("Kolom logbook {$month} baris {$number} tidak lengkap.");
                    }
                    $values = array_map(fn ($value) => trim((string) $value), $values);
                    if ($values[1] !== '') {
                        $date = $values[1];
                        $time = '';
                    }
                    if ($values[2] !== '') {
                        $time = $values[2];
                    }
                    if ($values[4] === '') {
                        continue;
                    }
                    if (! preg_match('/^(\d{1,2}) (Oktober|November) 2026$/u', $date, $parts)
                        || ! preg_match('/^(\d{1,2})[.:](\d{2})\s*–\s*(\d{1,2})\.?[.:](\d{2})$/u', $time, $times)
                        || ! ctype_digit($values[5])) {
                        throw new RuntimeException("Tanggal, waktu, atau SDM logbook {$month} baris {$number} tidak valid.");
                    }
                    $workDate = CarbonImmutable::create(2026, $parts[2] === 'Oktober' ? 10 : 11, (int) $parts[1], 0, 0, 0, 'Asia/Jakarta')->toDateString();
                    $start = sprintf('%02d:%02d', (int) $times[1], (int) $times[2]);
                    $end = sprintf('%02d:%02d', (int) $times[3], (int) $times[4]);
                    if ($start >= $end) {
                        throw new RuntimeException("Rentang waktu logbook {$month} baris {$number} tidak valid.");
                    }
                    $rows[] = [
                        'source' => "{$month}:{$number}", 'date' => $workDate, 'source_date' => $date,
                        'time' => $time, 'start' => $start, 'end' => $end, 'place' => $values[3],
                        'activity' => $values[4], 'workers' => (int) $values[5], 'result' => $values[6],
                        'obstacle' => $values[7], 'solution' => $values[8], 'action' => $values[9],
                        'documentation' => $values[10], 'initials' => $values[11],
                    ];
                }
            } finally {
                fclose($file);
            }
        }
        if (count($rows) !== 42) {
            throw new RuntimeException('Snapshot logbook Cariu berubah atau tidak lengkap; impor dibatalkan.');
        }

        return $rows;
    }

    /** @param array<string, mixed> $row */
    public static function notes(array $row): string
    {
        $empty = fn (string $value): string => $value === '' ? '(tidak dicantumkan)' : $value;

        return "SUMBER SPREADSHEET {$row['source']} — identitas Petugas pencatat adalah simulasi.\n"
            ."Tanggal sumber: {$row['source_date']}\nWaktu sumber: {$row['time']}\n"
            .'Tempat pada baris: '.$empty($row['place'])."\nKegiatan: {$row['activity']}\nSDM: {$row['workers']}\n"
            .'Hasil: '.$empty($row['result'])."\nKendala: ".$empty($row['obstacle'])
            ."\nSolusi: ".$empty($row['solution'])."\nTindakan: ".$empty($row['action'])
            ."\nDokumentasi (referensi teks, bukan foto): ".$empty($row['documentation'])
            ."\nParaf: ".$empty($row['initials']);
    }
}
