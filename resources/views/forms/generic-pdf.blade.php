<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10px; color: #111; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        .sub { color: #555; font-size: 9px; margin-bottom: 8px; }
        .instructions { font-size: 9px; color: #444; font-style: italic; margin-bottom: 10px; }
        .fields { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .fields td { padding: 3px 6px; vertical-align: top; }
        .fields td.k { width: 200px; color: #555; font-weight: bold; }
        table.checklist { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.checklist th, table.checklist td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; vertical-align: top; }
        table.checklist th.section { background: #e5e7eb; font-size: 10px; text-transform: uppercase; letter-spacing: .03em; }
        td.ans { width: 44px; text-align: center; font-weight: bold; }
        .yes { color: #166534; } .no { color: #b91c1c; } .na { color: #888; }
        td.note { width: 160px; color: #444; font-size: 9px; }
    </style>
</head>
<body>
    @include('forms._rsl-letterhead')
    @if (! empty($def['instructions']))
        <div class="instructions">{{ $def['instructions'] }}</div>
    @endif

    @if (! empty($def['fields']))
        <table class="fields">
            @foreach ($def['fields'] as $f)
                <tr>
                    <td class="k">{{ $f['label'] }}</td>
                    <td>{{ data_get($form->answers, "fields.{$f['key']}") ?: '—' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @foreach ($def['sections'] ?? [] as $section)
        <table class="checklist">
            <thead><tr><th class="section" colspan="3">{{ $section['heading'] }}</th></tr></thead>
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

    @if (! empty($def['footer_fields']))
        <table class="fields">
            @foreach ($def['footer_fields'] as $f)
                <tr>
                    <td class="k">{{ $f['label'] }}</td>
                    <td>{{ data_get($form->answers, "fields.{$f['key']}") ?: '—' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @include('forms._rsl-footer')
</body>
</html>
