<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    /**
     * Any team member of the project's team can view tasks.
     */
    public function viewAny(User $user, Project $project): bool
    {
        return $user->belongsToTeam($project->team);
    }

    /**
     * Any team member can create tasks.
     */
    public function create(User $user, Project $project): bool
    {
        return $user->belongsToTeam($project->team);
    }

    /**
     * Any team member can update tasks.
     */
    public function update(User $user, Task $task): bool
    {
        return $user->belongsToTeam($task->project->team);
    }

    /**
     * Any team member can delete tasks.
     */
    public function delete(User $user, Task $task): bool
    {
        return $user->belongsToTeam($task->project->team);
    }
}
