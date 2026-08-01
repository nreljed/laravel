<h1>Welcome to {{ $siteName }}!</h1>
<p>Hi {{ $userName ?? 'there' }},</p>
<p>An account has been created for you on <strong>{{ $siteName }}</strong>. You're almost ready to get started!</p>
<p>Click the button below to set your password and activate your account:</p>
<p class="text-center mt-4">
  <a href="{{ $setPasswordUrl ?? '#' }}" class="button">Set Your Password</a>
</p>
<p class="mt-4">This link expires in {{ $expireMinutes ?? 60 }} minutes. If you don't set your password in time, you can request a new reset link from the login page.</p>
<p style="margin-top: 24px; color: #71717a; font-size: 14px;">
  Can't click the button? Copy this URL into your browser:<br>
  <span style="font-size: 13px; word-break: break-all;">{{ $setPasswordUrl ?? '#' }}</span>
</p>
