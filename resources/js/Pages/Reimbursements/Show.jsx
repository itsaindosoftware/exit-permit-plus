import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const currencyFormatter = new Intl.NumberFormat('en-US');

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
                            <td className="border border-slate-300 px-3 py-2 text-slate-800 whitespace-pre-line">{row.value ?? '-'}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function Show({ reimbursement, approvalStage }) {
    const mainRows = [
        { label: 'Employee', value: reimbursement.employee_name },
        { label: 'Email', value: reimbursement.employee_email },
        { label: 'Status', value: reimbursement.status_label ?? reimbursement.status?.toUpperCase() },
        { label: 'Payment Date', value: reimbursement.request_date },
        { label: 'Paid To', value: reimbursement.paid_to },
        { label: 'Expense Type', value: reimbursement.expense_type },
        { label: 'Purpose', value: reimbursement.purpose },
        { label: 'Additional Notes', value: reimbursement.description ?? '-' },
    ];

    const amountRows = [
        { label: 'Meal Order Cost', value: `Rp ${currencyFormatter.format(reimbursement.amount_order_meal ?? 0)}` },
        { label: 'Fuel Cost', value: `Rp ${currencyFormatter.format(reimbursement.amount_fuel ?? 0)}` },
        { label: 'Toll Cost', value: `Rp ${currencyFormatter.format(reimbursement.amount_toll ?? 0)}` },
        { label: 'Amount', value: `Rp ${currencyFormatter.format(reimbursement.amount ?? 0)}` },
        { label: 'Amount in Words', value: reimbursement.amount_in_words ?? '-' },
        { label: 'Cost Center (Department)', value: reimbursement.cost_center_name ?? '-' },
    ];

    const referenceRows = [
        { label: 'Exit Permit', value: reimbursement.exit_permit_label ?? '-' },
        { label: 'Reference Doc', value: reimbursement.ref_document ?? '-' },
    ];

    const approvalRows = [
        { label: 'Manager Approved By', value: reimbursement.manager_approved_by_name ?? '-' },
        { label: 'Manager Approved At', value: reimbursement.manager_approved_at ?? '-' },
        { label: 'MD Approved By', value: reimbursement.md_approved_by_name ?? '-' },
        { label: 'MD Approved At', value: reimbursement.md_approved_at ?? '-' },
        { label: 'Ratna Submitted By', value: reimbursement.ratna_submitted_by_name ?? '-' },
        { label: 'Ratna Submitted At', value: reimbursement.ratna_submitted_at ?? '-' },
        { label: 'Accounting Processed By', value: reimbursement.accounting_processed_by_name ?? '-' },
        { label: 'Accounting Processed At', value: reimbursement.accounting_processed_at ?? '-' },
    ];

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-bold leading-tight text-slate-800">
                    Reimbursement Details
                </h2>
            }
        >
            <Head title="Reimbursement Details" />

            <div className="space-y-6">
                <div className="rounded-2xl border border-cyan-200 bg-gradient-to-r from-cyan-50 via-white to-slate-50 p-5">
                    <p className="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-700">Reimbursement Details</p>
                    <p className="mt-2 text-sm text-slate-700">Summary of reimbursement request data, amounts, documents, and approval flow.</p>
                    <p className="mt-3 inline-flex rounded-full bg-slate-900 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-white">
                        {approvalStage}
                    </p>
                </div>

                <div className="space-y-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Main Information</p>
                    <DetailTable rows={mainRows} />
                </div>

                <div className="space-y-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Amount Breakdown</p>
                    <DetailTable rows={amountRows} />
                </div>

                <div className="space-y-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Reference Information</p>
                    <DetailTable rows={referenceRows} />
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Reference Docs / Attachments</p>
                    <div className="mt-3 overflow-x-auto rounded-lg border border-slate-300">
                        <table className="min-w-full border-collapse text-xs md:text-sm">
                            <thead className="bg-slate-100 text-slate-700">
                                <tr>
                                    <th className="border border-slate-300 px-3 py-2 text-left font-semibold">NO</th>
                                    <th className="border border-slate-300 px-3 py-2 text-left font-semibold">REFERENCE DOC</th>
                                    <th className="border border-slate-300 px-3 py-2 text-left font-semibold">ATTACHMENT</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(reimbursement.documents ?? []).length > 0 ? (
                                    reimbursement.documents.map((doc, index) => (
                                        <tr key={`doc-show-${doc.id ?? index}`}>
                                            <td className="border border-slate-300 px-3 py-2">{index + 1}.</td>
                                            <td className="border border-slate-300 px-3 py-2">{doc.ref_document || '-'}</td>
                                            <td className="border border-slate-300 px-3 py-2">
                                                {doc.attachment_original_name && doc.attachment_url ? (
                                                    <a
                                                        href={doc.attachment_url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="font-semibold text-cyan-700 underline"
                                                    >
                                                        {doc.attachment_original_name}
                                                    </a>
                                                ) : (
                                                    '-'
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td className="border border-slate-300 px-3 py-2 text-center text-slate-500" colSpan={3}>
                                            No documents.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="space-y-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Approval History</p>
                    <DetailTable rows={approvalRows} />
                </div>

                <div className="flex justify-end">
                    <a
                        href={route('reimbursements.print', reimbursement.id)}
                        target="_blank"
                        rel="noreferrer"
                        className="mr-2 rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700"
                    >
                        Print PDF
                    </a>
                    <Link
                        href={route('reimbursements.index')}
                        className="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                    >
                        Back
                    </Link>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
