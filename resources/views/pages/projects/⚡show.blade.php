<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Livewire\Concerns\HasFilters;
use App\Models\Project;
use App\Models\Task;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Project')] class extends Component {
    use HasFilters;

    public Project $project;

    // ── Filter state ──────────────────────────────────────────────────────────
    public string $filterSearch   = '';
    public string $filterStatus   = '';
    public string $filterPriority = '';
    public ?int   $filterAssignee = null;
    public bool   $filterOverdue  = false;

    /** @var array<string, mixed> */
    protected array $filterDefaults = [
        'filterSearch'   => '',
        'filterStatus'   => '',
        'filterPriority' => '',
        'filterAssignee' => null,
        'filterOverdue'  => false,
    ];

    /** Keys of #[Computed] properties to bust when filters change */
    protected array $computedCacheKeys = ['tasks'];

    // ── Task form (shared create / edit) ──────────────────────────────────────
    public string  $title       = '';
    public string  $description = '';
    public string  $status      = 'todo';
    public string  $priority    = 'medium';
    public ?int    $assigned_to = null;
    public ?string $due_date    = null;

    public ?int $editingTaskId = null;

    public function mount(Project $project): void
    {
        Gate::authorize('viewAny', [Task::class, $project]);

        $this->project = $project;
    }

    /**
     * Tasks grouped by status, respecting active filters.
     * Uses Filterable scopes from App\Concerns\Filterable.
     *
     * @return array<string, \Illuminate\Support\Collection<int, Task>>
     */
    #[Computed]
    public function tasks(): array
    {
        $grouped = [];

        foreach (TaskStatus::cases() as $s) {
            $grouped[$s->value] = collect();
        }

        $query = $this->project->tasks()
            ->with('assignee')
            ->filterLike('title', $this->filterSearch)
            ->filterWhere('priority', $this->filterPriority)
            ->ofAssignee($this->filterAssignee)
            ->when($this->filterOverdue, fn ($q) => $q->overdue())
            ->orderBy('sort_order')
            ->orderBy('due_date');

        // When a status filter is active, only load that column
        if ($this->filterStatus !== '') {
            $query->filterWhere('status', $this->filterStatus);
        }

        $query->get()->each(function (Task $task) use (&$grouped) {
            $grouped[$task->status->value][] = $task;
        });

        return $grouped;
    }

    #[Computed]
    public function teamMembers()
    {
        return $this->project->team->members()->get();
    }

    #[Computed]
    public function statuses(): array
    {
        return collect(TaskStatus::cases())
            ->map(fn (TaskStatus $s) => ['value' => $s->value, 'label' => $s->label()])
            ->toArray();
    }

    #[Computed]
    public function priorities(): array
    {
        return collect(TaskPriority::cases())
            ->map(fn (TaskPriority $p) => ['value' => $p->value, 'label' => $p->label()])
            ->toArray();
    }

    // ── Sorting ───────────────────────────────────────────────────────────────

    public function handleSort(int $id, int $position, string $columnStatus): void
    {
        $task = $this->project->tasks()->findOrFail($id);

        Gate::authorize('update', $task);

        $newStatus = TaskStatus::from($columnStatus);

        DB::transaction(function () use ($task, $newStatus, $position) {
            $this->project->tasks()
                ->where('status', $newStatus->value)
                ->where('id', '!=', $task->id)
                ->orderBy('sort_order')
                ->get()
                ->each(function (Task $sibling, int $index) use ($position) {
                    $sibling->update([
                        'sort_order' => $index >= $position ? $index + 1 : $index,
                    ]);
                });

            $task->update([
                'status'     => $newStatus,
                'sort_order' => $position,
            ]);
        });

        unset($this->tasks);
    }

    // ── CRUD ──────────────────────────────────────────────────────────────────

    public function createTask(): void
    {
        Gate::authorize('create', [Task::class, $this->project]);

        $validated = $this->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status'      => ['required', 'string', 'in:todo,in_progress,done'],
            'priority'    => ['required', 'string', 'in:low,medium,high'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'due_date'    => ['nullable', 'date'],
        ]);

        $validated['sort_order'] = $this->project->tasks()
            ->where('status', $validated['status'])
            ->max('sort_order') + 1;

        $this->project->tasks()->create($validated);

        $this->reset('title', 'description', 'status', 'priority', 'assigned_to', 'due_date');
        $this->dispatch('close-modal', name: 'create-task');

        Flux::toast(variant: 'success', text: __('Task created.'));

        unset($this->tasks);
    }

    public function editTask(int $taskId): void
    {
        $task = $this->project->tasks()->findOrFail($taskId);

        Gate::authorize('update', $task);

        $this->editingTaskId = $task->id;
        $this->title         = $task->title;
        $this->description   = $task->description ?? '';
        $this->status        = $task->status->value;
        $this->priority      = $task->priority->value;
        $this->assigned_to   = $task->assigned_to;
        $this->due_date      = $task->due_date?->format('Y-m-d');

        $this->dispatch('open-modal', name: 'edit-task');
    }

    public function updateTask(): void
    {
        $task = $this->project->tasks()->findOrFail($this->editingTaskId);

        Gate::authorize('update', $task);

        $validated = $this->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status'      => ['required', 'string', 'in:todo,in_progress,done'],
            'priority'    => ['required', 'string', 'in:low,medium,high'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'due_date'    => ['nullable', 'date'],
        ]);

        $task->update($validated);

        $this->reset('title', 'description', 'status', 'priority', 'assigned_to', 'due_date', 'editingTaskId');
        $this->dispatch('close-modal', name: 'edit-task');

        Flux::toast(variant: 'success', text: __('Task updated.'));

        unset($this->tasks);
    }

    public function deleteTask(int $taskId): void
    {
        $task = $this->project->tasks()->findOrFail($taskId);

        Gate::authorize('delete', $task);

        $task->delete();

        $this->dispatch('close-modal', name: 'edit-task');

        Flux::toast(variant: 'success', text: __('Task deleted.'));

        unset($this->tasks);
    }

    public function cancelEdit(): void
    {
        $this->reset('title', 'description', 'status', 'priority', 'assigned_to', 'due_date', 'editingTaskId');
        $this->dispatch('close-modal', name: 'edit-task');
    }
}; ?>

<x-layouts::app :title="$project->name">
    <div class="flex h-full w-full flex-1 flex-col gap-6 p-6">

        {{-- ── Header ──────────────────────────────────────────────────────── --}}
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="mb-1 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                    <a href="{{ route('projects.index') }}" wire:navigate
                       class="hover:text-zinc-700 dark:hover:text-zinc-200">{{ __('Projects') }}</a>
                    <flux:icon name="chevron-right" class="size-3" />
                    <span>{{ $project->name }}</span>
                </div>
                <flux:heading size="xl">{{ $project->name }}</flux:heading>
                @if ($project->description)
                    <flux:subheading>{{ $project->description }}</flux:subheading>
                @endif
            </div>

            <div class="flex items-center gap-2">
                {{-- Filter trigger --}}
                <flux:modal.trigger name="task-filters">
                    <flux:button
                        variant="ghost"
                        icon="funnel"
                        size="sm"
                        data-test="filter-button"
                    >
                        {{ __('Filters') }}
                        @if ($this->hasActiveFilters)
                            <flux:badge color="blue" size="sm" class="ml-1">
                                {{ $this->activeFilterCount }}
                            </flux:badge>
                        @endif
                    </flux:button>
                </flux:modal.trigger>

                <flux:button variant="ghost" icon="pencil" size="sm"
                    :href="route('projects.edit', $project)" wire:navigate>
                    {{ __('Edit') }}
                </flux:button>

                <flux:modal.trigger name="create-task">
                    <flux:button variant="primary" icon="plus" data-test="create-task-button">
                        {{ __('New task') }}
                    </flux:button>
                </flux:modal.trigger>
            </div>
        </div>

        {{-- ── Active filter chips ─────────────────────────────────────────── --}}
        @if ($this->hasActiveFilters)
            <div wire:transition class="flex flex-wrap items-center gap-2">
                @if ($filterSearch !== '')
                    <flux:badge color="zinc" size="sm">
                        {{ __('Search: ":q"', ['q' => $filterSearch]) }}
                        <button wire:click="$set('filterSearch', '')" class="ml-1 opacity-60 hover:opacity-100">×</button>
                    </flux:badge>
                @endif
                @if ($filterStatus !== '')
                    <flux:badge color="zinc" size="sm">
                        {{ \App\Enums\TaskStatus::from($filterStatus)->label() }}
                        <button wire:click="$set('filterStatus', '')" class="ml-1 opacity-60 hover:opacity-100">×</button>
                    </flux:badge>
                @endif
                @if ($filterPriority !== '')
                    <flux:badge color="zinc" size="sm">
                        {{ \App\Enums\TaskPriority::from($filterPriority)->label() }}
                        <button wire:click="$set('filterPriority', '')" class="ml-1 opacity-60 hover:opacity-100">×</button>
                    </flux:badge>
                @endif
                @if ($filterAssignee !== null)
                    @php $assigneeName = $this->teamMembers->firstWhere('id', $filterAssignee)?->name ?? '#'.$filterAssignee; @endphp
                    <flux:badge color="zinc" size="sm">
                        {{ $assigneeName }}
                        <button wire:click="$set('filterAssignee', null)" class="ml-1 opacity-60 hover:opacity-100">×</button>
                    </flux:badge>
                @endif
                @if ($filterOverdue)
                    <flux:badge color="red" size="sm">
                        {{ __('Overdue') }}
                        <button wire:click="$set('filterOverdue', false)" class="ml-1 opacity-60 hover:opacity-100">×</button>
                    </flux:badge>
                @endif

                <flux:button variant="ghost" size="xs" wire:click="resetFilters" data-test="clear-filters">
                    {{ __('Clear all') }}
                </flux:button>
            </div>
        @endif

        {{-- ── Kanban board ─────────────────────────────────────────────────── --}}
        <div class="grid gap-4 md:grid-cols-3">
            @foreach (\App\Enums\TaskStatus::cases() as $column)
                @php
                    $columnTasks = $this->tasks[$column->value];
                    // Hide empty columns when a status filter is active for a different column
                    $isFiltered = $filterStatus !== '' && $filterStatus !== $column->value;
                @endphp

                <div
                    wire:transition
                    class="flex flex-col gap-3 rounded-xl border border-zinc-200 bg-zinc-50 p-4
                           dark:border-zinc-700 dark:bg-zinc-800/50
                           {{ $isFiltered ? 'opacity-40' : '' }}"
                >
                    {{-- Column header --}}
                    <div class="flex items-center gap-2">
                        <flux:badge :color="$column->color()" size="sm">{{ $column->label() }}</flux:badge>
                        <span class="column-count text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $columnTasks->count() }}
                        </span>
                    </div>

                    {{-- Sortable list --}}
                    <div
                        wire:sort="handleSort"
                        wire:sort:group="tasks"
                        wire:sort:group-id="{{ $column->value }}"
                        class="flex min-h-16 flex-col gap-2"
                    >
                        @foreach ($columnTasks as $task)
                            <x-task-card :task="$task" />
                        @endforeach

                        @if ($columnTasks->isEmpty())
                            <p wire:transition
                               class="py-6 text-center text-xs text-zinc-400 dark:text-zinc-600">
                                {{ $this->hasActiveFilters ? __('No matching tasks') : __('No tasks') }}
                            </p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- ── Project members panel ───────────────────────────────────────── --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <flux:heading>{{ __('Project members') }}</flux:heading>
                    <flux:subheading>{{ __('Team members assigned to this project') }}</flux:subheading>
                </div>
            </div>
            <livewire:project-members :project="$project" />
        </div>
    </div>

    {{-- ── Filters modal ────────────────────────────────────────────────────── --}}
    <flux:modal name="task-filters" focusable class="max-w-sm">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Filter tasks') }}</flux:heading>
                <flux:subheading>{{ __('Narrow down tasks by any combination of fields.') }}</flux:subheading>
            </div>

            <flux:input
                wire:model.live.debounce.300ms="filterSearch"
                :label="__('Search title')"
                icon="magnifying-glass"
                :placeholder="__('Type to search…')"
                data-test="filter-search"
            />

            <flux:select wire:model.live="filterStatus" :label="__('Status')" data-test="filter-status">
                <flux:select.option value="">{{ __('Any status') }}</flux:select.option>
                @foreach ($this->statuses as $s)
                    <flux:select.option :value="$s['value']">{{ $s['label'] }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="filterPriority" :label="__('Priority')" data-test="filter-priority">
                <flux:select.option value="">{{ __('Any priority') }}</flux:select.option>
                @foreach ($this->priorities as $p)
                    <flux:select.option :value="$p['value']">{{ $p['label'] }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="filterAssignee" :label="__('Assignee')" data-test="filter-assignee">
                <flux:select.option :value="null">{{ __('Anyone') }}</flux:select.option>
                @foreach ($this->teamMembers as $member)
                    <flux:select.option :value="$member->id">{{ $member->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex items-center justify-between rounded-lg border border-zinc-200 px-4 py-3
                        dark:border-zinc-700">
                <flux:label>{{ __('Overdue only') }}</flux:label>
                <flux:switch wire:model.live="filterOverdue" data-test="filter-overdue" />
            </div>

            <div class="flex items-center justify-between pt-1">
                <flux:button
                    variant="ghost"
                    wire:click="resetFilters"
                    :disabled="! $this->hasActiveFilters"
                    data-test="filter-reset"
                >
                    {{ __('Reset') }}
                </flux:button>
                <flux:modal.close>
                    <flux:button variant="primary">{{ __('Done') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    {{-- ── Create task modal ───────────────────────────────────────────────── --}}
    <flux:modal name="create-task" :show="$errors->isNotEmpty() && ! $editingTaskId" focusable class="max-w-lg">
        <form wire:submit="createTask" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('New task') }}</flux:heading>
                <flux:subheading>{{ __('Add a task to this project') }}</flux:subheading>
            </div>

            <flux:input wire:model="title" :label="__('Title')" required autofocus data-test="task-title" />
            <flux:textarea wire:model="description" :label="__('Description')" rows="2" data-test="task-description" />

            <div class="grid grid-cols-2 gap-4">
                <flux:select wire:model="status" :label="__('Status')" data-test="task-status">
                    @foreach ($this->statuses as $s)
                        <flux:select.option :value="$s['value']">{{ $s['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="priority" :label="__('Priority')" data-test="task-priority">
                    @foreach ($this->priorities as $p)
                        <flux:select.option :value="$p['value']">{{ $p['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:select wire:model="assigned_to" :label="__('Assign to')" data-test="task-assignee">
                    <flux:select.option :value="null">{{ __('Unassigned') }}</flux:select.option>
                    @foreach ($this->teamMembers as $member)
                        <flux:select.option :value="$member->id">{{ $member->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="due_date" :label="__('Due date')" type="date" data-test="task-due-date" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit" data-test="task-create-submit">
                    {{ __('Create task') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- ── Edit task modal ─────────────────────────────────────────────────── --}}
    <flux:modal name="edit-task" :show="$errors->isNotEmpty() && $editingTaskId" focusable class="max-w-lg">
        <form wire:submit="updateTask" class="space-y-5">
            <flux:heading size="lg">{{ __('Edit task') }}</flux:heading>

            <flux:input wire:model="title" :label="__('Title')" required autofocus data-test="task-edit-title" />
            <flux:textarea wire:model="description" :label="__('Description')" rows="2" data-test="task-edit-description" />

            <div class="grid grid-cols-2 gap-4">
                <flux:select wire:model="status" :label="__('Status')" data-test="task-edit-status">
                    @foreach ($this->statuses as $s)
                        <flux:select.option :value="$s['value']">{{ $s['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="priority" :label="__('Priority')" data-test="task-edit-priority">
                    @foreach ($this->priorities as $p)
                        <flux:select.option :value="$p['value']">{{ $p['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:select wire:model="assigned_to" :label="__('Assign to')" data-test="task-edit-assignee">
                    <flux:select.option :value="null">{{ __('Unassigned') }}</flux:select.option>
                    @foreach ($this->teamMembers as $member)
                        <flux:select.option :value="$member->id">{{ $member->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="due_date" :label="__('Due date')" type="date" data-test="task-edit-due-date" />
            </div>

            <div class="flex items-center justify-between">
                <flux:button
                    variant="danger"
                    wire:click="deleteTask({{ $editingTaskId ?? 0 }})"
                    wire:confirm="{{ __('Delete this task?') }}"
                    data-test="task-delete-button"
                >
                    {{ __('Delete') }}
                </flux:button>
                <div class="flex gap-2">
                    <flux:button variant="filled" wire:click="cancelEdit">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="primary" type="submit" data-test="task-update-submit">
                        {{ __('Save') }}
                    </flux:button>
                </div>
            </div>
        </form>
    </flux:modal>
</x-layouts::app>
