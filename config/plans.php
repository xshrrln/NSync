<?php

return [
    'free' => [
        'members_limit' => 7,
        'boards_limit' => 5,
        'storage_limit' => 100, // MB
        'features' => ['basic-kanban'],
    ],
    'standard' => [
        'members_limit' => 20,
        'boards_limit' => 999,
        'storage_limit' => 5120, // 5GB
        'features' => ['unlimited-boards', 'file-attachments', 'basic-analytics'],
    ],
    'pro' => [
        'members_limit' => 9999,
        'boards_limit' => 9999,
        'storage_limit' => 999999,
        'features' => ['advanced-reporting', 'role-permissions', 'activity-logs'],
    ],
];