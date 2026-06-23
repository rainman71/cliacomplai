<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <p>Weekly overdue compliance digest — <strong>{{ $items->count() }}</strong> item(s) past due:</p>

    <table cellpadding="8" style="border-collapse: collapse; margin: 12px 0; width: 100%;">
        <thead>
            <tr style="background: #f3f4f6; text-align: left;">
                <th>ID</th><th>Obligation</th><th>Owner</th><th>Days overdue</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $row)
                <tr style="border-bottom: 1px solid #e5e7eb;">
                    <td>{{ $row['obligation']->code }}</td>
                    <td>{{ $row['obligation']->name }}</td>
                    <td>{{ $row['obligation']->owner_role }}</td>
                    <td style="color: #b91c1c; font-weight: bold;">{{ abs($row['days']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p>Please prioritize these items. Open the app for full details and document links.</p>

    <p style="color: #6b7280; font-size: 12px;">Rightsize Labs — CLIA Compliance · automated weekly digest</p>
</body>
</html>
