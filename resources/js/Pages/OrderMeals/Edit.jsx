import InputError from '@/Components/InputError';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const inputClass =
    'mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-cyan-600 focus:ring-2 focus:ring-cyan-200';

export default function Edit({ orderMeal, canApprove, mode, indexRouteName, updateRouteName }) {
    const isExitPermitMode = mode === 'exit_permit';

    const { data, setData, put, processing, errors } = useForm({
        meal_date: orderMeal.meal_date ?? '',
        menu_name: orderMeal.menu_name ?? '',
        quantity: orderMeal.quantity ?? 1,
        actual_quantity: orderMeal.actual_quantity ?? 0,
        visitor_count: orderMeal.visitor_count ?? 0,
        notes: orderMeal.notes ?? '',
        status: orderMeal.status ?? 'pending',
    });

    const totalProvided = Number(data.quantity || 0) + Number(data.visitor_count || 0);

    const submit = (e) => {
        e.preventDefault();
        put(route(updateRouteName, orderMeal.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-bold leading-tight text-slate-800">
                    {isExitPermitMode ? 'Edit Order Meal Exit Permit' : 'Edit Order Meal Umum'}
                </h2>
            }
        >
            <Head title={isExitPermitMode ? 'Edit Order Meal Exit Permit' : 'Edit Order Meal Umum'} />

            <div className="space-y-6">
                <div className="rounded-2xl border border-cyan-200 bg-gradient-to-r from-cyan-50 via-white to-slate-50 p-5 shadow-sm">
                    <p className="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-700">Meal Monitoring</p>
                    <p className="mt-2 text-sm text-slate-700">
                        {isExitPermitMode
                            ? 'Perbarui order meal yang berasal dari alur Exit Permit.'
                            : 'Perbarui order meal umum untuk rekap operasional canteen.'}
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="grid gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700 md:grid-cols-4">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Paket Disediakan</p>
                            <p className="mt-2 text-3xl font-black text-slate-900">{totalProvided}</p>
                        </div>
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Realisasi Makan</p>
                            <p className="mt-2 text-3xl font-black text-slate-900">{data.actual_quantity}</p>
                        </div>
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Sisa Paket</p>
                            <p className="mt-2 text-3xl font-black text-emerald-700">{Math.max(0, totalProvided - Number(data.actual_quantity || 0))}</p>
                        </div>
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Status</p>
                            <p className="mt-2 inline-flex rounded-full bg-amber-100 px-3 py-1 text-sm font-semibold uppercase text-amber-700">{data.status}</p>
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label htmlFor="meal_date" className="text-sm font-semibold text-slate-800">Tanggal Makan</label>
                            <input
                                id="meal_date"
                                type="date"
                                value={data.meal_date}
                                className={inputClass}
                                onChange={(e) => setData('meal_date', e.target.value)}
                            />
                            <InputError message={errors.meal_date} className="mt-2" />
                        </div>
                        <div>
                            <label htmlFor="menu_name" className="text-sm font-semibold text-slate-800">Menu Makan Siang</label>
                            <input
                                id="menu_name"
                                type="text"
                                value={data.menu_name}
                                className={inputClass}
                                onChange={(e) => setData('menu_name', e.target.value)}
                            />
                            <InputError message={errors.menu_name} className="mt-2" />
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-3">
                        <div>
                            <label htmlFor="quantity" className="text-sm font-semibold text-slate-800">Paket Dasar Karyawan</label>
                            <input
                                id="quantity"
                                type="number"
                                min="1"
                                value={data.quantity}
                                className={inputClass}
                                onChange={(e) => setData('quantity', Number(e.target.value))}
                            />
                            <InputError message={errors.quantity} className="mt-2" />
                        </div>
                        <div>
                            <label htmlFor="visitor_count" className="text-sm font-semibold text-slate-800">Additional Visitor</label>
                            <input
                                id="visitor_count"
                                type="number"
                                min="0"
                                value={data.visitor_count}
                                className={inputClass}
                                onChange={(e) => setData('visitor_count', Number(e.target.value))}
                            />
                            <InputError message={errors.visitor_count} className="mt-2" />
                        </div>
                        <div>
                            <label htmlFor="actual_quantity" className="text-sm font-semibold text-slate-800">Realisasi Makan</label>
                            <input
                                id="actual_quantity"
                                type="number"
                                min="0"
                                value={data.actual_quantity}
                                className={inputClass}
                                onChange={(e) => setData('actual_quantity', Number(e.target.value))}
                            />
                            <InputError message={errors.actual_quantity} className="mt-2" />
                        </div>
                    </div>

                    <div className="rounded-lg border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm text-cyan-900">
                        Total paket disediakan = <span className="font-semibold">{totalProvided}</span> (paket dasar + visitor).
                    </div>

                    <div>
                        <label htmlFor="notes" className="text-sm font-semibold text-slate-800">Catatan</label>
                        <textarea
                            id="notes"
                            className={inputClass}
                            rows="3"
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                        />
                        <InputError message={errors.notes} className="mt-2" />
                    </div>

                    {canApprove && (
                        <div>
                            <label htmlFor="status" className="text-sm font-semibold text-slate-800">Status Approve</label>
                            <select
                                id="status"
                                className={inputClass}
                                value={data.status}
                                onChange={(e) => setData('status', e.target.value)}
                            >
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                            <InputError message={errors.status} className="mt-2" />
                        </div>
                    )}

                    <div className="flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:justify-end">
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            Update
                        </button>
                        <Link
                            href={route(indexRouteName)}
                            className="rounded-md border border-slate-300 px-4 py-2 text-center text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                        >
                            Batal
                        </Link>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
