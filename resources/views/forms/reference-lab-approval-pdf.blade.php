<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10px; color: #111; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        .sub { color: #555; font-size: 9px; margin-bottom: 8px; }
        .statement { font-size: 10px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #e5e7eb; font-size: 9px; text-transform: uppercase; letter-spacing: .03em; }
        .sign { margin-top: 22px; font-size: 10px; }
        .line { display: inline-block; border-bottom: 1px solid #333; min-width: 240px; }
    </style>
</head>
<body>
    @include('forms._rsl-letterhead')

    <div class="statement">{{ $def['instructions'] }}</div>

    <table>
        <thead>
            <tr><th>Reference Laboratory</th><th>CLIA #</th><th>Address</th><th>Tests Referred</th></tr>
        </thead>
        <tbody>
            @forelse (data_get($form->answers, 'reference_labs', []) as $l)
                <tr>
                    <td>{{ $l['name'] ?? '' }}</td>
                    <td>{{ $l['clia_number'] ?? '' }}</td>
                    <td>{{ $l['address'] ?? '' }}</td>
                    <td>{{ $l['tests'] ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No reference labs listed.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="sign">
        <div>Laboratory Director: <span class="line">{{ data_get($form->answers, 'approver') }}</span> &nbsp;&nbsp;
             Date: <span class="line" style="min-width:120px">{{ $form->completed_date?->toDateString() }}</span></div>
    </div>

    @include('forms._rsl-footer')
</body>
</html>
