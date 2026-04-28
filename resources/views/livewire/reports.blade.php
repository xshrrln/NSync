<?php
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\ActivityLog;
use App\Models\Board;
use App\Models\Task;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

new class extends Component {
    public string $reportRange = '30d';

    public function with()
    {
        $tenant = app('currentTenant');

        $canViewReporting = $tenant ? $tenant->hasFeature('advanced-reporting') : false;
        $canExportAudit = $tenant ? $tenant->hasFeature('audit-export') : false;

        $rangeDays = match ($this->reportRange) {
            '7d' => 7,
            '90d' => 90,
            default => 30,
        };

        $periodStart = now()->subDays($rangeDays);

        $reporting = [
            'tasks_due_this_week' => 0,
            'overdue_tasks' => 0,
            'boards_without_tasks' => 0,
            'tasks_completed_period' => 0,
            'completion_rate' => 0,
            'avg_tasks_per_board' => 0,
        ];

        $capstoneTasks = $this->collectCapstoneTasks();

        if ($tenant && $canViewReporting) {
            $completedPeriod = ActivityLog::query()
                ->with('newStage:id,name')
                ->where('created_at', '>=', $periodStart)
                ->get()
                ->filter(function (ActivityLog $log) {
                    $stageName = Str::lower((string) ($log->new_stage_name_snapshot ?: ($log->newStage?->name ?? '')));

                    return in_array($stageName, ['done', 'completed', 'complete', 'resolved', 'published'], true);
                })
                ->count();

            $dbTotalTasks = Task::count();
            $capstoneTotalTasks = $capstoneTasks->count();
            $totalTasks = $dbTotalTasks + $capstoneTotalTasks;
            $boardCount = max(1, Board::count());

            $capstoneDueThisWeek = $capstoneTasks->filter(function (array $task) {
                $due = $this->safeDate($task['due_date'] ?? null);
                $progress = (int) ($task['progress'] ?? 0);

                return $due && $due->betweenIncluded(now()->startOfWeek(), now()->endOfWeek()) && $progress < 100;
            })->count();

            $capstoneOverdue = $capstoneTasks->filter(function (array $task) {
                $due = $this->safeDate($task['due_date'] ?? null);
                $progress = (int) ($task['progress'] ?? 0);

                return $due && $due->lt(now()->startOfDay()) && $progress < 100;
            })->count();

            $boardsWithoutTasks = Board::query()
                ->withCount('tasks')
                ->get()
                ->filter(function (Board $board) {
                    if ((int) ($board->tasks_count ?? 0) > 0) {
                        return false;
                    }

                    $members = is_array($board->members) ? $board->members : [];
                    $capstone = $members['capstone_tasks'] ?? [];

                    return ! is_array($capstone) || count($capstone) === 0;
                })
                ->count();

            $reporting = [
                'tasks_due_this_week' => Task::whereBetween('due_date', [now()->startOfWeek(), now()->endOfWeek()])->count() + $capstoneDueThisWeek,
                'overdue_tasks' => Task::whereDate('due_date', '<', now()->toDateString())->count() + $capstoneOverdue,
                'boards_without_tasks' => $boardsWithoutTasks,
                'tasks_completed_period' => $completedPeriod,
                'completion_rate' => $totalTasks > 0 ? round(($completedPeriod / $totalTasks) * 100, 1) : 0,
                'avg_tasks_per_board' => round($totalTasks / $boardCount, 1),
            ];
        }

        return [
            'tenant' => $tenant,
            'canViewReporting' => $canViewReporting,
            'canExportAudit' => $canExportAudit,
            'reporting' => $reporting,
            'periodLabel' => "Last {$rangeDays} days",
        ];
    }

    public function exportAdvancedReportPdf()
    {
        $tenant = app('currentTenant');
        abort_unless($tenant && $tenant->hasFeature('advanced-reporting'), 403);

        $dbRows = Task::query()
            ->with([
                'board:id,name',
                'stage:id,name',
            ])
            ->orderByDesc('tasks.id')
            ->get();

        $capstoneRows = $this->collectCapstoneTasks()
            ->map(function (array $task) {
                return (object) [
                    'id' => $task['task_id'],
                    'title' => $task['title'] ?: 'Untitled task',
                    'board_name' => $task['board_name'],
                    'stage_name' => 'Capstone Tracker',
                    'due_date' => $task['due_date'] ?: '-',
                    'created_at' => '-',
                    'updated_at' => '-',
                ];
            });

        $rows = $dbRows
            ->concat($capstoneRows)
            ->map(function ($row) {
                $dueDate = filled($row->due_date) && $row->due_date !== '-'
                    ? Carbon::parse($row->due_date)
                    : null;
                $title = $row instanceof Task
                    ? (string) ($row->title ?? '')
                    : (string) ($row->title ?? '');
                $boardName = $row instanceof Task
                    ? (string) ($row->board?->name ?? '')
                    : (string) ($row->board_name ?? '');
                $stageName = $row instanceof Task
                    ? (string) ($row->stage?->name ?? '')
                    : (string) ($row->stage_name ?? '');
                $stage = Str::lower($stageName);
                $isCompleted = in_array($stage, ['done', 'completed', 'complete', 'resolved', 'published'], true);

                $status = $isCompleted
                    ? 'Completed'
                    : ($dueDate && $dueDate->isPast() ? 'Overdue' : ($dueDate && $dueDate->isToday() ? 'Due Today' : 'Open'));

                return (object) [
                    'id' => (string) ($row->id ?? '-'),
                    'title' => $this->readableValue($title, 'Untitled task', 120),
                    'board_name' => $this->readableValue($boardName, 'N/A', 70),
                    'stage_name' => $this->readableValue($stageName, 'N/A', 40),
                    'due_date' => $dueDate ? $dueDate->format('M d, Y') : '-',
                    'created_at' => $this->formatTimestamp($row->created_at ?? null),
                    'updated_at' => $this->formatTimestamp($row->updated_at ?? null),
                    'status' => $status,
                ];
            })
            ->values();

        $generatedAt = now();
        $filename = 'advanced-report-' . strtolower($tenant->domain ?? 'tenant') . '-' . $generatedAt->format('Ymd_His') . '.pdf';

        $pdf = Pdf::loadView('pdf.advanced-report', [
            'title' => 'Task Performance Report',
            'tenantName' => (string) ($tenant->name ?? 'Workspace'),
            'tenantDomain' => (string) ($tenant->domain ?? 'tenant'),
            'generatedAt' => $generatedAt,
            'periodLabel' => $this->reportRangeLabel(),
            'preparedBy' => auth()->user()?->name ?? 'Workspace Admin',
            'reportPurpose' => 'Task performance summary and status insights for the workspace.',
            'rows' => $rows,
            'summary' => [
                'total_tasks' => (int) $rows->count(),
                'completed' => (int) $rows->where('status', 'Completed')->count(),
                'overdue' => (int) $rows->where('status', 'Overdue')->count(),
                'open' => (int) $rows->whereIn('status', ['Open', 'Due Today'])->count(),
            ],
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    public function exportAdvancedReportCsv()
    {
        return $this->exportAdvancedReportPdf();
    }

    public function exportAuditPdf()
    {
        $tenant = app('currentTenant');
        abort_unless($tenant && $tenant->hasFeature('audit-export'), 403);

        $rows = ActivityLog::query()
            ->with([
                'user:id,name',
                'task:id,title,board_id',
                'oldStage:id,name',
                'newStage:id,name',
            ])
            ->orderByDesc('id')
            ->get();

        $preparedRows = $rows
            ->map(function ($row) {
                $taskTitle = (string) ($row->task_title_snapshot ?: ($row->task?->title ?? ''));
                $oldStageName = (string) ($row->old_stage_name_snapshot ?: ($row->oldStage?->name ?? ''));
                $newStageName = (string) ($row->new_stage_name_snapshot ?: ($row->newStage?->name ?? ''));
                $actionSummary = trim("Moved from {$oldStageName} to {$newStageName}");

                return (object) [
                    'id' => (int) ($row->id ?? 0),
                    'created_at' => $this->formatTimestamp($row->created_at ?? null),
                    'actor_name' => $this->readableValue((string) ($row->user?->name ?? ''), 'Unknown user', 70),
                    'task_title' => $this->readableValue($taskTitle, 'Unknown task', 120),
                    'action_summary' => $this->readableValue($actionSummary ?: 'Task update', 'Task update', 100),
                    'old_stage_name' => $this->readableValue($oldStageName, 'Previous stage', 40),
                    'new_stage_name' => $this->readableValue($newStageName, 'New stage', 40),
                    'ip_address' => $this->readableValue((string) ($row->ip_address ?? ''), '-', 45),
                ];
            })
            ->values();

        $generatedAt = now();
        $filename = 'audit-export-' . strtolower($tenant->domain ?? 'tenant') . '-' . $generatedAt->format('Ymd_His') . '.pdf';

        $pdf = Pdf::loadView('pdf.audit-report', [
            'title' => 'Audit Trail Report',
            'tenantName' => (string) ($tenant->name ?? 'Workspace'),
            'tenantDomain' => (string) ($tenant->domain ?? 'tenant'),
            'generatedAt' => $generatedAt,
            'preparedBy' => auth()->user()?->name ?? 'Workspace Admin',
            'reportPurpose' => 'Task movement audit trail with actor, stage transition and IP details.',
            'scopeLabel' => 'Workflow activity audit trail',
            'rows' => $preparedRows,
            'summary' => [
                'total' => (int) $preparedRows->count(),
                'unique_actors' => (int) $preparedRows->pluck('actor_name')->unique()->count(),
                'unique_tasks' => (int) $preparedRows->pluck('task_title')->unique()->count(),
            ],
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    public function exportAuditCsv()
    {
        return $this->exportAuditPdf();
    }

    private function collectCapstoneTasks()
    {
        return Board::query()
            ->get()
            ->flatMap(function (Board $board) {
                $members = is_array($board->members) ? $board->members : [];
                $tasks = $members['capstone_tasks'] ?? [];
                if (! is_array($tasks)) {
                    return collect();
                }

                $isCapstone = Str::contains(Str::lower((string) $board->name), 'capstone')
                    || Str::contains(Str::lower((string) $board->slug), 'capstone')
                    || Str::contains(Str::lower((string) $board->name), 'gantt')
                    || Str::contains(Str::lower((string) $board->slug), 'gantt')
                    || count($tasks) > 0;

                if (! $isCapstone) {
                    return collect();
                }

                return collect($tasks)
                    ->filter(fn ($task) => is_array($task))
                    ->values()
                    ->map(function (array $task) use ($board) {
                        return [
                            'task_id' => 'CAP-' . $board->id . '-' . ((int) ($task['uid'] ?? 0)),
                            'title' => trim((string) ($task['title'] ?? '')),
                            'board_name' => (string) $board->name,
                            'due_date' => (string) ($task['due_date'] ?? ''),
                            'progress' => max(0, min(100, (int) ($task['progress'] ?? 0))),
                        ];
                    });
            })
            ->values();
    }

    private function safeDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function reportRangeLabel(): string
    {
        return match ($this->reportRange) {
            '7d' => 'Last 7 days',
            '90d' => 'Last 90 days',
            default => 'Last 30 days',
        };
    }

    private function readableValue(string $value, string $fallback = '-', int $limit = 120): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return $fallback;
        }

        $looksEncoded = Str::startsWith($trimmed, 'eyJ') || (strlen($trimmed) > 48 && ! preg_match('/\s/', $trimmed));
        if ($looksEncoded) {
            return $fallback;
        }

        return Str::limit($trimmed, $limit, '...');
    }

    private function formatTimestamp(mixed $value): string
    {
        if (! $value) {
            return '-';
        }

        try {
            return Carbon::parse($value)->format('M d, Y h:i A');
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}; ?>

<div class="min-h-screen bg-white">
    <div class="bg-white shadow-sm border-b sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex items-center justify-between py-4 min-h-[116px]">
                <div>
                    <h1 class="text-2xl font-bold mb-0" style="color: color-mix(in srgb, var(--tenant-primary) 88%, black 12%);">Advanced Reporting & Audit Exports</h1>
                    <p class="text-gray-600 mb-0">Operational insights and downloadable compliance data for your workspace.</p>
                </div>
                <div>
                    <span class="rounded-full border px-3 py-1 text-[10px] font-bold uppercase" style="border-color: color-mix(in srgb, var(--tenant-primary) 25%, white 75%); background-color: color-mix(in srgb, var(--tenant-primary) 10%, white 90%); color: color-mix(in srgb, var(--tenant-primary) 80%, black 20%);">Reports</span>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-6 py-8 text-left">

        @if($canViewReporting)
            <div class="rounded-2xl border bg-white shadow-sm overflow-hidden" style="border-color: color-mix(in srgb, var(--tenant-primary) 18%, white 82%);">
                <div class="px-6 py-5 bg-white" style="border-bottom: 1px solid color-mix(in srgb, var(--tenant-primary) 15%, white 85%);">
                    <h2 class="text-lg font-bold" style="color: color-mix(in srgb, var(--tenant-primary) 85%, black 15%);">Advanced Reporting</h2>
                    <p class="mt-1 text-xs text-slate-600">Operational and compliance insights for {{ $periodLabel }}</p>
                </div>
                <div class="p-6">
                    <div class="mb-4 flex flex-wrap items-center gap-2">
                        <select wire:model.live="reportRange" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700">
                            <option value="7d">Last 7 days</option>
                            <option value="30d">Last 30 days</option>
                            <option value="90d">Last 90 days</option>
                        </select>
                        <button wire:click="exportAdvancedReportPdf" class="rounded-lg px-3 py-2 text-xs font-bold text-white" style="background-color: color-mix(in srgb, var(--tenant-primary) 80%, black 20%);">Export Report PDF</button>
                        @if($canExportAudit)
                            <button wire:click="exportAuditPdf" class="rounded-lg px-3 py-2 text-xs font-bold text-white" style="background-color: var(--tenant-primary);">Export Audit PDF</button>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Tasks Due This Week</p>
                            <p class="mt-1 text-xl font-black text-slate-900">{{ number_format($reporting['tasks_due_this_week']) }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Overdue Tasks</p>
                            <p class="mt-1 text-xl font-black text-slate-900">{{ number_format($reporting['overdue_tasks']) }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Boards Without Tasks</p>
                            <p class="mt-1 text-xl font-black text-slate-900">{{ number_format($reporting['boards_without_tasks']) }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Completed (Period)</p>
                            <p class="mt-1 text-xl font-black text-slate-900">{{ number_format($reporting['tasks_completed_period']) }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Completion Rate</p>
                            <p class="mt-1 text-xl font-black text-slate-900">{{ number_format($reporting['completion_rate'], 1) }}%</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Average Tasks per Board</p>
                            <p class="mt-1 text-xl font-black text-slate-900">{{ number_format($reporting['avg_tasks_per_board'], 1) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="rounded-2xl border border-amber-100 bg-amber-50 px-6 py-5 text-sm text-amber-900">
                Advanced reporting is not enabled for your plan. Ask your workspace supervisor or upgrade your plan.
            </div>
        @endif
    </div>
</div>



