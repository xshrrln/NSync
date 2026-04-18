<!DOCTYPE html>
<html>
<head>
    <title>Welcome to {{ $tenant->name }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #374151; max-width: 600px; margin: 0 auto; padding: 40px 20px;">
    <div style="background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); padding: 40px 20px; border-radius: 12px 12px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 28px; font-weight: 700;">Welcome to {{ $tenant->name }}</h1>
    </div>
    <div style="background: white; padding: 40px 30px; border-radius: 0 0 12px 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
<p style="font-size: 18px; margin-bottom: 30px;">Hi,</p>
        
<p style="margin-bottom: 24px;">{{ $inviter->name }} has invited you to join the <strong>{{ $tenant->name }}</strong> team on NSync as <strong>"{{ $invite->role }}"</strong>!</p>
        
        <div style="background: #f0fdf4; border-left: 4px solid #16a34a; padding: 20px; border-radius: 8px; margin: 30px 0;">
            <h3 style="margin: 0 0 12px 0; font-size: 16px; font-weight: 600;">Your Account</h3>
<p style="margin: 0 0 12px 0;"><strong>Email:</strong> {{ $invite->email }}</p>
            <p style="margin: 0 0 12px 0;"><strong>Role:</strong> {{ ucfirst(str_replace('_', ' ', $invite->role)) }}</p>
        </div>

        <p style="margin-bottom: 12px;"><strong>How to access your account:</strong></p>
        <ol style="margin: 0 0 24px 18px; padding: 0; color: #4b5563;">
            <li style="margin-bottom: 8px;">Click <strong>Accept Invite</strong> to set your name and password.</li>
            <li style="margin-bottom: 8px;">After that, login using your email and the password you created.</li>
        </ol>

        <a href="{{ $acceptUrl }}" style="display: inline-block; background: #16a34a; color: white; padding: 14px 26px; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 15px; margin: 10px 0 8px 0;">Accept Invite & Set Password</a>
        <br>
        <a href="{{ $loginUrl }}" style="display: inline-block; color: #15803d; text-decoration: none; font-size: 14px; margin-top: 6px;">Already accepted? Login here</a>
        
        <p style="margin: 30px 0 12px 0; font-size: 14px; color: #6b7280;">Need help? <a href="#" style="color: #15803d;">Contact support</a></p>
        
        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;">
        <p style="text-align: center; font-size: 12px; color: #9ca3af;">This is an automated message from NSync. Please do not reply.</p>
    </div>
</body>
</html>
