import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

// s
const exitTypeLabel = {
    business_trip: 'Perjalanan Dinas',
    sick: 'Sakit',
};

const monthOptions = [
    { value: '', label: 'Semua Bulan' },
    { value: '1', label: 'Januari' },
    { value: '2', label: 'Februari' },
    { value: '3', label: 'Maret' },
    { value: '4', label: 'April' },
    { value: '5', label: 'Mei' },
    { value: '6', label: 'Juni' },
    { value: '7', label: 'Juli' },
    { value: '8', label: 'Agustus' },
    { value: '9', label: 'September' },
    { value: '10', label: 'Oktober' },
    { value: '11', label: 'November' },
    { value: '12', label: 'Desember' },
];

export default function Index({ exitPermits, canCreate, pageMode = 'personal', filters, exitTypes = [] }) {
    const isApprovalMode = pageMode === 'approval';
    const [submitter, setSubmitter] = useState(filters?.submitter ?? '');
    const [requestor, setRequestor] = useState(filters?.requestor ?? '');
    const [permitDate, setPermitDate] = useState(filters?.date ?? '');
    const [month, setMonth] = useState(filters?.month ?? '');
    const [year, setYear] = useState(filters?.year ?? '');
    const [exitType, setExitType] = useState(filters?.exit_type ?? '');
    const [destination, setDestination] = useState(filters?.destination ?? '');
    const firstRender = useRef(true);
    const hasActiveFilters = Boolean(submitter || requestor || permitDate || month || year || exitType || destination);
    const totalItems = exitPermits?.total ?? exitPermits?.data?.length ?? 0;
    const approvedItems = exitPermits?.data?.filter((item) => item.status === 'approved').length ?? 0;
    const eligibleItems = exitPermits?.data?.filter((item) => item.eligible_for_meal).length ?? 0;
    const indexRouteName = isApprovalMode ? 'exit-permit-approvals.index' : 'exit-permits.index';

    const handleDelete = (id) => {
        if (confirm('Hapus data exit permit ini?')) {
            router.delete(route('exit-permits.destroy', id));
        }
    };

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }

        const timeoutId = setTimeout(() => {
            router.get(route(indexRouteName), {
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
    }, [indexRouteName, submitter, requestor, permitDate, month, year, exitType, destination]);

    const resetFilters = () => {
        setSubmitter('');
        setRequestor('');
        setPermitDate('');
        setMonth('');
        setYear('');
        setExitType('');
        setDestination('');

        router.get(route(indexRouteName), {}, {
            preserveState: true,
            replace: true,
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-bold leading-tight text-slate-800">
                    {isApprovalMode ? 'Exit Permit Approval' : 'Exit Permit'}
                </h2>
            }
        >
            <Head title={isApprovalMode ? 'Exit Permit Approval' : 'Exit Permit'} />

            <div className="space-y-6">
                <div className="rounded-2xl border border-cyan-200 bg-gradient-to-r from-cyan-50 via-slate-50 to-white p-6 shadow-sm">
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-700">
                                {isApprovalMode ? 'Approval Workflow' : 'Personal Request'}
                            </p>
                            <h3 className="mt-2 text-2xl font-black uppercase tracking-wide text-slate-900">
                                {isApprovalMode ? 'Exit Permit Approval Monitoring' : 'Exit Permit'}
                            </h3>
                            <p className="mt-2 text-sm text-slate-600">
                                {isApprovalMode
                                    ? 'Menu khusus approver untuk proses review, approval, dan verifikasi absensi pengajuan Exit Permit.'
                                    : 'Menu khusus user untuk membuat dan memantau pengajuan Exit Permit.'}
                            </p>
                        </div>
                        {canCreate && (
                            <Link
                                href={route('exit-permits.create')}
                                className="inline-flex items-center justify-center rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700"
                            >
                                + Tambah Exit Permit
                            </Link>
                        )}
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Data</p>
                        <p className="mt-2 text-3xl font-bold text-slate-900">{totalItems}</p>
                    </div>
                    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Approved</p>
                        <p className="mt-2 text-3xl font-bold text-emerald-700">{approvedItems}</p>
                    </div>
                    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Eligible Meal</p>
                        <p className="mt-2 text-3xl font-bold text-cyan-700">{eligibleItems}</p>
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
                    <div className="border-b border-slate-200 bg-gradient-to-r from-slate-50 to-cyan-50/40 px-4 py-4">
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <p className="text-sm font-semibold text-slate-900">Pencarian Exit Permit</p>
                                <p className="text-xs text-slate-500">Data terfilter otomatis saat input berubah.</p>
                            </div>
                            <div className="flex items-center gap-2">
                                <div className="inline-flex items-center rounded-full border border-cyan-200 bg-cyan-50 px-3 py-1 text-xs font-semibold text-cyan-700">
                                    Auto Filter Aktif
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
                                <label className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Pengaju</label>
                                <input
                                    type="text"
                                    value={submitter}
                                    onChange={(e) => setSubmitter(e.target.value)}
                                    placeholder="Nama pengaju"
                                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700"
                                />
                            </div>
                            <div className="space-y-1">
                                <label className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Requestor</label>
                                <input
                                    type="text"
                                    value={requestor}
                                    onChange={(e) => setRequestor(e.target.value)}
                                    placeholder="Nama requestor"
                                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700"
                                />
                            </div>
                            <div className="space-y-1">
                                <label className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Tanggal</label>
                                <input
                                    type="date"
                                    value={permitDate}
                                    onChange={(e) => setPermitDate(e.target.value)}
                                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700"
                                />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1">
                                    <label className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Bulan</label>
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
                                    <label className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Tahun</label>
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
                                <label className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Jenis</label>
                                <select
                                    value={exitType}
                                    onChange={(e) => setExitType(e.target.value)}
                                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700"
                                >
                                    <option value="">Semua Jenis</option>
                                    {exitTypes.map((type) => (
                                        <option key={type} value={type}>{exitTypeLabel[type] ?? type}</option>
                                    ))}
                                </select>
                            </div>
                            <div className="space-y-1 xl:col-span-3">
                                <label className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Tujuan</label>
                                <input
                                    type="text"
                                    value={destination}
                                    onChange={(e) => setDestination(e.target.value)}
                                    placeholder="Ketik tujuan perjalanan"
                                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700"
                                />
                            </div>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-100">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">User Pengaju</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Requestor</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Tanggal</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Jenis</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Tujuan</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Meal</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Status</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Tahap Approval</th>
                                    <th className="w-52 px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {exitPermits.data.map((item) => (
                                    <tr key={item.id} className="transition hover:bg-slate-50">
                                        <td className="px-4 py-3 font-medium text-slate-800">{item.employee_name}</td>
                                        <td className="px-4 py-3 text-slate-700">
                                            {item.requestor_names?.length
                                                ? item.requestor_names.join(', ')
                                                : '-'}
                                        </td>
                                        <td className="px-4 py-3 text-slate-700">{item.permit_date}</td>
                                        <td className="px-4 py-3">{exitTypeLabel[item.exit_type] ?? item.exit_type}</td>
                                        <td className="px-4 py-3">
                                            <div className="font-medium text-slate-800">{item.destination}</div>
                                            {item.vehicle_plate && (
                                                <div className="text-xs text-slate-500">No. Polisi: {item.vehicle_plate}</div>
                                            )}
                                            {item.driver_name && (
                                                <div className="text-xs text-slate-500">Supir: {item.driver_name}</div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span
                                                className={
                                                    `inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ` +
                                                    (item.eligible_for_meal
                                                        ? 'bg-emerald-100 text-emerald-700'
                                                        : 'bg-slate-100 text-slate-600')
                                                }
                                            >
                                                {item.eligible_for_meal ? 'Eligible' : 'No'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span
                                                className={
                                                    `inline-flex rounded-full px-2.5 py-1 text-xs font-semibold uppercase ` +
                                                    (item.is_attendance_checked
                                                        ? 'bg-cyan-100 text-cyan-700'
                                                        : item.status === 'approved'
                                                        ? 'bg-emerald-100 text-emerald-700'
                                                        : item.status === 'rejected'
                                                            ? 'bg-rose-100 text-rose-700'
                                                            : 'bg-amber-100 text-amber-700')
                                                }
                                            >
                                                {item.status_label ?? item.status}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-xs font-semibold text-cyan-700">{item.approval_stage}</td>
                                        <td className="w-52 px-4 py-3 align-middle">
                                            <div className="flex flex-wrap items-center content-center gap-3">
                                                <Link
                                                    href={route(
                                                        item.can_submit_approval || item.can_arrange_car || item.can_update_request || item.can_verify_attendance
                                                            ? 'exit-permits.edit'
                                                            : 'exit-permits.show',
                                                        item.id,
                                                    )}
                                                    className={
                                                        `inline-flex w-28 items-center justify-center rounded bg-cyan-700 px-3 text-xs font-semibold text-white transition hover:bg-cyan-600 ` +
                                                        (item.can_submit_approval || item.can_verify_attendance ? 'h-19' : 'h-8')
                                                    }
                                                >
                                                    {item.can_submit_approval
                                                        ? 'Detail & Approval'
                                                        : item.can_arrange_car
                                                            ? 'Arrange Car'
                                                            : item.can_verify_attendance
                                                                ? 'Verifikasi Absensi'
                                                            : (item.can_update_request ? 'Edit' : 'Detail')}
                                                </Link>
                                                {item.can_delete && (
                                                    <button
                                                        type="button"
                                                        onClick={() => handleDelete(item.id)}
                                                        className="inline-flex h-8 w-28 items-center justify-center rounded bg-rose-600 px-3 text-xs font-semibold text-white transition hover:bg-rose-500"
                                                    >
                                                        Hapus
                                                    </button>
                                                )}
                                                {/* dicomment dulu */}
                                                <a
                                                    href={route('exit-permits.print', item.id)}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="inline-flex h-8 w-28 items-center justify-center rounded bg-slate-700 px-3 text-xs font-semibold text-white transition hover:bg-slate-600"
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

                    {!exitPermits.data.length && (
                        <div className="px-6 py-10 text-center text-sm text-slate-500">
                            Belum ada data exit permit.
                        </div>
                    )}

                    {exitPermits.links && exitPermits.links.length > 3 && (
                        <div className="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 px-4 py-4">
                            {exitPermits.links.map((link) => {
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
