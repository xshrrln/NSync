<?php
use Livewire\Volt\Component;
use App\Models\Board;
use App\Models\Stage;

new class extends Component {
    public string $slug = '';
    public string $token = '';
    public int $boardId = 0;
    public string $boardName = '';

    public function mount(string $slug, string $token): void
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        abort_unless($tenant && $tenant->is_active && $tenant->hasFeature('guest-boards'), 403, 'Guest board sharing is not enabled.');

        $this->slug = $slug;
        $this->token = $token;

        $board = Board::query()
            ->where('slug', $slug)
            ->firstOrFail();

        $members = is_array($board->members) ? $board->members : [];
        $savedToken = (string) ($members['guest_access_token'] ?? '');
        abort_unless($savedToken !== '' && hash_equals($savedToken, $token), 403, 'Invalid guest board token.');

        $this->boardId = (int) $board->id;
        $this->boardName = (string) $board->name;
    }

    public function with(): array
    {
        return [
            'stages' => Stage::where('board_id', $this->boardId)
                ->orderBy('position')
                ->with(['tasks' => fn ($q) => $q->orderBy('position')])
                ->get(),
        ];
    }
}; ?>

<div class="min-h-screen bg-slate-50">
    <div class="border-b border-slate-200 bg-white px-6 py-5">
        <div class="mx-auto max-w-7xl">
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-600">Guest Board View</p>
            <h1 class="mt-2 text-2xl font-black text-slate-900">{{ $boardName }}</h1>
            <p class="mt-1 text-sm text-slate-500">Read-only shared board access.</p>
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-6 py-8">
        <div class="flex gap-6 overflow-x-auto pb-3">
            @foreach($stages as $stage)
                <div class="w-[340px] shrink-0 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-sm font-bold text-slate-900">{{ $stage->name }}</h2>
                        <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600">{{ $stage->tasks->count() }}</span>
                    </div>

                    <div class="space-y-3">
                        @forelse($stage->tasks as $task)
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                <p class="text-sm font-semibold text-slate-900">{{ $task->title }}</p>
                                @if($task->description)
                                    <p class="mt-2 text-xs text-slate-600">{{ $task->description }}</p>
                                @endif
                                @if($task->due_date)
                                    <p class="mt-2 text-[11px] font-semibold text-emerald-700">
                                        Due {{ \Illuminate\Support\Carbon::parse($task->due_date)->format('M d, Y') }}
                                    </p>
                                @endif
                            </div>
                        @empty
                            <p class="text-xs text-slate-500">No cards in this stage.</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
