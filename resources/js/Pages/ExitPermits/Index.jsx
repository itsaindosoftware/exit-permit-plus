import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

const exitTypeLabel = {
    business_trip: 'Perjalanan Dinas',
    sick: 'Sakit',
};

export default function Index({ exitPermits, canCreate }) {
    const totalItems = exitPermits?.total ?? exitPermits?.data?.length ?? 0;
    const approvedItems = exitPermits?.data?.filter((item) => item.status === 'approved').length ?? 0;
    const eligibleItems = exitPermits?.data?.filter((item) => item.eligible_for_meal).length ?? 0;

    const handleDelete = (id) => {
        if (confirm('Hapus data exit permit ini?')) {
            router.delete(route('exit-permits.destroy', id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-bold leading-tight text-slate-800">
                    Exit Permit
                </h2>
            }
        >
            <Head title="Exit Permit" />

            <div className="space-y-6">
                <div className="rounded-2xl border border-cyan-200 bg-gradient-to-r from-cyan-50 via-slate-50 to-white p-6 shadow-sm">
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-700">Operational Request</p>
                            <h3 className="mt-2 text-2xl font-black uppercase tracking-wide text-slate-900">Exit Permit Monitoring</h3>
                            <p className="mt-2 text-sm text-slate-600">
                                Monitor seluruh pengajuan keluar area pabrik untuk kebutuhan operasional, reimbursement, dan approval.
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
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-100">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Karyawan</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Tanggal</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Jenis</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Tujuan</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Meal</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Status</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Tahap Approval</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {exitPermits.data.map((item) => (
                                    <tr key={item.id} className="transition hover:bg-slate-50">
                                        <td className="px-4 py-3 font-medium text-slate-800">{item.employee_name}</td>
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
                                        <td className="px-4 py-3">
                                            <div className="flex gap-2">
                                                <Link
                                                    href={route(
                                                        item.can_submit_approval || item.can_arrange_car || item.can_update_request || item.can_verify_attendance
                                                            ? 'exit-permits.edit'
                                                            : 'exit-permits.show',
                                                        item.id,
                                                    )}
                                                    className="rounded bg-cyan-700 px-3 py-1 text-xs font-semibold text-white transition hover:bg-cyan-600"
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
                                                        className="rounded bg-rose-600 px-3 py-1 text-xs font-semibold text-white transition hover:bg-rose-500"
                                                    >
                                                        Hapus
                                                    </button>
                                                )}
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
