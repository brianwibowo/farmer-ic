<?php

namespace Tests\Fakes;

use App\Contracts\KebunAssignments;
use App\Models\User;

final class InMemoryKebunAssignments implements KebunAssignments
{
    /** @var array<int|string, list<string>> */
    private array $assignments = [];

    public function assign(User $user, string ...$ids): void
    {
        $this->assignments[$user->getKey()] = $ids;
    }

    public function idsFor(User $user): array
    {
        return $this->assignments[$user->getKey()] ?? [];
    }
}
