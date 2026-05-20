<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Exit Permit Form</title>
    <style>
        @page {
            margin: 8mm;
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10.5px;
            color: #111;
            margin: 0;
        }

        .sheet {
            border: 1px solid #111;
            padding: 10px 10px 8px;
        }

        .head {
            position: relative;
            min-height: 36px;
            margin-bottom: 4px;
        }

        .logo-box {
            position: absolute;
            top: 1px;
            left: 0;
            width: 38px;
            height: 34px;
            border: 1px solid #777;
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
            font-size: 8px;
            color: #666;
            line-height: 34px;
        }

        .title {
            text-align: center;
            font-weight: 700;
            font-size: 34px;
            letter-spacing: 0.2px;
            margin: 0;
        }

        .main-grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }

        .main-grid td,
        .main-grid th {
            border: 1px solid #111;
            padding: 3px 4px;
            vertical-align: top;
        }

        .code-col {
            width: 36px;
            text-align: center;
            white-space: nowrap;
        }

        .section-head {
            font-weight: 700;
            font-style: italic;
            background: #f7f7f7;
        }

        .requestor-grid {
            width: 100%;
            border-collapse: collapse;
        }

        .requestor-grid th,
        .requestor-grid td {
            border: 1px solid #111;
            padding: 3px 4px;
            vertical-align: top;
        }

        .requestor-grid th {
            text-align: center;
            font-weight: 700;
            font-size: 9px;
        }

        .requestor-grid td {
            height: 17px;
        }

        .center {
            text-align: center;
        }

        .line {
            display: inline-block;
            border-bottom: 1px solid #111;
            min-height: 12px;
            vertical-align: bottom;
            line-height: 12px;
        }

        .dot-line {
            display: inline-block;
            border-bottom: 1px dotted #111;
            min-height: 12px;
            vertical-align: bottom;
            line-height: 12px;
        }

        .w-40 { width: 40px; }
        .w-50 { width: 50px; }
        .w-70 { width: 70px; }
        .w-80 { width: 80px; }
        .w-90 { width: 90px; }
        .w-100 { width: 100px; }
        .w-120 { width: 120px; }
        .w-130 { width: 130px; }
        .w-140 { width: 140px; }
        .w-150 { width: 150px; }
        .w-160 { width: 160px; }
        .w-180 { width: 180px; }
        .w-200 { width: 200px; }
        .w-220 { width: 220px; }
        .w-260 { width: 260px; }
        .w-300 { width: 300px; }
        .w-360 { width: 360px; }

        .line-row {
            line-height: 1.8;
        }

        .checkbox {
            display: inline-block;
            margin-right: 18px;
            white-space: nowrap;
        }

        .mini-box {
            display: inline-block;
            width: 9px;
            height: 9px;
            border: 1px solid #111;
            vertical-align: middle;
            margin-right: 3px;
            text-align: center;
            font-size: 8px;
            line-height: 8px;
        }

        .sign-grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3px;
        }

        .sign-grid td {
            border: 0;
            width: 50%;
            padding: 0;
            vertical-align: top;
        }

        .sign-cell {
            text-align: center;
            padding-top: 1px;
        }

        .note-row {
            border-top: 1px solid #111;
            border-bottom: 1px solid #111;
            padding: 3px 4px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1px;
        }

        .security-label {
            width: 64%;
        }

        .security-name {
            width: 36%;
        }

        .footer-code {
            text-align: right;
            margin-top: 7px;
            font-size: 10px;
        }
    </style>
</head>
<body>
@php
    $requestors = $exitPermit->requestors ?? collect();
    $rows = max(6, $requestors->count());
    $permitDate = $exitPermit->permit_date ? \Carbon\Carbon::parse($exitPermit->permit_date)->format('d / m / Y') : '';
    $exitType = (string) $exitPermit->exit_type;
    $isPersonal = in_array($exitType, ['personal', 'sick'], true);
    $isCompany = in_array($exitType, ['company', 'business_trip', 'assignment'], true);
    $isSick = $exitType === 'sick';
    $isAssignment = in_array($exitType, ['assignment', 'business_trip'], true);
    $isOther = !$isSick && !$isAssignment;
    $requestDate = $exitPermit->created_at ? \Carbon\Carbon::parse($exitPermit->created_at)->format('d / m / Y') : '';
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
        <div class="title">EXIT PERMIT FORM</div>
    </div>

    <table class="main-grid">
        <tr>
            <td class="code-col">1</td>
            <td class="section-head">For Requestor</td>
        </tr>
        <tr>
            <td class="code-col">1.1</td>
            <td style="padding: 0;">
                <table class="requestor-grid">
                    <thead>
                        <tr>
                            <th style="width: 8%;">NO</th>
                            <th style="width: 24%;">NAME</th>
                            <th style="width: 17%;">EMPLOYEE ID NO.</th>
                            <th style="width: 16%;">POSITION</th>
                            <th style="width: 14%;">DEPARTMENT</th>
                            <th style="width: 21%;">LUNCH BOX REIMBURSEMENT (Y/N)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @for ($i = 0; $i < $rows; $i++)
                            @php $item = $requestors[$i] ?? null; @endphp
                            <tr>
                                <td class="center">{{ $i + 1 }}.</td>
                                <td>{{ $item?->name }}</td>
                                <td>{{ $item?->employee_id }}</td>
                                <td>{{ $item?->position }}</td>
                                <td>{{ $item?->department }}</td>
                                <td class="center">{{ $item?->reimburs_lunch_box }}</td>
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </td>
        </tr>
        <tr>
            <td class="code-col">1.2</td>
            <td>
                <div class="line-row">
                    Permit date
                    <span class="dot-line w-80 center">{{ $permitDate }}</span>
                    &nbsp;&nbsp;Reason :
                    <span class="dot-line w-300">{{ $exitPermit->reason }}</span>
                </div>
                <div class="line-row" style="margin-top: 3px;">
                    <span class="checkbox"><span class="mini-box">{{ $isPersonal ? 'x' : '' }}</span>Personal :</span>
                    <span class="checkbox"><span class="mini-box">{{ $isSick ? 'x' : '' }}</span>Sick</span>
                    <span class="checkbox"><span class="mini-box">{{ $isOther ? 'x' : '' }}</span>Other</span>
                </div>
                <div class="line-row" style="margin-top: 2px;">
                    <span class="checkbox"><span class="mini-box">{{ $isCompany ? 'x' : '' }}</span>Company :</span>
                    <span class="checkbox"><span class="mini-box">{{ $isAssignment ? 'x' : '' }}</span>Assignment,</span>
                    Destination : <span class="dot-line w-200">{{ $exitPermit->destination }}</span>
                    <span style="font-size: 10px;">* (Please attach related document) (Invitation,Email)</span>
                </div>
                <div class="line-row" style="margin-top: 2px;">
                    Exit from factory at <span class="line w-90 center">{{ $exitPermit->start_time ? substr((string) $exitPermit->start_time, 0, 5) : '' }}</span>
                    &nbsp;&nbsp;Planned return time <span class="line w-90 center">{{ $exitPermit->end_time ? substr((string) $exitPermit->end_time, 0, 5) : '' }}</span>
                </div>
            </td>
        </tr>
        <tr>
            <td class="code-col">1.3</td>
            <td>
                Reason details <span class="line w-360">{{ $exitPermit->reason }}</span>
            </td>
        </tr>
        <tr>
            <td class="code-col">1.4</td>
            <td>
                License Plate <span class="line w-100">{{ $exitPermit->vehicle_plate }}</span>
                <span style="display: inline-block; width: 40px;"></span>
                Requester <span class="line w-130"></span>
                Name <span class="line w-150">{{ $exitPermit->user?->name }}</span>
                <span style="display: inline-block; width: 20px;"></span>
                Date <span class="line w-90">{{ $requestDate }}</span>
            </td>
        </tr>
        <tr>
            <td class="code-col">1.5</td>
            <td>
                Permitted by,
                <table class="sign-grid">
                    <tr>
                        <td class="sign-cell">
                            Name <span class="line w-160"></span>
                            <div style="margin-top: 4px;">Department/Section Head</div>
                        </td>
                        <td class="sign-cell">
                            Name <span class="line w-160"></span>
                            <div style="margin-top: 4px;">HR &amp; GA Department</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td colspan="2" class="note-row">
                PLEASE FILL IN PROPERLY, THIS DOCUMENT IS AN ATTACHMENT FOR GASOLINE, TOLL, PARKING &amp; LUNCH REIMBURSEMENT
            </td>
        </tr>
        <tr>
            <td class="code-col">2</td>
            <td class="section-head">Special Fulfillment by Security</td>
        </tr>
        <tr>
            <td class="code-col">2.1</td>
            <td>
                Exit from factory time <span class="line w-90"></span>
                <span style="display: inline-block; width: 150px;"></span>
                Name <span class="line w-90"></span> (Security)
            </td>
        </tr>
        <tr>
            <td class="code-col">2.2</td>
            <td>
                Return to factory time <span class="line w-90"></span>
                <span style="display: inline-block; width: 122px;"></span>
                Name <span class="line w-90"></span> (Security)
            </td>
        </tr>
    </table>

    <div class="footer-code">F-HRM-025-4</div>
</div>
</body>
</html>
