<?php

use App\Models\Project;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit Project')] class extends Component {
    public Project $project;

    public string $name = '';
    public string $description = '';

    public function mount(Project $project): void
    {
        Gate::authorize('update', $project);

        $this->project = $project;
        $this->name = $project->name;
        $this->description = $project->description ?? '';
    }

    public function updateProject(): void
    {
        Gate::authorize('update', $this->project);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->project->update($validated);

        Flux::toast(variant: 'success', text: __('Project updated.'));

        $this->redirectRoute('projects.show', $this->project->fresh(), navigate: true);
    }

    public function deleteProject(): void
    {
        Gate::authorize('delete', $this->project);

        $this->project->delete();

        Flux::toast(variant: 'success', text: __('Project deleted.'));

        $this->redirectRoute('projects.index', navigate: true);
    }
}; ?>

<x-layouts::app :title="__('Edit Project')">
    <div class="mx-auto w-full max-w-xl p-6">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ __('Edit project') }}</flux:heading>
                <flux:subheading>{{ $project->name }}</flux:subheading>
            </div>
            <flux:button variant="ghost" :href="route('projects.show', $project)" wire:navigate icon="arrow-left">
                {{ __('Back') }}
            </flux:button>
        </div>

        <form wire:submit="updateProject" class="space-y-6">
            <flux:input wire:model="name" :label="__('Project name')" required autofocus data-test="project-name" />
            <flux:textarea wire:model="description" :label="__('Description')" rows="3" data-test="project-description" />

            <div class="flex items-center gap-3">
                <flux:button variant="primary" type="submit" data-test="project-update-submit">
                    {{ __('Save changes') }}
                </flux:button>
                <flux:button variant="ghost" :href="route('projects.show', $project)" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        </form>

        <div class="mt-10 space-y-4 rounded-lg border border-red-200 bg-red-50 p-4 text-red-700 dark:border-red-200/10 dark:bg-red-900/20 dark:text-red-100">
            <div>
                <p class="font-medium">{{ __('Delete project') }}</p>
                <p class="text-sm">{{ __('This will permanently delete the project and all its tasks.') }}</p>
            </div>
            <flux:button variant="danger" wire:click="deleteProject" wire:confirm="{{ __('Are you sure? This cannot be undone.') }}" data-test="project-delete-button">
                {{ __('Delete project') }}
            </flux:button>
        </div>
    </div>
</x-layouts::app>
