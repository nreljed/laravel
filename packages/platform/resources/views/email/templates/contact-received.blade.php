<h1>New contact message</h1>
<p>A new message was submitted through the contact form on <strong>{{ $siteName }}</strong>.</p>

<table class="details-table" cellpadding="0" cellspacing="0" role="presentation">
  <tr><td>From</td><td>{{ $senderName ?? '' }}</td></tr>
  <tr><td>Email</td><td>{{ $senderEmail ?? '' }}</td></tr>
  @if (! empty($subjectLine))
  <tr><td>Subject</td><td>{{ $subjectLine }}</td></tr>
  @endif
  <tr><td>Received</td><td>{{ $submittedAt ?? '' }}</td></tr>
</table>

<p style="margin-top: 24px; margin-bottom: 4px; font-weight: 600;">Message</p>
<p style="white-space: pre-wrap; margin: 0;">{{ $messageBody ?? '' }}</p>

<p class="text-center mt-4">
  <a href="{{ $contactUrl ?? '#' }}" class="button">View in Dashboard</a>
</p>
