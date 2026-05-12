<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Exit Permit Form</title>
    <style>
        @page {
            margin: 14mm;
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10px;
            color: #111;
            margin: 0;
        }

        .sheet {
            border: 1px solid #111;
            padding: 8px;
        }

        .title {
            text-align: center;
            font-weight: 700;
            font-size: 16px;
            margin: 2px 0 8px;
            letter-spacing: 0.3px;
        }

        .section-title {
            border: 1px solid #111;
            border-left: 0;
            border-right: 0;
            padding: 3px 4px;
            font-weight: 700;
            font-style: italic;
            margin-top: 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #111;
            padding: 3px 4px;
            vertical-align: top;
        }

        th {
            text-align: center;
            font-weight: 700;
            font-size: 9px;
        }

        .center {
            text-align: center;
        }

        .row {
            margin-top: 6px;
        }

        .line {
            display: inline-block;
            border-bottom: 1px solid #111;
            min-height: 12px;
            vertical-align: bottom;
        }

        .w-80 { width: 80px; }
        .w-90 { width: 90px; }
        .w-100 { width: 100px; }
        .w-110 { width: 110px; }
        .w-120 { width: 120px; }
        .w-140 { width: 140px; }
        .w-160 { width: 160px; }
        .w-180 { width: 180px; }
        .w-220 { width: 220px; }

        .checkbox {
            display: inline-block;
            min-width: 56px;
            margin-right: 10px;
        }

        .small-note {
            margin-top: 6px;
            border-top: 1px solid #111;
            border-bottom: 1px solid #111;
            padding: 3px 4px;
            font-weight: 700;
            letter-spacing: 0.1px;
        }

        .sign-grid {
            width: 100%;
            margin-top: 6px;
        }

        .sign-grid td {
            border: none;
            padding: 0;
            vertical-align: top;
        }

        .muted {
            color: #333;
        }

        .right {
            text-align: right;
        }

        .footer-code {
            text-align: right;
            margin-top: 12px;
            font-size: 9px;
        }
    </style>
</head>
<body>
@php
    $requestors = $exitPermit->requestors ?? collect();
    $rows = max(5, $requestors->count());
    $permitDate = $exitPermit->permit_date ? \Carbon\Carbon::parse($exitPermit->permit_date)->format('d / m / Y') : '';
    $isPersonal = $exitPermit->exit_type === 'sick';
    $isCompany = $exitPermit->exit_type === 'business_trip';
@endphp

<div class="sheet">
    <div class="title">EXIT PERMIT FORM</div>

    <div class="section-title">1.&nbsp;&nbsp;For Requestor</div>

    <table>
        <thead>
            <tr>
                <th style="width: 6%;">NO</th>
                <th style="width: 25%;">NAME</th>
                <th style="width: 17%;">EMPLOYEE ID NO.</th>
                <th style="width: 16%;">POSITION</th>
                <th style="width: 16%;">DEPARTMENT</th>
                <th style="width: 20%;">REIMBURS LUNCH BOX (Y/N)</th>
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

    <div class="row">
        <strong>1.2</strong>&nbsp;Request permit date&nbsp;
        <span class="line w-90 center">{{ $permitDate }}</span>
        <span style="margin-left: 14px;"></span>
        Reason&nbsp;
        <span class="line w-220">{{ $exitPermit->reason }}</span>
    </div>

    <div class="row">
        <span class="checkbox">{{ $isPersonal ? '[x]' : '[ ]' }} Personal</span>
        <span class="checkbox">{{ $isCompany ? '[x]' : '[ ]' }} Company</span>
        <span class="checkbox">[ ] Sick</span>
        <span class="checkbox">{{ $isCompany ? '[x]' : '[ ]' }} Assignment</span>
        <span class="checkbox">[ ] Other</span>
    </div>

    <div class="row">
        <strong>1.3</strong>&nbsp;Exit from factory on time&nbsp;
        <span class="line w-80 center">{{ $exitPermit->start_time ? substr((string) $exitPermit->start_time, 0, 5) : '' }}</span>
        <span style="margin-left: 12px;"></span>
        Plan back time&nbsp;
        <span class="line w-80 center">{{ $exitPermit->end_time ? substr((string) $exitPermit->end_time, 0, 5) : '' }}</span>
    </div>

    <div class="row">
        Detail the reasons&nbsp;
        <span class="line w-220">{{ $exitPermit->destination }}</span>
        <span style="margin-left: 8px;"></span>
        <span class="muted">*Please attach related document (Invitation, Email)</span>
    </div>

    <div class="row">
        <strong>1.4</strong>&nbsp;No. Police Car&nbsp;
        <span class="line w-140">{{ $exitPermit->vehicle_plate }}</span>
        <span style="margin-left: 20px;"></span>
        Requestor&nbsp;
        <span class="line w-120"></span>
        Name&nbsp;
        <span class="line w-120">{{ $exitPermit->user?->name }}</span>
    </div>

    <div class="row">
        <strong>1.5</strong>&nbsp;Permitted by,&nbsp;
        <span class="line w-140"></span>
        <span style="margin-left: 26px;"></span>
        Date&nbsp;
        <span class="line w-110"></span>
    </div>

    <table class="sign-grid">
        <tr>
            <td style="width: 50%;" class="center">
                <div>Name <span class="line w-160"></span></div>
                <div style="margin-top: 4px;">Department/Section Head</div>
            </td>
            <td style="width: 50%;" class="center">
                <div>Name <span class="line w-160"></span></div>
                <div style="margin-top: 4px;">HR &amp; GA Department</div>
            </td>
        </tr>
    </table>

    <div class="small-note">
        PLEASE FILL IN PROPERLY, THIS DOCUMENT IS ATTACHMENT FOR GASOLINE, TOL, PARKING &amp; LUNCH REIMBURSEMENT
    </div>

    <div class="section-title">2.&nbsp;&nbsp;Special Fill by Security</div>

    <div class="row">
        <strong>2.1</strong>&nbsp;Exit from factory time&nbsp;
        <span class="line w-90"></span>
        <span style="margin-left: 24px;"></span>
        Name&nbsp;
        <span class="line w-180"></span>
        (Security)
    </div>

    <div class="row">
        <strong>2.2</strong>&nbsp;Comeback to factory time&nbsp;
        <span class="line w-90"></span>
        <span style="margin-left: 24px;"></span>
        Name&nbsp;
        <span class="line w-180"></span>
        (Security)
    </div>

    <div class="footer-code">F-HRM-025-4</div>
</div>
</body>
</html>
