<?php

namespace App\Support\Access;

use App\Models\Team;

/**
 * Policy subject for resources whose domain models are not integrated yet.
 *
 * Future callers must resolve the team and kebun from persisted relationships,
 * including the parent kebun for lahan, progress, harvest, and finance records.
 * Never construct this subject directly from unvalidated browser scope values.
 */
final readonly class ResourceAccess
{
    public function __construct(
        public string $resource,
        public Team $team,
        public ?string $kebunId = null,
    ) {}
}
