<!DOCTYPE html>
<html>
<head>
    <title>Workspace Request Received</title>
</head>
<body>
    <h1>Welcome to NSync! 🚀</h1>
    <p>Hi {{ $tenant->tenant_admin }},</p>
    <p>Thank you for signing up for <strong>{{ $tenant->name }}</strong>.</p>
    <p>Your workspace is now pending approval by our admin team.</p>
    <p><strong>Details:</strong></p>
    <ul>
        <li>Organization: {{ $tenant->name }}</li>
href="http://{{ $tenant->domain }}:8000"
        <li>Plan: {{ ucfirst($tenant->plan) }}</li>
    </ul>
    <p>We'll notify you once approved. You can login with your signup credentials.</p>
    <p>Best,<br>NSync Team</p>
</body>
</html>

