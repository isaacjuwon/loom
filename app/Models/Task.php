<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['title', 'description', 'status', 'priority', 'project_id', 'assigned_to', 'due_date', 'sort_order'])]
class Task extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Get the project this task belongs to.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user assigned to this task.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Determine if the task is overdue (past due date and not done).
     */
    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->due_date->isPast()
            && $this->status !== TaskStatus::Done;
    }

    /**
     * Scope to tasks with a given status.
     *
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    public function scopeStatus(Builder $query, TaskStatus|string $status): Builder
    {
        $value = $status instanceof TaskStatus ? $status->value : $status;

        return $query->where('status', $value);
    }

    /**
     * Scope to tasks assigned to a specific user.
     *
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    public function scopeOfAssignee(Builder $query, ?int $userId): Builder
    {
        return $query->when($userId, fn (Builder $q) => $q->where('assigned_to', $userId));
    }

    /**
     * Scope to overdue tasks.
     *
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('due_date')->whereDate('due_date', '<', now());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'due_date' => 'date',
        ];
    }
}
