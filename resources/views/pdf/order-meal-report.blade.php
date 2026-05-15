<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Meal Report</title>
    <style>
        @page {
            margin: 8mm;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10px;
            color: #111;
        }

        .sheet {
            border: 1px solid #111;
            padding: 10px 10px 8px;
        }

        .head {
            position: relative;
            min-height: 40px;
            margin-bottom: 4px;
        }

        .logo-box {
            position: absolute;
            top: 1px;
            left: 0;
            width: 42px;
            height: 22px;
            border: 1px solid #999;
            border-radius: 2px;
            text-align: center;
            background: #fff;
            overflow: hidden;
        }

        .logo-box img {
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
            margin: 2px 0 10px;
            letter-spacing: 0.4px;
            font-size: 11px;
        }

        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        .meta td {
            border: 1px solid #111;
            padding: 3px 4px;
            vertical-align: top;
        }

        .section-title {
            margin: 8px 0 4px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }

        table.report {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        table.report th,
        table.report td {
            border: 1px solid #111;
            padding: 3px 4px;
            vertical-align: top;
        }

        table.report th {
            background: #f4f4f4;
            font-weight: 700;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .footer {
            border-top: 1px solid #111;
            margin-top: 10px;
            padding-top: 2px;
            text-align: right;
            font-size: 9px;
        }
    </style>
</head>
<body>
@php
    $logoPath = public_path('storage/logo-itsa2.png');
    $logoDataUri = file_exists($logoPath)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
        : null;

    $periodValue = strtoupper((string) ($periodLabel ?? 'Harian'));
    $scopeValue = strtoupper((string) ($scopeLabel ?? 'General'));
    $printedAtText = $printedAt ? $printedAt->format('d/m/Y H:i') : now()->format('d/m/Y H:i');

    $filterText = [];
    if (!empty($filters['search'])) {
        $filterText[] = 'Search: ' . $filters['search'];
    }
    if (!empty($filters['menu'])) {
        $filterText[] = 'Menu: ' . $filters['menu'];
    }
    if (!empty($filters['date_from'])) {
        $filterText[] = 'From: ' . $filters['date_from'];
    }
    if (!empty($filters['date_to'])) {
        $filterText[] = 'To: ' . $filters['date_to'];
    }
    if (!empty($filters['shift'])) {
        $filterText[] = 'Shift: ' . $filters['shift'];
    }
@endphp

<div class="sheet">
    <div class="head">
        <div class="logo-box">
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

    <div class="title">ORDER MEAL REPORT - {{ $periodValue }}</div>

    <table class="meta">
        <tr>
            <td style="width: 20%;"><strong>Scope</strong></td>
            <td style="width: 30%;">{{ $scopeValue }}</td>
            <td style="width: 20%;"><strong>Printed At</strong></td>
            <td style="width: 30%;">{{ $printedAtText }}</td>
        </tr>
        <tr>
            <td><strong>Filter</strong></td>
            <td colspan="3">{{ count($filterText) > 0 ? implode(' | ', $filterText) : '-' }}</td>
        </tr>
    </table>

    <div class="section-title">Ringkasan {{ $periodValue }}</div>
    <table class="report">
        <thead>
            <tr>
                <th style="width: 24%;">Periode</th>
                <th style="width: 12%;" class="text-right">Disediakan</th>
                <th style="width: 12%;" class="text-right">Realisasi</th>
                <th style="width: 12%;" class="text-right">Sisa</th>
                <th style="width: 20%;" class="text-right">Amount</th>
                <th style="width: 20%;" class="text-right">Jumlah Data</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($summaryRows as $row)
                <tr>
                    <td>{{ $row['label'] ?? '-' }}</td>
                    <td class="text-right">{{ number_format((int) ($row['provided_total'] ?? 0), 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((int) ($row['actual_total'] ?? 0), 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((int) ($row['remaining_total'] ?? 0), 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format((int) ($row['amount_total'] ?? 0), 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((int) ($row['row_count'] ?? 0), 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center">Tidak ada data untuk periode/filter ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Detail Data</div>
    <table class="report">
        <thead>
            <tr>
                <th style="width: 6%;">No</th>
                <th style="width: 12%;">Tanggal</th>
                <th style="width: 20%;">Karyawan</th>
                <th style="width: 16%;">Menu</th>
                <th style="width: 10%;" class="text-center">Schedule</th>
                <th style="width: 9%;" class="text-right">Disediakan</th>
                <th style="width: 9%;" class="text-right">Realisasi</th>
                <th style="width: 9%;" class="text-right">Sisa</th>
                <th style="width: 9%;" class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($orderMeals as $index => $orderMeal)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $orderMeal->meal_date ? \Carbon\Carbon::parse((string) $orderMeal->meal_date)->format('d/m/Y') : '-' }}</td>
                    <td>{{ $orderMeal->user?->name ?? '-' }}</td>
                    <td>{{ $orderMeal->menu_name ?? '-' }}</td>
                    <td class="text-center">{{ $orderMeal->schedule_type ?? '-' }}</td>
                    <td class="text-right">{{ number_format((int) ($orderMeal->quantity ?? 0), 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((int) ($orderMeal->actual_quantity ?? 0), 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format(max(0, (int) ($orderMeal->quantity ?? 0) - (int) ($orderMeal->actual_quantity ?? 0)), 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format((int) ($orderMeal->total_amount ?? 0), 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center">Tidak ada detail data.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">F-OPS-ORDER-MEAL-REPORT</div>
</div>
</body>
</html>
