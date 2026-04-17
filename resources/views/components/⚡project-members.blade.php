<?php

use App\Models\Project;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Project $project;

    /** @var array<int, int> Selected user IDs to add */
    public array $selectedUserIds = [];

    public function mount(Project $project): void
    {
        $this->project = $project;
    }

    /**
     * Team members who are not yet on this project.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    #[Computed]
    public function availableMembers()
    {
        $existingIds = $this->project->members()->pluck('users.id');

        return $this->project->team
            ->members()
            ->whereNotIn('users.id', $existingIds)
            ->get();
    }

    /**
     * Current project members.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    #[Computed]
    public function members()
    {
        return $this->project->members()->get();
    }

    public function addMembers(): void
    {
        Gate::authorize('update', $this->project);

        $this->validate([
            'selectedUserIds' => ['required', 'array', 'min:1'],
            'selectedUserIds.*' => ['integer', 'exists:users,id'],
        ]);

        // Only attach users who are actually team members
        $validIds = $this->project->team
            ->members()
            ->whereIn('users.id', $this->selectedUserIds)
            ->pluck('users.id');

        $this->project->members()->syncWithoutDetaching($validIds);

        $this->reset('selectedUserIds');
        $this->dispatch('close-modal', name: 'manage-project-members');

        Flux::toast(variant: 'success', text: __('Members added.'));

        unset($this->members, $this->availableMembers);
    }

    public function removeMember(int $userId): void
    {
        Gate::authorize('update', $this->project);

        $this->project->members()->detach($userId);

        Flux::toast(variant: 'success', text: __('Member removed.'));

        unset($this->members, $this->availableMembers);
    }
}; ?>

<div>
    {{-- Members list --}}
    <div class="space-y-2">
        @forelse ($this->members as $member)
            <div
                wire:key="project-member-{{ $member->id }}"
                wire:transition
                class="flex items-center justify-between rounded-lg border border-zinc-200 bg-white p-3
                       dark:border-zinc-700 dark:bg-zinc-900"
                data-test="project-member-row"
            >
                <div class="flex items-center gap-3">
                    <flux:avatar size="sm" :name="$member->name" />
                    <div>
                        <p class="text-sm font-medium">{{ $member->name }}</p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $member->email }}</p>
                    </div>
                </div>

                <flux:tooltip :content="__('Remove from project')">
                    <flux:button
                        variant="ghost"
                        size="sm"
                        icon="x-mark"
                        wire:click="removeMember({{ $member->id }})"
                        wire:confirm="{{ __('Remove :name from this project?', ['name' => $member->name]) }}"
                        data-test="project-member-remove"
                    />
                </flux:tooltip>
            </div>
        @empty
            <p wire:transition class="py-4 text-center text-sm text-zinc-400 dark:text-zinc-600">
                {{ __('No members assigned yet.') }}
            </p>
        @endforelse
    </div>

    {{-- Add members button --}}
    @if ($this->availableMembers->isNotEmpty())
        <div class="mt-4">
            <flux:modal.trigger name="manage-project-members">
                <flux:button variant="outline" icon="user-plus" size="sm" data-test="add-project-members-button">
                    {{ __('Add members') }}
                </flux:button>
            </flux:modal.trigger>
        </div>
    @endif

    {{-- Add members modal --}}
    <flux:modal name="manage-project-members" :show="$errors->isNotEmpty()" focusable class="max-w-md">
        <form wire:submit="addMembers" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Add project members') }}</flux:heading>
                <flux:subheading>{{ __('Select team members to add to this project.') }}</flux:subheading>
            </div>

            <div class="space-y-2">
                @foreach ($this->availableMembers as $member)
                    <label
                        wire:key="available-{{ $member->id }}"
                        class="flex cursor-pointer items-center gap-3 rounded-lg border border-zinc-200 p-3
                               transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                    >
                        <flux:checkbox
                            wire:model="selectedUserIds"
                            :value="$member->id"
                            data-test="add-member-checkbox"
                        />
                        <flux:avatar size="sm" :name="$member->name" />
                        <div>
                            <p class="text-sm font-medium">{{ $member->name }}</p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $member->email }}</p>
                        </div>
                    </label>
                @endforeach
            </div>

            @error('selectedUserIds')
                <flux:text class="text-sm text-red-500">{{ $message }}</flux:text>
            @enderror

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit" data-test="add-members-submit">
                    {{ __('Add members') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
