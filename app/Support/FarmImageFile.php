<?php

namespace App\Support;

use App\Models\Farm;

class FarmImageFile
{
    public static function valid(Farm $farm): bool
    {
        return is_string($farm->image_url)
            && preg_match('#^farms/'.preg_quote($farm->id, '#').'/[a-zA-Z0-9_-]+\.(jpg|jpeg|png|webp)$#D', $farm->image_url) === 1;
    }

    public static function url(Farm $farm): ?string
    {
        return self::valid($farm) ? route('farms.image', $farm, false) : null;
    }
}
