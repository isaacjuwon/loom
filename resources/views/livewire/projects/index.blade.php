<?php
use App\Models\Team;
use Illuminate\Support\Collection;
use App\Models\Project;
use Livewire\Component;

return new class extends Component {
    /**
     * @var Collection<int, Project>
     */
    public Collection $projects;

    public function mount($current_team): void
    {
        $team = $current_team instanceof Team
            ? $current_team
            : Team::where('slug', $current_team)->orWhere('id', $current_team)->first();

        $this->projects = $team ? $team->projects()->latest('created_at')->get() : collect();
    }
};

?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Projects</h1>
        <div>
            <flux:button variant="primary">New Project</flux:button>
        </div>
    </div>

    @if($projects->isEmpty())
        <div class="rounded-lg border border-dashed border-gray-200 p-6 text-center">
            <p class="text-gray-500">No projects yet. Create one to get started.</p>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($projects as $project)
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <h2 class="text-lg font-medium text-gray-900 dark:text-white">{{ $project->name }}</h2>
                            @if($project->description)
                                <p class="text-sm text-gray-500 mt-1">{{ $project->description }}</p>
                            @endif
                        </div>
                        <div class="text-sm text-gray-400">{{ $project->created_at->diffForHumans() }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
