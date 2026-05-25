import InputError from '@/Components/InputError';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

function translateConversionNotes(text) {
    if (!text) {
        return text;
    }

    const translatedFromLegacy = String(text).replace(
        /\[AUTO-CONVERT\s+EP#(\d+)\s*-\s*(\d+)\]/g,
        '[Lunch box allowance converted to reimbursement for employee EP#$1: -$2 packs]',
    );

    const translatedFromLegacyLabel = translatedFromLegacy.replace(
        /\[Konversi\s+Lunch\s+Box\s+EP#(\d+):\s*-(\d+)\s*paket\]/g,
        '[Lunch box allowance converted to reimbursement for employee EP#$1: -$2 packs]',
    );

    return translatedFromLegacyLabel.replace(
        /\[Pengalihan\s+jatah\s+lunch\s+box\s+ke\s+uang\s+reimbursement\s+karyawan\s+EP#(\d+):\s*-(\d+)\s*paket\]/g,
        '[Lunch box allowance converted to reimbursement for employee EP#$1: -$2 packs]',
    );
}

const inputClass =
    'mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-cyan-600 focus:ring-2 focus:ring-cyan-200';

const currencyFormatter = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

export default function Edit({ orderMeal, canApprove, mode, indexRouteName, updateRouteName, mealPricing = null }) {
    const isExitPermitMode = mode === 'exit_permit';
    const readableNotes = translateConversionNotes(orderMeal.notes ?? '');
    const initialShiftData = (() => {
        const dayQty = Number(orderMeal.day_shift_qty ?? 0);
        const otDayQty = Number(orderMeal.overtime_day_shift_qty ?? 0);
        const nightQty = Number(orderMeal.night_shift_qty ?? 0);
        const otNightQty = Number(orderMeal.overtime_night_shift_qty ?? 0);

        if (dayQty > 0) {
            return { type: 'day', qty: dayQty };
        }

        if (otDayQty > 0) {
            return { type: 'ot_day', qty: otDayQty };
        }

        if (nightQty > 0) {
            return { type: 'night', qty: nightQty };
        }

        if (otNightQty > 0) {
            return { type: 'ot_night', qty: otNightQty };
        }

        return { type: 'day', qty: 0 };
    })();
    const storedPricing = {
        meal_unit_price: Number(orderMeal.meal_unit_price ?? 12000),
        local_tax_rate: Number(orderMeal.local_tax_rate ?? 10),
        service_tax_rate: Number(orderMeal.service_tax_rate ?? 2),
    };
    const activePricing = {
        supplier_name: mealPricing?.supplier_name ?? 'System Default',
        meal_unit_price: Number(mealPricing?.meal_unit_price ?? 12000),
        local_tax_rate: Number(mealPricing?.local_tax_rate ?? 10),
        service_tax_rate: Number(mealPricing?.service_tax_rate ?? 2),
        source: mealPricing?.source ?? 'fallback',
    };

    const { data, setData, put, processing, errors } = useForm({
        meal_date: orderMeal.meal_date ?? '',
        menu_name: orderMeal.menu_name ?? '',
        quantity: orderMeal.quantity ?? 1,
        actual_quantity: orderMeal.actual_quantity ?? 0,
        visitor_count: orderMeal.visitor_count ?? 0,
        day_shift_qty: orderMeal.day_shift_qty ?? 0,
        overtime_day_shift_qty: orderMeal.overtime_day_shift_qty ?? 0,
        night_shift_qty: orderMeal.night_shift_qty ?? 0,
        overtime_night_shift_qty: orderMeal.overtime_night_shift_qty ?? 0,
        notes: readableNotes,
        status: orderMeal.status ?? 'pending',
    });

    const [selectedShift, setSelectedShift] = useState(initialShiftData.type);
    const [shiftQuantity, setShiftQuantity] = useState(initialShiftData.qty);

    const shiftTotal =
        Number(data.day_shift_qty || 0)
        + Number(data.overtime_day_shift_qty || 0)
        + Number(data.night_shift_qty || 0)
        + Number(data.overtime_night_shift_qty || 0);
    const baseProvided = isExitPermitMode ? Number(data.quantity || 0) : shiftTotal;
    const totalProvided = baseProvided + Number(isExitPermitMode ? (data.visitor_count || 0) : 0);
    const subtotalAmount = isExitPermitMode
        ? 0
        : totalProvided * storedPricing.meal_unit_price;
    const localTaxAmount = isExitPermitMode
        ? 0
        : Math.round(subtotalAmount * (storedPricing.local_tax_rate / 100));
    const serviceTaxAmount = isExitPermitMode
        ? 0
        : Math.round(subtotalAmount * (storedPricing.service_tax_rate / 100));
    const grandTotalAmount = isExitPermitMode
        ? 0
        : subtotalAmount + localTaxAmount - serviceTaxAmount;

    useEffect(() => {
        if (isExitPermitMode) {
            return;
        }

        const nextQuantity = Math.max(0, Number(shiftQuantity || 0));
        const nextShiftData = {
            day_shift_qty: selectedShift === 'day' ? nextQuantity : 0,
            overtime_day_shift_qty: selectedShift === 'ot_day' ? nextQuantity : 0,
            night_shift_qty: selectedShift === 'night' ? nextQuantity : 0,
            overtime_night_shift_qty: selectedShift === 'ot_night' ? nextQuantity : 0,
        };

        if (Number(data.day_shift_qty || 0) !== nextShiftData.day_shift_qty) {
            setData('day_shift_qty', nextShiftData.day_shift_qty);
        }

        if (Number(data.overtime_day_shift_qty || 0) !== nextShiftData.overtime_day_shift_qty) {
            setData('overtime_day_shift_qty', nextShiftData.overtime_day_shift_qty);
        }

        if (Number(data.night_shift_qty || 0) !== nextShiftData.night_shift_qty) {
            setData('night_shift_qty', nextShiftData.night_shift_qty);
        }

        if (Number(data.overtime_night_shift_qty || 0) !== nextShiftData.overtime_night_shift_qty) {
            setData('overtime_night_shift_qty', nextShiftData.overtime_night_shift_qty);
        }

        if (Number(data.quantity || 0) !== nextQuantity) {
            setData('quantity', nextQuantity);
        }

        if (Number(data.visitor_count || 0) !== 0) {
            setData('visitor_count', 0);
        }

        const clampedActual = Math.min(Number(data.actual_quantity || 0), nextQuantity);
        if (Number(data.actual_quantity || 0) !== clampedActual) {
            setData('actual_quantity', clampedActual);
        }
    }, [
        isExitPermitMode,
        selectedShift,
        shiftQuantity,
        data.day_shift_qty,
        data.overtime_day_shift_qty,
        data.night_shift_qty,
        data.overtime_night_shift_qty,
        data.quantity,
        data.actual_quantity,
        data.visitor_count,
        setData,
    ]);

    const submit = (e) => {
        e.preventDefault();
        put(route(updateRouteName, orderMeal.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-bold leading-tight text-slate-800">
                    {isExitPermitMode ? 'Edit Order Meal Exit Permit' : 'Edit Order Meal'}
                </h2>
            }
        >
            <Head title={isExitPermitMode ? 'Edit Order Meal Exit Permit' : 'Edit Order Meal'} />

            <div className="space-y-6">
                <div className="rounded-2xl border border-cyan-200 bg-gradient-to-r from-cyan-50 via-white to-slate-50 p-5 shadow-sm">
                    <p className="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-700">Meal Monitoring</p>
                    <p className="mt-2 text-sm text-slate-700">
                        {isExitPermitMode
                            ? 'Update meal orders originating from the Exit Permit flow.'
                            : 'Update meal orders for canteen operational recap.'}
                    </p>
                    {!isExitPermitMode && (
                        <div className="mt-4 rounded-xl border border-cyan-200 bg-white/80 p-4">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p className="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Price Supplier Reference</p>
                                    <p className="mt-1 text-sm font-semibold text-slate-900">{activePricing.supplier_name}</p>
                                </div>
                                <div className="rounded-full bg-cyan-100 px-3 py-1 text-xs font-semibold text-cyan-700">
                                    {activePricing.source === 'supplier' ? 'Current Supplier Master' : 'System Default'}
                                </div>
                            </div>
                            <div className="mt-3 grid gap-3 text-sm text-slate-700 md:grid-cols-3">
                                <p><span className="font-semibold">Amount / Portion:</span> {currencyFormatter.format(activePricing.meal_unit_price)}</p>
                                <p><span className="font-semibold">Local Tax:</span> {activePricing.local_tax_rate}%</p>
                                <p><span className="font-semibold">Service Tax:</span> {activePricing.service_tax_rate}%</p>
                            </div>
                        </div>
                    )}
                </div>

                <form onSubmit={submit} className="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="grid gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700 md:grid-cols-4">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Provided Packs</p>
                            <p className="mt-2 text-3xl font-black text-slate-900">{totalProvided}</p>
                        </div>
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Actual Meals</p>
                            <p className="mt-2 text-3xl font-black text-slate-900">{data.actual_quantity}</p>
                        </div>
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Remaining Packs</p>
                            <p className="mt-2 text-3xl font-black text-emerald-700">{Math.max(0, totalProvided - Number(data.actual_quantity || 0))}</p>
                        </div>
                        {/* <div>
                            <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Status</p>
                            <p className="mt-2 inline-flex rounded-full bg-amber-100 px-3 py-1 text-sm font-semibold uppercase text-amber-700">{data.status}</p>
                        </div> */}
                    </div>

                    {!isExitPermitMode && (
                        <div className="space-y-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                <div>
                                    <label htmlFor="quantity" className="text-sm font-semibold text-slate-800">Total Order</label>
                                    <input
                                        id="quantity"
                                        type="number"
                                        min="0"
                                        value={shiftQuantity}
                                        className={inputClass}
                                        onChange={(e) => setShiftQuantity(Number(e.target.value))}
                                    />
                                </div>
                                <div className="rounded-lg border border-cyan-200 bg-white p-4 md:col-span-3">
                                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Locked Pricing</p>
                                    <div className="mt-3 grid gap-3 text-sm text-slate-700 md:grid-cols-3">
                                        <p><span className="font-semibold">Amount / Portion:</span> {currencyFormatter.format(storedPricing.meal_unit_price)}</p>
                                        <p><span className="font-semibold">Local Tax:</span> {storedPricing.local_tax_rate}%</p>
                                        <p><span className="font-semibold">Service Tax:</span> {storedPricing.service_tax_rate}%</p>
                                    </div>
                                    <p className="mt-2 text-xs text-slate-500">Pricing follows the Price Supplier master and is not edited from this form.</p>
                                </div>
                            </div>

                            <div className="grid gap-3 rounded-lg border border-cyan-200 bg-cyan-50 p-4 text-sm text-cyan-900 md:grid-cols-2 xl:grid-cols-4">
                                <p><span className="font-semibold">Subtotal:</span> {currencyFormatter.format(subtotalAmount)}</p>
                                <p><span className="font-semibold">Local Tax:</span> {currencyFormatter.format(localTaxAmount)}</p>
                                <p><span className="font-semibold">Service Tax:</span> {currencyFormatter.format(serviceTaxAmount)}</p>
                                <p><span className="font-semibold">Grand Total:</span> {currencyFormatter.format(grandTotalAmount)}</p>
                            </div>
                        </div>
                    )}

                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label htmlFor="meal_date" className="text-sm font-semibold text-slate-800">Meal Date</label>
                            <input
                                id="meal_date"
                                type="date"
                                value={data.meal_date}
                                className={inputClass}
                                onChange={(e) => setData('meal_date', e.target.value)}
                            />
                            <InputError message={errors.meal_date} className="mt-2" />
                        </div>
                        {!isExitPermitMode && (
                            <div>
                                <label htmlFor="shift_type" className="text-sm font-semibold text-slate-800">Shift</label>
                                <select
                                    id="shift_type"
                                    className={inputClass}
                                    value={selectedShift}
                                    onChange={(e) => setSelectedShift(e.target.value)}
                                >
                                    <option value="day">Day Shift (12.00 - 13.00)</option>
                                    <option value="ot_day">Overtime Day Shift (18.15 - 18.35)</option>
                                    <option value="night">Night Shift (00.00 - 01.11)</option>
                                    <option value="ot_night">Overtime Night Shift (06.15 - 06.35)</option>
                                </select>
                            </div>
                        )}
                    </div>

                    <div className="grid gap-4 md:grid-cols-3">
                        <div>
                            <label htmlFor="quantity" className="text-sm font-semibold text-slate-800">Base Employee Packs</label>
                            <input
                                id="quantity"
                                type="number"
                                min="0"
                                value={data.quantity}
                                className={inputClass}
                                readOnly={!isExitPermitMode}
                                onChange={(e) => setData('quantity', Number(e.target.value))}
                            />
                            <InputError message={errors.quantity} className="mt-2" />
                        </div>
                        {isExitPermitMode && (
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
                        )}
                        <div>
                            <label htmlFor="actual_quantity" className="text-sm font-semibold text-slate-800">Actual Meals</label>
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
                        {isExitPermitMode
                            ? <>Total packs provided = <span className="font-semibold">{totalProvided}</span> (base packs + visitors).</>
                            : <>Total packs provided = <span className="font-semibold">{shiftTotal}</span> (day shift + overtime day + night shift + overtime night).</>}
                    </div>

                    <div>
                        <label htmlFor="notes" className="text-sm font-semibold text-slate-800">Notes</label>
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
                            <label htmlFor="status" className="text-sm font-semibold text-slate-800">Approval Status</label>
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
                            Cancel
                        </Link>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
