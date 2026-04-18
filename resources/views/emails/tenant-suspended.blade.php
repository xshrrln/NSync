<!DOCTYPE html>
<html>
<head>
    <title>Workspace Suspended</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #374151; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 20px;">
        <h1 style="color: white; font-size: 24px; margin: 0; font-weight: 800;">Workspace Suspended</h1>
    </div>
    
    <div style="background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); margin: 20px;">
        <h2 style="color: #1f2937; font-size: 20px; margin-bottom: 16px;">Hi {{ $tenant->tenant_admin }},</h2>
        
        <p style="margin-bottom: 20px;">Your NSync workspace <strong>{{ $tenant->name }}</strong> ({{ $tenant->domain }}) has been temporarily suspended.</p>
        
        <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 20px; margin: 24px 0; border-radius: 8px;">
            <h3 style="color: #92400e; margin: 0 0 8px 0; font-size: 16px;">Reason:</h3>
            <p style="color: #92400e; margin: 0;">{{ $reason }}</p>
        </div>
        
        <p style="margin-bottom: 24px;">Your data is safe and you can regain access once the issue is resolved. Please contact platform support to reactivate your workspace.</p>
        
        <div style="text-align: center; margin: 32px 0;">
            <a href="{{ $login_url }}" style="background: #10b981; color: white; padding: 12px 32px; text-decoration: none; border-radius: 8px; font-weight: 600; display: inline-block;">Log In</a>
        </div>
        
        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 32px 0;">
        
        <p style="font-size: 14px; color: #6b7280; margin-bottom: 8px;">Need help? Reply to this email or contact support@nsyncapp.com</p>
        <p style="font-size: 14px; color: #6b7280;">Best,<br><strong>NSync Team</strong></p>
    </div>
</body>
</html>

