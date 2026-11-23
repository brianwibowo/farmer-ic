<?php

namespace App\Services;

use App\Models\Farm;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

class FarmImageService
{
    public function store(Farm $farm, UploadedFile $image): string
    {
        return app(MediaImageService::class)->store($image, 'farms/'.$farm->id, 'farm');
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
