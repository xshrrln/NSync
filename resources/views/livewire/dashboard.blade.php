<?php
use Livewire\Volt\Component;
use App\Models\Board;
use App\Models\Task;
use App\Models\Stage;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public $newBoardName = '';

    public function with() {
        $tenant = app('currentTenant');
        return [
            'boards' => $tenant ? Board::where('tenant_id', $tenant->id)->latest()->get() : collect(),
            'totalTasks' => $tenant ? Task::where('tenant_id', $tenant->id)->count() : 0,
            'totalStages' => $tenant ? Stage::where('tenant_id', $tenant->id)->count() : 0,
            'tenant' => $tenant,
        ];
    }

    public function createBoard() {
        $this->validate(['newBoardName' => 'required|min:3|max:50']);

        $tenant = app('currentTenant');
        Board::create([
            'tenant_id' => $tenant->id,
            'name' => $this->newBoardName,
            'slug' => str($this->newBoardName)->slug() . '-' . rand(100, 999),
        ]);

        $this->newBoardName = '';
        $this->dispatch('notify', 'Board created successfully!');
    }
}; ?>

<div class="py-5 bg-light min-vh-100">
    <!-- Header -->
    <div class="bg-white shadow-sm border-bottom sticky-top">
        <div class="container">
            <div class="row align-items-center py-4">
                <div class="col">
                    <h1 class="h2 fw-bold text-dark mb-0">Dashboard</h1>
                    <p class="text-muted mb-0">Welcome back, {{ Auth::user()->name }}!</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container py-5">
        <!-- Stats Cards -->
        <div class="row g-4 mb-5">
            <!-- Total Boards -->
            <div class="col-lg-3 col-md-6">
                <div class="card h-100 shadow-sm border border-secondary-subtle hover-shadow-lg transition-all">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h6 class="text-uppercase fw-semibold text-muted small tracking-wide mb-0">Total Boards</h6>
                            <div class="bg-primary-subtle rounded-circle d-flex align-items-center justify-content-center" style="width: 2.5rem; height: 2.5rem;">
                                <svg class="text-primary w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/>
                                </svg>
                            </div>
                        </div>
                        <h2 class="fw-bold text-dark mb-2">{{ $boards->count() }}</h2>
                        <p class="text-muted small mb-0 d-flex align-items-center gap-1">
                            <span class="badge bg-success rounded-pill" style="width: 0.5rem; height: 0.5rem;"></span>
                            Active projects
                        </p>
                    </div>
                </div>
            </div>

            <!-- Total Tasks -->
            <div class="col-lg-3 col-md-6">
                <div class="card h-100 shadow-sm border border-secondary-subtle hover-shadow-lg transition-all">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h6 class="text-uppercase fw-semibold text-muted small tracking-wide mb-0">Total Tasks</h6>
                            <div class="bg-success-subtle rounded-circle d-flex align-items-center justify-content-center" style="width: 2.5rem; height: 2.5rem;">
                                <svg class="text-success w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                                </svg>
                            </div>
                        </div>
                        <h2 class="fw-bold text-dark mb-2">{{ number_format($totalTasks) }}</h2>
                        <p class="text-muted small mb-0">Total tasks across all boards</p>
                    </div>
                </div>
            </div>

            <!-- Total Stages -->
            <div class="col-lg-3 col-md-6">
                <div class="card h-100 shadow-sm border border-secondary-subtle hover-shadow-lg transition-all">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h6 class="text-uppercase fw-semibold text-muted small tracking-wide mb-0">Total Stages</h6>
                            <div class="bg-warning-subtle rounded-circle d-flex align-items-center justify-content-center" style="width: 2.5rem; height: 2.5rem;">
                                <svg class="text-warning w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                                </svg>
                            </div>
                        </div>
                        <h2 class="fw-bold text-dark mb-2">{{ number_format($totalStages) }}</h2>
                        <p class="text-muted small mb-0">Workflow stages configured</p>
                    </div>
                </div>
            </div>

            <!-- System Status -->
            <div class="col-lg-3 col-md-6">
                <div class="card h-100 shadow-sm border border-secondary-subtle hover-shadow-lg transition-all">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h6 class="text-uppercase fw-semibold text-muted small tracking-wide mb-0">System Status</h6>
                            <div class="bg-purple-subtle rounded-circle d-flex align-items-center justify-content-center" style="width: 2.5rem; height: 2.5rem;">
                                <svg class="text-purple w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                        </div>
                        <h2 class="fw-bold text-dark mb-2">{{ $tenant->plan ?? 'Free' }} Plan</h2>
                        <p class="text-muted small mb-0">Your subscription tier</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-5">
            <div class="col-12">
                <div class="card shadow-sm border border-secondary-subtle">
                    <div class="card-body">
                        <h5 class="card-title fw-bold text-dark mb-3">Quick Actions</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <form wire:submit="createBoard" class="d-flex gap-2">
                                    <input type="text" wire:model="newBoardName" class="form-control" placeholder="Enter board name..." required>
                                    <button type="submit" class="btn btn-primary btn-nsync">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m0 0h6"/>
                                        </svg>
                                        Create Board
                                    </button>
                                </form>
                            </div>
                            <div class="col-md-6 d-flex align-items-center">
                                <a href="{{ route('boards.index') }}" class="btn btn-outline-secondary">
                                    <svg class="w-4 h-4 me-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                    Manage Boards
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Boards Section -->
        <div class="row mb-5">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="h3 fw-bold text-dark mb-2">Your Boards</h2>
                        <p class="text-muted mb-0">Quick access to your recent projects</p>
                    </div>
                    <a href="{{ route('boards.index') }}" class="btn btn-outline-primary btn-nsync">
                        View All 
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>

        @if($boards->count() > 0)
            <div class="row g-4">
                @forelse($boards->take(8) as $board)
                    <div class="col-lg-3 col-xl-2">
                        <a href="{{ route('boards.show', $board->slug) }}" class="text-decoration-none">
                            <div class="card h-100 shadow-sm border-primary-subtle overflow-hidden transition-all hover-shadow-lg">
                                <div class="card-body d-flex flex-column justify-content-between p-4 text-white" style="background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);">
                                    <div>
                                        <h6 class="fw-bold mb-2 text-truncate">{{ $board->name }}</h6>
                                    </div>
                                    <small class="opacity-90">Open board</small>
                                </div>
                            </div>
                        </a>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="card border border-dashed border-secondary-subtle text-center py-5 bg-light">
                            <div class="mb-4">
                                <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle" style="width: 5rem; height: 5rem;">
                                    <svg class="text-primary" style="width: 2.5rem; height: 2.5rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m0 0h6"/>
                                    </svg>
                                </div>
                            </div>
                            <h3 class="h4 fw-bold text-dark mb-3">No boards yet</h3>
                            <p class="text-muted mb-4 max-width-sm mx-auto">Create your first board to start organizing your projects and managing tasks</p>
                            <a href="{{ route('boards.index') }}" class="btn btn-primary btn-nsync shadow-sm">
                                Create Your First Board
                            </a>
                        </div>
                    </div>
                @endforelse
            </div>
        @else
            <div class="row">
                <div class="col-12">
                    <div class="card border border-dashed border-secondary-subtle text-center py-5 bg-light">
                        <div class="mb-4">
                            <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle" style="width: 5rem; height: 5rem;">
                                <svg class="text-primary" style="width: 2.5rem; height: 2.5rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m0 0h6"/>
                                </svg>
                            </div>
                        </div>
                        <h3 class="h4 fw-bold text-dark mb-3">No boards yet</h3>
                        <p class="text-muted mb-4 max-width-sm mx-auto">Create your first board to start organizing your projects and managing tasks</p>
                        <a href="{{ route('boards.index') }}" class="btn btn-primary btn-nsync shadow-sm">
                            Create Your First Board
                        </a>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
