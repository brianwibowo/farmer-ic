<?php

namespace App\Models;

use App\Concerns\HasVisibleScope;
use Illuminate\Database\Eloquent\Model;

class AdministrativeRegion extends Model
{
    use HasVisibleScope;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;
}
