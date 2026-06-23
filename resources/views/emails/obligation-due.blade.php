<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    @php
        $overdue = $type === 'overdue_1';
        $label = match ($type) {
            'due_30' => 'is due in 30 days',
            'due_7' => 'is due in 7 days',
            'due_0' => 'is due TODAY',
            'overdue_1' => 'is now OVERDUE',
            default => 'needs attention',
        };
    @endphp

    <p>Hello,</p>

    <p>
        Compliance obligation <strong>{{ $obligation->code }} — {{ $obligation->name }}</strong>
        ({{ $obligation->category }}) <strong style="color: {{ $overdue ? '#b91c1c' : '#b45309' }};">{{ $label }}</strong>.
    </p>

    <table cellpadding="6" style="border-collapse: collapse; margin: 12px 0;">
        <tr><td><strong>Owner</strong></td><td>{{ $obligation->owner_role }}</td></tr>
        <tr><td><strong>Frequency</strong></td><td>{{ $obligation->frequency_label }}</td></tr>
        <tr><td><strong>Next due</strong></td><td>{{ $nextDue }}{{ $days < 0 ? ' ('.abs($days).' days ago)' : ' (in '.$days.' days)' }}</td></tr>
    </table>

    @if ($overdue)
        <p style="color: #b91c1c;"><strong>This item is overdue and has been escalated.</strong> Please complete it and submit for signature as soon as possible.</p>
    @else
        <p>Please complete this obligation and submit it for signature before the due date.</p>
    @endif

    @if ($obligation->document_link)
        <p><a href="{{ $obligation->document_link }}">Open the related document/folder</a></p>
    @endif

    <p style="color: #6b7280; font-size: 12px;">Rightsize Labs — CLIA Compliance · automated reminder</p>
</body>
</html>
