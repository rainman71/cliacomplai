{{-- Shared Rightsize letterhead for generated official forms. Expects $def, $lab, $form. --}}
@php($sop = $def['sop_code'] ?? null)
<div style="border-bottom:2px solid #1F3A5F; padding-bottom:4px; margin-bottom:8px;">
    <table style="width:100%; border-collapse:collapse; border:none;">
        <tr>
            <td style="border:none; font-size:12px; font-weight:bold; color:#1F3A5F; letter-spacing:.04em;">RIGHTSIZE COMPLIANCE</td>
            <td style="border:none; text-align:right; font-size:9px; color:#555;">@if($sop){{ $sop }}@endif</td>
        </tr>
    </table>
</div>
<h1 style="font-size:15px; margin:0 0 2px; color:#1F3A5F;">{{ $def['title'] }}</h1>
<div style="color:#555; font-size:9px; margin-bottom:10px;">
    @if($sop){{ $sop }} · @endif{{ $lab->name }} · {{ $form->completed_date?->toDateString() }}
</div>
