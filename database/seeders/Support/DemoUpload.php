<?php

namespace Database\Seeders\Support;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Local reference photograph copied into the configured operational disk. */
class DemoUpload
{
    public static function image(string $disk, string $path): string
    {
        $storage = Storage::disk($disk);
        if (! $storage->exists($path)) {
            $stream = fopen(public_path('images/farm_fields/jagung_1.jpg'), 'rb');
            if ($stream === false) {
                throw new RuntimeException('Foto referensi demo tidak tersedia.');
            }
            try {
                if (! $storage->put($path, $stream)) {
                    throw new RuntimeException('Foto demo tidak berhasil disimpan.');
                }
            } finally {
                fclose($stream);
            }
        }

        return $path;
    }
}
