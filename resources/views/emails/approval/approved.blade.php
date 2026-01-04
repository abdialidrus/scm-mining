@extends('emails.layout')

@section('content')
    <h2 style="color: #10b981; margin-top: 0;">✓ Document Approved</h2>

    <p>Hello <strong>{{ $submitterName }}</strong>,</p>

    <p>Great news! Your document has been approved:</p>

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
                <td style="border: none; padding: 5px 10px 5px 0; font-weight: 600;">Approved By:</td>
                <td style="border: none; padding: 5px 0;">{{ $approvedBy }}</td>
            </tr>
            <tr>
                <td style="border: none; padding: 5px 10px 5px 0; font-weight: 600;">Approved Date:</td>
                <td style="border: none; padding: 5px 0;">{{ $approvedDate }}</td>
            </tr>
            @if(isset($currentStep) && $totalSteps)
            <tr>
                <td style="border: none; padding: 5px 10px 5px 0; font-weight: 600;">Approval Status:</td>
                <td style="border: none; padding: 5px 0;">Step {{ $currentStep }} of {{ $totalSteps }}</td>
            </tr>
            @endif
        </table>
    </div>

    @if(isset($comments) && $comments)
    <p><strong>Approver Comments:</strong></p>
    <div class="info-box">
        <p style="margin: 0;">{{ $comments }}</p>
    </div>
    @endif

    @if(isset($isFinalApproval) && $isFinalApproval)
    <div style="background: #d1fae5; padding: 15px; border-left: 4px solid #10b981; margin: 20px 0; border-radius: 4px;">
        <p style="margin: 0; font-weight: 600; color: #065f46;">
            🎉 This was the final approval step! Your document is now fully approved and ready for processing.
        </p>
    </div>
    @else
    <div class="info-box">
        <p style="margin: 0;">
            <strong>Next Step:</strong> Your document will proceed to the next approval level.
        </p>
    </div>
    @endif

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $documentUrl }}" class="button">
            View Document
        </a>
    </div>
@endsection
