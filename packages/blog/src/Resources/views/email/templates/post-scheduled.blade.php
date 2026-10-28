<h1>Your post is scheduled</h1>
<p>Hi {{ $userName ?? 'there' }},</p>
<p>Your post <strong>{{ $postTitle }}</strong> is scheduled for publication on {{ $siteName }}.</p>

<table class="details-table" cellpadding="0" cellspacing="0" role="presentation">
  <tr>
    <td>Post</td>
    <td>{{ $postTitle }}</td>
  </tr>
  @if (!empty($scheduledAt))
  <tr>
    <td>Published at</td>
    <td>{{ $scheduledAt }}</td>
  </tr>
  @endif
</table>

<p class="text-center mt-4">
  <a href="{{ $postUrl ?? '#' }}" class="button">Manage Post</a>
</p>