<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in — CLIAComplai</title>
    <link rel="icon" type="image/png" href="{{ asset('img/favicon-64.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-gradient-to-b from-slate-50 to-[#e9f1f4] antialiased">
    <div class="w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="h-1.5 w-full bg-gradient-to-r from-[#2aa7b8] via-[#2f7fb8] to-[#1c3f5f]"></div>
        <div class="p-8">
            <img src="{{ asset('img/cliacomplai-logo.png') }}" alt="CLIAComplai — AI-Driven CLIA Compliance Platform" class="mx-auto w-full max-w-xs">
            <p class="mt-5 text-center text-sm font-medium text-slate-500">Staff sign in</p>

        @if ($errors->any())
            <div class="mt-4 rounded-md bg-red-50 p-3 text-sm text-red-700 ring-1 ring-red-200">
                {{ $errors->first() }}
            </div>
        @endif

        <a href="{{ route('google.redirect') }}"
            class="mt-6 flex w-full items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
            <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true">
                <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3c-1.6 4.7-6.1 8-11.3 8a12 12 0 1 1 0-24c3 0 5.8 1.1 7.9 3l5.7-5.7A20 20 0 1 0 24 44a20 20 0 0 0 19.6-23.5z"/>
                <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8A12 12 0 0 1 24 12c3 0 5.8 1.1 7.9 3l5.7-5.7A20 20 0 0 0 6.3 14.7z"/>
                <path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2A12 12 0 0 1 24 36c-5.2 0-9.6-3.3-11.3-7.9l-6.5 5A20 20 0 0 0 24 44z"/>
                <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3a12 12 0 0 1-4.1 5.6l6.2 5.2C39 35.7 44 30.6 44 24c0-1.2-.1-2.4-.4-3.5z"/>
            </svg>
            Sign in with Google
        </a>

        @if (app()->environment('local') && ! config('services.google.client_id'))
            <div class="mt-6 border-t border-dashed border-gray-200 pt-4">
                <p class="text-xs text-gray-400">Local dev (no Google credentials configured yet):</p>
                <a href="{{ route('dev.login') }}"
                    class="mt-2 block w-full rounded-lg bg-gray-800 px-4 py-2 text-center text-sm font-medium text-white hover:bg-gray-700">
                    Dev login (Compliance Specialist)
                </a>
            </div>
        @endif
        </div>
    </div>
</body>
</html>
