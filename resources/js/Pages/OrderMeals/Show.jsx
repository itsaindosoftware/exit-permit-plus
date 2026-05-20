import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const currencyFormatter = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

function translateConversionNotes(text) {
    if (!text) {
        return text;
    }

    const translatedFromLegacy = String(text).replace(
        /\[AUTO-CONVERT\s+EP#(\d+)\s*-\s*(\d+)\]/g,
        '[Lunch box allowance converted to reimbursement for employee EP#$1: -$2 packs]',
    );

    const translatedFromLegacyLabel = translatedFromLegacy.replace(
        /\[Konversi\s+Lunch\s+Box\s+EP#(\d+):\s*-(\d+)\s*paket\]/g,
        '[Lunch box allowance converted to reimbursement for employee EP#$1: -$2 packs]',
    );

    return translatedFromLegacyLabel.replace(
        /\[Pengalihan\s+jatah\s+lunch\s+box\s+ke\s+uang\s+reimbursement\s+karyawan\s+EP#(\d+):\s*-(\d+)\s*paket\]/g,
        '[Lunch box allowance converted to reimbursement for employee EP#$1: -$2 packs]',
    );
}

function DetailTable({ rows, headerClass = 'bg-slate-100 text-slate-700', borderClass = 'border-slate-300' }) {
    return (
        <div className={`overflow-x-auto rounded-lg border ${borderClass}`}>
            <table className="min-w-full border-collapse text-xs md:text-sm">
                <thead className={headerClass}>
                    <tr>
                        <th className={`w-56 border ${borderClass} px-3 py-2 text-left font-semibold`}>Field</th>
                        <th className={`border ${borderClass} px-3 py-2 text-left font-semibold`}>Value</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={row.label}>
                            <td className={`border ${borderClass} px-3 py-2 font-semibold text-slate-700`}>{row.label}</td>
                            <td className={`border ${borderClass} px-3 py-2 text-slate-800`}>{row.value ?? '-'}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function Show({ mode, orderMeal, exitPermit, indexRouteName, editRouteName }) {
    const isExitPermitMode = mode === 'exit_permit';
    const readableNotes = translateConversionNotes(orderMeal.notes);

    const mainRows = [
        { label: 'Employee', value: orderMeal.employee_name || '-' },
        { label: 'Email', value: orderMeal.employee_email || '-' },
        { label: 'Meal Date', value: orderMeal.meal_date || '-' },
        { label: 'Menu', value: orderMeal.menu_name || '-' },
        { label: 'Schedule', value: orderMeal.schedule_type || '-' },
        { label: 'Status', value: orderMeal.status || '-' },
        ...(!isExitPermitMode
            ? [
                { label: 'Day Shift', value: orderMeal.day_shift_qty ?? 0 },
                { label: 'Overtime Day Shift', value: orderMeal.overtime_day_shift_qty ?? 0 },
                { label: 'Night Shift', value: orderMeal.night_shift_qty ?? 0 },
                { label: 'Overtime Night Shift', value: orderMeal.overtime_night_shift_qty ?? 0 },
            ]
            : []),
        { label: 'Provided Packs', value: orderMeal.quantity ?? 0 },
        { label: 'Actual', value: orderMeal.actual_quantity ?? 0 },
        { label: 'Remaining', value: orderMeal.remaining_quantity ?? 0 },
        ...(!isExitPermitMode
            ? [
                { label: 'Amount / Portion', value: currencyFormatter.format(orderMeal.meal_unit_price ?? 0) },
                { label: 'Local Tax', value: `${orderMeal.local_tax_rate ?? 0}%` },
                { label: 'Service Tax', value: `${orderMeal.service_tax_rate ?? 0}%` },
                { label: 'Subtotal', value: currencyFormatter.format(orderMeal.subtotal_amount ?? 0) },
                { label: 'Nominal Local Tax', value: currencyFormatter.format(orderMeal.local_tax_amount ?? 0) },
                { label: 'Nominal Service Tax', value: currencyFormatter.format(orderMeal.service_tax_amount ?? 0) },
                { label: 'Grand Total', value: currencyFormatter.format(orderMeal.total_amount ?? 0) },
            ]
            : []),
    ];

    const exitPermitRows = exitPermit
        ? [
            { label: 'Exit Permit ID', value: `#${exitPermit.id}` },
            { label: 'Permit Date', value: exitPermit.permit_date || '-' },
            { label: 'Destination', value: exitPermit.destination || '-' },
            { label: 'Attendance Verified', value: exitPermit.attendance_checked_at || '-' },
            { label: 'Requester', value: exitPermit.owner_name || '-' },
            { label: 'Requester Email', value: exitPermit.owner_email || '-' },
        ]
        : [];

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-bold leading-tight text-slate-800">
                    {isExitPermitMode ? 'Detail Order Meal Exit Permit' : 'Detail Order Meal'}
                </h2>
            }
        >
            <Head title={isExitPermitMode ? 'Detail Order Meal Exit Permit' : 'Detail Order Meal'} />

            <div className="space-y-6">
                <div className="space-y-3 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Main Information</p>
                    <DetailTable rows={mainRows} />

                    {readableNotes && (
                        <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                            <p className="font-semibold text-slate-900">Notes</p>
                            <p className="mt-1 whitespace-pre-line">{readableNotes}</p>
                        </div>
                    )}
                </div>

                {isExitPermitMode && exitPermit && (
                    <div className="space-y-3 rounded-2xl border border-cyan-200 bg-cyan-50 p-6 shadow-sm">
                        <p className="text-sm font-semibold text-cyan-900">Exit Permit Reference</p>
                        <DetailTable
                            rows={exitPermitRows}
                            headerClass="bg-cyan-100 text-cyan-900"
                            borderClass="border-cyan-200"
                        />

                        <div className="overflow-x-auto rounded-md border border-cyan-200 bg-white">
                            <table className="min-w-full border-collapse text-xs">
                                <thead className="bg-cyan-100 text-cyan-900">
                                    <tr>
                                        <th className="border border-cyan-200 px-2 py-1 text-left">No</th>
                                        <th className="border border-cyan-200 px-2 py-1 text-left">Name</th>
                                        <th className="border border-cyan-200 px-2 py-1 text-left">Employee ID</th>
                                        <th className="border border-cyan-200 px-2 py-1 text-left">Position</th>
                                        <th className="border border-cyan-200 px-2 py-1 text-left">Department</th>
                                        <th className="border border-cyan-200 px-2 py-1 text-left">Lunch Box</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(exitPermit.requestors ?? []).map((requestor) => (
                                        <tr key={`show-requestor-${exitPermit.id}-${requestor.row_number}`}>
                                            <td className="border border-cyan-200 px-2 py-1">{requestor.row_number}</td>
                                            <td className="border border-cyan-200 px-2 py-1">{requestor.name || '-'}</td>
                                            <td className="border border-cyan-200 px-2 py-1">{requestor.employee_id || '-'}</td>
                                            <td className="border border-cyan-200 px-2 py-1">{requestor.position || '-'}</td>
                                            <td className="border border-cyan-200 px-2 py-1">{requestor.department || '-'}</td>
                                            <td className="border border-cyan-200 px-2 py-1">{requestor.reimburs_lunch_box || '-'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Link
                        href={route(indexRouteName)}
                        className="rounded-md border border-slate-300 px-4 py-2 text-center text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                    >
                        Back
                    </Link>
                    <Link
                        href={route(editRouteName, orderMeal.id)}
                        className="rounded-md bg-slate-900 px-4 py-2 text-center text-sm font-semibold text-white transition hover:bg-slate-700"
                    >
                        Edit
                    </Link>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
