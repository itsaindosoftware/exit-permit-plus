import InputError from '@/Components/InputError';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const inputClass =
    'mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-cyan-600 focus:ring-2 focus:ring-cyan-200';

export default function PriceSupplierForm({ mode, priceSupplier = null, defaultIsActive = false, indexRouteName = 'price-suppliers.index', submitRouteName = 'price-suppliers.store' }) {
    const isEditMode = mode === 'edit';

    const { data, setData, post, put, processing, errors } = useForm({
        supplier_name: priceSupplier?.supplier_name ?? '',
        meal_unit_price: priceSupplier?.meal_unit_price ?? 12000,
        local_tax_rate: priceSupplier?.local_tax_rate ?? 10,
        service_tax_rate: priceSupplier?.service_tax_rate ?? 2,
        effective_date: priceSupplier?.effective_date ?? '',
        is_active: priceSupplier?.is_active ?? defaultIsActive,
        notes: priceSupplier?.notes ?? '',
    });

    const submit = (e) => {
        e.preventDefault();

        if (isEditMode && priceSupplier?.id) {
            put(route(submitRouteName, priceSupplier.id));
            return;
        }

        post(route(submitRouteName));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-bold leading-tight text-slate-800">
                    {isEditMode ? 'Edit Price Supplier' : 'Add Price Supplier'}
                </h2>
            }
        >
            <Head title={isEditMode ? 'Edit Price Supplier' : 'Add Price Supplier'} />

            <div className="space-y-6">
                <div className="rounded-2xl border border-cyan-200 bg-gradient-to-r from-cyan-50 via-white to-slate-50 p-5 shadow-sm">
                    <p className="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-700">Price Master</p>
                    <p className="mt-2 text-sm text-slate-700">
                        {isEditMode
                            ? 'Update the supplier meal price that Order Meal will use automatically.'
                            : 'Register a new supplier meal price. The active record becomes the default pricing source.'}
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label htmlFor="supplier_name" className="text-sm font-semibold text-slate-800">Supplier Name</label>
                            <input
                                id="supplier_name"
                                type="text"
                                value={data.supplier_name}
                                className={inputClass}
                                onChange={(e) => setData('supplier_name', e.target.value)}
                                placeholder="Example: PT Supplier Catering A"
                            />
                            <InputError message={errors.supplier_name} className="mt-2" />
                        </div>
                        <div>
                            <label htmlFor="effective_date" className="text-sm font-semibold text-slate-800">Effective Date</label>
                            <input
                                id="effective_date"
                                type="date"
                                value={data.effective_date}
                                className={inputClass}
                                onChange={(e) => setData('effective_date', e.target.value)}
                            />
                            <InputError message={errors.effective_date} className="mt-2" />
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-3">
                        <div>
                            <label htmlFor="meal_unit_price" className="text-sm font-semibold text-slate-800">Amount / Portion</label>
                            <input
                                id="meal_unit_price"
                                type="number"
                                min="1"
                                value={data.meal_unit_price}
                                className={inputClass}
                                onChange={(e) => setData('meal_unit_price', Number(e.target.value))}
                            />
                            <InputError message={errors.meal_unit_price} className="mt-2" />
                        </div>
                        <div>
                            <label htmlFor="local_tax_rate" className="text-sm font-semibold text-slate-800">Local Tax (%)</label>
                            <input
                                id="local_tax_rate"
                                type="number"
                                min="0"
                                step="0.01"
                                value={data.local_tax_rate}
                                className={inputClass}
                                onChange={(e) => setData('local_tax_rate', Number(e.target.value))}
                            />
                            <InputError message={errors.local_tax_rate} className="mt-2" />
                        </div>
                        <div>
                            <label htmlFor="service_tax_rate" className="text-sm font-semibold text-slate-800">Service Tax (%)</label>
                            <input
                                id="service_tax_rate"
                                type="number"
                                min="0"
                                step="0.01"
                                value={data.service_tax_rate}
                                className={inputClass}
                                onChange={(e) => setData('service_tax_rate', Number(e.target.value))}
                            />
                            <InputError message={errors.service_tax_rate} className="mt-2" />
                        </div>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <label className="inline-flex items-center gap-3 text-sm font-semibold text-slate-800">
                            <input
                                type="checkbox"
                                checked={Boolean(data.is_active)}
                                onChange={(e) => setData('is_active', e.target.checked)}
                                className="h-4 w-4 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500"
                            />
                            Set as active price source
                        </label>
                        <p className="mt-2 text-xs text-slate-500">Only one price supplier should be active at a time. Saving an active record will deactivate the others.</p>
                    </div>

                    <div>
                        <label htmlFor="notes" className="text-sm font-semibold text-slate-800">Notes</label>
                        <textarea
                            id="notes"
                            rows="4"
                            value={data.notes}
                            className={inputClass}
                            onChange={(e) => setData('notes', e.target.value)}
                            placeholder="Supplier remarks, quote reference, or pricing notes"
                        />
                        <InputError message={errors.notes} className="mt-2" />
                    </div>

                    <div className="flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:justify-end">
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            Save
                        </button>
                        <Link
                            href={route(indexRouteName)}
                            className="rounded-md border border-slate-300 px-4 py-2 text-center text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                        >
                            Cancel
                        </Link>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}