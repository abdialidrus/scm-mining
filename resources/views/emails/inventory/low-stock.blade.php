@extends('emails.layout')

@section('content')
    <h2 style="color: #ef4444; margin-top: 0;">⚠️ Low Stock Alert</h2>

    <p>Hello,</p>

    <p>The following items have reached low stock levels and require attention:</p>

    <table>
        <thead>
            <tr>
                <th>Item Code</th>
                <th>Item Name</th>
                <th>Location</th>
                <th>Current Stock</th>
                <th>Min. Stock</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
            <tr>
                <td><strong>{{ $item['code'] }}</strong></td>
                <td>{{ $item['name'] }}</td>
                <td>{{ $item['location'] }}</td>
                <td style="color: #ef4444; font-weight: 600;">{{ $item['current_stock'] }} {{ $item['uom'] }}</td>
                <td>{{ $item['min_stock'] }} {{ $item['uom'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @if($outOfStockCount > 0)
    <div class="warning-box">
        <p style="margin: 0; font-weight: 600;">
            🚨 <strong>{{ $outOfStockCount }}</strong> item(s) are completely out of stock!
        </p>
    </div>
    @endif

    <div class="info-box">
        <p style="margin: 0;">
            <strong>Recommended Action:</strong><br>
            Please review these items and consider creating purchase requests to replenish stock levels.
        </p>
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $reportUrl }}" class="button">
            View Full Stock Report
        </a>
    </div>
@endsection
