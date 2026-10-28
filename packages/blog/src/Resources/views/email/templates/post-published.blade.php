<h1>Your post has been published</h1>
<p>Hi {{ $userName ?? 'there' }},</p>
<p>Your post <strong>{{ $postTitle }}</strong> has been published on {{ $siteName }}.</p>

<table class="details-table" cellpadding="0" cellspacing="0" role="presentation">
  <tr>
    <td>Post</td>
    <td>{{ $postTitle }}</td>
  </tr>
</table>

<p class="text-center mt-4">
  <a href="{{ $postUrl ?? '#' }}" class="button">View Post</a>
</p>