<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Overdue Worklist — Rightsize CLIA Compliance</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 text-gray-900 antialiased">
    <div class="mx-auto max-w-4xl px-4 py-8">
        <div class="mb-6 flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Overdue Worklist</h1>
                <p class="text-sm text-gray-500">
                    Everything overdue across all your labs, most urgent first ·
                    <a href="{{ route('portfolio') }}" class="text-indigo-600 hover:underline">← My Labs</a> ·
                    <a href="{{ route('executive') }}" class="text-indigo-600 hover:underline">By lab</a>
                </p>
            </div>
            <span class="rounded-full bg-red-100 px-3 py-1 text-sm font-semibold text-red-800">{{ $items->count() }} overdue</span>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Lab</th>
                        <th class="px-4 py-3">Obligation</th>
                        <th class="px-4 py-3">Owner</th>
                        <th class="px-4 py-3 text-right">Overdue</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($items as $item)
                        <tr class="hover:bg-gray-50/60">
                            <td class="px-4 py-2">
                                <a href="{{ route('dashboard', $item['lab']) }}" class="font-medium text-indigo-700 hover:underline">{{ $item['lab']->name }}</a>
                            </td>
                            <td class="px-4 py-2">
                                <span class="font-medium text-gray-700">{{ $item['o']->code }}</span> — {{ $item['o']->name }}
                            </td>
                            <td class="px-4 py-2 text-gray-500">{{ $item['o']->owner_role }}</td>
                            <td class="px-4 py-2 text-right font-semibold text-red-700">{{ abs($item['days']) }} days</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-10 text-center text-green-700">Nothing overdue across any of your labs. 🎉</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
