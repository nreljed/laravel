<h1>Welcome to {{ $siteName }}</h1>
<p>Hi {{ $userName ?? 'there' }},</p>
<p>Your account on <strong>{{ $siteName }}</strong> has been created successfully.</p>
<p>You can now log in with your registered email address:</p>
<table class="details-table" cellpadding="0" cellspacing="0" role="presentation">
  <tr><td>Email</td><td>{{ $userEmail ?? '' }}</td></tr>
</table>
<p class="text-center mt-4">
  <a href="{{ $loginUrl ?? '#' }}" class="button">Log In</a>
</p>
