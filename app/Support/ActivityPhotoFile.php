<?php

namespace App\Support;

use App\Models\ActivityPhoto;
use Illuminate\Support\Facades\Storage;

class ActivityPhotoFile
{
    public static function available(ActivityPhoto $photo): bool
    {
        if ($photo->image_url === '/images/kebun-placeholder.svg') {
            return is_file(public_path('images/kebun-placeholder.svg'));
        }

        if (! str_starts_with($photo->image_url, 'task-activities/'.$photo->activity_id.'/')
            || str_contains($photo->image_url, '..')) {
            return false;
        }

        return Storage::disk('local')->exists($photo->image_url);
    }
}
