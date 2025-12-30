<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Order - {{ $po->po_number ?? '-' }}</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #333;
            margin: 0;
            padding: 0;
        }

        .container {
            padding: 30px 40px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .company-info h2 {
            margin: 0;
            font-size: 20px;
        }

        .company-info p {
            margin: 2px 0;
            font-size: 12px;
        }

        .po-info {
            text-align: right;
        }

        .po-info h3 {
            margin: 0;
            font-size: 18px;
        }

        .po-info p {
            margin: 2px 0;
            font-size: 12px;
        }

        .section {
            margin-top: 25px;
        }

        .section-title {
            font-weight: bold;
            margin-bottom: 8px;
            font-size: 13px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        table thead th {
            background: #f2f2f2;
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
            font-size: 12px;
        }

        table tbody td {
            padding: 8px;
            border: 1px solid #ddd;
            font-size: 12px;
        }

        .text-right {
            text-align: right;
        }

        .summary {
            margin-top: 20px;
            width: 40%;
            float: right;
        }

        .summary table td {
            padding: 6px;
        }

        .footer {
            margin-top: 60px;
            display: flex;
            justify-content: space-between;
        }

        .signature {
            text-align: center;
            margin-top: 50px;
        }

        .signature-line {
            margin-top: 40px;
            border-top: 1px solid #000;
            width: 200px;
        }

    </style>
</head>
<body>

<div class="container">

    {{-- HEADER --}}
    <div class="header">
        <div class="company-info">
            <h2>SCM Mining</h2>
            <p>Jl. Pertambangan No. 123, Site Area, Mining Site</p>
            <p>Phone: +62 123 4567 8900</p>
            <p>Email: procurement@scm-mining.com</p>
        </div>

        <div class="po-info">
            <h3>PURCHASE ORDER</h3>
            <p><strong>No:</strong> {{ $po->po_number }}</p>
            <p><strong>Date:</strong> {{ \Carbon\Carbon::parse($po->created_at)->format('d M Y') }}</p>
            <p><strong>Status:</strong> {{ strtoupper($po->status) }}</p>
        </div>
    </div>

    {{-- SUPPLIER INFO --}}
    <div class="section">
        <div class="section-title">Supplier Information</div>
        @if($po->supplier)
            <p><strong>{{ $po->supplier->name }}</strong></p>
            <p>{{ $po->supplier->address ?? '-' }}</p>
            <p>Phone: {{ $po->supplier->phone ?? '-' }}</p>
            <p>Email: {{ $po->supplier->email ?? '-' }}</p>
        @else
            <p>-</p>
        @endif
    </div>

    {{-- ITEMS TABLE --}}
    <div class="section">
        <table>
            <thead>
                <tr>
                    <th style="width: 5%">#</th>
                    <th>Item</th>
                    <th style="width: 15%">Qty</th>
                    <th style="width: 15%">Price ({{ $po->currency_code }})</th>
                    <th style="width: 15%">Total ({{ $po->currency_code }})</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($po->lines ?? [] as $line)
                    @php
                        $lineTotal = ($line->quantity ?? 0) * ($line->unit_price ?? 0);
                    @endphp
                    <tr>
                        <td>{{ $line->line_no }}</td>
                        <td>
                            <strong>{{ $line->item_snapshot['sku'] ?? '-' }}</strong> - {{ $line->item_snapshot['name'] ?? '-' }}<br>
                            @if($line->remarks)
                                <small style="color: #999;">{{ $line->remarks }}</small>
                            @endif
                        </td>
                        <td class="text-right">{{ number_format($line->quantity, 2) }} {{ $line->uom_snapshot['code'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($line->unit_price, 2) }}</td>
                        <td class="text-right">{{ number_format($lineTotal, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- SUMMARY --}}
    <div class="summary">
        <table>
            <tr>
                <td>Subtotal ({{ $po->currency_code }})</td>
                <td class="text-right">{{ number_format($po->subtotal_amount ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td>Tax ({{ number_format($po->tax_rate ?? 0, 2) }}%) ({{ $po->currency_code }})</td>
                <td class="text-right">{{ number_format($po->tax_amount ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td><strong>Grand Total ({{ $po->currency_code }})</strong></td>
                <td class="text-right"><strong>{{ number_format($po->total_amount ?? 0, 2) }}</strong></td>
            </tr>
        </table>
    </div>

    <div style="clear: both;"></div>

    @if($po->remarks)
    <div class="section">
        <div class="section-title">Remarks</div>
        <p>{{ $po->remarks }}</p>
    </div>
    @endif

    {{-- FOOTER --}}
    <div class="footer">
        <div class="signature">
            Prepared By
            <div class="signature-line"></div>
            <small>{{ $po->submittedBy->name ?? '-' }}</small>
        </div>

        <div class="signature">
            Approved By
            <div class="signature-line"></div>
            <small>{{ $po->approvedBy->name ?? '-' }}</small>
        </div>
    </div>

</div>

</body>
</html>
