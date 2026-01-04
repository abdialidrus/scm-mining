@extends('emails.layout')

@section('content')
    <h2 style="color: #ef4444; margin-top: 0;">✗ Document Rejected</h2>

    <p>Hello <strong>{{ $submitterName }}</strong>,</p>

    <p>Unfortunately, your document has been rejected:</p>

    <div class="warning-box">
        <table style="margin: 0;">
            <tr>
                <td style="border: none; padding: 5px 10px 5px 0; font-weight: 600; width: 150px;">Document Type:</td>
                <td style="border: none; padding: 5px 0;">{{ $documentType }}</td>
            </tr>
            <tr>
                <td style="border: none; padding: 5px 10px 5px 0; font-weight: 600;">Document Number:</td>
                <td style="border: none; padding: 5px 0;">{{ $documentNumber }}</td>
            </tr>
            <tr>
                <td style="border: none; padding: 5px 10px 5px 0; font-weight: 600;">Rejected By:</td>
                <td style="border: none; padding: 5px 0;">{{ $rejectedBy }}</td>
            </tr>
            <tr>
                <td style="border: none; padding: 5px 10px 5px 0; font-weight: 600;">Rejected Date:</td>
                <td style="border: none; padding: 5px 0;">{{ $rejectedDate }}</td>
            </tr>
        </table>
    </div>

    @if(isset($reason) && $reason)
    <p><strong>Rejection Reason:</strong></p>
    <div class="warning-box">
        <p style="margin: 0;">{{ $reason }}</p>
    </div>
    @endif

    <div class="info-box">
        <p style="margin: 0;">
            <strong>What to do next:</strong><br>
            Please review the rejection reason, make necessary corrections, and resubmit the document if needed.
        </p>
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $documentUrl }}" class="button">
            View Document Details
        </a>
    </div>

    <p style="font-size: 14px; color: #6b7280;">
        If you have any questions about the rejection, please contact the approver directly or reach out to your supervisor.
    </p>
@endsection
