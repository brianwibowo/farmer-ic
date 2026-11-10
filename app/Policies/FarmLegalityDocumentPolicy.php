<?php

namespace App\Policies;

use App\Models\Farm;
use App\Models\FarmLegality;

class FarmLegalityDocumentPolicy extends ResourcePolicy
{
    protected string $resource = 'farm_legality_documents';

    protected ?string $farmPath = 'legality.farm';

    // Farm is accepted for an atomic new legality + documents submission.
    protected array $parents = [Farm::class, FarmLegality::class];
}
