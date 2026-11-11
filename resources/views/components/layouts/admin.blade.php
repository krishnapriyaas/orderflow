<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'OrderFlow back office' }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font: 15px/1.5 system-ui, sans-serif; color: #1f2933; background: #f5f7fa; }
        header { display: flex; justify-content: space-between; align-items: center; padding: 12px 24px; background: #1f2933; color: #fff; }
        header form { margin: 0; }
        main { max-width: 1100px; margin: 24px auto; padding: 0 16px; }
        h1 { font-size: 22px; margin: 0 0 16px; }
        .card { background: #fff; border: 1px solid #d9e2ec; border-radius: 6px; padding: 16px; margin-bottom: 16px; }
        .meta { color: #52606d; font-size: 13px; }
        .note { white-space: pre-wrap; background: #f0f4f8; border-radius: 4px; padding: 8px; margin: 8px 0; }
        .badge { display: inline-block; padding: 1px 8px; border-radius: 10px; background: #fce8c3; font-size: 13px; }
        .badge.warn { background: #facdcd; }
        label { display: block; font-weight: 600; margin-top: 8px; }
        input, textarea { width: 100%; padding: 6px 8px; border: 1px solid #bcccdc; border-radius: 4px; font: inherit; }
        textarea { min-height: 70px; }
        .error { color: #b42318; font-size: 13px; }
        .flash { padding: 8px 12px; border-radius: 4px; margin-bottom: 16px; background: #e3f9e5; }
        .flash.problem { background: #facdcd; }
        .actions { display: flex; gap: 8px; margin-top: 12px; }
        button { padding: 6px 14px; border: 0; border-radius: 4px; font: inherit; cursor: pointer; }
        .approve { background: #2f8132; color: #fff; }
        .reject { background: #b42318; color: #fff; }
        .plain { background: transparent; color: inherit; border: 1px solid currentColor; }
    </style>
</head>
<body>
    <header>
        <strong>OrderFlow back office</strong>
        @auth
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <span>{{ auth()->user()?->email }}</span>
                <button type="submit" class="plain">Log out</button>
            </form>
        @endauth
    </header>
    <main>
        {{ $slot }}
    </main>
</body>
</html>
