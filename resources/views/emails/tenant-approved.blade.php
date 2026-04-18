<!DOCTYPE html>
<html>
<head>
    <title>Your NSync Workspace is Ready!</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: {{ $theme['primary'] ?? '#16A34A' }}; color: white; padding: 30px; text-align: center; border-radius: 8px; }
        .header h1 { margin: 0; font-size: 28px; }
        .content { background-color: #f9fafb; padding: 30px; margin: 20px 0; border-radius: 8px; }
        .credentials { background-color: white; border-left: 4px solid {{ $theme['primary'] ?? '#16A34A' }}; padding: 20px; margin: 20px 0; border-radius: 4px; }
        .credentials-label { font-weight: bold; color: {{ $theme['primary'] ?? '#16A34A' }}; margin-bottom: 5px; }
        .credentials-value { font-family: monospace; background-color: #f3f4f6; padding: 12px; border-radius: 4px; margin-bottom: 15px; word-break: break-all; }
        .button { display: inline-block; background-color: {{ $theme['primary'] ?? '#16A34A' }}; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; margin: 20px 0; }
        .instructions { background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .instructions p { margin: 5px 0; }
        .footer { text-align: center; font-size: 12px; color: #999; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 Your NSync Workspace is Ready!</h1>
        </div>
        
        <div class="content">
            <p>Hi <strong>{{ $tenant->tenant_admin }}</strong>,</p>
            
            <p>Congratulations! Your organization <strong>{{ $tenant->name }}</strong> workspace has been created and is now live!</p>
            
            <div class="instructions">
                <p><strong>📍 How to Access Your Workspace:</strong></p>
                <p style="margin: 10px 0;">1. Visit your workspace URL: <a href="{{ $workspace_url }}" style="color: #0066cc; text-decoration: none;"><strong>{{ $workspace_url }}</strong></a></p>
                <p style="margin: 10px 0;">2. Click the "Sign in" button</p>
                <p style="margin: 10px 0;">3. Use the credentials below to log in</p>
                <p style="margin: 10px 0;">4. Change your password on first login</p>
            </div>
            
            <h3 style="color: {{ $theme['primary'] ?? '#16A34A' }};">Your Login Credentials:</h3>
            
            <div class="credentials">
                <div class="credentials-label">Workspace URL:</div>
                <div class="credentials-value">{{ $workspace_url }}</div>
                
                <div class="credentials-label">Email Address:</div>
                <div class="credentials-value">{{ $username }}</div>
                
                <div class="credentials-label">Temporary Password:</div>
                <div class="credentials-value">{{ $password }}</div>
            </div>
            
            <div style="text-align: center;">
                <a href="{{ $login_url }}" class="button">Login to Your Workspace →</a>
            </div>
            
            <div style="background-color: #eff6ff; border-left: 4px solid #3b82f6; padding: 15px; margin: 20px 0; border-radius: 4px;">
                <p style="margin: 5px 0;"><strong>💡 Tip:</strong> Make sure to change your temporary password after logging in for security.</p>
                <p style="margin: 5px 0;"><strong>📋 Plan:</strong> {{ ucfirst($tenant->plan) }} Plan</p>
                <p style="margin: 5px 0;"><strong>❓ Questions?</strong> Contact our support team if you need assistance.</p>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; 2026 NSync. All rights reserved.</p>
            <p>This is an automated message. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>

