<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10px; color: #111; }
        .instructions { font-size: 9px; color: #444; font-style: italic; margin-bottom: 10px; }
        .fields { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .fields td { padding: 3px 6px; vertical-align: top; }
        .fields td.k { width: 200px; color: #555; font-weight: bold; }
        table.log { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.log th, table.log td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; vertical-align: top; font-size: 9px; }
        table.log th { background: #e5e7eb; text-transform: uppercase; letter-spacing: .03em; }
        .sign { margin-top: 22px; font-size: 10px; }
        .line { display: inline-block; border-bottom: 1px solid #333; min-width: 220px; }
    </style>
</head>
<body>
    @include('forms._rsl-letterhead')
    @if (! empty($def['instructions']))
        <div class="instructions">{{ $def['instructions'] }}</div>
    @endif

    @php($fields = $def['fields'] ?? [])
    @if (! empty($fields))
        <table class="fields">
            @foreach ($fields as $f)
                <tr>
                    <td class="k">{{ $f['label'] }}</td>
                    <td>{{ data_get($form->answers, "fields.{$f['key']}") ?: '—' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <table class="log">
        <thead>
            <tr>@foreach ($def['columns'] as $c)<th>{{ $c['label'] }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @forelse (data_get($form->answers, 'rows', []) as $row)
                <tr>@foreach ($def['columns'] as $c)<td>{{ $row[$c['key']] ?? '' }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($def['columns']) }}">No entries recorded.</td></tr>
            @endforelse
        </tbody>
    </table>

    @include('forms._rsl-footer')
</body>
</html>
