<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class TeamPolicy extends ResourcePolicy
{
    protected string $resource = 'teams';

    protected ?string $teamPath = '';

    public function delete(User $user, Model $record): bool
    {
        return $record instanceof Team && ! $record->is_personal && parent::delete($user, $record);
    }

    public function addMember(User $user, Team $team): bool
    {
        return $this->permissions->scope($user, 'memberships.create') === 'any'
            && $this->permissions->withinTeam($user, $team);
    }

    public function updateMember(User $user, Team $team): bool
    {
        return $this->permissions->scope($user, 'memberships.update') === 'any'
            && $this->permissions->scope($user, 'memberships.view') === 'any'
            && $this->permissions->withinTeam($user, $team);
    }

    public function removeMember(User $user, Team $team): bool
    {
        return $this->permissions->scope($user, 'memberships.delete') === 'any'
            && $this->permissions->scope($user, 'memberships.view') === 'any'
            && $this->permissions->withinTeam($user, $team);
    }

    public function inviteMember(User $user, Team $team): bool
    {
        return $this->permissions->scope($user, 'team_invitations.create') === 'any'
            && $this->permissions->withinTeam($user, $team);
    }

    public function cancelInvitation(User $user, Team $team): bool
    {
        return $this->permissions->scope($user, 'team_invitations.delete') === 'any'
            && $this->permissions->scope($user, 'team_invitations.view') === 'any'
            && $this->permissions->withinTeam($user, $team);
    }

    public function leave(User $user, Team $team): bool
    {
        return $this->permissions->scope($user, 'teams.leave') === 'any'
            && ! $team->is_personal && ! $user->ownsTeam($team)
            && $this->permissions->withinTeam($user, $team);
    }
}
