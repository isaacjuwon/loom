<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Any team member can view projects.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Any team member can create projects.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Any team member can update projects.
     */
    public function update(User $user, Project $project): bool
    {
        return $user->belongsToTeam($project->team);
    }

    /**
     * Any team member can delete projects.
     */
    public function delete(User $user, Project $project): bool
    {
        return $user->belongsToTeam($project->team);
    }
}
