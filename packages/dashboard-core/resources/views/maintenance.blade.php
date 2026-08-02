<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Cost Breaker 360') }} — Under Maintenance</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .container { max-width: 520px; text-align: center; }
        .icon {
            width: 80px; height: 80px; margin: 0 auto 2rem;
            background: #e2e8f0; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
        }
        .icon svg { width: 40px; height: 40px; color: #64748b; }
        h1 { font-size: 1.875rem; font-weight: 700; margin-bottom: 0.75rem; letter-spacing: -0.025em; }
        .message { font-size: 1.125rem; color: #64748b; line-height: 1.6; margin-bottom: 2rem; }
        .countdown {
            display: flex; justify-content: center; gap: 1rem; margin-bottom: 2rem;
        }
        .countdown-unit {
            display: flex; flex-direction: column; align-items: center;
        }
        .countdown-value {
            font-size: 2rem; font-weight: 700; color: #1e293b;
            font-variant-numeric: tabular-nums; min-width: 3rem;
        }
        .countdown-label {
            font-size: 0.75rem; color: #94a3b8; text-transform: uppercase;
            letter-spacing: 0.05em; margin-top: 0.25rem;
        }
        .countdown-separator { font-size: 2rem; font-weight: 700; color: #cbd5e1; padding-top: 0; }
        .no-end-time { font-size: 1rem; color: #64748b; margin-bottom: 2rem; }
        .meta { font-size: 0.875rem; color: #94a3b8; }
        @media (prefers-color-scheme: dark) {
            body { background: #0f172a; color: #e2e8f0; }
            .icon { background: #1e293b; }
            .icon svg { color: #94a3b8; }
            .message { color: #94a3b8; }
            .countdown-value { color: #e2e8f0; }
            .countdown-separator { color: #475569; }
            .no-end-time { color: #94a3b8; }
            .meta { color: #64748b; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z" />
            </svg>
        </div>

        <h1>Under Maintenance</h1>

        <p class="message">{{ $message }}</p>

        @if(!empty($endsAt))
            <div class="countdown" id="countdown">
                <div class="countdown-unit">
                    <span class="countdown-value" id="cd-hours">--</span>
                    <span class="countdown-label">Hours</span>
                </div>
                <span class="countdown-separator">:</span>
                <div class="countdown-unit">
                    <span class="countdown-value" id="cd-minutes">--</span>
                    <span class="countdown-label">Minutes</span>
                </div>
                <span class="countdown-separator">:</span>
                <div class="countdown-unit">
                    <span class="countdown-value" id="cd-seconds">--</span>
                    <span class="countdown-label">Seconds</span>
                </div>
            </div>
        @else
            <p class="no-end-time">Please check back later.</p>
        @endif

        <p class="meta" id="meta-text">
            @if(!empty($endsAt))
                This page will refresh automatically when maintenance is complete.
            @else
                This page will refresh periodically.
            @endif
        </p>
    </div>

    @if(!empty($endsAt))
    <script>
        (function() {
            var endsAt = new Date({!! json_encode($endsAt) !!}).getTime();
            var hoursEl = document.getElementById('cd-hours');
            var minutesEl = document.getElementById('cd-minutes');
            var secondsEl = document.getElementById('cd-seconds');
            var metaText = document.getElementById('meta-text');

            function pad(n) { return n < 10 ? '0' + n : '' + n; }

            function tick() {
                var now = Date.now();
                var diff = Math.max(0, Math.floor((endsAt - now) / 1000));

                if (diff <= 0) {
                    metaText.textContent = 'Maintenance is complete. Refreshing...';
                    hoursEl.textContent = '00';
                    minutesEl.textContent = '00';
                    secondsEl.textContent = '00';
                    setTimeout(function() { location.reload(); }, 2000);
                    return;
                }

                var h = Math.floor(diff / 3600);
                var m = Math.floor((diff % 3600) / 60);
                var s = diff % 60;

                hoursEl.textContent = pad(h);
                minutesEl.textContent = pad(m);
                secondsEl.textContent = pad(s);

                setTimeout(tick, 1000);
            }

            tick();
        })();
    </script>
    @endif
</body>
</html>
