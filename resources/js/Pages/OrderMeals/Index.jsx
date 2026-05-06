import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

function NotEatenChart({ title, points }) {
    const maxValue = Math.max(1, ...(points ?? []).map((item) => item.remaining ?? 0));

    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p className="text-sm font-semibold text-slate-900">{title}</p>
            <p className="mt-1 text-xs text-slate-500">Order meal yang enggak makan</p>

            {!points?.length && (
                <div className="mt-4 rounded-lg border border-dashed border-slate-300 px-3 py-8 text-center text-xs text-slate-500">
                    Belum ada data.
                </div>
            )}

            {!!points?.length && (
                <div className="mt-4 space-y-2">
                    {points.map((item) => (
                        <div key={item.label} className="space-y-1">
                            <div className="flex items-center justify-between text-xs">
                                <span className="font-medium text-slate-600">{item.label}</span>
                                <span className="font-semibold text-rose-700">{item.remaining}</span>
                            </div>
                            <div className="h-2 rounded-full bg-slate-100">
                                <div
                                    className="h-2 rounded-full bg-rose-500"
                                    style={{ width: `${Math.max(6, Math.round(((item.remaining ?? 0) / maxValue) * 100))}%` }}
                                />
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

export default function Index({ orderMeals, summary, notEatenCharts, mode, createRouteName, showRouteName, editRouteName, destroyRouteName }) {
    const isExitPermitMode = mode === 'exit_permit';

    const todayLabel = new Intl.DateTimeFormat('id-ID', {
        weekday: 'long',
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    }).format(new Date());

    const totalRows = orderMeals?.total ?? orderMeals?.data?.length ?? 0;

    const handleDelete = (id) => {
        if (confirm('Hapus data order meal ini?')) {
            router.delete(route(destroyRouteName, id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-bold leading-tight text-slate-800">
                    {isExitPermitMode ? 'Order Meal Exit Permit' : 'Order Meal Umum'}
                </h2>
            }
        >
            <Head title={isExitPermitMode ? 'Order Meal Exit Permit' : 'Order Meal Umum'} />

            <div className="space-y-6">
                <div className="relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="pointer-events-none absolute -right-16 -top-16 h-48 w-48 rounded-full bg-cyan-100/50" />
                    <div className="pointer-events-none absolute -bottom-20 -left-12 h-52 w-52 rounded-full bg-emerald-100/40" />
                    <div className="relative flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-700">Meal Operations</p>
                            <h3 className="mt-2 text-3xl font-black tracking-tight text-slate-900">
                                {isExitPermitMode ? 'Order Meal Exit Permit' : 'Order Meal Umum'}
                            </h3>
                            <p className="mt-2 text-sm text-slate-600">
                                {isExitPermitMode
                                    ? 'Khusus karyawan yang sudah lolos alur Exit Permit dan verifikasi absensi Sisca.'
                                    : 'Pantau distribusi paket umum, realisasi konsumsi, dan additional visitor dalam satu tampilan.'}
                            </p>
                        </div>
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <div className="rounded-xl border border-slate-200 bg-white/90 px-4 py-3 text-sm shadow-sm backdrop-blur">
                                <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Today</p>
                                <p className="mt-1 font-semibold text-slate-700">{todayLabel}</p>
                            </div>
                            <Link
                                href={route(createRouteName)}
                                className="inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-700"
                            >
                                + Tambah Order Meal
                            </Link>
                        </div>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Paket Disediakan</p>
                        <p className="mt-3 text-4xl font-black text-slate-900">{summary.provided_total}</p>
                    </div>
                    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Realisasi Makan</p>
                        <p className="mt-3 text-4xl font-black text-cyan-700">{summary.actual_total}</p>
                    </div>
                    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Sisa Paket</p>
                        <p className="mt-3 text-4xl font-black text-emerald-700">{summary.remaining_total}</p>
                    </div>
                </div>

                <div className="grid gap-4 xl:grid-cols-3">
                    <NotEatenChart title="Grafik Harian" points={notEatenCharts?.daily ?? []} />
                    <NotEatenChart title="Grafik Mingguan" points={notEatenCharts?.weekly ?? []} />
                    <NotEatenChart title="Grafik Bulanan" points={notEatenCharts?.monthly ?? []} />
                </div>

                <div className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-5 py-4">
                        <div>
                            <p className="text-sm font-semibold text-slate-900">Daftar Order Meal</p>
                            <p className="text-xs text-slate-500">Total data: {totalRows}</p>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-100">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Karyawan</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Tanggal</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Menu</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Schedule</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Visitor</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Disediakan</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Realisasi</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Sisa</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Status</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {orderMeals.data.map((item) => (
                                    <tr key={item.id} className="transition hover:bg-slate-50">
                                        <td className="px-4 py-3 font-semibold text-slate-800">{item.employee_name}</td>
                                        <td className="px-4 py-3 text-slate-700">{item.meal_date}</td>
                                        <td className="px-4 py-3 text-slate-700">{item.menu_name}</td>
                                        <td className="px-4 py-3 text-slate-700">{item.schedule_type ?? 'single'}</td>
                                        <td className="px-4 py-3 text-slate-700">{item.visitor_count ?? 0}</td>
                                        <td className="px-4 py-3 text-slate-700">{item.quantity}</td>
                                        <td className="px-4 py-3 text-slate-700">{item.actual_quantity}</td>
                                        <td className="px-4 py-3 font-semibold text-emerald-600">{item.remaining_quantity}</td>
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
                                                {item.status}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex gap-2">
                                                <Link
                                                    href={route(showRouteName, item.id)}
                                                    className="rounded bg-slate-700 px-3 py-1 text-xs font-semibold text-white transition hover:bg-slate-600"
                                                >
                                                    Detail
                                                </Link>
                                                <Link
                                                    href={route(editRouteName, item.id)}
                                                    className="rounded bg-cyan-700 px-3 py-1 text-xs font-semibold text-white transition hover:bg-cyan-600"
                                                >
                                                    Edit
                                                </Link>
                                                <button
                                                    type="button"
                                                    onClick={() => handleDelete(item.id)}
                                                    className="rounded bg-rose-600 px-3 py-1 text-xs font-semibold text-white transition hover:bg-rose-500"
                                                >
                                                    Hapus
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {!orderMeals.data.length && (
                        <div className="px-4 py-10 text-center text-sm text-slate-500">Belum ada data order meal.</div>
                    )}

                    {orderMeals.links && orderMeals.links.length > 3 && (
                        <div className="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 px-4 py-4">
                            {orderMeals.links.map((link) => {
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
