<h1>Password changed</h1>
<p>Hi {{ $userName ?? 'there' }},</p>
<p>Your password on <strong>{{ $siteName }}</strong> has been changed successfully.</p>
<p>If you made this change, no further action is needed.</p>
<p>If you did not make this change, please reset your password immediately using the button below:</p>
<p class="text-center mt-4">
  <a href="{{ $resetUrl ?? '#' }}" class="button">Reset Password</a>
</p>
