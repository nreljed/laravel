<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="light">
  <meta name="supported-color-schemes" content="light">
  <title>{{ $subject ?? $siteName ?? '' }}</title>
  <style>
    body { margin:0; padding:0; background-color:#f4f4f5; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif; line-height:1.6; color:#18181b; }
    table { border-collapse:collapse; }
    .email-wrapper { width:100%; table-layout:fixed; background-color:#f4f4f5; padding:24px 0; }
    .email-container { max-width:600px; margin:0 auto; background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.08); }
    .email-header { padding:32px 32px 16px; text-align:center; border-bottom:1px solid #e4e4e7; }
    .email-header img { max-height:48px; width:auto; }
    .email-body { padding:32px; }
    .email-footer { padding:24px 32px; text-align:center; font-size:13px; color:#71717a; border-top:1px solid #e4e4e7; }
    .email-footer a { color:#71717a; text-decoration:underline; }
    h1 { font-size:22px; font-weight:700; margin:0 0 16px; color:#18181b; }
    h2 { font-size:18px; font-weight:600; margin:0 0 12px; color:#18181b; }
    p { margin:0 0 16px; font-size:15px; color:#3f3f46; }
    .button { display:inline-block; padding:12px 28px; font-size:15px; font-weight:600; color:#ffffff; background-color:#18181b; border-radius:6px; text-decoration:none; }
    .details-table { width:100%; margin:16px 0; }
    .details-table td { padding:8px 12px; font-size:14px; border-bottom:1px solid #f4f4f5; }
    .details-table td:first-child { color:#71717a; width:120px; }
    .details-table td:last-child { color:#18181b; font-weight:500; }
    .text-center { text-align:center; }
    .mt-4 { margin-top:24px; }
    .mb-4 { margin-bottom:24px; }
  </style>
</head>
<body>
  <table class="email-wrapper" cellpadding="0" cellspacing="0" role="presentation">
    <tr>
      <td align="center">
        <table class="email-container" cellpadding="0" cellspacing="0" role="presentation">
          @if($logoUrl)
          <tr>
            <td class="email-header">
              <img src="{{ $logoUrl }}" alt="{{ $siteName }}">
            </td>
          </tr>
          @else
          <tr>
            <td class="email-header">
              <h1 style="margin:0; font-size:20px;">{{ $siteName }}</h1>
            </td>
          </tr>
          @endif
          <tr>
            <td class="email-body">
              {!! $body !!}
            </td>
          </tr>
          <tr>
            <td class="email-footer">
              <p style="font-size:13px; color:#71717a; margin:0;">
                &copy; {{ $year }} {{ $siteName }}. All rights reserved.
              </p>
              <p style="font-size:13px; color:#71717a; margin:8px 0 0;">
                <a href="{{ $siteUrl }}">{{ $siteUrl }}</a>
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
