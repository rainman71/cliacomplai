<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <p>Hello,</p>

    @if ($escalation)
        <p style="color: #b91c1c;">
            <strong>Signature escalation.</strong> The document for
            <strong>{{ $obligation->code }} — {{ $obligation->name }}</strong>
            has been out for signature for <strong>{{ $daysPending }} days</strong> and is still not complete.
        </p>
    @else
        <p>
            A reminder that <strong>{{ $obligation->code }} — {{ $obligation->name }}</strong>
            has been awaiting signature for <strong>{{ $daysPending }} days</strong>.
        </p>
    @endif

    <table cellpadding="6" style="border-collapse: collapse; margin: 12px 0;">
        <tr><td><strong>Sent</strong></td><td>{{ optional($request->sent_date)->toFormattedDateString() }}</td></tr>
        <tr><td><strong>Still pending</strong></td><td>{{ $pendingSigners ?: '—' }}</td></tr>
        @if ($request->deadline)
            <tr><td><strong>Deadline</strong></td><td>{{ $request->deadline->toFormattedDateString() }}</td></tr>
        @endif
    </table>

    @if ($obligation->document_link)
        <p><a href="{{ $obligation->document_link }}">Open the document to sign</a></p>
    @endif

    <p style="color: #6b7280; font-size: 12px;">Rightsize Labs — CLIA Compliance · automated reminder</p>
</body>
</html>
