<h1>Reset your password</h1>
<p>Hi {{ $userName ?? 'there' }},</p>
<p>We received a request to reset the password for your account on <strong>{{ $siteName }}</strong>.</p>
<p>Click the button below to set a new password:</p>
<p class="text-center mt-4">
  <a href="{{ $resetUrl ?? '#' }}" class="button">Reset Password</a>
</p>
<p class="mt-4">If you didn't request a password reset, you can safely ignore this email.</p>
<p>This link expires in {{ $expireMinutes ?? 60 }} minutes.</p>
<p style="margin-top: 24px; color: #71717a; font-size: 14px;">
  Can't click the button? Copy this URL into your browser:<br>
  <span style="font-size: 13px; word-break: break-all;">{{ $resetUrl ?? '#' }}</span>
</p>
