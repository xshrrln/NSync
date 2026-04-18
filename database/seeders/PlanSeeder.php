<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Plan;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'price' => 'Free Trial - 14 Days',
                'members_limit' => 7,
                'boards_limit' => 5,
                'storage_limit' => 100,
                'features' => ['basic-kanban', 'board-creation', 'member-invites', 'task-checklists'],
            ],
            [
                'name' => 'Standard',
                'slug' => 'standard',
                'price' => 'PHP 799/month',
                'members_limit' => 20,
                'boards_limit' => 999,
                'storage_limit' => 5120,
                'features' => ['basic-kanban', 'board-creation', 'member-invites', 'role-permissions', 'task-checklists', 'file-attachments', 'due-date-reminders', 'unlimited-boards', 'basic-analytics'],
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'price' => 'PHP 1,499/month',
                'members_limit' => 9999,
                'boards_limit' => 9999,
                'storage_limit' => 999999,
                'features' => ['basic-kanban', 'board-creation', 'member-invites', 'role-permissions', 'guest-boards', 'task-checklists', 'file-attachments', 'due-date-reminders', 'unlimited-boards', 'basic-analytics', 'advanced-reporting', 'activity-logs', 'two-factor', 'audit-export'],
            ],
        ];

        foreach ($plans as $planData) {
            Plan::updateOrCreate(
                ['slug' => $planData['slug']],
                $planData
            );
        }
    }
}

