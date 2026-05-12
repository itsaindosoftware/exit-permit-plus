<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Internal Receipt</title>
    <style>
        @page {
            margin: 10mm;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            color: #111;
        }

        .sheet {
            border: 1px solid #111;
            padding: 8px 10px 6px;
        }

        .company {
            text-align: center;
            line-height: 1.3;
            font-size: 10px;
            margin-bottom: 8px;
        }

        .company .name {
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        .title {
            text-align: center;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: 0.3px;
        }

        .field {
            margin-bottom: 5px;
            white-space: nowrap;
        }

        .label {
            display: inline-block;
            width: 190px;
        }

        .line {
            display: inline-block;
            border-bottom: 1px solid #111;
            min-height: 12px;
            vertical-align: bottom;
        }

        .w-460 { width: 460px; }
        .w-420 { width: 420px; }
        .w-390 { width: 390px; }
        .w-360 { width: 360px; }
        .w-330 { width: 330px; }

        .sign-grid {
            width: 100%;
            margin-top: 28px;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .sign-grid td {
            width: 25%;
            text-align: center;
            vertical-align: top;
            padding: 0 6px;
            border: 0;
        }

        .role {
            font-size: 10px;
            margin-bottom: 46px;
            text-transform: uppercase;
        }

        .role.small {
            text-transform: none;
        }

        .name-line {
            border-bottom: 1px solid #111;
            height: 12px;
            margin-bottom: 4px;
        }

        .meta {
            text-align: left;
            font-size: 10px;
            margin-top: 3px;
        }

        .footer {
            border-top: 1px solid #111;
            margin-top: 10px;
            padding-top: 3px;
            text-align: right;
            font-size: 10px;
        }
    </style>
</head>
<body>
@php
    $requestDate = $reimbursement->request_date ? $reimbursement->request_date->format('Y-m-d') : '';
@endphp

<div class="sheet">
    <div class="company">
        <div class="name">PT INDONESIA THAI SUMMIT AUTO</div>
        <div>Jl. Permata Raya Lot FF 5, Kawasan Industri KIIC, Karawang 41361</div>
        <div>Tlp: 0267-845784 Fax. 0264-8453187</div>
    </div>

    <div class="title">INTERNAL RECEIPT</div>

    <div class="field">
        <span class="label">Tgl Bayar / Payment Date</span>: <span class="line w-420">{{ $requestDate }}</span>
    </div>
    <div class="field">
        <span class="label">Dibayar Kepada / Paid To</span>: <span class="line w-420">{{ $reimbursement->paid_to }}</span>
    </div>
    <div class="field">
        <span class="label">Jumlah / Amount</span>: <span class="line w-420">Rp {{ number_format((int) $reimbursement->amount, 0, ',', '.') }} ({{ $reimbursement->amount_in_words }})</span>
    </div>
    <div class="field">
        <span class="label">Terbilang / Stated</span>: <span class="line w-420">{{ $reimbursement->amount_in_words }}</span>
    </div>
    <div class="field">
        <span class="label">Jenis Biaya / Expense Type</span>: <span class="line w-390">{{ $reimbursement->expense_type }}</span>
    </div>
    <div class="field">
        <span class="label">Tujuan / Purpose</span>: <span class="line w-460">{{ $reimbursement->purpose }}</span>
    </div>
    <div class="field">
        <span class="label">Dok Reff / Reff Document</span>: <span class="line w-360">{{ $reimbursement->ref_document }}</span>
    </div>

    <table class="sign-grid">
        <tr>
            <td><div class="role">RECEIVER</div></td>
            <td><div class="role">PIC</div></td>
            <td><div class="role small">Checker</div></td>
            <td><div class="role small">Approved</div></td>
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
