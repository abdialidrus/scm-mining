@extends('emails.layout')

@section('content')
    <h2 style="color: #f59e0b; margin-top: 0;">⏰ Pending Approval Reminder</h2>

    <p>Hello <strong>{{ $approverName }}</strong>,</p>

    <p>This is a friendly reminder that you have <strong>{{ $pendingCount }}</strong> document(s) waiting for your approval:</p>

    @if(count($documents) > 0)
    <table>
        <thead>
            <tr>
                <th>Document</th>
                <th>Type</th>
                <th>Submitted</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($documents as $doc)
            <tr>
                <td><strong>{{ $doc['number'] }}</strong></td>
                <td>{{ $doc['type'] }}</td>
                <td>{{ $doc['submitted_date'] }}</td>
                <td>{{ $doc['amount'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if($overdueCount > 0)
    <div class="warning-box">
        <p style="margin: 0; font-weight: 600;">
            ⚠️ <strong>{{ $overdueCount }}</strong> document(s) are overdue and require urgent attention!
        </p>
    </div>
    @endif

    <p style="margin-top: 30px;">Please take a moment to review and process these approvals:</p>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $dashboardUrl }}" class="button">
            Go to My Approvals
        </a>
    </div>

    <p style="font-size: 14px; color: #6b7280;">
        You're receiving this reminder because you have pending approvals. To manage your notification preferences,
        please visit your <a href="{{ $settingsUrl }}" style="color: #667eea;">notification settings</a>.
    </p>
@endsection
