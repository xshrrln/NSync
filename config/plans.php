<?php

return [
    'free' => [
        'price' => 'Free Trial - 14 Days',
        'members_limit' => 7,
        'boards_limit' => 5,
        'storage_limit' => 100, // MB
        'features' => [
            'basic-kanban',
            'board-creation',
            'member-invites',
            'task-checklists',
        ],
    ],
    'standard' => [
        'price' => 'PHP 799/month',
        'members_limit' => 20,
        'boards_limit' => 999,
        'storage_limit' => 5120, // 5GB
        'features' => [
            'basic-kanban',
            'board-creation',
            'member-invites',
            'role-permissions',
            'task-checklists',
            'file-attachments',
            'due-date-reminders',
            'unlimited-boards',
            'basic-analytics',
        ],
    ],
    'pro' => [
        'price' => 'PHP 1,499/month',
        'members_limit' => 9999,
        'boards_limit' => 9999,
        'storage_limit' => 999999,
        'features' => [
            'basic-kanban',
            'board-creation',
            'member-invites',
            'role-permissions',
            'guest-boards',
            'task-checklists',
            'file-attachments',
            'due-date-reminders',
            'unlimited-boards',
            'basic-analytics',
            'advanced-reporting',
            'activity-logs',
            'two-factor',
            'audit-export',
        ],
    ],
];
