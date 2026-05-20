import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

const exitTypeLabel = {
    business_trip: 'Business Trip',
    sick: 'Sick',
};

const monthOptions = [
    { value: '', label: 'All Months' },
    { value: '1', label: 'January' },
    { value: '2', label: 'February' },
    { value: '3', label: 'March' },
    { value: '4', label: 'April' },
    { value: '5', label: 'May' },
    { value: '6', label: 'June' },
    { value: '7', label: 'July' },
    { value: '8', label: 'August' },
    { value: '9', label: 'September' },
    { value: '10', label: 'October' },
    { value: '11', label: 'November' },
    { value: '12', label: 'December' },
];

export default function Index({ items, filters, exitTypes = [] }) {
    const [submitter, setSubmitter] = useState(filters?.submitter ?? '');
    const [requestor, setRequestor] = useState(filters?.requestor ?? '');
    const [permitDate, setPermitDate] = useState(filters?.date ?? '');
    const [month, setMonth] = useState(filters?.month ?? '');
    const [year, setYear] = useState(filters?.year ?? '');
    const [exitType, setExitType] = useState(filters?.exit_type ?? '');
    const [destination, setDestination] = useState(filters?.destination ?? '');
    const firstRender = useRef(true);
    const hasActiveFilters = Boolean(submitter || requestor || permitDate || month || year || exitType || destination);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }

        const timeoutId = setTimeout(() => {
            router.get(route('exit-permit-list.index'), {
                submitter: submitter || undefined,
                requestor: requestor || undefined,
                date: permitDate || undefined,
                month: month || undefined,
                year: year || undefined,
                exit_type: exitType || undefined,
                destination: destination || undefined,
            }, {
                preserveState: true,
                replace: true,
                preserveScroll: true,
            });
        }, 350);

        return () => clearTimeout(timeoutId);
    }, [submitter, requestor, permitDate, month, year, exitType, destination]);

    const resetFilters = () => {
        setSubmitter('');
        setRequestor('');
        setPermitDate('');
        setMonth('');
        setYear('');
        setExitType('');
        setDestination('');

        router.get(route('exit-permit-list.index'), {}, {
            preserveState: true,
            replace: true,
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-bold leading-tight text-slate-800">
                    Exit Permit List
                </h2>
            }
        >
            <Head title="Exit Permit List" />

            <div className="space-y-6">
                <div className="rounded-2xl border border-cyan-200 bg-gradient-to-r from-cyan-50 via-white to-slate-50 p-6 shadow-sm">
                    <p className="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-700">HR Monitoring</p>
                    <h3 className="mt-2 text-2xl font-black uppercase tracking-wide text-slate-900">All Exit Permit Requests</h3>
                    <p className="mt-2 text-sm text-slate-600">
                        Showing all users and requestors who have submitted exit permits, including the latest status and approval stage.
                    </p>
                </div>

                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
                    <div className="border-b border-slate-200 bg-gradient-to-r from-slate-50 to-cyan-50/40 px-4 py-4">
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <p className="text-sm font-semibold text-slate-900">Exit Permit Search</p>
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
                                <label className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Submitter</label>
                                <input
                                    type="text"
                                    value={submitter}
                                    onChange={(e) => setSubmitter(e.target.value)}
                                    placeholder="Submitter name"
                                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700"
                                />
                            </div>
                            <div className="space-y-1">
                                <label className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Requester</label>
                                <input
                                    type="text"
                                    value={requestor}
                                    onChange={(e) => setRequestor(e.target.value)}
                                    placeholder="Requester name"
                                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700"
                                />
                            </div>
                            <div className="space-y-1">
                                <label className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Date</label>
                                <input
                                    type="date"
                                    value={permitDate}
                                    onChange={(e) => setPermitDate(e.target.value)}
                                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700"
                                />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1">
                                    <label className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Month</label>
                                    <select
                                        value={month}
                                        onChange={(e) => setMonth(e.target.value)}
                                        className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700"
                                    >
                                        {monthOptions.map((option) => (
                                            <option key={option.value || 'all-month'} value={option.value}>{option.label}</option>
                                        ))}
                                    </select>
                                </div>
                                <div className="space-y-1">
                                    <label className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Year</label>
                                    <input
                                        type="number"
                                        min="1900"
                                        max="3000"
                                        value={year}
                                        onChange={(e) => setYear(e.target.value)}
                                        placeholder="2026"
                                        className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700"
                                    />
                                </div>
                            </div>
                            <div className="space-y-1">
                                <label className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Type</label>
                                <select
                                    value={exitType}
                                    onChange={(e) => setExitType(e.target.value)}
                                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700"
                                >
                                    <option value="">All Types</option>
                                    {exitTypes.map((type) => (
                                        <option key={type} value={type}>{exitTypeLabel[type] ?? type}</option>
                                    ))}
                                </select>
                            </div>
                            <div className="space-y-1 xl:col-span-3">
                                <label className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Destination</label>
                                <input
                                    type="text"
                                    value={destination}
                                    onChange={(e) => setDestination(e.target.value)}
                                    placeholder="Type destination"
                                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700"
                                />
                            </div>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-100">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">ID</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Submitter</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Requestor</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Date & Time</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Type</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Destination</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Status</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Stage</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {items.data.map((item) => (
                                    <tr key={item.id} className="align-top transition hover:bg-slate-50">
                                        <td className="px-4 py-3 font-semibold text-slate-800">#{item.id}</td>
                                        <td className="px-4 py-3 text-slate-800">{item.submitter_name || '-'}</td>
                                        <td className="px-4 py-3 text-slate-700">
                                            {item.requestors.length === 0 ? (
                                                <span>-</span>
                                            ) : (
                                                <div className="space-y-1">
                                                    {item.requestors.map((requestor, index) => (
                                                        <p key={`requestor-${item.id}-${index}`}>
                                                            <span className="font-semibold text-slate-800">{requestor.name || '-'}</span>
                                                            <span className="text-xs text-slate-500"> ({requestor.employee_id || '-'} | {requestor.department || '-'})</span>
                                                        </p>
                                                    ))}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-slate-700">
                                            <p>{item.permit_date || '-'}</p>
                                            <p className="text-xs text-slate-500">{item.start_time || '-'} - {item.end_time || '-'}</p>
                                        </td>
                                        <td className="px-4 py-3 text-slate-700">{exitTypeLabel[item.exit_type] ?? item.exit_type ?? '-'}</td>
                                        <td className="px-4 py-3 text-slate-700">{item.destination || '-'}</td>
                                        <td className="px-4 py-3">
                                            <span
                                                className={
                                                    `inline-flex rounded-full px-2.5 py-1 text-xs font-semibold uppercase ` +
                                                    (item.status === 'approved'
                                                        ? 'bg-emerald-100 text-emerald-700'
                                                        : item.status === 'rejected'
                                                            ? 'bg-rose-100 text-rose-700'
                                                            : 'bg-amber-100 text-amber-700')
                                                }
                                            >
                                                {item.status_label || item.status}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-xs font-semibold text-cyan-700">{item.approval_stage || '-'}</td>
                                        <td className="px-4 py-3">
                                            <Link
                                                href={route('exit-permits.show', item.id)}
                                                className="rounded bg-cyan-700 px-3 py-1 text-xs font-semibold text-white transition hover:bg-cyan-600"
                                            >
                                                Detail
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {!items.data.length && (
                        <div className="px-6 py-10 text-center text-sm text-slate-500">
                            No exit permit data yet.
                        </div>
                    )}

                    {items.links && items.links.length > 3 && (
                        <div className="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 px-4 py-4">
                            {items.links.map((link) => {
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
