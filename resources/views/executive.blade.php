<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Across All Labs — Rightsize CLIA Compliance</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 text-gray-900 antialiased">
    <div class="mx-auto max-w-5xl px-4 py-8">
        <div class="mb-6 flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Across All Labs</h1>
                <p class="text-sm text-gray-500">Overdue obligations rolled up by lab · <a href="{{ route('portfolio') }}" class="text-indigo-600 hover:underline">← My Labs</a> · <a href="{{ route('worklist') }}" class="text-indigo-600 hover:underline">Merged worklist</a></p>
            </div>
            <a href="{{ route('executive.csv') }}" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-indigo-500">Export CSV</a>
        </div>

        @php $totalOverdue = $report->sum(fn ($r) => $r['counts']['overdue']); @endphp
        <div class="mb-6 rounded-xl border border-gray-200 bg-white p-4 text-sm shadow-sm">
            <span class="font-semibold text-gray-900">{{ $report->count() }}</span> labs ·
            <span class="font-semibold text-red-700">{{ $totalOverdue }}</span> overdue obligations total ·
            <span class="font-semibold text-amber-700">{{ $report->sum(fn ($r) => $r['counts']['due_soon']) }}</span> due soon
        </div>

        @foreach ($report as $row)
            <div class="mb-5 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-4 py-3">
                    <a href="{{ route('dashboard', $row['lab']) }}" class="font-semibold text-gray-900 hover:text-indigo-700">{{ $row['lab']->name }}</a>
                    <span class="text-sm {{ $row['counts']['overdue'] > 0 ? 'font-semibold text-red-700' : 'text-green-700' }}">
                        {{ $row['counts']['overdue'] }} overdue · {{ $row['counts']['due_soon'] }} due soon
                    </span>
                </div>
                @if ($row['overdue']->isNotEmpty())
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($row['overdue'] as $item)
                                <tr>
                                    <td class="px-4 py-2 font-medium text-gray-700">{{ $item['o']->code }}</td>
                                    <td class="px-4 py-2">{{ $item['o']->name }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $item['o']->owner_role }}</td>
                                    <td class="px-4 py-2 text-right font-semibold text-red-700">{{ abs($item['days']) }} days overdue</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="px-4 py-3 text-sm text-gray-400">No overdue items.</div>
                @endif
            </div>
        @endforeach
    </div>
</body>
</html>
