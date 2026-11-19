<?php

namespace App\Support;

use App\Models\ActivityPhoto;
use Illuminate\Support\Facades\Storage;

class ActivityPhotoFile
{
    public static function available(ActivityPhoto $photo): bool
    {
        if (! str_starts_with($photo->image_url, 'farms/'.$photo->activity->farm_id.'/'.$photo->activity_id.'/')
            || str_contains($photo->image_url, '..')) {
            return false;
        }

        return Storage::disk('activity')->exists($photo->image_url);
    }
}
