<?php

namespace App\Models\Farmer;

use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $activity_id
 * @property string|null $incident_category
 * @property string|null $severity
 * @property string|null $affected_area_ha
 * @property string|null $proposed_solution
 */
#[Fillable(['activity_id', 'incident_category', 'severity', 'affected_area_ha', 'proposed_solution'])]
class IncidentDetail extends ActivityDetail
{
    protected $table = 'incident_details';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'affected_area_ha' => 'decimal:4',
        ];
    }
}
