<?php

use App\Models\Project;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Projects')] class extends Component {
    #[Computed]
    public function projects()
    {
        $team = Auth::user()->currentTeam;

        Gate::authorize('viewAny', [Project::class, $team]);

        return $team->projects()->withCount('tasks')->latest()->get();
    }
}; ?>

<x-layouts::app :title="__('Projects')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 p-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ __('Projects') }}</flux:heading>
                <flux:subheading>{{ __('All projects for your current team') }}</flux:subheading>
            </div>
            <flux:button variant="primary" icon="plus" :href="route('projects.create')" wire:navigate>
                {{ __('New project') }}
            </flux:button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($this->projects as $project)
                <a href="{{ route('projects.show', $project) }}" wire:navigate
                   class="group flex flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-5 transition hover:border-zinc-300 hover:shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600"
                   data-test="project-card">
                    <div class="flex items-start justify-between gap-2">
                        <span class="font-semibold group-hover:text-blue-600 dark:group-hover:text-blue-400">{{ $project->name }}</span>
                        <flux:icon name="folder-git-2" class="mt-0.5 size-4 shrink-0 text-zinc-400" />
                    </div>
                    @if ($project->description)
                        <flux:text class="line-clamp-2 text-sm text-zinc-500 dark:text-zinc-400">{{ $project->description }}</flux:text>
                    @endif
                    <div class="mt-auto flex items-center gap-1 text-sm text-zinc-500 dark:text-zinc-400">
                        <flux:icon name="check-circle" class="size-4" />
                        {{ $project->tasks_count }} {{ Str::plural('task', $project->tasks_count) }}
                    </div>
                </a>
            @empty
                <div class="col-span-full py-16 text-center">
                    <flux:icon name="folder-git-2" class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                    <flux:heading>{{ __('No projects yet') }}</flux:heading>
                    <flux:subheading class="mb-4">{{ __('Create your first project to get started.') }}</flux:subheading>
                    <flux:button variant="primary" icon="plus" :href="route('projects.create')" wire:navigate>
                        {{ __('New project') }}
                    </flux:button>
                </div>
            @endforelse
        </div>
    </div>
</x-layouts::app>
