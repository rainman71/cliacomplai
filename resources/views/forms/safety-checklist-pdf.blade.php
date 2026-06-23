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
        th.section { background: #e5e7eb; font-size: 10px; text-transform: uppercase; letter-spacing: .03em; }
        td.ans { width: 44px; text-align: center; font-weight: bold; }
        .yes { color: #166534; }
        .no { color: #b91c1c; }
        .na { color: #888; }
        td.note { width: 160px; color: #444; font-size: 9px; }
        .sign { margin-top: 18px; font-size: 10px; }
        .sign div { margin-bottom: 10px; }
        .line { display: inline-block; border-bottom: 1px solid #333; min-width: 220px; }
    </style>
</head>
<body>
    @include('forms._rsl-letterhead')
    <div class="instructions">{{ $def['instructions'] }}</div>

    <div class="meta">
        <span><strong>Review date:</strong> {{ $form->completed_date?->toDateString() }}</span>
        <span><strong>Completed by:</strong> {{ data_get($form->answers, 'completed_by') ?: '—' }}</span>
        <span><strong>Technical Supervisor:</strong> {{ data_get($form->answers, 'tech_supervisor') ?: '—' }}</span>
    </div>

    @foreach ($def['sections'] as $section)
        <table>
            <thead>
                <tr><th class="section" colspan="3">{{ $section['heading'] }}</th></tr>
            </thead>
            <tbody>
                @foreach ($section['items'] as $item)
                    @php
                        $ans = data_get($form->answers, "items.{$item['key']}.answer");
                        $note = data_get($form->answers, "items.{$item['key']}.note");
                        $label = ['yes' => 'Yes', 'no' => 'No', 'na' => 'N/A'][$ans] ?? '—';
                    @endphp
                    <tr>
                        <td>{{ $item['label'] }}</td>
                        <td class="ans {{ $ans ?? '' }}">{{ $label }}</td>
                        <td class="note">{{ $note }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <div class="sign">
        <div>Completed by: <span class="line">{{ data_get($form->answers, 'completed_by') }}</span> &nbsp;&nbsp; Date: <span class="line" style="min-width:120px">{{ $form->completed_date?->toDateString() }}</span></div>
        <div>Technical Supervisor: <span class="line">{{ data_get($form->answers, 'tech_supervisor') }}</span> &nbsp;&nbsp; Date: <span class="line" style="min-width:120px">{{ $form->completed_date?->toDateString() }}</span></div>
    </div>

    @include('forms._rsl-footer')
</body>
</html>
