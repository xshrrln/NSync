<?php
use Livewire\Volt\Component;
use App\Models\Board;
use App\Models\Message;
use App\Models\Stage;
use App\Models\Task;
use App\Models\ActivityLog;
use Livewire\WithFileUploads;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

new class extends Component {
    use WithFileUploads;

    public $boardSlug;
    public $boardId;
    public $boardName = '';
    public $boardSlugValue = '';
    public $newTaskTitle = '';
    public bool $isCapstoneTracker = false;
    public array $capstoneTasks = [];
    public int $refreshTick = 0;
    public ?int $editingTaskId = null;
    public string $editingTaskTitle = '';
    public string $editingTaskDescription = '';
    public ?string $editingTaskDueDate = null;
    public array $editingTaskAssigneeIds = [];
    public array $editingTaskAttachments = [];
    public string $newAttachmentUrl = '';
    public array $newTaskFiles = [];
    public string $editingTaskChecklistText = '';
    public string $editingTaskAttachmentText = '';
    public string $newBoardMessage = '';
    public ?string $guestShareUrl = null;

    private function ensureSubscriptionAccess(string $title = 'Subscription Required'): bool
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        if (! $tenant || ! $tenant->requiresSubscriptionRenewal()) {
            return true;
        }

        $this->dispatch('subscription-expired', title: $title, message: $tenant->subscriptionLockMessage());

        return false;
    }

    public function mount($slug = null, $boardSlug = null)
    {
        $resolvedSlug = $slug ?? $boardSlug;
        if (!$resolvedSlug) {
            abort(404, 'Board slug is missing.');
        }

        $this->boardSlug = $resolvedSlug;
        $tenant = app('currentTenant');

        $board = Board::where('slug', $resolvedSlug)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $this->boardId = $board->id;
        $this->boardName = $board->name;
        $this->boardSlugValue = $board->slug;

        $this->isCapstoneTracker = $this->detectCapstoneBoard($board);

        if ($this->isCapstoneTracker) {
            $this->capstoneTasks = $this->loadCapstoneTasks($board);
        }

        $members = is_array($board->members) ? $board->members : [];
        $guestToken = (string) ($members['guest_access_token'] ?? '');
        if ($guestToken !== '') {
            $this->guestShareUrl = route('guest.board.view', ['slug' => $this->boardSlugValue, 'token' => $guestToken]);
        }
    }

    private function detectCapstoneBoard(Board $board): bool
    {
        $name = Str::lower(trim((string) $board->name));
        $slug = Str::lower(trim((string) $board->slug));
        $members = is_array($board->members) ? $board->members : [];

        // Strong explicit marker: capstone task payload already exists.
        if (isset($members['capstone_tasks']) && is_array($members['capstone_tasks'])) {
            return true;
        }

        $nameOrSlugSignals = [
            'capstone task tracker',
            'capstone tracker',
            'capstone board',
            'capstone',
            'gantt chart',
            'gantt',
        ];

        foreach ($nameOrSlugSignals as $signal) {
            if (Str::contains($name, $signal) || Str::contains($slug, Str::slug($signal))) {
                return true;
            }
        }

        // Fallback signature used by capstone template: a single "Tasks" stage.
        $stageNames = $board->stages()->orderBy('position')->pluck('name')->map(
            fn (string $stageName) => Str::lower(trim($stageName))
        )->values();

        return $stageNames->count() === 1 && $stageNames->first() === 'tasks';
    }

    public function with()
    {
        $tenant = app('currentTenant');
        $featureFlags = [
            'basic_kanban' => $tenant ? $tenant->hasFeature('basic-kanban') : false,
            'member_invites' => $tenant ? $tenant->hasFeature('member-invites') : false,
            'card_assignees' => $tenant ? $tenant->hasFeature('role-permissions') : false,
            'guest_boards' => $tenant ? $tenant->hasFeature('guest-boards') : false,
            'task_checklists' => $tenant ? $tenant->hasFeature('task-checklists') : false,
            'file_attachments' => $tenant ? $tenant->hasFeature('file-attachments') : false,
            'due_date_reminders' => $tenant ? $tenant->hasFeature('due-date-reminders') : false,
            'activity_logs' => $tenant ? $tenant->hasFeature('activity-logs') : false,
            'audit_export' => $tenant ? $tenant->hasFeature('audit-export') : false,
        ];

        $workspaceMembers = collect();
        $workspaceMembersMap = [];
        if (! $this->isCapstoneTracker) {
            try {
                $workspaceMembers = DB::connection('tenant')
                    ->table('users')
                    ->select(['id', 'name', 'email'])
                    ->orderBy('name')
                    ->get();
                $workspaceMembersMap = $workspaceMembers
                    ->mapWithKeys(fn ($member) => [(int) $member->id => (string) $member->name])
                    ->all();
            } catch (\Throwable $e) {
                Log::warning('Unable to load workspace members for card assignment.', [
                    'board_id' => $this->boardId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $activityLogs = collect();
        if (! $this->isCapstoneTracker && ($featureFlags['activity_logs'] || $featureFlags['audit_export'])) {
            $activityLogs = DB::connection('tenant')
                ->table('activity_logs as al')
                ->leftJoin('tasks as t', 't.id', '=', 'al.task_id')
                ->leftJoin('stages as os', 'os.id', '=', 'al.old_stage_id')
                ->leftJoin('stages as ns', 'ns.id', '=', 'al.new_stage_id')
                ->leftJoin('users as u', 'u.id', '=', 'al.user_id')
                ->select([
                    'al.id',
                    'al.created_at',
                    'al.ip_address',
                    't.title as task_title',
                    'os.name as old_stage_name',
                    'ns.name as new_stage_name',
                    'u.name as actor_name',
                ])
                ->where('t.board_id', $this->boardId)
                ->orderByDesc('al.id')
                ->limit(25)
                ->get();
        }

        $boardMessages = collect();
        try {
            $boardMessages = Message::forRoom($this->boardId)
                ->with('sender')
                ->orderBy('id')
                ->get()
                ->values();
        } catch (\Throwable $e) {
            Log::warning('Unable to load board messages.', [
                'board_id' => $this->boardId,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'boardName' => $this->boardName,
            'boardSlugValue' => $this->boardSlugValue,
            'boardMessages' => $boardMessages,
            'stages' => $this->isCapstoneTracker
                ? collect()
                : Stage::where('board_id', $this->boardId)
                    ->orderBy('position')
                    ->with(['tasks' => fn ($q) => $q->orderBy('position')])
                    ->get(),
            'tenant' => $tenant,
            'featureFlags' => $featureFlags,
            'activityLogs' => $activityLogs,
            'guestShareUrl' => $this->guestShareUrl,
            'workspaceMembers' => $workspaceMembers,
            'workspaceMembersMap' => $workspaceMembersMap,
        ];
    }

    private function tenantHasFeature(string $feature): bool
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        return (bool) ($tenant?->hasFeature($feature));
    }

    public function refreshBoardData(): void
    {
        $this->refreshTick++;

        if ($this->isCapstoneTracker) {
            $board = Board::where('id', $this->boardId)->first();
            if ($board) {
                $this->capstoneTasks = $this->loadCapstoneTasks($board);
            }
        }
    }

    public function sendBoardMessage(): void
    {
        if (! $this->ensureSubscriptionAccess('Subscription Required to Send Messages')) {
            return;
        }

        $message = trim($this->newBoardMessage);
        if ($message === '') {
            return;
        }

        if (mb_strlen($message) > 1000) {
            $this->dispatch('notify', message: 'Message is too long. Please keep it under 1000 characters.', type: 'error');
            return;
        }

        $tenant = app('currentTenant');
        if (! $tenant) {
            $this->dispatch('notify', message: 'No active tenant workspace found.', type: 'error');
            return;
        }

        $tenantUserId = $this->resolveTenantUserId($tenant->id);
        if (! $tenantUserId) {
            $this->dispatch('notify', message: 'Unable to send message right now. Your workspace user record could not be mapped.', type: 'error');
            return;
        }

        try {
            Message::create([
                'room_id' => $this->boardId,
                'sender_id' => $tenantUserId,
                'message' => $message,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to save board message.', [
                'board_id' => $this->boardId,
                'sender_id' => $tenantUserId,
                'error' => $e->getMessage(),
            ]);
            $this->dispatch('notify', message: 'Unable to send message right now. Please try again.', type: 'error');
            return;
        }

        $this->newBoardMessage = '';
        $this->dispatch('notify', message: 'Message sent.', type: 'success');
        $this->dispatch('board-message-sent');
    }

    public function syncCapstoneTasks($tasks): void
    {
        if (! $this->ensureSubscriptionAccess()) {
            return;
        }

        if (!$this->isCapstoneTracker) {
            return;
        }

        $board = Board::where('id', $this->boardId)->first();
        if (!$board) {
            return;
        }

        $cleaned = collect(is_array($tasks) ? $tasks : [])
            ->map(function ($task, $index) {
                $task = is_array($task) ? $task : [];

                return [
                    'uid' => (int) ($task['uid'] ?? ($index + 1)),
                    'id' => $index + 1,
                    'title' => trim((string) ($task['title'] ?? '')),
                    'owner' => trim((string) ($task['owner'] ?? '')),
                    'start_date' => $task['start_date'] ?: '',
                    'due_date' => $task['due_date'] ?: '',
                    'progress' => max(0, min(100, (int) ($task['progress'] ?? 0))),
                ];
            })
            ->values()
            ->all();

        if (empty($cleaned)) {
            $cleaned[] = [
                'uid' => 1,
                'id' => 1,
                'title' => '',
                'owner' => '',
                'start_date' => '',
                'due_date' => '',
                'progress' => 0,
            ];
        }

        $members = is_array($board->members) ? $board->members : [];
        $members['capstone_tasks'] = $cleaned;

        $board->update(['members' => $members]);
        $this->capstoneTasks = $cleaned;
    }

    public function moveTask($taskId, $newStageId)
    {
        if (! $this->ensureSubscriptionAccess()) {
            return;
        }

        if ($this->isCapstoneTracker) {
            return;
        }

        if (! $this->tenantHasFeature('basic-kanban')) {
            $this->dispatch('notify', message: 'Task drag and drop is not available on your current plan.', type: 'error');
            return;
        }

        $tenant = app('currentTenant');

        $normalizedTaskId = (int) $taskId;
        $normalizedStageId = (int) $newStageId;

        if ($normalizedTaskId <= 0 || $normalizedStageId <= 0) {
            return;
        }

        $task = Task::where('id', $normalizedTaskId)
            ->where('board_id', $this->boardId)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$task) {
            return;
        }

        $destinationStage = Stage::where('id', $normalizedStageId)
            ->where('board_id', $this->boardId)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (! $destinationStage) {
            return;
        }

        $oldStageId = $task->stage_id;

        $task->update([
            'stage_id' => $normalizedStageId,
            'position' => (Task::where('stage_id', $normalizedStageId)->where('tenant_id', $tenant->id)->max('position') ?? 0) + 1,
        ]);

        if ($destinationStage && in_array(Str::lower($destinationStage->name), ['done', 'completed', 'complete', 'resolved', 'published'], true)) {
            $this->dispatch('task-completed', taskTitle: $task->title, stageName: $destinationStage->name);
        }

        $tenantUserId = $this->resolveTenantUserId($tenant->id);
        if ($tenantUserId) {
            ActivityLog::create([
                'user_id' => $tenantUserId,
                'task_id' => $task->id,
                'old_stage_id' => $oldStageId,
                'new_stage_id' => $normalizedStageId,
                'ip_address' => request()->ip(),
            ]);
        }
    }

    public function addTask($stageId)
    {
        if (! $this->ensureSubscriptionAccess()) {
            return;
        }

        if ($this->isCapstoneTracker || empty(trim($this->newTaskTitle))) {
            return;
        }

        if (! $this->tenantHasFeature('basic-kanban')) {
            $this->dispatch('notify', message: 'Card creation is not available on your current plan.', type: 'error');
            return;
        }

        $tenantUserId = $this->resolveTenantUserId(app('currentTenant')->id);
        if (!$tenantUserId) {
            $this->dispatch('notify', message: 'Unable to add card right now. Your workspace user record could not be mapped.', type: 'error');
            return;
        }

        Task::create([
            'title' => trim($this->newTaskTitle),
            'stage_id' => $stageId,
            'board_id' => $this->boardId,
            'tenant_id' => app('currentTenant')->id,
            'user_id' => $tenantUserId,
            'position' => Task::where('stage_id', $stageId)->count() + 1,
        ]);

        $this->newTaskTitle = '';
    }

    public function deleteTask($taskId)
    {
        if (! $this->ensureSubscriptionAccess()) {
            return;
        }

        if ($this->isCapstoneTracker) {
            return;
        }

        Task::where('id', $taskId)
            ->where('board_id', $this->boardId)
            ->delete();
    }

    public function openTaskEditor($taskId): void
    {
        if ($this->isCapstoneTracker) {
            return;
        }

        $task = Task::where('id', $taskId)
            ->where('board_id', $this->boardId)
            ->first();

        if (!$task) {
            return;
        }

        $this->editingTaskId = $task->id;
        $this->editingTaskTitle = $task->title ?? '';
        $this->editingTaskDescription = $task->description ?? '';
        $this->editingTaskDueDate = $task->due_date ? (string) $task->due_date : null;
        $this->editingTaskAssigneeIds = collect($task->assignees ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        $this->editingTaskChecklistText = collect($task->checklists ?? [])
            ->map(function ($item) {
                if (is_string($item)) {
                    return '- [ ] ' . trim($item);
                }

                $text = trim((string) ($item['text'] ?? ''));
                if ($text === '') {
                    return null;
                }

                $checked = (bool) ($item['done'] ?? false);
                return '- [' . ($checked ? 'x' : ' ') . '] ' . $text;
            })
            ->filter()
            ->implode(PHP_EOL);
        $this->editingTaskAttachmentText = collect($task->attachments ?? [])
            ->map(function ($item) {
                if (is_string($item)) {
                    return trim($item);
                }

                return trim((string) ($item['url'] ?? ''));
            })
            ->filter()
            ->implode(PHP_EOL);
    }

    public function closeTaskEditor(): void
    {
        $this->editingTaskId = null;
        $this->editingTaskTitle = '';
        $this->editingTaskDescription = '';
        $this->editingTaskDueDate = null;
        $this->editingTaskAssigneeIds = [];
        $this->editingTaskChecklistText = '';
        $this->editingTaskAttachmentText = '';
    }

    public function saveTaskEditor(): void
    {
        if (! $this->ensureSubscriptionAccess()) {
            return;
        }

        if (!$this->editingTaskId) {
            return;
        }

        $this->validate([
            'editingTaskTitle' => 'required|string|max:255',
            'editingTaskDescription' => 'nullable|string',
            'editingTaskDueDate' => 'nullable|date',
            'editingTaskAssigneeIds' => 'array',
            'editingTaskAssigneeIds.*' => 'integer|min:1',
        ]);

        $task = Task::where('id', $this->editingTaskId)
            ->where('board_id', $this->boardId)
            ->first();

        if (!$task) {
            $this->closeTaskEditor();
            return;
        }

        $task->update([
            'title' => trim($this->editingTaskTitle),
            'description' => filled($this->editingTaskDescription) ? trim($this->editingTaskDescription) : null,
            'due_date' => $this->tenantHasFeature('due-date-reminders')
                ? ($this->editingTaskDueDate ?: null)
                : $task->due_date,
            'assignees' => $this->tenantHasFeature('role-permissions')
                ? $this->sanitizeAssigneeIds($this->editingTaskAssigneeIds)
                : ($task->assignees ?? []),
            'checklists' => $this->tenantHasFeature('task-checklists')
                ? $this->parseChecklistLines($this->editingTaskChecklistText)
                : ($task->checklists ?? []),
            'attachments' => $this->tenantHasFeature('file-attachments')
                ? $this->parseAttachmentLines($this->editingTaskAttachmentText)
                : ($task->attachments ?? []),
        ]);

        $this->dispatch('notify', message: 'Card updated.', type: 'success');
        $this->closeTaskEditor();
    }

    private function sanitizeAssigneeIds(array $candidateIds): array
    {
        $ids = collect($candidateIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            return [];
        }

        try {
            return DB::connection('tenant')
                ->table('users')
                ->whereIn('id', $ids)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::warning('Failed to validate assignee ids; dropping selection.', [
                'board_id' => $this->boardId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function exportAuditPdf()
    {
        if (! $this->tenantHasFeature('audit-export')) {
            abort(403, 'Audit export is not available on your current plan.');
        }

        $rows = DB::connection('tenant')
            ->table('activity_logs as al')
            ->leftJoin('tasks as t', 't.id', '=', 'al.task_id')
            ->leftJoin('stages as os', 'os.id', '=', 'al.old_stage_id')
            ->leftJoin('stages as ns', 'ns.id', '=', 'al.new_stage_id')
            ->leftJoin('users as u', 'u.id', '=', 'al.user_id')
            ->select([
                'al.id',
                'al.created_at',
                'u.name as actor_name',
                't.title as task_title',
                'os.name as old_stage_name',
                'ns.name as new_stage_name',
                'al.ip_address',
            ])
            ->where('t.board_id', $this->boardId)
            ->orderByDesc('al.id')
            ->get();

        $preparedRows = $rows
            ->map(function ($row) {
                return (object) [
                    'id' => (int) ($row->id ?? 0),
                    'created_at' => $this->formatAuditTimestamp($row->created_at ?? null),
                    'actor_name' => $this->readableAuditValue((string) ($row->actor_name ?? ''), 'Unknown user', 70),
                    'task_title' => $this->readableAuditValue((string) ($row->task_title ?? ''), 'Unknown task', 120),
                    'old_stage_name' => $this->readableAuditValue((string) ($row->old_stage_name ?? ''), 'Previous stage', 40),
                    'new_stage_name' => $this->readableAuditValue((string) ($row->new_stage_name ?? ''), 'New stage', 40),
                    'ip_address' => $this->readableAuditValue((string) ($row->ip_address ?? ''), '-', 45),
                ];
            })
            ->values();

        $generatedAt = now();
        $filename = 'board-audit-' . $this->boardId . '-' . $generatedAt->format('Ymd_His') . '.pdf';

        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        $pdf = Pdf::loadView('pdf.audit-report', [
            'title' => 'Board Audit Report',
            'tenantName' => (string) ($tenant?->name ?? 'Workspace'),
            'tenantDomain' => (string) ($tenant?->domain ?? 'tenant'),
            'generatedAt' => $generatedAt,
            'scopeLabel' => 'Board: ' . ($this->boardName ?: ('Board #' . $this->boardId)),
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

    public function generateGuestBoardLink(): void
    {
        if (! $this->tenantHasFeature('guest-boards')) {
            $this->dispatch('notify', message: 'Guest board sharing is not included in your current plan.', type: 'error');
            return;
        }

        if (! auth()->user()?->hasRole('Team Supervisor')) {
            abort(403);
        }

        $board = Board::where('id', $this->boardId)->first();
        if (! $board) {
            return;
        }

        $token = Str::random(48);
        $members = is_array($board->members) ? $board->members : [];
        $members['guest_access_token'] = $token;
        $members['guest_access_generated_at'] = now()->toDateTimeString();
        $board->update(['members' => $members]);

        $this->guestShareUrl = route('guest.board.view', ['slug' => $this->boardSlugValue, 'token' => $token]);
        $this->dispatch('notify', message: 'Guest board link generated.', type: 'success');
    }

    private function parseChecklistLines(string $text): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $text) ?: [])
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->map(function (string $line) {
                if (preg_match('/^-\s*\[(x| )\]\s*(.+)$/i', $line, $matches)) {
                    return [
                        'text' => trim($matches[2]),
                        'done' => strtolower($matches[1]) === 'x',
                    ];
                }

                return [
                    'text' => ltrim($line, '- '),
                    'done' => false,
                ];
            })
            ->filter(fn (array $item) => $item['text'] !== '')
            ->values()
            ->all();
    }

    private function parseAttachmentLines(string $text): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $text) ?: [])
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->map(fn (string $line) => ['name' => Str::limit($line, 70, ''), 'url' => $line])
            ->values()
            ->all();
    }

    private function loadCapstoneTasks(Board $board): array
    {
        $members = is_array($board->members) ? $board->members : [];
        $tasks = $members['capstone_tasks'] ?? [];

        if (!is_array($tasks) || empty($tasks)) {
            return [[
                'uid' => 1,
                'id' => 1,
                'title' => '',
                'owner' => '',
                'start_date' => '',
                'due_date' => '',
                'progress' => 0,
            ]];
        }

        return collect($tasks)
            ->values()
            ->map(function ($task, $index) {
                $task = is_array($task) ? $task : [];

                return [
                    'uid' => (int) ($task['uid'] ?? ($index + 1)),
                    'id' => $index + 1,
                    'title' => (string) ($task['title'] ?? ''),
                    'owner' => (string) ($task['owner'] ?? ''),
                    'start_date' => (string) ($task['start_date'] ?? ''),
                    'due_date' => (string) ($task['due_date'] ?? ''),
                    'progress' => max(0, min(100, (int) ($task['progress'] ?? 0))),
                ];
            })
            ->all();
    }

    private function readableAuditValue(string $value, string $fallback = '-', int $limit = 120): string
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

    private function formatAuditTimestamp(mixed $value): string
    {
        if (! $value) {
            return '-';
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('M d, Y h:i A');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function resolveTenantUserId(int $tenantId): ?int
    {
        $authUser = auth()->user();
        if (!$authUser) {
            return null;
        }

        $users = DB::connection('tenant')->table('users');

        $existingId = $users
            ->where('email', $authUser->email)
            ->value('id');

        if ($existingId) {
            return (int) $existingId;
        }

        $legacyId = $users->where('id', $authUser->id)->value('id');
        if ($legacyId) {
            return (int) $legacyId;
        }

        $now = now();
        $payload = [
            'name' => $authUser->name,
            'email' => $authUser->email,
            'password' => $authUser->password,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (Schema::connection('tenant')->hasColumn('users', 'email_verified_at')) {
            $payload['email_verified_at'] = $authUser->email_verified_at ?: $now;
        }

        if (Schema::connection('tenant')->hasColumn('users', 'remember_token')) {
            $payload['remember_token'] = $authUser->remember_token;
        }

        if (Schema::connection('tenant')->hasColumn('users', 'tenant_id')) {
            $payload['tenant_id'] = $tenantId;
        }

        if (Schema::connection('tenant')->hasColumn('users', 'role')) {
            $payload['role'] = $authUser->hasRole('Team Supervisor') ? 'supervisor' : 'member';
        }

        try {
            $insertedId = $users->insertGetId($payload);
            return $insertedId ? (int) $insertedId : null;
        } catch (\Throwable $e) {
            Log::warning('Tenant user auto-sync failed during task creation.', [
                'email' => $authUser->email,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            $retryId = DB::connection('tenant')
                ->table('users')
                ->where('email', $authUser->email)
                ->value('id');

            if ($retryId) {
                return (int) $retryId;
            }

            return null;
        }
    }
}; ?>

<div class="bg-white min-h-screen" wire:poll.5s="refreshBoardData" x-data="kanbanBoardDnD()">
    <div class="bg-white shadow-sm border-b sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between gap-4">
            <div class="flex min-w-0 items-center gap-4">
                <a href="{{ route('boards.index') }}" class="inline-flex items-center gap-2 text-gray-600 hover:text-gray-900 text-sm font-medium">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back
                </a>
                <h1 class="text-2xl font-bold text-gray-900 mb-0 truncate">{{ $boardName }}</h1>
            </div>
            <div class="hidden md:flex items-center gap-2">
                @if($featureFlags['member_invites'])
                    <a href="{{ route('team.invite') }}" class="px-3 py-2 bg-emerald-600 text-white rounded-lg text-xs font-semibold hover:bg-emerald-700">Invite Member</a>
                @endif
                @if($featureFlags['guest_boards'] && auth()->user()?->hasRole('Team Supervisor'))
                    <button wire:click="generateGuestBoardLink" class="px-3 py-2 bg-indigo-600 text-white rounded-lg text-xs font-semibold hover:bg-indigo-700">
                        {{ $guestShareUrl ? 'Regenerate Guest Link' : 'Generate Guest Link' }}
                    </button>
                @endif
                @if($featureFlags['audit_export'])
                    <button wire:click="exportAuditPdf" class="px-3 py-2 bg-slate-800 text-white rounded-lg text-xs font-semibold hover:bg-slate-900">
                        Export Audit PDF
                    </button>
                @endif
                <a href="{{ route('billing') }}" class="px-3 py-2 bg-emerald-600 text-white rounded-lg text-xs font-semibold hover:bg-emerald-700">Billing</a>
            </div>
        </div>
    </div>

    @if($featureFlags['guest_boards'] && filled($guestShareUrl))
        <div class="mx-auto mt-4 max-w-7xl px-6">
            <div class="rounded-xl border border-indigo-100 bg-indigo-50 px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700">Guest Board Link</p>
                <div class="mt-2 flex items-center gap-2">
                    <input type="text" readonly value="{{ $guestShareUrl }}" class="w-full rounded-lg border border-indigo-200 bg-white px-3 py-2 text-xs text-slate-700">
                </div>
            </div>
        </div>
    @endif

    @if($isCapstoneTracker)
        <div class="max-w-7xl mx-auto px-6 py-6 space-y-6" x-data="capstoneTaskTracker(@entangle('capstoneTasks').live)" x-init="init()">
            <div class="bg-gradient-to-r from-emerald-50 to-sky-50 border border-emerald-100 rounded-2xl p-4 mb-5">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">Capstone Task Tracker</h2>
                        <p class="text-sm text-gray-600">Single-list task table with auto duration and real-time summary.</p>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-xl px-4 py-2 text-sm">
                        <span class="text-gray-500">Overall Progress</span>
                        <div class="font-bold text-gray-900" x-text="getOverallProgress().toFixed(1) + '%'">0%</div>
                    </div>
                </div>
                <div class="mt-3 h-2.5 bg-gray-200 rounded-full overflow-hidden">
                    <div class="h-full bg-emerald-500 transition-all" :style="`width: ${Math.min(100, Math.max(0, getOverallProgress()))}%`"></div>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-2xl overflow-x-auto">
                <table class="w-full min-w-[980px] text-sm">
                    <thead class="bg-gray-50 text-gray-600">
                        <tr>
                            <th class="text-left px-3 py-3 font-semibold w-16">Task ID</th>
                            <th class="text-left px-3 py-3 font-semibold">Task Title</th>
                            <th class="text-left px-3 py-3 font-semibold">Task Owner</th>
                            <th class="text-left px-3 py-3 font-semibold w-40">Start Date</th>
                            <th class="text-left px-3 py-3 font-semibold w-40">Due Date</th>
                            <th class="text-left px-3 py-3 font-semibold w-32">Duration</th>
                            <th class="text-left px-3 py-3 font-semibold w-32">Progress (%)</th>
                            <th class="text-left px-3 py-3 font-semibold w-48">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(task, index) in tasks" :key="task.uid">
                            <tr class="border-t border-gray-100" :class="isOverdue(task) ? 'bg-red-50/70' : ''">
                                <td class="px-3 py-2 font-semibold text-gray-700" x-text="index + 1"></td>
                                <td class="px-3 py-2">
                                    <input type="text" x-model="task.title" @input="persist()" class="w-full border border-gray-200 rounded-lg px-2 py-1.5 focus:ring-2 focus:ring-emerald-500 focus:border-transparent" placeholder="Define task" />
                                </td>
                                <td class="px-3 py-2">
                                    <input type="text" x-model="task.owner" @input="persist()" class="w-full border border-gray-200 rounded-lg px-2 py-1.5 focus:ring-2 focus:ring-emerald-500 focus:border-transparent" placeholder="Assign owner" />
                                </td>
                                <td class="px-3 py-2">
                                    <input type="date" x-model="task.start_date" @change="persist()" class="w-full border border-gray-200 rounded-lg px-2 py-1.5 focus:ring-2 focus:ring-emerald-500 focus:border-transparent" />
                                </td>
                                <td class="px-3 py-2">
                                    <input type="date" x-model="task.due_date" @change="persist()" class="w-full border border-gray-200 rounded-lg px-2 py-1.5 focus:ring-2 focus:ring-emerald-500 focus:border-transparent" />
                                </td>
                                <td class="px-3 py-2 font-medium text-gray-700" x-text="formatDuration(task)"></td>
                                <td class="px-3 py-2">
                                    <input type="number" min="0" max="100" x-model.number="task.progress" @input="task.progress = clampProgress(task.progress); persist()" class="w-full border border-gray-200 rounded-lg px-2 py-1.5 focus:ring-2 focus:ring-emerald-500 focus:border-transparent" />
                                </td>
                                <td class="px-3 py-2">
                                    <div class="flex items-center gap-2">
                                        <button @click="insertAbove(index)" class="px-2.5 py-1.5 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 text-xs font-semibold">Insert Above</button>
                                        <button @click="insertBelow(index)" class="px-2.5 py-1.5 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 text-xs font-semibold">Insert Below</button>
                                        <button @click="removeRow(index)" class="px-2.5 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 text-xs font-semibold">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex items-center gap-3">
                <button @click="insertBelow(tasks.length - 1)" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-semibold hover:bg-emerald-700">Add Row</button>
                <p class="text-xs text-gray-500">Computed fields (Task ID, Duration, Overall Progress) update automatically and are not editable.</p>
            </div>

        </div>
    @else
        @if($stages->isEmpty())
            <div class="flex items-center justify-center min-h-[70vh] p-8">
                <div class="max-w-md text-center">
                    <div class="w-24 h-24 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">No Stages Yet</h3>
                    <p class="text-gray-600">Create stages to organize your tasks.</p>
                </div>
            </div>
        @else
            <div class="px-6 py-8">
                @if(! $featureFlags['basic_kanban'])
                    <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
                        Basic Kanban interactions are disabled on your current plan. You can still view board data.
                    </div>
                @endif
                <div class="rounded-[22px] border border-nsync-green-200 bg-gradient-to-b from-white to-nsync-green-50/40 px-4 py-4 shadow-lg">
                    <div class="mb-4 rounded-xl border border-nsync-green-200 bg-white px-4 py-3">
                        <div class="flex items-center justify-between gap-3 px-1">
                        <div>
                            <h2 class="text-xl font-black tracking-tight text-slate-900">{{ $boardName }}</h2>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">Board Workspace</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full border border-nsync-green-200 bg-nsync-green-50 px-3 py-1 text-xs font-semibold text-nsync-green-700">
                                {{ $stages->count() }} {{ \Illuminate\Support\Str::plural('List', $stages->count()) }}
                            </span>
                            <span class="rounded-full border border-slate-200 bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                {{ $stages->sum(fn ($stage) => $stage->tasks->count()) }} Cards
                            </span>
                        </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-5">
                        <div class="min-w-0 overflow-x-auto pb-4 [scrollbar-width:thin]">
                            <div class="inline-flex min-w-max items-start gap-6 pr-2">
                            @foreach($stages as $stage)
                                <div class="w-[360px] lg:w-[380px] flex-shrink-0 rounded-xl border border-slate-300 bg-white shadow-sm" x-data="{ open: false }" wire:key="stage-{{ $stage->id }}">
                                    <div class="relative rounded-t-xl border-b border-nsync-green-200 px-3.5 py-3" style="background: linear-gradient(135deg, color-mix(in srgb, var(--tenant-primary) 12%, white 88%), color-mix(in srgb, var(--tenant-primary) 22%, white 78%));">
                                        <h2 class="mx-auto max-w-[82%] truncate text-center text-[17px] font-extrabold tracking-tight text-slate-800">{{ $stage->name }}</h2>
                                        <span class="absolute right-3.5 top-1/2 -translate-y-1/2 rounded-full border border-nsync-green-200 bg-white px-2.5 py-1 text-xs font-bold text-nsync-green-700">{{ $stage->tasks->count() }}</span>
                                    </div>

                                    <div class="space-y-3 min-h-[320px] max-h-[calc(100vh-350px)] overflow-y-auto px-2.5 pb-2.5 pt-2.5"
                                         data-stage-id="{{ $stage->id }}"
                                         data-stage-name="{{ $stage->name }}"
                                         x-on:dragover.prevent="$el.classList.add('ring-2','ring-emerald-300')"
                                         x-on:dragleave="$el.classList.remove('ring-2','ring-emerald-300')"
                                         x-on:drop.prevent="$el.classList.remove('ring-2','ring-emerald-300'); dropTask($event, {{ $stage->id }})">

                                        @foreach($stage->tasks as $task)
                                            @php
                                                $taskChecklists = collect($task->checklists ?? []);
                                                $completedChecklistCount = $taskChecklists->filter(fn ($item) => (bool) (is_array($item) ? ($item['done'] ?? false) : false))->count();
                                                $attachmentCount = collect($task->attachments ?? [])->count();
                                                $assigneeNames = collect($task->assignees ?? [])
                                                    ->map(fn ($id) => (int) $id)
                                                    ->map(fn (int $id) => $workspaceMembersMap[$id] ?? null)
                                                    ->filter()
                                                    ->values();
                                            @endphp
                                            <div class="group relative isolate cursor-move overflow-hidden rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition hover:-translate-y-0.5 hover:border-nsync-green-300 hover:shadow-md"
                                                 wire:key="task-{{ $task->id }}"
                                                 data-task-id="{{ $task->id }}"
                                                 @if($featureFlags['basic_kanban'])
                                                 draggable="true"
                                                x-on:dragstart="dragTask($event, {{ $task->id }})"
                                                x-on:dragend="clearDraggedTask()"
                                                 @endif>
                                                <div class="mb-1 flex items-start justify-between gap-2">
                                                    <button wire:click="openTaskEditor({{ $task->id }})" class="flex-1 text-left">
                                                        <p class="font-semibold text-slate-900 text-sm leading-snug">{{ $task->title }}</p>
                                                    </button>
                                                    <button wire:click="deleteTask({{ $task->id }})" x-on:click.stop class="p-1 text-slate-400 hover:text-red-600 opacity-0 group-hover:opacity-100">
                                                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                            <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
                                                            <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 01-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 01 1-1H6a1 1 0 0 1 1-1h2a1 1 0 01 1 1h3.5a1 1 0 01 1 1v1z"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                                <button wire:click="openTaskEditor({{ $task->id }})" class="block w-full text-left">
                                                    @if($task->description)
                                                        <p class="text-xs text-slate-600 line-clamp-2">{{ $task->description }}</p>
                                                    @else
                                                        <p class="text-xs text-slate-400">Add a description...</p>
                                                    @endif
                                                    @if($featureFlags['due_date_reminders'] && $task->due_date)
                                                        <p class="mt-2 inline-flex rounded-md bg-emerald-50 px-2 py-1 text-[11px] font-semibold text-emerald-700">
                                                            Due {{ \Illuminate\Support\Carbon::parse($task->due_date)->format('M d, Y') }}
                                                        </p>
                                                    @endif
                                                    @if($featureFlags['task_checklists'] && $taskChecklists->isNotEmpty())
                                                        <p class="mt-2 text-[11px] font-semibold text-slate-600">
                                                            Checklist: {{ $completedChecklistCount }}/{{ $taskChecklists->count() }}
                                                        </p>
                                                    @endif
                                                    @if($featureFlags['file_attachments'] && $attachmentCount > 0)
                                                        <p class="mt-1 text-[11px] font-semibold text-slate-600">
                                                            Attachments: {{ $attachmentCount }}
                                                        </p>
                                                    @endif
                                                    @if($featureFlags['card_assignees'] && $assigneeNames->isNotEmpty())
                                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                                            @foreach($assigneeNames->take(3) as $assigneeName)
                                                                <span class="inline-flex rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-semibold text-emerald-700">
                                                                    {{ $assigneeName }}
                                                                </span>
                                                            @endforeach
                                                            @if($assigneeNames->count() > 3)
                                                                <span class="inline-flex rounded-full bg-slate-100 px-2 py-1 text-[10px] font-semibold text-slate-700">
                                                                    +{{ $assigneeNames->count() - 3 }} more
                                                                </span>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </button>
                                            </div>
                                        @endforeach

                                        <div>
                                            <button x-show="!open" @click="open = true; $nextTick(() => $refs.taskInput.focus())" class="w-full rounded-lg border border-dashed border-nsync-green-300 bg-nsync-green-50/70 px-3 py-2 text-left text-sm font-semibold text-nsync-green-700 transition hover:border-nsync-green-400 hover:bg-nsync-green-50" @disabled(! $featureFlags['basic_kanban'])>
                                                + Add a card
                                            </button>
                                            <div x-show="open" x-transition @click.away="open = false" class="space-y-2">
                                                <input type="text" wire:model="newTaskTitle" x-ref="taskInput" @keydown.enter="$wire.addTask({{ $stage->id }}); open = false" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Enter card title..." />
                                                <div class="flex gap-2">
                                                    <button wire:click="addTask({{ $stage->id }})" @click="open = false" class="flex-1 rounded-lg px-3 py-2 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700">Add</button>
                                                    <button @click="open = false" class="flex-1 rounded-lg bg-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300">Cancel</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    @if($featureFlags['activity_logs'] || $featureFlags['audit_export'])
                    <aside class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div class="border-t border-slate-100 bg-slate-50 px-6 py-5">
                                <div class="mb-3 flex items-center justify-between">
                                    <h3 class="text-sm font-bold text-slate-800">Activity Logs</h3>
                                    @if($featureFlags['audit_export'])
                                        <button wire:click="exportAuditPdf" class="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-900">
                                            Export PDF
                                        </button>
                                    @endif
                                </div>
                                <div class="max-h-56 space-y-2 overflow-y-auto">
                                    @forelse($activityLogs as $log)
                                        @php
                                            $taskTitleRaw = (string) ($log->task_title ?? '');
                                            $fromStageRaw = (string) ($log->old_stage_name ?? '');
                                            $toStageRaw = (string) ($log->new_stage_name ?? '');

                                            $looksEncoded = function (string $value): bool {
                                                $trimmed = trim($value);
                                                if ($trimmed === '') {
                                                    return false;
                                                }

                                                return \Illuminate\Support\Str::startsWith($trimmed, 'eyJ')
                                                    || (strlen($trimmed) > 48 && !preg_match('/\s/', $trimmed));
                                            };

                                            $displayTaskTitle = $taskTitleRaw === ''
                                                ? 'a task'
                                                : ($looksEncoded($taskTitleRaw) ? 'a task' : \Illuminate\Support\Str::limit($taskTitleRaw, 90));
                                            $displayFromStage = $fromStageRaw === ''
                                                ? '-'
                                                : ($looksEncoded($fromStageRaw) ? 'previous stage' : \Illuminate\Support\Str::limit($fromStageRaw, 40));
                                            $displayToStage = $toStageRaw === ''
                                                ? '-'
                                                : ($looksEncoded($toStageRaw) ? 'next stage' : \Illuminate\Support\Str::limit($toStageRaw, 40));
                                        @endphp
                                        <div class="rounded-xl border border-slate-200 bg-white px-3 py-2">
                                            <p class="text-xs font-semibold text-slate-800">
                                                {{ $log->actor_name ?? 'Unknown' }} moved
                                                <span class="break-words text-slate-900">{{ $displayTaskTitle }}</span>
                                            </p>
                                            <p class="mt-1 break-words text-[11px] text-slate-500">
                                                {{ $displayFromStage }} -> {{ $displayToStage }} | {{ \Illuminate\Support\Carbon::parse($log->created_at)->diffForHumans() }}
                                            </p>
                                        </div>
                                    @empty
                                        <p class="text-xs text-slate-500">No activity has been logged for this board yet.</p>
                                    @endforelse
                                </div>
                            </div>
                    </aside>
                    @endif
                </div>
            </div>
        @endif
    @endif

    <div class="fixed z-[90]" style="right: 1.5rem; bottom: calc(env(safe-area-inset-bottom, 0px) + 1.5rem);" x-on:board-message-sent.window="chatOpen = true; $nextTick(() => scrollChatToBottom())">
        <button
            type="button"
            x-show="!chatOpen"
            @click="chatOpen = true; $nextTick(() => scrollChatToBottom())"
            class="group inline-flex items-center justify-center rounded-full text-white shadow-xl transition hover:scale-105 hover:shadow-2xl"
            style="width: 3rem; height: 3rem; min-width: 3rem; min-height: 3rem; border-radius: 9999px; background: linear-gradient(135deg, color-mix(in srgb, var(--tenant-primary) 92%, white 8%), color-mix(in srgb, var(--tenant-primary) 78%, black 22%));"
            aria-label="Open board messages"
        >
            <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h8m-8 4h6m8-2a8 8 0 11-16 0 8 8 0 0116 0z"/>
            </svg>
        </button>

        <div
            x-show="chatOpen"
            x-cloak
            x-transition.origin.bottom.right
            class="relative overflow-hidden rounded-3xl border border-nsync-green-200 bg-white shadow-2xl"
            style="width: min(34rem, calc(100vw - 2rem)); height: clamp(24rem, 58vh, 34rem);"
        >
            <div class="flex h-full flex-col">
            <div class="px-4 py-2 text-white" style="background: linear-gradient(135deg, color-mix(in srgb, var(--tenant-primary) 92%, white 8%), color-mix(in srgb, var(--tenant-primary) 78%, black 22%)); min-height: 3rem;">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-3">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-white/50 bg-white/20 text-xs font-black uppercase text-white">
                                {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr((string) ($tenant?->name ?? 'NSync'), 0, 1)) }}
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-black">{{ $tenant?->name ?? 'NSync' }}</p>
                                <p class="truncate text-xs text-emerald-100">Board Messages | {{ $boardName }}</p>
                            </div>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="chatOpen = false"
                        class="shrink-0 rounded-full bg-white/15 p-1.5 text-emerald-100 hover:bg-white/25 hover:text-white"
                        aria-label="Close board messages"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div x-ref="chatThread" class="flex-1 space-y-3 overflow-y-auto bg-gradient-to-b from-nsync-green-50 to-white px-4 py-2.5" style="min-height: 0;">
                @forelse($boardMessages as $message)
                    @php $isOwnMessage = $message->sender?->email === auth()->user()->email; @endphp
                    <div class="flex {{ $isOwnMessage ? 'justify-end' : 'justify-start' }}">
                        <div
                            class="max-w-[18rem] px-4 py-2.5 {{ $isOwnMessage ? 'text-slate-900' : 'bg-slate-100 text-slate-900 border border-slate-200' }}"
                            style="border-radius: 9999px; @if($isOwnMessage) background-color: color-mix(in srgb, var(--tenant-primary) 20%, #d1d5db 80%); @endif"
                        >
                            <p class="text-sm leading-5">{{ $message->message }}</p>
                            <p class="mt-1 text-[11px] text-slate-600">
                                {{ $message->sender?->name ?? 'Teammate' }} | {{ $message->created_at->diffForHumans() }}
                            </p>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-nsync-green-200 bg-white/80 px-4 py-8 text-center">
                        <p class="text-sm font-semibold text-slate-700">No messages yet</p>
                        <p class="mt-1 text-xs text-slate-500">Open the chat and start the board conversation.</p>
                    </div>
                @endforelse
            </div>

            <div class="sticky bottom-0 border-t border-nsync-green-100 bg-white px-4 pt-2 pb-1" style="min-height: 3rem;">
                <div class="flex items-center gap-2">
                    <input
                        type="text"
                        wire:model="newBoardMessage"
                        wire:keydown.enter.prevent="sendBoardMessage"
                        @keydown.enter="$nextTick(() => scrollChatToBottom())"
                        maxlength="1000"
                        placeholder="Type a message..."
                        class="flex-1 rounded-full border border-nsync-green-200 px-4 py-2 text-sm focus:border-transparent focus:ring-2 focus:ring-nsync-green-300"
                    >
                    <button
                        wire:click="sendBoardMessage"
                        @click="$nextTick(() => scrollChatToBottom())"
                        type="button"
                        aria-label="Send message"
                        class="inline-flex shrink-0 items-center justify-center rounded-full bg-nsync-green-600 px-5 py-2 text-sm font-bold text-white transition hover:bg-nsync-green-700"
                    >
                        Send
                    </button>
                </div>
            </div>
            </div>
        </div>
    </div>

    @if($editingTaskId)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" x-data="{ open: true }" x-show="open" x-cloak>
        <div @click.away="$wire.closeTaskEditor(); open = false" class="w-full max-w-lg overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-5">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">Edit Card</h2>
                    <p class="mt-1 text-sm text-gray-600">Update the card details like a Trello-style card editor.</p>
                </div>
                <button wire:click="closeTaskEditor" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="space-y-5 px-6 py-6">
                <div>
                    <label class="mb-2 block text-sm font-semibold text-gray-700">Title</label>
                    <input type="text" wire:model="editingTaskTitle" class="w-full rounded-2xl border border-gray-300 px-4 py-3 text-sm focus:border-transparent focus:ring-2 focus:ring-emerald-500">
                    @error('editingTaskTitle') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-gray-700">Description</label>
                    <textarea wire:model="editingTaskDescription" rows="5" class="w-full rounded-2xl border border-gray-300 px-4 py-3 text-sm focus:border-transparent focus:ring-2 focus:ring-emerald-500" placeholder="Add more details about this card..."></textarea>
                    @error('editingTaskDescription') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                @if($featureFlags['due_date_reminders'])
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gray-700">Due Date</label>
                        <input type="date" wire:model="editingTaskDueDate" class="w-full rounded-2xl border border-gray-300 px-4 py-3 text-sm focus:border-transparent focus:ring-2 focus:ring-emerald-500">
                        @error('editingTaskDueDate') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                @if($featureFlags['card_assignees'])
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gray-700">Assignees</label>
                        <select wire:model="editingTaskAssigneeIds" multiple class="w-full rounded-2xl border border-gray-300 px-4 py-3 text-sm focus:border-transparent focus:ring-2 focus:ring-emerald-500">
                            @forelse($workspaceMembers as $member)
                                <option value="{{ $member->id }}">{{ $member->name }} ({{ $member->email }})</option>
                            @empty
                                <option disabled>No members available</option>
                            @endforelse
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Hold Ctrl/Cmd to select multiple members for this card.</p>
                        @error('editingTaskAssigneeIds') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                        @error('editingTaskAssigneeIds.*') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @else
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900">
                        Card assignees are available on Standard and Pro plans.
                    </div>
                @endif

                @if($featureFlags['task_checklists'])
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gray-700">Checklist</label>
                        <textarea wire:model="editingTaskChecklistText" rows="4" class="w-full rounded-2xl border border-gray-300 px-4 py-3 text-sm focus:border-transparent focus:ring-2 focus:ring-emerald-500" placeholder="- [ ] Define requirement&#10;- [x] Completed item"></textarea>
                        <p class="mt-1 text-xs text-gray-500">One item per line. Use <code>- [x]</code> for completed and <code>- [ ]</code> for pending.</p>
                    </div>
                @endif

                @if($featureFlags['file_attachments'])
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gray-700">Attachment Links</label>
                        <textarea wire:model="editingTaskAttachmentText" rows="3" class="w-full rounded-2xl border border-gray-300 px-4 py-3 text-sm focus:border-transparent focus:ring-2 focus:ring-emerald-500" placeholder="https://example.com/file-1&#10;https://example.com/file-2"></textarea>
                        <p class="mt-1 text-xs text-gray-500">Paste one URL per line.</p>
                    </div>
                @endif
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-5">
                <button wire:click="closeTaskEditor" class="rounded-xl bg-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-200">Cancel</button>
                <button wire:click="saveTaskEditor" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Save Changes</button>
            </div>
        </div>
    </div>
    @endif
</div>

<script>
    function kanbanBoardDnD() {
        return {
            draggedTaskId: null,
            chatOpen: false,

            init() {
                this.$watch('chatOpen', (value) => {
                    if (value) {
                        this.$nextTick(() => this.scrollChatToBottom());
                    }
                });

                const autoScroll = () => {
                    if (this.chatOpen) {
                        this.$nextTick(() => this.scrollChatToBottom());
                    }
                };

                if (window.Livewire && typeof window.Livewire.hook === 'function') {
                    try {
                        window.Livewire.hook('message.processed', autoScroll);
                    } catch (error) {
                        // Livewire hook name differs by version; ignore safely.
                    }
                }
            },

            scrollChatToBottom() {
                const thread = this.$refs.chatThread;
                if (!thread) {
                    return;
                }

                thread.scrollTop = thread.scrollHeight;
            },

            dragTask(event, taskId) {
                this.draggedTaskId = String(taskId);
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('taskId', String(taskId));
            },

            clearDraggedTask() {
                this.draggedTaskId = null;
            },

            dropTask(event, stageId) {
                const taskId = event.dataTransfer.getData('taskId') || this.draggedTaskId;
                if (!taskId) {
                    return;
                }

                const card = document.querySelector(`[data-task-id="${taskId}"]`);
                const stageColumn = event.currentTarget;
                const stageName = String(stageColumn?.dataset?.stageName || '').trim().toLowerCase();

                if (card && stageColumn && card.parentElement !== stageColumn) {
                    const addCardPanel = stageColumn.lastElementChild;
                    stageColumn.insertBefore(card, addCardPanel);
                }

                if (card && ['done', 'completed', 'complete', 'resolved', 'published'].includes(stageName)) {
                    this.launchConfetti(card);
                }

                this.$wire.moveTask(taskId, stageId);
                this.clearDraggedTask();
            },

            launchConfetti(card) {
                const colors = ['#16a34a', '#22c55e', '#86efac', '#facc15', '#f97316'];
                const pieces = 14;

                if (!card) {
                    return;
                }

                const existing = card.querySelector('.nsync-card-confetti-layer');
                if (existing) {
                    existing.remove();
                }

                const layer = document.createElement('div');
                layer.className = 'nsync-card-confetti-layer';
                card.appendChild(layer);

                for (let i = 0; i < pieces; i++) {
                    const particle = document.createElement('span');
                    particle.className = 'nsync-card-confetti';
                    particle.style.left = `${10 + Math.random() * 80}%`;
                    particle.style.top = `${8 + Math.random() * 20}%`;
                    particle.style.backgroundColor = colors[i % colors.length];
                    particle.style.animationDuration = `${900 + Math.random() * 500}ms`;
                    particle.style.animationDelay = `${Math.random() * 90}ms`;
                    particle.style.setProperty('--confetti-x', `${(Math.random() - 0.5) * 120}px`);
                    particle.style.setProperty('--confetti-y', `${55 + Math.random() * 30}px`);
                    particle.style.setProperty('--confetti-rotate', `${220 + Math.random() * 240}deg`);

                    layer.appendChild(particle);
                }

                setTimeout(() => layer.remove(), 1800);
            },
        };
    }

    function capstoneTaskTracker(entangledTasks) {
        return {
            tasks: entangledTasks,
            nextUid: 1,
            saveTimer: null,

            init() {
                this.tasks = Array.isArray(this.tasks) && this.tasks.length
                    ? this.tasks.map((t, idx) => this.normalizeTask(t, idx))
                    : [this.newTask()];
                this.reindex();
                this.nextUid = Math.max(...this.tasks.map(t => t.uid), 0) + 1;
            },

            normalizeTask(task, idx) {
                return {
                    uid: Number(task?.uid || idx + 1),
                    id: idx + 1,
                    title: task?.title || '',
                    owner: task?.owner || '',
                    start_date: task?.start_date || '',
                    due_date: task?.due_date || '',
                    progress: this.clampProgress(task?.progress ?? 0),
                };
            },

            newTask() {
                return {
                    uid: this.nextUid++,
                    id: 0,
                    title: '',
                    owner: '',
                    start_date: '',
                    due_date: '',
                    progress: 0,
                };
            },

            insertAbove(index) {
                this.tasks.splice(index, 0, this.newTask());
                this.reindex();
                this.persist();
            },

            insertBelow(index) {
                const safeIndex = index < 0 ? this.tasks.length - 1 : index;
                this.tasks.splice(safeIndex + 1, 0, this.newTask());
                this.reindex();
                this.persist();
            },

            removeRow(index) {
                this.tasks.splice(index, 1);
                if (!this.tasks.length) {
                    this.tasks.push(this.newTask());
                }
                this.reindex();
                this.persist();
            },

            reindex() {
                this.tasks = this.tasks.map((t, idx) => ({ ...t, id: idx + 1, progress: this.clampProgress(t.progress) }));
            },

            clampProgress(value) {
                const number = Number(value);
                if (Number.isNaN(number)) {
                    return 0;
                }
                return Math.max(0, Math.min(100, number));
            },

            getDuration(start, end) {
                if (!start || !end) {
                    return 0;
                }
                const s = new Date(start);
                const e = new Date(end);
                if (Number.isNaN(s.getTime()) || Number.isNaN(e.getTime())) {
                    return 0;
                }
                return (e - s) / (1000 * 60 * 60 * 24);
            },

            formatDuration(task) {
                const days = this.getDuration(task.start_date, task.due_date);
                if (!task.start_date || !task.due_date) {
                    return '-';
                }
                return `${days} day${Math.abs(days) === 1 ? '' : 's'}`;
            },

            getOverallProgress() {
                const total = this.tasks.reduce((sum, t) => sum + this.clampProgress(t.progress || 0), 0);
                return this.tasks.length ? (total / this.tasks.length) : 0;
            },

            isOverdue(task) {
                if (!task.due_date) {
                    return false;
                }
                const due = new Date(task.due_date);
                const now = new Date();
                due.setHours(23, 59, 59, 999);
                return due < now && this.clampProgress(task.progress) < 100;
            },

            persist() {
                this.reindex();
                this.tasks = this.tasks.map((t) => ({
                    uid: t.uid,
                    id: t.id,
                    title: t.title || '',
                    owner: t.owner || '',
                    start_date: t.start_date || '',
                    due_date: t.due_date || '',
                    progress: this.clampProgress(t.progress || 0),
                }));

                clearTimeout(this.saveTimer);
                this.saveTimer = setTimeout(() => {
                    this.$wire.syncCapstoneTasks(this.tasks);
                }, 250);
            },
        };
    }
</script>

<style>
    .nsync-card-confetti-layer {
        position: absolute;
        inset: 0;
        overflow: hidden;
        pointer-events: none;
        z-index: 20;
    }

    .nsync-card-confetti {
        position: absolute;
        width: 0.7rem;
        height: 1rem;
        border-radius: 0.2rem;
        opacity: 0.95;
        pointer-events: none;
        animation-name: nsync-card-confetti-burst;
        animation-timing-function: ease-out;
        animation-fill-mode: forwards;
    }

    @keyframes nsync-card-confetti-burst {
        0% {
            opacity: 0;
            transform: translate3d(0, 0, 0) rotate(0deg) scale(0.7);
        }

        10% {
            opacity: 1;
        }

        100% {
            opacity: 0;
            transform: translate3d(var(--confetti-x), var(--confetti-y), 0) rotate(var(--confetti-rotate)) scale(1);
        }
    }
</style>



