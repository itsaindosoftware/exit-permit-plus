import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Index({ items }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-bold leading-tight text-slate-800">
                    List Exit Permit
                </h2>
            }
        >
            <Head title="List Exit Permit" />

            <div className="space-y-6">
                <div className="rounded-2xl border border-cyan-200 bg-gradient-to-r from-cyan-50 via-white to-slate-50 p-6 shadow-sm">
                    <p className="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-700">HR Monitoring</p>
                    <h3 className="mt-2 text-2xl font-black uppercase tracking-wide text-slate-900">Semua Pengajuan Exit Permit</h3>
                    <p className="mt-2 text-sm text-slate-600">
                        Menampilkan seluruh user beserta requestor yang pernah mengajukan exit permit, termasuk status dan tahap approval terbaru.
                    </p>
                </div>

                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-100">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">ID</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">User Pengaju</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Requestor</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Tanggal & Jam</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Tujuan</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Status</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Tahapan</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Aksi</th>
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
                            Belum ada data exit permit.
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
