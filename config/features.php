<?php

return [
    'categories' => [
        'collaboration' => [
            'label' => 'Collaboration',
            'features' => [
                'basic-kanban' => [
                    'name' => 'Kanban Workspace',
                    'description' => 'Core boards and columns for everyday work.',
                ],
                'board-creation' => [
                    'name' => 'Board Creation',
                    'description' => 'Create and manage project boards.',
                ],
                'member-invites' => [
                    'name' => 'Member Invites',
                    'description' => 'Invite teammates and assign roles.',
                ],
                'role-permissions' => [
                    'name' => 'Role Permissions',
                    'description' => 'Granular permissions per role.',
                ],
                'guest-boards' => [
                    'name' => 'Guest Boards',
                    'description' => 'Share boards with guests and stakeholders.',
                ],
            ],
        ],
        'productivity' => [
            'label' => 'Productivity',
            'features' => [
                'task-checklists' => [
                    'name' => 'Task Checklists',
                    'description' => 'Break work into subtasks with checklists.',
                ],
                'file-attachments' => [
                    'name' => 'File Attachments',
                    'description' => 'Upload and attach files to tasks.',
                ],
                'due-date-reminders' => [
                    'name' => 'Due Date Reminders',
                    'description' => 'Email reminders before tasks are due.',
                ],
                'unlimited-boards' => [
                    'name' => 'Unlimited Boards',
                    'description' => 'Remove limits on how many boards you can create.',
                ],
            ],
        ],
        'insights' => [
            'label' => 'Insights',
            'features' => [
                'basic-analytics' => [
                    'name' => 'Analytics Dashboard',
                    'description' => 'Board-level charts for throughput and cycle time.',
                ],
                'advanced-reporting' => [
                    'name' => 'Advanced Reporting',
                    'description' => 'Exportable reports and billing visibility.',
                ],
                'activity-logs' => [
                    'name' => 'Activity Logs',
                    'description' => 'View the audit trail of board and task changes.',
                ],
            ],
        ],
        'security' => [
            'label' => 'Security',
            'features' => [
                'two-factor' => [
                    'name' => '2FA Enforcement',
                    'description' => 'Require 2FA for tenant members.',
                ],
                'audit-export' => [
                    'name' => 'Audit Export',
                    'description' => 'Export activity logs for compliance.',
                ],
            ],
        ],
    ],
];
