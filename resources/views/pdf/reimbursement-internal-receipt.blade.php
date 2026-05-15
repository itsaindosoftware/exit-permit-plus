<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Internal Receipt</title>
    <style>
        @page {
            margin: 8mm;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10.5px;
            color: #111;
        }

        .sheet {
            border: 1px solid #111;
            padding: 10px 12px 6px;
        }

        .header {
            position: relative;
            min-height: 40px;
            margin-bottom: 4px;
        }

        .logo {
            position: absolute;
            left: 0;
            top: 1px;
            width: 42px;
            height: 22px;
            border: 1px solid #999;
            border-radius: 2px;
            text-align: center;
            overflow: hidden;
            background: #fff;
        }

        .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        .logo-fallback {
            line-height: 22px;
            font-size: 8px;
            color: #666;
        }

        .company {
            text-align: center;
            line-height: 1.3;
            font-size: 10px;
            margin-bottom: 3px;
        }

        .company .name {
            font-weight: 700;
            letter-spacing: 0.2px;
        }

        .title {
            text-align: center;
            font-weight: 700;
            margin: 1px 0 9px;
            letter-spacing: 0.4px;
            font-size: 10px;
        }

        .field {
            margin-bottom: 6px;
            white-space: nowrap;
        }

        .label {
            display: inline-block;
            width: 188px;
        }

        .line {
            display: inline-block;
            border-bottom: 1px solid #111;
            height: 13px;
            vertical-align: bottom;
            line-height: 13px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .w-470 { width: 470px; }
        .w-430 { width: 430px; }
        .w-400 { width: 400px; }
        .w-370 { width: 370px; }

        .sign-grid {
            width: 100%;
            margin-top: 26px;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .sign-grid td {
            width: 25%;
            text-align: left;
            vertical-align: top;
            padding: 0 10px 0 0;
            border: 0;
        }

        .role {
            font-size: 10px;
            margin-bottom: 42px;
            text-decoration: underline;
        }

        .name-line {
            border-bottom: 1px solid #111;
            height: 14px;
            line-height: 14px;
            margin-bottom: 4px;
            overflow: hidden;
        }

        .meta {
            text-align: left;
            font-size: 10px;
            margin-top: 7px;
            line-height: 1;
        }

        .footer {
            border-top: 1px solid #111;
            margin-top: 18px;
            padding-top: 2px;
            text-align: right;
            font-size: 10px;
        }
    </style>
</head>
<body>
@php
    $requestDate = $reimbursement->request_date ? $reimbursement->request_date->format('Y-m-d') : '';
    $amount = 'Rp ' . number_format((int) $reimbursement->amount, 0, ',', '.');
    $amountInWords = \Illuminate\Support\Str::title((string) ($reimbursement->amount_in_words ?? ''));
    $logoPath = public_path('storage/logo-itsa2.png');
    $logoDataUri = file_exists($logoPath)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
        : null;
@endphp

<div class="sheet">
    <div class="header">
        <div class="logo">
            @if ($logoDataUri)
                <img src="{{ $logoDataUri }}" alt="ITSA Logo">
            @else
                <div class="logo-fallback">ITSA</div>
            @endif
        </div>
        <div class="company">
            <div class="name">PT INDONESIA THAI SUMMIT AUTO</div>
            <div>Jl. Permata Raya Lot FF.5, Kawasan Industri KIIC, Karawang 41361</div>
            <div>Tlp : 0267-8457184 Fax : 0264-8457187</div>
        </div>
    </div>

    <div class="title">INTERNAL RECEIPT</div>

    <div class="field">
        <span class="label">Tgl Bayar / Payment Date</span>: <span class="line w-430">{{ $requestDate }}</span>
    </div>
    <div class="field">
        <span class="label">Dibayar Kepada / Paid To</span>: <span class="line w-430">{{ $reimbursement->paid_to }}</span>
    </div>
    <div class="field">
        <span class="label">Jumlah / Amount</span>: <span class="line w-430">{{ $amount }}</span>
    </div>
    <div class="field">
        <span class="label">Terbilang / Stated</span>: <span class="line w-430">{{ $amountInWords }}</span>
    </div>
    <div class="field">
        <span class="label">Jenis Biaya / Expense Type</span>: <span class="line w-400">{{ $reimbursement->expense_type }}</span>
    </div>
    <div class="field">
        <span class="label">Tujuan / Purpose</span>: <span class="line w-470">{{ $reimbursement->purpose }}</span>
    </div>
    <div class="field">
        <span class="label">Dok Reff / Reff.Document</span>: <span class="line w-370">{{ $reimbursement->ref_document }}</span>
    </div>

    <table class="sign-grid">
        <tr>
            <td><div class="role">RECEIVER</div></td>
            <td><div class="role">PIC</div></td>
            <td><div class="role">Checked</div></td>
            <td><div class="role">Approved</div></td>
        </tr>
        <tr>
            <td>
                <div class="name-line">{{ $reimbursement->user?->name }}</div>
                <div class="meta">Name</div>
                <div class="meta">Section</div>
                <div class="meta">Dated</div>
            </td>
            <td>
                <div class="name-line">{{ $reimbursement->ratnaSubmitter?->name }}</div>
                <div class="meta">Name</div>
                <div class="meta">Section</div>
                <div class="meta">Dated</div>
            </td>
            <td>
                <div class="name-line">{{ $reimbursement->managerApprover?->name }}</div>
                <div class="meta">Name</div>
                <div class="meta">Section</div>
                <div class="meta">Dated</div>
            </td>
            <td>
                <div class="name-line">{{ $reimbursement->mdApprover?->name ?? $reimbursement->accountingProcessor?->name }}</div>
                <div class="meta">Name</div>
                <div class="meta">Section</div>
                <div class="meta">Dated</div>
            </td>
        </tr>
    </table>

    <div class="footer">F-ACC-008-0</div>
</div>
</body>
</html>
