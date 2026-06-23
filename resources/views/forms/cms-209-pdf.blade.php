<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10px; color: #111; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        .sub { color: #555; font-size: 9px; margin-bottom: 8px; }
        .meta { margin-bottom: 10px; font-size: 10px; }
        .meta span { margin-right: 18px; }
        .instructions { font-size: 9px; color: #444; font-style: italic; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #e5e7eb; font-size: 9px; text-transform: uppercase; letter-spacing: .03em; }
        .sign { margin-top: 18px; font-size: 10px; }
        .line { display: inline-block; border-bottom: 1px solid #333; min-width: 220px; }
    </style>
</head>
<body>
    <h1>{{ $def['title'] }}</h1>
    <div class="sub">{{ $lab->name }}</div>
    <div class="instructions">{{ $def['instructions'] }}</div>

    <div class="meta">
        <span><strong>CLIA #:</strong> {{ data_get($form->answers, 'clia_number') ?: '—' }}</span>
        <span><strong>Report date:</strong> {{ $form->completed_date?->toDateString() }}</span>
        <span><strong>Address:</strong> {{ data_get($form->answers, 'address') ?: '—' }}</span>
    </div>

    <table>
        <thead>
            <tr><th>Name</th><th>Position(s) Held</th><th>Qualifications</th></tr>
        </thead>
        <tbody>
            @forelse (data_get($form->answers, 'people', []) as $p)
                <tr>
                    <td>{{ $p['name'] ?? '' }}</td>
                    <td>{{ $p['positions'] ?? '' }}</td>
                    <td>{{ $p['qualifications'] ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="3">No personnel listed.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="sign">
        <div>Prepared by: <span class="line">{{ data_get($form->answers, 'prepared_by') }}</span> &nbsp;&nbsp;
             Date: <span class="line" style="min-width:120px">{{ $form->completed_date?->toDateString() }}</span></div>
        <div style="margin-top:10px">Laboratory Director: <span class="line"></span> &nbsp;&nbsp;
             Date: <span class="line" style="min-width:120px"></span></div>
    </div>
</body>
</html>
