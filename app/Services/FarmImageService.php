<?php

namespace App\Services;

use App\Models\Farm;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class FarmImageService
{
    public function store(Farm $farm, UploadedFile $image): string
    {
        $path = 'farms/'.$farm->id.'/'.Str::uuid().'.'.$image->extension();
        try {
            if (Storage::disk('farm')->putFileAs(dirname($path), $image, basename($path)) !== $path) {
                throw new RuntimeException('Foto lahan tidak berhasil disimpan.');
            }
        } catch (Throwable $exception) {
            $this->delete($path);
            report($exception);
            throw \Illuminate\Validation\ValidationException::withMessages([
                'image' => 'Foto lahan tidak berhasil disimpan. Silakan coba lagi.',
            ]);
        }

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path === null) {
            return;
        }
        try {
            Storage::disk('farm')->delete($path);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
