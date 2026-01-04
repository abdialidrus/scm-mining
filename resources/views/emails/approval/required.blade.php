@extends('emails.layout')

@section('content')
    <h2 style="color: #1f2937; margin-top: 0;">Approval Required</h2>

    <p>Hello <strong>{{ $approverName }}</strong>,</p>

    <p>A document requires your approval:</p>

    <div class="info-box">
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
                <td style="border: none; padding: 5px 10px 5px 0; font-weight: 600;">Submitted By:</td>
                <td style="border: none; padding: 5px 0;">{{ $submittedBy }}</td>
            </tr>
            <tr>
                <td style="border: none; padding: 5px 10px 5px 0; font-weight: 600;">Amount:</td>
                <td style="border: none; padding: 5px 0;">{{ $amount }}</td>
            </tr>
            <tr>
                <td style="border: none; padding: 5px 10px 5px 0; font-weight: 600;">Submitted Date:</td>
                <td style="border: none; padding: 5px 0;">{{ $submittedDate }}</td>
            </tr>
        </table>
    </div>

    @if(isset($description) && $description)
    <p><strong>Description:</strong></p>
    <p>{{ $description }}</p>
    @endif

    <p style="margin-top: 30px;">Please review and take action:</p>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $approvalUrl }}" class="button">
            Review & Approve
        </a>
    </div>

    <p style="font-size: 14px; color: #6b7280;">
        You can also access your pending approvals from the
        <a href="{{ $dashboardUrl }}" style="color: #667eea;">My Approvals Dashboard</a>.
    </p>
@endsection
