<h1>New Property Inquiry</h1>
<p>A guest has sent an inquiry regarding <strong>{{ $propertyTitle ?? 'a property' }}</strong>.</p>
<table class="details-table" cellpadding="0" cellspacing="0" role="presentation">
  <tr><td>Name</td><td>{{ $inquiryName ?? '' }}</td></tr>
  <tr><td>Email</td><td>{{ $inquiryEmail ?? '' }}</td></tr>
  <tr><td>Message</td><td>{{ $inquiryMessage ?? '' }}</td></tr>
</table>
