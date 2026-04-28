<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Central Audit Trail</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1f2937;
        }
        h1 {
            font-size: 20px;
            margin: 0 0 6px;
        }
        .meta {
            margin-bottom: 16px;
            color: #6b7280;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #d1d5db;
            padding: 6px;
            vertical-align: top;
            text-align: left;
        }
        th {
            background: #f3f4f6;
            font-size: 10px;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <h1>Central Audit Trail</h1>
    <div class="meta">Generated {{ $generatedAt->format('M d, Y h:i:s A') }}</div>

    <table>
        <thead>
            <tr>
                <th>Occurred</th>
                <th>Action</th>
                <th>Description</th>
                <th>User</th>
                <th>Status</th>
                <th>Request</th>
                <th>Workspace</th>
            </tr>
        </thead>
        <tbody>
            @foreach($logs as $log)
                <tr>
                    <td>{{ optional($log->occurred_at)->timezone(config('app.timezone'))->format('M d, Y h:i:s A') }}</td>
                    <td>{{ $log->action }}</td>
                    <td>{{ $log->description }}</td>
                    <td>{{ $log->user_name ?: 'System' }}{{ $log->user_email ? ' | ' . $log->user_email : '' }}</td>
                    <td>{{ $log->status_code ?? 'N/A' }}</td>
                    <td>{{ trim(collect([$log->method, $log->path])->filter()->implode(' ')) }}</td>
                    <td>{{ trim(collect([data_get($log->context, 'tenant_name'), data_get($log->context, 'tenant_domain')])->filter()->implode(' | ')) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
