<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Project')] class extends Component {
    public Project $project;

    // Task form fields (shared between create + edit)
    public string $title = '';
    public string $description = '';
    public string $status = 'todo';
    public string $priority = 'medium';
    public ?int $assigned_to = null;
    public ?string $due_date = null;

    public ?int $editingTaskId = null;

    public function mount(Project $project): void
    {
        Gate::authorize('viewAny', [Task::class, $project]);

        $this->project = $project;
    }

    /**
     * Tasks grouped by status, ordered by sort_order then due_date.
     *
     * @return array<string, \Illuminate\Support\Collection<int, Task>>
     */
    #[Computed]
    public function tasks(): array
    {
        $grouped = [];

        foreach (TaskStatus::cases() as $status) {
            $grouped[$status->value] = collect();
        }

        $this->project->tasks()
            ->with('assignee')
            ->orderBy('sort_order')
            ->orderBy('due_date')
            ->get()
            ->each(function (Task $task) use (&$grouped) {
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

    /**
     * Called by wire:sort when a card is dropped.
     * $id       — task id (wire:sort:item value)
     * $position — zero-based index in the destination column
     * $columnStatus — the status value of the destination column (wire:sort:group-id)
     */
    public function handleSort(int $id, int $position, string $columnStatus): void
    {
        $task = $this->project->tasks()->findOrFail($id);

        Gate::authorize('update', $task);

        $newStatus = TaskStatus::from($columnStatus);

        DB::transaction(function () use ($task, $newStatus, $position) {
            // Shift other tasks in the destination column to make room
            $this->project->tasks()
                ->where('status', $newStatus->value)
                ->where('id', '!=', $task->id)
                ->orderBy('sort_order')
                ->get()
                ->each(function (Task $sibling, int $index) use ($position, $task) {
                    $sibling->update([
                        'sort_order' => $index >= $position ? $index + 1 : $index,
                    ]);
                });

            $task->update([
                'status' => $newStatus,
                'sort_order' => $position,
            ]);
        });

        unset($this->tasks);
    }

    public function createTask(): void
    {
        Gate::authorize('create', [Task::class, $this->project]);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'string', 'in:todo,in_progress,done'],
            'priority' => ['required', 'string', 'in:low,medium,high'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
        ]);

        // Place new task at the end of its column
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
        $this->title = $task->title;
        $this->description = $task->description ?? '';
        $this->status = $task->status->value;
        $this->priority = $task->priority->value;
        $this->assigned_to = $task->assigned_to;
        $this->due_date = $task->due_date?->format('Y-m-d');

        $this->dispatch('open-modal', name: 'edit-task');
    }

    public function updateTask(): void
    {
        $task = $this->project->tasks()->findOrFail($this->editingTaskId);

        Gate::authorize('update', $task);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'string', 'in:todo,in_progress,done'],
            'priority' => ['required', 'string', 'in:low,medium,high'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
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

        {{-- Header --}}
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

        {{-- Kanban board --}}
        <div class="grid gap-4 md:grid-cols-3">
            @foreach (TaskStatus::cases() as $column)
                @php $columnTasks = $this->tasks[$column->value]; @endphp

                <div class="flex flex-col gap-3 rounded-xl border border-zinc-200 bg-zinc-50 p-4
                            dark:border-zinc-700 dark:bg-zinc-800/50">

                    {{-- Column header --}}
                    <div class="flex items-center gap-2">
                        <flux:badge :color="$column->color()" size="sm">{{ $column->label() }}</flux:badge>
                        <span class="column-count text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $columnTasks->count() }}
                        </span>
                    </div>

                    {{-- Sortable task list --}}
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
                                {{ __('No tasks') }}
                            </p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Create task modal --}}
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

    {{-- Edit task modal --}}
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
                <flux:button variant="danger"
                    wire:click="deleteTask({{ $editingTaskId ?? 0 }})"
                    wire:confirm="{{ __('Delete this task?') }}"
                    data-test="task-delete-button">
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
