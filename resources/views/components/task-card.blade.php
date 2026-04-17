@props(['task'])

<div
    wire:key="task-{{ $task->id }}"
    wire:sort:item="{{ $task->id }}"
    wire:transition
    wire:click="editTask({{ $task->id }})"
    wire:sort:ignore.self
    class="task-card group flex cursor-pointer flex-col gap-2 rounded-lg border bg-white p-3 shadow-xs
        transition-colors duration-150 hover:border-zinc-300 dark:bg-zinc-900 dark:hover:border-zinc-600
        {{ $task->isOverdue() ? 'border-red-300 dark:border-red-700' : 'border-zinc-200 dark:border-zinc-700' }}"
    data-test="task-card"
>
    {{-- Drag handle + title row --}}
    <div class="flex items-start gap-2">
        <div
            wire:sort:handle
            class="mt-0.5 shrink-0 cursor-grab text-zinc-300 opacity-0 transition-opacity group-hover:opacity-100 active:cursor-grabbing dark:text-zinc-600"
            title="{{ __('Drag to reorder') }}"
        >
            <svg class="size-4" viewBox="0 0 16 16" fill="currentColor">
                <circle cx="5.5" cy="4" r="1.25"/>
                <circle cx="10.5" cy="4" r="1.25"/>
                <circle cx="5.5" cy="8" r="1.25"/>
                <circle cx="10.5" cy="8" r="1.25"/>
                <circle cx="5.5" cy="12" r="1.25"/>
                <circle cx="10.5" cy="12" r="1.25"/>
            </svg>
        </div>

        <span class="flex-1 text-sm font-medium leading-snug">{{ $task->title }}</span>

        <flux:badge :color="$task->priority->color()" size="sm" class="shrink-0">
            {{ $task->priority->label() }}
        </flux:badge>
    </div>

    {{-- Description --}}
    @if ($task->description)
        <p class="line-clamp-2 pl-6 text-xs text-zinc-500 dark:text-zinc-400">{{ $task->description }}</p>
    @endif

    {{-- Footer: assignee + due date --}}
    <div class="flex items-center justify-between gap-2 pl-6 text-xs text-zinc-400 dark:text-zinc-500">
        @if ($task->assignee)
            <div class="flex items-center gap-1">
                <flux:avatar size="xs" :name="$task->assignee->name" />
                <span>{{ $task->assignee->name }}</span>
            </div>
        @else
            <span></span>
        @endif

        @if ($task->due_date)
            <div class="flex items-center gap-1 {{ $task->isOverdue() ? 'font-medium text-red-500' : '' }}">
                <flux:icon name="calendar" class="size-3" />
                <span>{{ $task->due_date->format('M j') }}</span>
                @if ($task->isOverdue())
                    <span>· {{ __('Overdue') }}</span>
                @endif
            </div>
        @endif
    </div>
</div>
