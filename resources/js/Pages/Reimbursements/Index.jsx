import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

const currencyFormatter = new Intl.NumberFormat('en-US');

const statusClass = {
    pending_manager: 'bg-amber-100 text-amber-700',
    pending_md: 'bg-cyan-100 text-cyan-700',
    pending_ratna: 'bg-indigo-100 text-indigo-700',
    submitted_to_accounting: 'bg-violet-100 text-violet-700',
    finished: 'bg-emerald-100 text-emerald-700',
    rejected: 'bg-rose-100 text-rose-700',
};

const statusLabel = {
    pending_manager: 'Pending Manager',
    pending_md: 'Pending MD',
    pending_ratna: 'Pending Ratna',
    submitted_to_accounting: 'Submitted to Accounting',
    finished: 'Finished',
    rejected: 'Rejected',
};

export default function Index({
    reimbursements,
    canCreateInternal,
    canCreateFromExitPermit,
    eligibleExitPermits,
    viewerRole,
    isRequester = false,
    pageMode = 'personal',
    filters,
    statusOptions = [],
    stageOptions = [],
}) {
    const totalItems = reimbursements?.total ?? reimbursements?.data?.length ?? 0;
    const isApprovalMode = pageMode === 'approval';
    const isHistoryMode = pageMode === 'history';
    const isMdViewer = viewerRole === 'md';
    const isManagerViewer = viewerRole === 'manager';
    const isMdApprovalViewer = isApprovalMode && isMdViewer;
    const [employee, setEmployee] = useState(filters?.employee ?? '');
    const [exitPermit, setExitPermit] = useState(filters?.exit_permit ?? '');
    const [requestDate, setRequestDate] = useState(filters?.date ?? '');
    const [amount, setAmount] = useState(filters?.amount ?? '');
    const [status, setStatus] = useState(filters?.status ?? '');
    const [stage, setStage] = useState(filters?.stage ?? '');
    const firstRender = useRef(true);
    const hasActiveFilters = Boolean(employee || exitPermit || requestDate || amount || status || stage);
    const indexRouteName = isApprovalMode
        ? 'reimbursement-approvals.index'
        : (isHistoryMode ? 'reimbursement-history.index' : 'reimbursements.index');
    const pageTitle = isApprovalMode
        ? 'Reimbursement Approval'
        : (isHistoryMode ? 'Reimbursement History' : 'Reimbursement');
    const heroLabel = isApprovalMode
        ? 'Approval Workflow'
        : (isHistoryMode ? 'Approval Archive' : 'Claim Flow');
    const heroTitle = isApprovalMode
        ? 'Reimbursement Approval Monitoring'
        : (isHistoryMode ? 'Reimbursement Approval History' : 'Reimbursement');
    const heroDescription = isApprovalMode
        ? 'Reimbursement approval flow: User, Manager, MD, Ratna (submit accounting), then finish by Accounting.'
        : (isHistoryMode
            ? 'Archived reimbursement approvals for managerial oversight and reporting.'
            : 'Manage your own reimbursements.');

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }

        const timeoutId = setTimeout(() => {
            router.get(route(indexRouteName), {
                employee: employee || undefined,
                exit_permit: exitPermit || undefined,
                date: requestDate || undefined,
                amount: amount || undefined,
                status: status || undefined,
                stage: stage || undefined,
            }, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 350);

        return () => clearTimeout(timeoutId);
    }, [indexRouteName, employee, exitPermit, requestDate, amount, status, stage]);

    const resetFilters = () => {
        setEmployee('');
        setExitPermit('');
        setRequestDate('');
        setAmount('');
        setStatus('');
        setStage('');

        router.get(route(indexRouteName), {}, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-bold leading-tight text-slate-800">
                    {pageTitle}
                </h2>
            }
        >
            <Head title={pageTitle} />

            <div className="space-y-6">
                <div className="rounded-2xl border border-cyan-200 bg-gradient-to-r from-cyan-50 via-white to-slate-50 p-6 shadow-sm">
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-700">{heroLabel}</p>
                            <h3 className="mt-2 text-2xl font-black uppercase tracking-wide text-slate-900">
                                {heroTitle}
                            </h3>
                            {/* Proses approval dipantau melalui menu Reimbursement Approval. */}
                            <p className="mt-2 text-sm text-slate-600">
                                {heroDescription}
                            </p>
                        </div>
                        {!isApprovalMode && !isHistoryMode && isRequester && (
                            <div className="flex flex-wrap items-center gap-2">
                                <Link
                                    href={`${route('reimbursements.create')}?source=internal`}
                                    className={
                                        `inline-flex items-center justify-center rounded-md px-4 py-2 text-sm font-semibold text-white transition ` +
                                        (canCreateInternal
                                            ? 'bg-slate-900 hover:bg-slate-700'
                                            : 'bg-slate-700 hover:bg-slate-600')
                                    }
                                >
                                    + Create New
                                </Link>
                                <Link
                                    href={`${route('reimbursements.create')}?source=exit_permit`}
                                    className={
                                        `inline-flex items-center justify-center rounded-md px-4 py-2 text-sm font-semibold text-white transition ` +
                                        (canCreateFromExitPermit
                                            ? 'bg-cyan-700 hover:bg-cyan-600'
                                            : 'pointer-events-none bg-cyan-400 opacity-70')
                                    }
                                >
                                    + From Exit Permit
                                </Link>
                            </div>
                        )}
                    </div>
                </div>

                {!isApprovalMode && isRequester && (eligibleExitPermits?.length ?? 0) === 0 && (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        No Exit Permit with status Checked By HR: Sisca is available for reimbursement.
                    </div>
                )}

                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Records</p>
                    <p className="mt-2 text-3xl font-bold text-slate-900">{totalItems}</p>
                </div>

                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
                    <div className="border-b border-slate-200 bg-gradient-to-r from-slate-50 to-cyan-50/40 px-4 py-4">
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <p className="text-sm font-semibold text-slate-900">Reimbursement Search</p>
                                <p className="text-xs text-slate-500">Data is filtered automatically as you type.</p>
                            </div>
                            <div className="flex items-center gap-2">
                                <div className="inline-flex items-center rounded-full border border-cyan-200 bg-cyan-50 px-3 py-1 text-xs font-semibold text-cyan-700">
                                    Auto Filter On
                                </div>
                                <button
                                    type="button"
                                    onClick={resetFilters}
                                    disabled={!hasActiveFilters}
                                    className="rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    Reset
                                </button>
                            </div>
                        </div>

                        <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <div className="space-y-1">
                                <label className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Employee</label>
                                <input
                                    type="text"
                                    value={employee}
                                    onChange={(e) => setEmployee(e.target.value)}
                                    placeholder="Employee name"
                                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700"
                                />
                            </div>
                            <div className="space-y-1">
                                <label className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Exit Permit</label>
                                <input
                                    type="text"
                                    value={exitPermit}
                                    onChange={(e) => setExitPermit(e.target.value)}
                                    placeholder="ID, date, or destination"
                                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700"
                                />
                            </div>
                            <div className="space-y-1">
                                <label className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Date</label>
                                <input
                                    type="date"
                                    value={requestDate}
                                    onChange={(e) => setRequestDate(e.target.value)}
                                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700"
                                />
                            </div>
                            <div className="space-y-1">
                                <label className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Amount</label>
                                <input
                                    type="text"
                                    value={amount}
                                    onChange={(e) => setAmount(e.target.value)}
                                    placeholder="Example: 24000"
                                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700"
                                />
                            </div>
                            <div className="space-y-1">
                                <label className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Status</label>
                                <select
                                    value={status}
                                    onChange={(e) => setStatus(e.target.value)}
                                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700"
                                >
                                    <option value="">All Statuses</option>
                                    {statusOptions.map((option) => (
                                        <option key={option} value={option}>{statusLabel[option] ?? option}</option>
                                    ))}
                                </select>
                            </div>
                            <div className="space-y-1 xl:col-span-3">
                                <label className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Stage</label>
                                <select
                                    value={stage}
                                    onChange={(e) => setStage(e.target.value)}
                                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700"
                                >
                                    <option value="">All Stages</option>
                                    {stageOptions.map((option) => (
                                        <option key={option.value} value={option.value}>{option.label}</option>
                                    ))}
                                </select>
                            </div>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-100">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Employee</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Exit Permit</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Date</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Amount</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Status</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Stage</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {reimbursements.data.map((item) => (
                                    <tr key={item.id} className="transition hover:bg-slate-50">
                                        <td className="px-4 py-3 font-medium text-slate-800">{item.employee_name}</td>
                                        <td className="px-4 py-3 text-slate-700">{item.exit_permit_label}</td>
                                        <td className="px-4 py-3 text-slate-700">{item.request_date}</td>
                                        <td className="px-4 py-3 font-medium text-slate-700">Rp {currencyFormatter.format(item.amount ?? 0)}</td>
                                        <td className="px-4 py-3">
                                            <span
                                                className={
                                                    `inline-flex rounded-full px-2.5 py-1 text-xs font-semibold uppercase ` +
                                                    (statusClass[item.status] ?? 'bg-slate-100 text-slate-700')
                                                }
                                            >
                                                {statusLabel[item.status] ?? item.status}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-xs font-semibold text-cyan-700">{item.approval_stage}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex gap-2">
                                                {isMdApprovalViewer && item.can_take_action && (
                                                    <button
                                                        type="button"
                                                        onClick={() => router.post(route('reimbursements.update', item.id), {
                                                            _method: 'put',
                                                            status: 'approved',
                                                        })}
                                                        className="rounded bg-emerald-600 px-3 py-1 text-xs font-semibold text-white transition hover:bg-emerald-500"
                                                    >
                                                        Approve
                                                    </button>
                                                )}
                                                <Link
                                                    href={route('reimbursements.edit', item.id)}
                                                    className="rounded bg-cyan-700 px-3 py-1 text-xs font-semibold text-white transition hover:bg-cyan-600"
                                                >
                                                    {isApprovalMode && item.can_take_action && (isMdViewer || isManagerViewer)
                                                        ? 'Detail & Approval'
                                                        : (item.can_update_request ? 'Edit' : 'Detail')}
                                                </Link>
                                                <a
                                                    href={route('reimbursements.print', item.id)}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="rounded bg-slate-700 px-3 py-1 text-xs font-semibold text-white transition hover:bg-slate-600"
                                                >
                                                    Print PDF
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {!reimbursements.data.length && (
                        <div className="px-6 py-10 text-center text-sm text-slate-500">
                            No reimbursement data yet.
                        </div>
                    )}

                    {reimbursements.links && reimbursements.links.length > 3 && (
                        <div className="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 px-4 py-4">
                            {reimbursements.links.map((link) => {
                                if (!link.url) {
                                    return (
                                        <span
                                            key={link.label}
                                            className="rounded-md border border-slate-200 px-3 py-1.5 text-xs text-slate-400"
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    );
                                }

                                return (
                                    <Link
                                        key={link.label}
                                        href={link.url}
                                        className={
                                            `rounded-md border px-3 py-1.5 text-xs font-medium transition ` +
                                            (link.active
                                                ? 'border-slate-900 bg-slate-900 text-white'
                                                : 'border-slate-300 bg-white text-slate-600 hover:bg-slate-100')
                                        }
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
