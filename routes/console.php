<?php

use App\Support\PrivacyEncryption;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('tenants:migrate {--fresh} {--path=database/migrations/tenant}', function () {
    $tenants = \App\Models\Tenant::whereNotNull('database')->get();
    $this->info("Running migrations for {$tenants->count()} tenants");

    foreach ($tenants as $tenant) {
        $this->info("→ {$tenant->name} ({$tenant->database})");

        Config::set('database.connections.tenant', array_merge(
            Config::get('database.connections.mysql'),
            ['database' => $tenant->database]
        ));
        DB::purge('tenant');

        $params = [
            '--database' => 'tenant',
            '--force' => true,
        ];

        if ($path = $this->option('path')) {
            $params['--path'] = $path;
        }

        $this->option('fresh')
            ? Artisan::call('migrate:fresh', $params)
            : Artisan::call('migrate', $params);

        $this->line(trim(Artisan::output()));
    }
})->purpose('Run tenant migrations across all tenant databases');

Artisan::command('tenants:provision-missing {--rename}', function () {
    $missing = \App\Models\Tenant::all();
    $created = 0;

    foreach ($missing as $tenant) {
        $needsName = blank($tenant->database) || $this->option('rename');
        if ($needsName) {
            $slug = \Illuminate\Support\Str::slug($tenant->name ?? 'tenant', '_');
            $tenant->updateQuietly(['database' => ($slug ?: 'tenant_'.$tenant->id) . '_nsync_db']);
        }

        // If DB schema not present, CreateTenantDatabase will create and migrate
        $dbExists = \Illuminate\Support\Facades\DB::selectOne(
            "SELECT SCHEMA_NAME FROM information_schema.schemata WHERE SCHEMA_NAME = ?",
            [$tenant->database]
        );

        if (!$dbExists || $needsName) {
            (new \App\Jobs\CreateTenantDatabase($tenant->fresh()))->handle();
            $created++;
            $this->info("Provisioned DB for tenant {$tenant->id}: {$tenant->database}");
        }
    }

    $this->info("Completed. Provisioned/verified {$created} tenant databases.");
})->purpose('Backfill database-per-tenant for legacy tenants (optionally rename to *_nsync_db)');

Artisan::command('tenants:list-simple', function () {
    $rows = \App\Models\Tenant::select('id', 'name', 'domain', 'database')->get()->toArray();
    $this->table(['id', 'name', 'domain', 'database'], $rows);
});

Artisan::command('tenants:backfill-user-tenant-id', function () {
    $tenants = \App\Models\Tenant::whereNotNull('database')->get();
    $updated = 0;

    foreach ($tenants as $tenant) {
        Config::set('database.connections.tenant', array_merge(
            Config::get('database.connections.mysql'),
            ['database' => $tenant->database]
        ));
        DB::purge('tenant');

        // Ensure column exists
        if (!DB::connection('tenant')->getSchemaBuilder()->hasColumn('users', 'tenant_id')) {
            $this->warn("Skipping {$tenant->name}: users.tenant_id column missing");
            continue;
        }

        $count = DB::connection('tenant')->table('users')
            ->whereNull('tenant_id')
            ->update(['tenant_id' => $tenant->id]);

        if ($count > 0) {
            $this->info("Updated {$count} users for {$tenant->name}");
            $updated += $count;
        }
    }

    $this->info("Backfill complete. Updated {$updated} user rows.");
})->purpose('Set tenant_id on tenant DB users rows where missing');

Artisan::command('privacy:encrypt-tenant-data', function () {
    $tenants = \App\Models\Tenant::whereNotNull('database')->get();
    $totalUpdates = 0;

    $encryptTable = function (string $connection, string $table, array $columns) use (&$totalUpdates) {
        if (!Schema::connection($connection)->hasTable($table)) {
            return;
        }

        DB::connection($connection)
            ->table($table)
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($connection, $table, $columns, &$totalUpdates) {
                foreach ($rows as $row) {
                    $updates = [];

                    foreach ($columns as $column => $type) {
                        if (!property_exists($row, $column)) {
                            continue;
                        }

                        $rawValue = $row->{$column};
                        $encryptedValue = $type === 'array'
                            ? PrivacyEncryption::encryptArrayIfNeeded($rawValue)
                            : PrivacyEncryption::encryptStringIfNeeded($rawValue);

                        if ($encryptedValue !== $rawValue) {
                            $updates[$column] = $encryptedValue;
                        }
                    }

                    if ($updates !== []) {
                        DB::connection($connection)
                            ->table($table)
                            ->where('id', $row->id)
                            ->update($updates);

                        $totalUpdates++;
                    }
                }
            });
    };

    foreach ($tenants as $tenant) {
        Config::set('database.connections.tenant', array_merge(
            Config::get('database.connections.mysql'),
            ['database' => $tenant->database]
        ));
        DB::purge('tenant');

        $this->info("Encrypting tenant data for {$tenant->name} ({$tenant->database})");

        $encryptTable('tenant', 'boards', [
            'name' => 'string',
            'members' => 'array',
        ]);

        $encryptTable('tenant', 'stages', [
            'name' => 'string',
        ]);

        $encryptTable('tenant', 'tasks', [
            'title' => 'string',
            'description' => 'string',
            'assignees' => 'array',
            'labels' => 'array',
            'attachments' => 'array',
            'checklists' => 'array',
        ]);
    }

    $this->info("Encryption backfill complete. Updated {$totalUpdates} rows.");
})->purpose('Encrypt sensitive tenant workspace content at rest in tenant databases');

Artisan::command('tasks:send-due-reminders {--date=}', function () {
    $targetDate = $this->option('date')
        ? \Illuminate\Support\Carbon::parse((string) $this->option('date'))->toDateString()
        : now()->addDay()->toDateString();

    $tenants = \App\Models\Tenant::whereNotNull('database')
        ->where('status', 'active')
        ->get();

    $sent = 0;
    $checked = 0;

    foreach ($tenants as $tenant) {
        if (! $tenant->hasFeature('due-date-reminders')) {
            continue;
        }

        $checked++;

        Config::set('database.connections.tenant', array_merge(
            Config::get('database.connections.mysql'),
            ['database' => $tenant->database]
        ));
        DB::purge('tenant');

        $alreadySentKey = "task_due_reminder_sent:tenant:{$tenant->id}:{$targetDate}";
        if (! Cache::add($alreadySentKey, true, now()->endOfDay())) {
            continue;
        }

        $tasks = DB::connection('tenant')
            ->table('tasks')
            ->leftJoin('boards', 'boards.id', '=', 'tasks.board_id')
            ->leftJoin('stages', 'stages.id', '=', 'tasks.stage_id')
            ->select([
                'tasks.id',
                'tasks.title',
                'tasks.due_date',
                'boards.name as board_name',
                'stages.name as stage_name',
            ])
            ->whereDate('tasks.due_date', '=', $targetDate)
            ->where(function ($query) {
                $query->whereNull('stages.name')
                    ->orWhereRaw('LOWER(stages.name) NOT IN ("done","completed","complete","resolved","published")');
            })
            ->orderBy('tasks.id')
            ->get();

        if ($tasks->isEmpty()) {
            continue;
        }

        $recipients = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('email')
            ->pluck('email')
            ->filter()
            ->unique()
            ->values();

        if ($recipients->isEmpty()) {
            continue;
        }

        $summaryLines = $tasks
            ->take(20)
            ->map(fn ($task) => '- ' . ($task->title ?: 'Untitled task') . ' (Board: ' . ($task->board_name ?: 'N/A') . ')')
            ->implode(PHP_EOL);

        if ($tasks->count() > 20) {
            $summaryLines .= PHP_EOL . '- ...and ' . ($tasks->count() - 20) . ' more task(s)';
        }

        $subject = 'NSync due date reminders for ' . $targetDate;
        $body = implode(PHP_EOL . PHP_EOL, [
            'This is your NSync due date reminder.',
            "Tenant: {$tenant->name}",
            "Tasks due on {$targetDate}:",
            $summaryLines,
        ]);

        foreach ($recipients as $email) {
            try {
                Mail::raw($body, fn ($message) => $message->to($email)->subject($subject));
                $sent++;
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    $this->info("Due reminders complete. Tenants checked: {$checked}. Emails sent: {$sent}.");
})->purpose('Send due date reminder emails to tenant members for tasks due tomorrow');
