<h1>Email address updated</h1>
<p>Hi there,</p>
<p>The email address associated with your <strong>{{ $siteName }}</strong> account has been changed.</p>
<table class="details-table" cellpadding="0" cellspacing="0" role="presentation">
  <tr><td>Previous email</td><td>{{ $oldEmail ?? '' }}</td></tr>
  <tr><td>New email</td><td>{{ $newEmail ?? '' }}</td></tr>
</table>
<p>If you didn't request this change, please contact the site administrator immediately.</p>
