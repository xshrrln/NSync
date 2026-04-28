<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Support\GitHubReleaseService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
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
            $latestVersion = app(GitHubReleaseService::class)->latestVersion();
            $appliedAt = now();

            // Build database name if missing
            $databaseName = $this->tenant->database
                ?: Tenant::generateDatabaseName($this->tenant->name, $this->tenant->id);

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
                if (Schema::connection('tenant')->hasColumn('users', 'status')) {
                    $seed['status'] = 'active';
                }
                DB::connection('tenant')->table('users')->updateOrInsert(
                    ['email' => $this->seedUser['email']],
                    $seed
                );
            }

            // Seed if needed (optional)
            // $process = new Process(['php', 'artisan', 'db:seed', '--class=TenantSeeder']);
            // $process->run();

            $this->tenant->forceFill([
                'applied_release_version' => $latestVersion,
                'applied_release_at' => $appliedAt,
            ])->save();

            if (Schema::connection('tenant')->hasTable('tenants')
                && Schema::connection('tenant')->hasColumn('tenants', 'applied_release_version')
                && Schema::connection('tenant')->hasColumn('tenants', 'applied_release_at')) {
                DB::connection('tenant')->table('tenants')->updateOrInsert(
                    ['id' => $this->tenant->id],
                    [
                        'organization' => $this->tenant->organization,
                        'name' => $this->tenant->name,
                        'address' => $this->tenant->address,
                        'tenant_admin' => $this->tenant->tenant_admin,
                        'tenant_admin_email' => $this->tenant->tenant_admin_email,
                        'domain' => $this->tenant->domain,
                        'database' => $this->tenant->database,
                        'plan' => $this->tenant->plan,
                        'status' => $this->tenant->status,
                        'theme' => is_array($this->tenant->theme) ? json_encode($this->tenant->theme) : $this->tenant->getRawOriginal('theme'),
                        'actions' => is_array($this->tenant->actions) ? json_encode($this->tenant->actions) : $this->tenant->getRawOriginal('actions'),
                        'billing_data' => is_array($this->tenant->billing_data) ? json_encode($this->tenant->billing_data) : $this->tenant->getRawOriginal('billing_data'),
                        'start_date' => $this->tenant->start_date,
                        'due_date' => $this->tenant->due_date,
                        'applied_release_version' => $latestVersion,
                        'applied_release_at' => $appliedAt,
                        'created_at' => $this->tenant->created_at ?? $appliedAt,
                        'updated_at' => $appliedAt,
                    ]
                );
            }

            \Log::info("Database created for tenant: {$databaseName}");
        } catch (\Exception $e) {
            \Log::error("Failed to create tenant database: " . $e->getMessage());
            throw $e;
        }
    }
}
