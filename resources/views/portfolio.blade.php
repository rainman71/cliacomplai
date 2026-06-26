<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Labs — CLIAComplai</title>
    <link rel="icon" type="image/png" href="{{ asset('img/favicon-64.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 text-gray-900 antialiased">
    <div class="mx-auto max-w-5xl px-4 py-8">
        <div class="mb-6 flex items-start justify-between gap-4">
            <div>
                <img src="{{ asset('img/cliacomplai-logo.png') }}" alt="CLIAComplai" class="h-14 w-auto">
                <p class="mt-2 text-sm text-gray-500">Select a lab to work in.</p>
            </div>
            <div class="flex items-center gap-3 text-sm">
                @if ($user->isSuperAdmin() || $cards->count() > 1)
                    <a href="{{ route('executive') }}" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 font-medium text-gray-700 shadow-sm hover:bg-gray-50">Across all labs</a>
                @endif
                @if ($user->isSuperAdmin())
                    <a href="{{ route('labs.index') }}" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 font-medium text-gray-700 shadow-sm hover:bg-gray-50">Manage labs</a>
                @endif
                <div class="text-right">
                    <div class="font-medium text-gray-800">{{ $user->name }}</div>
                    <div class="text-xs text-gray-400">{{ $user->isSuperAdmin() ? 'Super Admin' : 'Staff' }}</div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 shadow-sm hover:bg-gray-50">Sign out</button>
                </form>
            </div>
        </div>

        @if ($cards->isEmpty())
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-800">
                You don't have access to any labs yet. A compliance administrator needs to add you to one.
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($cards as $card)
                    @php $lab = $card['lab']; $c = $card['counts']; @endphp
                    <a href="{{ route('dashboard', $lab) }}"
                        class="block rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:shadow">
                        <div class="flex items-start justify-between">
                            <div class="font-semibold text-gray-900">{{ $lab->name }}</div>
                            @if ($c['overdue'] > 0)
                                <span class="rounded-full bg-red-600 px-2 py-0.5 text-xs font-semibold text-white">{{ $c['overdue'] }} overdue</span>
                            @else
                                <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800">on track</span>
                            @endif
                        </div>
                        @if ($lab->clia_number)
                            <div class="mt-0.5 text-xs text-gray-400">CLIA {{ $lab->clia_number }}</div>
                        @endif
                        <div class="mt-4 flex gap-4 text-sm">
                            <span class="text-red-700"><strong>{{ $c['overdue'] }}</strong> overdue</span>
                            <span class="text-amber-700"><strong>{{ $c['due_30'] + $c['due_60'] }}</strong> due soon</span>
                            <span class="text-gray-500"><strong>{{ $c['set_dates'] }}</strong> unset</span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</body>
</html>
