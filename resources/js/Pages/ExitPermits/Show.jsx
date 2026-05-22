import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const exitTypeLabel = {
    business_trip: 'Business Trip',
    sick: 'Sick',
};

const postMdPathLabel = {
    meal: 'Meal',
    reimbursement: 'Reimbursement',
};

function DetailTable({ rows }) {
    return (
        <div className="overflow-x-auto rounded-lg border border-slate-300">
            <table className="min-w-full border-collapse text-xs md:text-sm">
                <thead className="bg-slate-100 text-slate-700">
                    <tr>
                        <th className="w-56 border border-slate-300 px-3 py-2 text-left font-semibold">Field</th>
                        <th className="border border-slate-300 px-3 py-2 text-left font-semibold">Value</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={row.label}>
                            <td className="border border-slate-300 px-3 py-2 font-semibold text-slate-700">{row.label}</td>
                            <td className="border border-slate-300 px-3 py-2 text-slate-800">{row.value ?? '-'}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function Show({ exitPermit, approvalStage }) {
    const mainRows = [
        { label: 'Employee', value: exitPermit.employee_name },
        { label: 'Email', value: exitPermit.employee_email },
        { label: 'Status', value: exitPermit.status_label ?? exitPermit.status?.toUpperCase() },
        { label: 'Permit Date', value: exitPermit.permit_date },
        { label: 'Exit Time', value: exitPermit.start_time },
        { label: 'Return Time', value: exitPermit.end_time },
        { label: 'Exit Type', value: exitTypeLabel[exitPermit.exit_type] ?? exitPermit.exit_type },
        { label: 'Destination', value: exitPermit.destination },
        { label: 'Cost Center', value: exitPermit.cost_center_name ?? '-' },
        { label: 'License Plate (1.4)', value: exitPermit.vehicle_plate ?? 'Not set' },
        { label: 'Driver Name', value: exitPermit.driver_name ?? 'Not set' },
        { label: 'Returned To Office', value: exitPermit.returned_to_office ? 'Yes' : 'No' },
        { label: 'Eligible Meal', value: exitPermit.eligible_for_meal ? 'Yes' : 'No' },
    ];

    const noteRows = [
        { label: 'Reason', value: exitPermit.reason },
        { label: 'Notes', value: exitPermit.notes ?? '-' },
    ];

    const approvalRows = [
        { label: 'Manager Approved By', value: exitPermit.manager_approved_by_name ?? '-' },
        { label: 'Manager Approved At', value: exitPermit.manager_approved_at ?? '-' },
        { label: 'MD Approved By', value: exitPermit.md_approved_by_name ?? '-' },
        { label: 'MD Approved At', value: exitPermit.md_approved_at ?? '-' },
        { label: 'PIC HR', value: exitPermit.hr_approver_name ?? '-' },
        { label: 'HR Verified By', value: exitPermit.hr_verified_by_name ?? '-' },
        { label: 'HR Verified At', value: exitPermit.hr_verified_at ?? '-' },
        { label: 'Attendance Checked By', value: exitPermit.attendance_checked_by_name ?? '-' },
        { label: 'Attendance Checked At', value: exitPermit.attendance_checked_at ?? '-' },
        {
            label: 'Has Valid Check-in',
            value: exitPermit.has_valid_checkin === null ? '-' : (exitPermit.has_valid_checkin ? 'Yes' : 'No'),
        },
        { label: 'Post MD Path', value: postMdPathLabel[exitPermit.post_md_path] ?? '-' },
    ];

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-bold leading-tight text-slate-800">
                    Exit Permit Details
                </h2>
            }
        >
            <Head title="Exit Permit Details" />

            <div className="space-y-6">
                <div className="rounded-2xl border border-cyan-200 bg-gradient-to-r from-cyan-50 via-white to-slate-50 p-5">
                    <p className="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-700">Exit Permit Details</p>
                    <p className="mt-2 text-sm text-slate-700">Summary of request data, approvals, and vehicle information.</p>
                    <p className="mt-3 inline-flex rounded-full bg-slate-900 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-white">
                        {approvalStage}
                    </p>
                </div>

                <div className="space-y-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Main Information</p>
                    <DetailTable rows={mainRows} />
                </div>

                <div className="space-y-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Reasons and Notes</p>
                    <DetailTable rows={noteRows} />
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Requestor Details</p>
                    <div className="mt-3 overflow-x-auto rounded-lg border border-slate-300">
                        <table className="min-w-full border-collapse text-xs md:text-sm">
                            <thead className="bg-slate-100 text-slate-700">
                                <tr>
                                    <th className="border border-slate-300 px-3 py-2 text-left font-semibold">NO</th>
                                    <th className="border border-slate-300 px-3 py-2 text-left font-semibold">NAME</th>
                                    <th className="border border-slate-300 px-3 py-2 text-left font-semibold">EMPLOYEE ID</th>
                                    <th className="border border-slate-300 px-3 py-2 text-left font-semibold">POSITION</th>
                                    <th className="border border-slate-300 px-3 py-2 text-left font-semibold">DEPARTMENT</th>
                                    <th className="border border-slate-300 px-3 py-2 text-left font-semibold">REIMBURS LUNCH BOX (Y/N)</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(exitPermit.requestor_items ?? []).map((row, index) => (
                                    <tr key={`requestor-show-${index}`}>
                                        <td className="border border-slate-300 px-3 py-2">{index + 1}.</td>
                                        <td className="border border-slate-300 px-3 py-2">{row.name || '-'}</td>
                                        <td className="border border-slate-300 px-3 py-2">{row.employee_id || '-'}</td>
                                        <td className="border border-slate-300 px-3 py-2">{row.position || '-'}</td>
                                        <td className="border border-slate-300 px-3 py-2">{row.department || '-'}</td>
                                        <td className="border border-slate-300 px-3 py-2">{row.reimburs_lunch_box || '-'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Photo Attachment</p>
                    {exitPermit.attachment_url ? (
                        <div className="mt-3 space-y-3">
                            <img
                                src={exitPermit.attachment_url}
                                alt={exitPermit.attachment_original_name ?? 'Exit Permit Attachment'}
                                className="max-h-96 w-full rounded-lg border border-slate-200 object-contain"
                            />
                            <a
                                href={exitPermit.attachment_url}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex rounded-md bg-cyan-700 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-cyan-600"
                            >
                                Open Full Size ({exitPermit.attachment_original_name ?? 'Photo'})
                            </a>
                        </div>
                    ) : (
                        <p className="mt-2 text-sm text-slate-600">No attachment.</p>
                    )}
                </div>

                <div className="space-y-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Approval History</p>
                    <DetailTable rows={approvalRows} />
                </div>

                <div className="flex justify-end">
                    <a
                        href={route('exit-permits.print', exitPermit.id)}
                        target="_blank"
                        rel="noreferrer"
                        className="mr-2 rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700"
                    >
                        Print PDF
                    </a>
                    <Link
                        href={route('exit-permits.index')}
                        className="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                    >
                        Back
                    </Link>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
