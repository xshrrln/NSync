<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Audit Report' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 12px; }
        .header { border: 1px solid #d1fae5; background: #f0fdf4; padding: 12px 14px; margin-bottom: 12px; }
        .title { font-size: 18px; font-weight: 700; margin: 0 0 4px; }
        .meta { font-size: 11px; color: #334155; margin: 2px 0; }
        .summary { margin: 0 0 12px; border-collapse: collapse; width: 100%; }
        .summary td { border: 1px solid #cbd5e1; padding: 8px; width: 33.33%; }
        .label { font-size: 10px; color: #475569; text-transform: uppercase; }
        .value { font-size: 16px; font-weight: 700; margin-top: 4px; }
        table.report { border-collapse: collapse; width: 100%; }
        table.report th { background: #0f172a; color: #fff; font-size: 10px; text-transform: uppercase; padding: 7px; text-align: left; }
        table.report td { border: 1px solid #cbd5e1; padding: 6px; vertical-align: top; }
        .muted { color: #64748b; font-size: 10px; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <p class="title">{{ $title ?? 'Audit Report' }}</p>
        <p class="meta"><strong>Workspace:</strong> {{ $tenantName ?? 'Workspace' }} ({{ $tenantDomain ?? 'tenant' }})</p>
        <p class="meta"><strong>Scope:</strong> {{ $scopeLabel ?? 'Workspace Activity Logs' }}</p>
        <p class="meta"><strong>Generated:</strong> {{ ($generatedAt ?? now())->format('M d, Y h:i A') }}</p>
    </div>

    <table class="summary">
        <tr>
            <td>
                <div class="label">Total Log Entries</div>
                <div class="value">{{ number_format((int) ($summary['total'] ?? 0)) }}</div>
            </td>
            <td>
                <div class="label">Unique Actors</div>
                <div class="value">{{ number_format((int) ($summary['unique_actors'] ?? 0)) }}</div>
            </td>
            <td>
                <div class="label">Unique Tasks</div>
                <div class="value">{{ number_format((int) ($summary['unique_tasks'] ?? 0)) }}</div>
            </td>
        </tr>
    </table>

    <table class="report">
        <thead>
            <tr>
                <th style="width:6%;">ID</th>
                <th style="width:14%;">Timestamp</th>
                <th style="width:14%;">Actor</th>
                <th style="width:24%;">Task</th>
                <th style="width:13%;">From Stage</th>
                <th style="width:13%;">To Stage</th>
                <th style="width:16%;">IP Address</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows ?? [] as $row)
                <tr>
                    <td>{{ $row->id }}</td>
                    <td>{{ $row->created_at }}</td>
                    <td>{{ $row->actor_name }}</td>
                    <td>{{ $row->task_title }}</td>
                    <td>{{ $row->old_stage_name }}</td>
                    <td>{{ $row->new_stage_name }}</td>
                    <td>{{ $row->ip_address }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">No audit records available.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="muted">Audit report shows task movement history with actor, stage transition, and source IP for traceability.</p>
</body>
</html>
