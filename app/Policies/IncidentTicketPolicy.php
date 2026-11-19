<?php

namespace App\Policies;

use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\IncidentTicket;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class IncidentTicketPolicy extends ResourcePolicy
{
    protected string $resource = 'incident_tickets';

    protected ?string $farmPath = 'farm';

    protected array $parents = [Farm::class, FarmActivity::class];

    public function respond(User $user, IncidentTicket $ticket): bool
    {
        return $this->permissions->scope($user, $this->resource.'.update') === 'any'
            && $this->update($user, $ticket);
    }

    public function resolveDirectly(User $user, IncidentTicket $ticket): bool
    {
        return $this->permissions->scope($user, $this->resource.'.update') === 'any'
            && $this->update($user, $ticket);
    }

    public function resolveByFollowUp(User $user, IncidentTicket $ticket): bool
    {
        if (! $this->update($user, $ticket)) {
            return false;
        }
        $farm = $ticket->farm;

        return $farm instanceof Farm
            && Gate::forUser($user)->allows('create', [FarmActivity::class, $farm]);
    }

    public function reopen(User $user, IncidentTicket $ticket): bool
    {
        return $this->update($user, $ticket);
    }
}
