<?php

namespace App\Jobs;

use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Spatie\Multitenancy\Jobs\NotTenantAware;

class CreateTenantDatabase implements ShouldQueue, NotTenantAware
{
    use Queueable;

    protected $tenant;
    protected ?array $seedUser;

    /**
     * Create a new job instance.
     */
    /**
     * @param array|null $seedUser ['name'=>..., 'email'=>..., 'password'=>...]
     */
    public function __construct(Tenant $tenant, ?array $seedUser = null)
    {
        $this->tenant = $tenant;
        $this->seedUser = $seedUser;
    }

    /**
     * Execute the job to create tenant database and run migrations.
     */
    public function handle(): void
    {
        try {
            // Build database name if missing
            $slug = Str::slug($this->tenant->name ?? 'tenant', '_');
            $databaseName = $this->tenant->database ?: ($slug ?: 'tenant_'.$this->tenant->id) . '_nsync_db';

            if ($this->tenant->database !== $databaseName) {
                $this->tenant->updateQuietly(['database' => $databaseName]);
            }

            // Run CREATE DATABASE if it doesn't exist
            DB::statement("CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            // Configure tenant connection dynamically
            Config::set('database.connections.tenant', array_merge(
                Config::get('database.connections.mysql'),
                ['database' => $databaseName]
            ));
            DB::purge('tenant');

            // 1) Run base migrations (ensures users table exists)
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--force' => true,
            ]);

            // 2) Run tenant-specific migrations if present (overrides/additions)
            $tenantPath = database_path('migrations/tenants');
            if (is_dir($tenantPath)) {
                Artisan::call('migrate', [
                    '--database' => 'tenant',
                    '--path' => 'database/migrations/tenants',
                    '--force' => true,
                ]);
            }

            // Optional: import template SQL if present
            $templatePath = database_path('template.sql');
            if (file_exists($templatePath)) {
                DB::connection('tenant')->unprepared(file_get_contents($templatePath));
            }

            // Seed admin/user if provided
            if ($this->seedUser) {
                // include tenant_id if column exists
                $seed = [
                    'name' => $this->seedUser['name'] ?? $this->seedUser['email'],
                    'email' => $this->seedUser['email'],
                    'password' => $this->seedUser['password'],
                    'role' => $this->seedUser['role'] ?? 'admin',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $hasTenantId = Schema::connection('tenant')->hasColumn('users', 'tenant_id');
                if ($hasTenantId) {
                    $seed['tenant_id'] = $this->tenant->id;
                }
                DB::connection('tenant')->table('users')->updateOrInsert(
                    ['email' => $this->seedUser['email']],
                    $seed
                );
            }

            // Seed if needed (optional)
            // $process = new Process(['php', 'artisan', 'db:seed', '--class=TenantSeeder']);
            // $process->run();

            \Log::info("Database created for tenant: {$databaseName}");
        } catch (\Exception $e) {
            \Log::error("Failed to create tenant database: " . $e->getMessage());
            throw $e;
        }
    }
}
