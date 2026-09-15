<h1>New user registration</h1>
<p>A new user has registered on <strong>{{ $siteName }}</strong>.</p>
<table class="details-table" cellpadding="0" cellspacing="0" role="presentation">
  <tr><td>Name</td><td>{{ $userName ?? '' }}</td></tr>
  <tr><td>Username</td><td>{{ $userLogin ?? '' }}</td></tr>
  <tr><td>Email</td><td>{{ $userEmail ?? '' }}</td></tr>
</table>
<p class="text-center mt-4">
  <a href="{{ $profileUrl ?? '#' }}" class="button">View Profile</a>
</p>
