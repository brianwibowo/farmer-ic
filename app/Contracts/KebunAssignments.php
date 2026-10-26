<?php

namespace App\Contracts;

use App\Models\User;

interface KebunAssignments
{
    /**
     * Return all assigned kebun IDs from trusted backend relationships.
     *
     * IDs must be globally unique strings, including assignments across teams.
     * Never derive these assignments from request input or created_by.
     *
     * @return list<string>
     */
    public function idsFor(User $user): array;
}
