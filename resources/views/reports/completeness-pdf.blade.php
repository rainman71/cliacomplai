<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 9px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .sub { color: #666; font-size: 9px; margin-bottom: 10px; }
        .summary { margin-bottom: 10px; font-size: 10px; }
        .summary span { margin-right: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 5px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-size: 8px; text-transform: uppercase; letter-spacing: .03em; }
        tr.missing td { background: #fef2f2; }
        .ok { color: #166534; font-weight: bold; }
        .no { color: #b91c1c; font-weight: bold; }
        .muted { color: #999; }
    </style>
</head>
<body>
    <h1>Rightsize Labs — CLIA Completeness Report</h1>
    <div class="sub">Generated {{ $generatedAt }}</div>

    <div class="summary">
        <span><strong>{{ $summary['total'] }}</strong> obligations</span>
        <span class="ok">{{ $summary['completed'] }} completed</span>
        <span class="no">{{ $summary['missing'] }} missing a date</span>
        <span>{{ $summary['with_links'] }} with document links</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Obligation</th>
                <th>Owner</th>
                <th>Completed</th>
                <th>Last Completed</th>
                <th>Next Due</th>
                <th>Status</th>
                <th>Signature</th>
                <th>Required Signers</th>
                <th>Document Link</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $r)
                <tr class="{{ $r['completed'] ? '' : 'missing' }}">
                    <td>{{ $r['code'] }}</td>
                    <td>{{ $r['name'] }}<br><span class="muted">{{ $r['category'] }}</span></td>
                    <td>{{ $r['owner_role'] }}</td>
                    <td>{!! $r['completed'] ? '<span class="ok">Yes</span>' : '<span class="no">No</span>' !!}</td>
                    <td>{{ $r['last_completed'] ?? '—' }}</td>
                    <td>{{ $r['next_due'] ?? '—' }}</td>
                    <td>{{ $r['status'] }}</td>
                    <td>{{ $r['signature_status'] }}</td>
                    <td>{{ $r['required_signers'] }}</td>
                    <td>{{ $r['document_link'] ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
