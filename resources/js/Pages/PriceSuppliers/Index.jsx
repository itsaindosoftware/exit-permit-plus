import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

const currencyFormatter = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

export default function Index({ items = [], activePriceSupplier = null }) {
    const handleDelete = (item) => {
        if (!window.confirm(`Delete ${item.supplier_name}?`)) {
            return;
        }

        router.delete(route('price-suppliers.destroy', item.id), {
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-bold leading-tight text-slate-800">Price Supplier</h2>}
        >
            <Head title="Price Supplier" />

            <div className="space-y-6">
                <div className="rounded-2xl border border-cyan-200 bg-gradient-to-r from-cyan-50 via-white to-slate-50 p-5 shadow-sm">
                    <p className="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-700">Meal Price Master</p>
                    <p className="mt-2 text-sm text-slate-700">
                        Manage the supplier-based Amount / Portion price that Order Meal uses by default.
                    </p>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:col-span-2">
                        <p className="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Current Active Supplier</p>
                        {activePriceSupplier ? (
                            <div className="mt-3 space-y-2">
                                <p className="text-lg font-bold text-slate-900">{activePriceSupplier.supplier_name}</p>
                                <div className="grid gap-2 text-sm text-slate-700 md:grid-cols-3">
                                    <p><span className="font-semibold">Amount / Portion:</span> {currencyFormatter.format(activePriceSupplier.meal_unit_price)}</p>
                                    <p><span className="font-semibold">Local Tax:</span> {activePriceSupplier.local_tax_rate}%</p>
                                    <p><span className="font-semibold">Service Tax:</span> {activePriceSupplier.service_tax_rate}%</p>
                                </div>
                                <p className="text-xs text-slate-500">Effective date: {activePriceSupplier.effective_date ?? '-'}</p>
                            </div>
                        ) : (
                            <p className="mt-3 text-sm text-amber-700">No active price supplier yet. Create one and mark it active.</p>
                        )}
                    </div>

                    <div className="rounded-2xl border border-cyan-200 bg-cyan-50 p-5 shadow-sm">
                        <p className="text-xs font-semibold uppercase tracking-[0.22em] text-cyan-700">Action</p>
                        <p className="mt-2 text-sm text-slate-700">Create a new supplier price or update the active record to change what Order Meal uses.</p>
                        <Link
                            href={route('price-suppliers.create')}
                            className="mt-4 inline-flex rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700"
                        >
                            Add Price Supplier
                        </Link>
                    </div>
                </div>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-5 py-4">
                        <p className="text-sm font-semibold text-slate-900">Supplier Price List</p>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50 text-slate-600">
                                <tr>
                                    <th className="px-5 py-3 text-left font-semibold">Supplier</th>
                                    <th className="px-5 py-3 text-left font-semibold">Amount / Portion</th>
                                    <th className="px-5 py-3 text-left font-semibold">Taxes</th>
                                    <th className="px-5 py-3 text-left font-semibold">Effective Date</th>
                                    <th className="px-5 py-3 text-left font-semibold">Status</th>
                                    <th className="px-5 py-3 text-left font-semibold">Notes</th>
                                    <th className="px-5 py-3 text-left font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {items.length === 0 ? (
                                    <tr>
                                        <td className="px-5 py-8 text-center text-slate-500" colSpan="7">
                                            No price supplier data yet.
                                        </td>
                                    </tr>
                                ) : items.map((item) => (
                                    <tr key={item.id} className="align-top">
                                        <td className="px-5 py-4">
                                            <div className="font-semibold text-slate-900">{item.supplier_name}</div>
                                            {item.is_active && (
                                                <span className="mt-2 inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Active</span>
                                            )}
                                        </td>
                                        <td className="px-5 py-4 text-slate-700">{currencyFormatter.format(item.meal_unit_price)}</td>
                                        <td className="px-5 py-4 text-slate-700">
                                            <div>Local: {item.local_tax_rate}%</div>
                                            <div>Service: {item.service_tax_rate}%</div>
                                        </td>
                                        <td className="px-5 py-4 text-slate-700">{item.effective_date ?? '-'}</td>
                                        <td className="px-5 py-4 text-slate-700">{item.is_active ? 'Active' : 'Inactive'}</td>
                                        <td className="px-5 py-4 text-slate-700">
                                            <div className="max-w-xs whitespace-pre-line">{item.notes || '-'}</div>
                                        </td>
                                        <td className="px-5 py-4">
                                            <div className="flex flex-wrap gap-2">
                                                <Link
                                                    href={route('price-suppliers.edit', item.id)}
                                                    className="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-100"
                                                >
                                                    Edit
                                                </Link>
                                                <button
                                                    type="button"
                                                    onClick={() => handleDelete(item)}
                                                    className="rounded-md border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-50"
                                                >
                                                    Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}