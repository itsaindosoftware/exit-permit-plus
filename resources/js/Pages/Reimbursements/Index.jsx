import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const currencyFormatter = new Intl.NumberFormat('id-ID');

const statusClass = {
    pending_manager: 'bg-amber-100 text-amber-700',
    pending_md: 'bg-cyan-100 text-cyan-700',
    pending_ratna: 'bg-indigo-100 text-indigo-700',
    submitted_to_accounting: 'bg-violet-100 text-violet-700',
    finished: 'bg-emerald-100 text-emerald-700',
    rejected: 'bg-rose-100 text-rose-700',
};

export default function Index({ reimbursements, canCreate, eligibleExitPermits, viewerRole }) {
    const totalItems = reimbursements?.total ?? reimbursements?.data?.length ?? 0;

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-bold leading-tight text-slate-800">
                    Reimbursement
                </h2>
            }
        >
            <Head title="Reimbursement" />

            <div className="space-y-6">
                <div className="rounded-2xl border border-cyan-200 bg-gradient-to-r from-cyan-50 via-white to-slate-50 p-6 shadow-sm">
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-700">Claim Flow</p>
                            <h3 className="mt-2 text-2xl font-black uppercase tracking-wide text-slate-900">Reimbursement Monitoring</h3>
                            <p className="mt-2 text-sm text-slate-600">
                                Alur persetujuan reimbursement: User, Manager, MD, Ratna (submit accounting), lalu finish oleh Accounting.
                            </p>
                        </div>
                        {canCreate && (
                            <Link
                                href={route('reimbursements.create')}
                                className="inline-flex items-center justify-center rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700"
                            >
                                + Ajukan Reimbursement
                            </Link>
                        )}
                    </div>
                </div>

                {viewerRole === 'user' && (eligibleExitPermits?.length ?? 0) === 0 && (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        Belum ada Exit Permit yang lolos jalur reimbursement (approved MD + verifikasi Sisca).
                    </div>
                )}

                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Data</p>
                    <p className="mt-2 text-3xl font-bold text-slate-900">{totalItems}</p>
                </div>

                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-100">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Karyawan</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Exit Permit</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Tanggal</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Nominal</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Status</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Tahap</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Aksi</th>
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
                                                {item.status}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-xs font-semibold text-cyan-700">{item.approval_stage}</td>
                                        <td className="px-4 py-3">
                                            <Link
                                                href={route('reimbursements.edit', item.id)}
                                                className="rounded bg-cyan-700 px-3 py-1 text-xs font-semibold text-white transition hover:bg-cyan-600"
                                            >
                                                {item.can_take_action ? 'Proses' : (item.can_update_request ? 'Edit' : 'Detail')}
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {!reimbursements.data.length && (
                        <div className="px-6 py-10 text-center text-sm text-slate-500">
                            Belum ada data reimbursement.
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
