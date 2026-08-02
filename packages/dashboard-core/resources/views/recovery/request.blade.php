<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Cost Breaker 360') }} — Recovery</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f8fafc; color: #1e293b;
            min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem;
        }
        .container { max-width: 420px; width: 100%; text-align: center; }
        .card { background: #fff; border-radius: 12px; padding: 32px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        h1 { font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem; }
        p { color: #64748b; font-size: 0.875rem; line-height: 1.6; margin-bottom: 1.5rem; }
        input[type="email"] {
            width: 100%; padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px;
            font-size: 0.875rem; outline: none; margin-bottom: 1rem;
        }
        input[type="email"]:focus { border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59,130,246,0.15); }
        button {
            width: 100%; padding: 10px; background: #3b82f6; color: #fff; border: none; border-radius: 8px;
            font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: background 0.15s;
        }
        button:hover { background: #2563eb; }
        button:disabled { background: #94a3b8; cursor: not-allowed; }
        .success { color: #16a34a; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 12px; font-size: 0.875rem; margin-bottom: 1rem; }
        @media (prefers-color-scheme: dark) {
            body { background: #0f172a; color: #e2e8f0; }
            .card { background: #1e293b; }
            input[type="email"] { background: #0f172a; border-color: #334155; color: #e2e8f0; }
            input[type="email"]:focus { border-color: #60a5fa; }
            .success { background: #052e16; border-color: #166534; color: #4ade80; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>Recover Access</h1>
            <p>Enter your admin email address. If an admin account exists, we'll send you a recovery link.</p>
            @if(session('status'))
                <div class="success">{{ session('status') }}</div>
            @endif
            <form id="recovery-form">
                <input type="email" name="email" placeholder="admin@example.com" required autofocus>
                <button type="submit" id="submit-btn">Send Recovery Link</button>
            </form>
        </div>
    </div>
    <script>
        document.getElementById('recovery-form').addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = document.getElementById('submit-btn');
            btn.disabled = true;
            btn.textContent = 'Sending...';
            fetch('{{ route("maintenance.recovery.request-token") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ email: this.email.value })
            }).then(function(r) { return r.json(); }).then(function(data) {
                btn.textContent = 'Sent! Check your inbox.';
                btn.disabled = true;
            }).catch(function() {
                btn.textContent = 'Send Recovery Link';
                btn.disabled = false;
            });
        });
    </script>
</body>
</html>
