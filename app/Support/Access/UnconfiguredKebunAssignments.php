<?php

namespace App\Support\Access;

use App\Contracts\KebunAssignments;
use App\Models\User;

/**
 * Deny own-scoped record access until persisted kebun assignments are integrated.
 */
final class UnconfiguredKebunAssignments implements KebunAssignments
{
    public function idsFor(User $user): array
    {
        return [];
    }
}
