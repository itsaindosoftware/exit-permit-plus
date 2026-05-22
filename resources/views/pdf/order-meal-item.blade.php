<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Meal Detail</title>
    <style>
        @page { margin: 8mm; }
        body { margin: 0; font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #111; }
        .sheet { border: 1px solid #111; padding: 10px 10px 8px; }
        .head { position: relative; min-height: 40px; margin-bottom: 4px; }
        .logo-box { position: absolute; top: 1px; left: 0; width: 42px; height: 22px; border: 1px solid #999; border-radius: 2px; text-align: center; background: #fff; overflow: hidden; }
        .logo-box img { width: 100%; height: 100%; object-fit: contain; display: block; }
        .logo-fallback { line-height: 22px; font-size: 8px; color: #666; }
        .company { text-align: center; line-height: 1.3; font-size: 10px; margin-bottom: 3px; }
        .company .name { font-weight: 700; letter-spacing: 0.2px; }
        .title { text-align: center; font-weight: 700; margin: 2px 0 10px; letter-spacing: 0.4px; font-size: 11px; }
        table.report { width: 100%; border-collapse: collapse; }
        table.report th, table.report td { border: 1px solid #111; padding: 3px 4px; vertical-align: top; }
        table.report th { background: #f4f4f4; font-weight: 700; width: 33%; text-align: left; }
        .footer { border-top: 1px solid #111; margin-top: 10px; padding-top: 2px; text-align: right; font-size: 9px; }
    </style>
</head>
<body>
@php
    $logoPath = public_path('storage/logo-itsa2.png');
    $logoDataUri = file_exists($logoPath)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
        : null;
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
            <div>Tel : 0267-8457184 Fax : 0264-8457187</div>
        </div>
    </div>

    <div class="title">ORDER MEAL DETAIL - {{ strtoupper((string) ($scopeLabel ?? 'General')) }}</div>

    <table class="report">
        <tr><th>Order Meal ID</th><td>#{{ $orderMeal->id }}</td></tr>
        <tr><th>Employee</th><td>{{ $orderMeal->user?->name ?? '-' }}</td></tr>
        <tr><th>Email</th><td>{{ $orderMeal->user?->email ?? '-' }}</td></tr>
        <tr><th>Date</th><td>{{ $orderMeal->meal_date ? \Carbon\Carbon::parse((string) $orderMeal->meal_date)->format('d/m/Y') : '-' }}</td></tr>
        <tr><th>Menu</th><td>{{ $orderMeal->menu_name ?? '-' }}</td></tr>
        <tr><th>Schedule</th><td>{{ $orderMeal->schedule_type ?? '-' }}</td></tr>
        <tr><th>Provided</th><td>{{ number_format((int) ($orderMeal->quantity ?? 0), 0, ',', '.') }}</td></tr>
        <tr><th>Actual</th><td>{{ number_format((int) ($orderMeal->actual_quantity ?? 0), 0, ',', '.') }}</td></tr>
        <tr><th>Remaining</th><td>{{ number_format(max(0, (int) ($orderMeal->quantity ?? 0) - (int) ($orderMeal->actual_quantity ?? 0)), 0, ',', '.') }}</td></tr>
        <tr><th>Amount</th><td>Rp {{ number_format((int) ($orderMeal->total_amount ?? 0), 0, ',', '.') }}</td></tr>
        <tr><th>Status</th><td>{{ $orderMeal->status ?? '-' }}</td></tr>
        <tr><th>Exit Permit</th><td>{{ $orderMeal->exitPermit ? ('#' . $orderMeal->exitPermit->id . ' | ' . (string) $orderMeal->exitPermit->permit_date . ' | ' . (string) $orderMeal->exitPermit->destination) : '-' }}</td></tr>
        <tr><th>Notes</th><td>{{ $orderMeal->notes ?? '-' }}</td></tr>
        <tr><th>Printed At</th><td>{{ $printedAt ? $printedAt->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}</td></tr>
    </table>

    <div class="footer">F-OPS-ORDER-MEAL-DETAIL</div>
</div>
</body>
</html>
