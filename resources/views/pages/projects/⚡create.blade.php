<?php

use App\Models\Project;
use App\Models\Team;
use App\Rules\TeamName;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('New Project')] class extends Component {
    public string $name = '';
    public string $description = '';

    public function createProject(): void
    {
        $team = Auth::user()->currentTeam;

        Gate::authorize('create', [Project::class, $team]);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $project = $team->projects()->create($validated);

        Flux::toast(variant: 'success', text: __('Project created.'));

        $this->redirectRoute('projects.show', $project, navigate: true);
    }
}; ?>

<x-layouts::app :title="__('New Project')">
    <div class="mx-auto w-full max-w-xl p-6">
        <div class="mb-6">
            <flux:heading size="xl">{{ __('New project') }}</flux:heading>
            <flux:subheading>{{ __('Create a new project for your team') }}</flux:subheading>
        </div>

        <form wire:submit="createProject" class="space-y-6">
            <flux:input wire:model="name" :label="__('Project name')" required autofocus data-test="project-name" />
            <flux:textarea wire:model="description" :label="__('Description')" rows="3" :placeholder="__('Optional description…')" data-test="project-description" />

            <div class="flex items-center gap-3">
                <flux:button variant="primary" type="submit" data-test="project-create-submit">
                    {{ __('Create project') }}
                </flux:button>
                <flux:button variant="ghost" :href="route('projects.index')" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
