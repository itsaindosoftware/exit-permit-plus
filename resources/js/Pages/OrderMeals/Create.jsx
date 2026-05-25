import InputError from '@/Components/InputError';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const inputClass =
    'mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-cyan-600 focus:ring-2 focus:ring-cyan-200';

const currencyFormatter = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

export default function Create({ mode, storeRouteName, indexRouteName, eligibleExitPermits = [], eligibilityWarning = null, defaultCapacity = 120, mealPricing = null }) {
    const isExitPermitMode = mode === 'exit_permit';
    const firstPermitId = eligibleExitPermits?.[0]?.id ?? '';
    const firstPermitRequestorCount = Math.max(0, Number(eligibleExitPermits?.[0]?.requestors?.length ?? 0));
    const hasEligiblePermits = (eligibleExitPermits?.length ?? 0) > 0;
    const baseCapacity = Number.isFinite(Number(defaultCapacity))
        ? Math.max(0, Number(defaultCapacity))
        : 120;
    const pricing = {
        supplier_name: mealPricing?.supplier_name ?? 'System Default',
        meal_unit_price: Number(mealPricing?.meal_unit_price ?? 12000),
        local_tax_rate: Number(mealPricing?.local_tax_rate ?? 10),
        service_tax_rate: Number(mealPricing?.service_tax_rate ?? 2),
        effective_date: mealPricing?.effective_date ?? null,
        is_active: Boolean(mealPricing?.is_active),
        source: mealPricing?.source ?? 'fallback',
    };
    const [selectedShift, setSelectedShift] = useState('day');
    const [shiftQuantity, setShiftQuantity] = useState(baseCapacity);

    const { data, setData, post, processing, errors } = useForm({
        exit_permit_id: firstPermitId,
        meal_date: '',
        menu_name: '',
        quantity: isExitPermitMode ? firstPermitRequestorCount : baseCapacity,
        actual_quantity: 0,
        visitor_count: 0,
        day_shift_qty: 0,
        overtime_day_shift_qty: 0,
        night_shift_qty: 0,
        overtime_night_shift_qty: 0,
        notes: '',
    });

    const selectedPermit = isExitPermitMode
        ? eligibleExitPermits.find((permit) => String(permit.id) === String(data.exit_permit_id))
        : null;
    const selectedRequestorCount = Math.max(0, Number(selectedPermit?.requestors?.length ?? 0));
    const shiftTotal =
        Number(data.day_shift_qty || 0)
        + Number(data.overtime_day_shift_qty || 0)
        + Number(data.night_shift_qty || 0)
        + Number(data.overtime_night_shift_qty || 0);
    const displayedBaseQuantity = isExitPermitMode ? selectedRequestorCount : Number(data.quantity || 0);
    const totalProvided = displayedBaseQuantity + Number(data.visitor_count || 0);
    const subtotalAmount = isExitPermitMode
        ? 0
        : shiftTotal * pricing.meal_unit_price;
    const localTaxAmount = isExitPermitMode
        ? 0
        : Math.round(subtotalAmount * (pricing.local_tax_rate / 100));
    const serviceTaxAmount = isExitPermitMode
        ? 0
        : Math.round(subtotalAmount * (pricing.service_tax_rate / 100));
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

        const nextActualQuantity = Math.min(Number(data.actual_quantity || 0), nextQuantity);

        if (Number(data.actual_quantity || 0) !== nextActualQuantity) {
            setData('actual_quantity', nextActualQuantity);
        }

        if (Number(data.visitor_count || 0) !== 0) {
            setData('visitor_count', 0);
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

    useEffect(() => {
        if (!isExitPermitMode) {
            return;
        }

        if (Number(data.quantity || 0) !== selectedRequestorCount) {
            setData('quantity', selectedRequestorCount);
        }

        const visitorCount = Number(data.visitor_count || 0);
        const totalAllowed = selectedRequestorCount + visitorCount;
        const nextActualQuantity = Math.min(Number(data.actual_quantity || 0), totalAllowed);

        if (Number(data.actual_quantity || 0) !== nextActualQuantity) {
            setData('actual_quantity', nextActualQuantity);
        }
    }, [
        isExitPermitMode,
        selectedRequestorCount,
        data.quantity,
        data.visitor_count,
        data.actual_quantity,
        setData,
    ]);

    const submit = (e) => {
        e.preventDefault();

        if (isExitPermitMode && !hasEligiblePermits) {
            return;
        }

        post(route(storeRouteName));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-bold leading-tight text-slate-800">
                    {isExitPermitMode ? 'Add Exit Permit Meal Order' : 'Add Meal Order'}
                </h2>
            }
        >
            <Head title={isExitPermitMode ? 'Add Exit Permit Meal Order' : 'Add Meal Order'} />

            <div className="space-y-6">
                <div className="rounded-2xl border border-cyan-200 bg-gradient-to-r from-cyan-50 via-white to-slate-50 p-5 shadow-sm">
                    <p className="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-700">Canteen Request</p>
                    <p className="mt-2 text-sm text-slate-700">
                        {isExitPermitMode
                            ? 'Exit Permit meal order. Can only be submitted after Sisca attendance verification with requestor match = Y.'
                            : 'Meal order for daily employee needs and additional visitors.'}
                    </p>
                    {!isExitPermitMode && (
                        <div className="mt-4 rounded-xl border border-cyan-200 bg-white/80 p-4">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p className="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Active Price Supplier</p>
                                    <p className="mt-1 text-sm font-semibold text-slate-900">{pricing.supplier_name}</p>
                                </div>
                                <div className="rounded-full bg-cyan-100 px-3 py-1 text-xs font-semibold text-cyan-700">
                                    {pricing.source === 'supplier' ? 'From Supplier Master' : 'System Default'}
                                </div>
                            </div>
                            <div className="mt-3 grid gap-3 text-sm text-slate-700 md:grid-cols-3">
                                <p><span className="font-semibold">Amount / Portion:</span> {currencyFormatter.format(pricing.meal_unit_price)}</p>
                                <p><span className="font-semibold">Local Tax:</span> {pricing.local_tax_rate}%</p>
                                <p><span className="font-semibold">Service Tax:</span> {pricing.service_tax_rate}%</p>
                            </div>
                        </div>
                    )}
                </div>

                {isExitPermitMode && !hasEligiblePermits && (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        {eligibilityWarning ?? 'No Exit Permit qualifies for Exit Permit meal order mode yet.'}
                    </div>
                )}

                <form
                    onSubmit={submit}
                    className="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                >
                    <div className="grid gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4 md:grid-cols-3">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Meal Type</p>
                            <p className="mt-2 inline-flex rounded-full bg-cyan-100 px-3 py-1 text-sm font-semibold text-cyan-700">Lunch</p>
                        </div>
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Default Capacity</p>
                            <p className="mt-2 text-3xl font-black text-slate-900">{isExitPermitMode ? selectedRequestorCount : baseCapacity}</p>
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        {isExitPermitMode && (
                            <div>
                                <label htmlFor="exit_permit_id" className="text-sm font-semibold text-slate-800">Exit Permit (Requestor Matched Y)</label>
                                <select
                                    id="exit_permit_id"
                                    className={inputClass}
                                    value={data.exit_permit_id}
                                    disabled={!hasEligiblePermits}
                                    onChange={(e) => setData('exit_permit_id', Number(e.target.value))}
                                    required
                                >
                                    {!hasEligiblePermits && <option value="">No eligible data yet</option>}
                                    {eligibleExitPermits?.map((permit) => (
                                        <option key={permit.id} value={permit.id}>{permit.label}</option>
                                    ))}
                                </select>
                                <InputError message={errors.exit_permit_id} className="mt-2" />
                            </div>
                        )}

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

                    {!isExitPermitMode && (
                        <div className="space-y-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <p className="text-sm font-semibold text-slate-900">Calculation for Catering Cost (Excel Format)</p>

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
                            </div>

                            <div className="grid gap-3 rounded-lg border border-cyan-200 bg-cyan-50 p-4 text-sm text-cyan-900 md:grid-cols-2 xl:grid-cols-4">
                                <p><span className="font-semibold">Subtotal:</span> {currencyFormatter.format(subtotalAmount)}</p>
                                <p><span className="font-semibold">Local Tax:</span> {currencyFormatter.format(localTaxAmount)}</p>
                                <p><span className="font-semibold">Service Tax:</span> {currencyFormatter.format(serviceTaxAmount)}</p>
                                <p><span className="font-semibold">Grand Total:</span> {currencyFormatter.format(grandTotalAmount)}</p>
                            </div>
                        </div>
                    )}

                    {isExitPermitMode && selectedPermit && (
                        <div className="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <p className="text-sm font-semibold text-slate-900">Selected Exit Permit Details</p>
                            <div className="grid gap-3 text-sm text-slate-700 md:grid-cols-2">
                                <p><span className="font-semibold">Requester:</span> {selectedPermit.owner_name || '-'}</p>
                                <p><span className="font-semibold">Email:</span> {selectedPermit.owner_email || '-'}</p>
                            </div>

                            <div className="overflow-x-auto rounded-md border border-slate-200 bg-white">
                                <table className="min-w-full border-collapse text-xs">
                                    <thead className="bg-slate-100 text-slate-700">
                                        <tr>
                                            <th className="border border-slate-200 px-2 py-1 text-left">No</th>
                                            <th className="border border-slate-200 px-2 py-1 text-left">Name</th>
                                            <th className="border border-slate-200 px-2 py-1 text-left">Employee ID</th>
                                            <th className="border border-slate-200 px-2 py-1 text-left">Position</th>
                                            <th className="border border-slate-200 px-2 py-1 text-left">Department</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {(selectedPermit.requestors ?? []).map((requestor) => (
                                            <tr key={`requestor-${selectedPermit.id}-${requestor.row_number}`}>
                                                <td className="border border-slate-200 px-2 py-1">{requestor.row_number}</td>
                                                <td className="border border-slate-200 px-2 py-1">{requestor.name || '-'}</td>
                                                <td className="border border-slate-200 px-2 py-1">{requestor.employee_id || '-'}</td>
                                                <td className="border border-slate-200 px-2 py-1">{requestor.position || '-'}</td>
                                                <td className="border border-slate-200 px-2 py-1">{requestor.department || '-'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    <div className="grid gap-4 md:grid-cols-3">
                        <div>
                            <label htmlFor="quantity" className="text-sm font-semibold text-slate-800">Base Employee Packs</label>
                            <input
                                id="quantity"
                                type="number"
                                min="0"
                                value={displayedBaseQuantity}
                                className={inputClass}
                                disabled
                                onChange={(e) => setData('quantity', Number(e.target.value))}
                            />
                            {isExitPermitMode && (
                                <p className="mt-1 text-xs text-slate-500">Automatically matches employee count from Selected Exit Permit Details.</p>
                            )}
                            {!isExitPermitMode && <p className="mt-1 text-xs text-slate-500">Automatically the sum of the 4 shift columns.</p>}
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
                            placeholder="Additional info for the canteen team / approver"
                        />
                        <InputError message={errors.notes} className="mt-2" />
                    </div>

                    <div className="flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:justify-end">
                        <button
                            type="submit"
                            disabled={processing || (isExitPermitMode && !hasEligiblePermits)}
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
