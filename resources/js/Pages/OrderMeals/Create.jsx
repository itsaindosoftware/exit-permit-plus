import InputError from '@/Components/InputError';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect } from 'react';

const inputClass =
    'mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-cyan-600 focus:ring-2 focus:ring-cyan-200';

export default function Create({ mode, storeRouteName, indexRouteName, eligibleExitPermits = [], eligibilityWarning = null }) {
    const isExitPermitMode = mode === 'exit_permit';
    const firstPermitId = eligibleExitPermits?.[0]?.id ?? '';
    const firstPermitRequestorCount = Math.max(0, Number(eligibleExitPermits?.[0]?.requestors?.length ?? 0));
    const hasEligiblePermits = (eligibleExitPermits?.length ?? 0) > 0;

    const { data, setData, post, processing, errors } = useForm({
        exit_permit_id: firstPermitId,
        meal_date: '',
        menu_name: '',
        quantity: isExitPermitMode ? firstPermitRequestorCount : 120,
        actual_quantity: 0,
        visitor_count: 0,
        schedule_type: 'single',
        repeat_count: 1,
        notes: '',
    });

    const selectedPermit = isExitPermitMode
        ? eligibleExitPermits.find((permit) => String(permit.id) === String(data.exit_permit_id))
        : null;
    const selectedRequestorCount = Math.max(0, Number(selectedPermit?.requestors?.length ?? 0));
    const displayedBaseQuantity = isExitPermitMode ? selectedRequestorCount : Number(data.quantity || 0);
    const totalProvided = displayedBaseQuantity + Number(data.visitor_count || 0);

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
                    {isExitPermitMode ? 'Tambah Order Meal Exit Permit' : 'Tambah Order Meal Umum'}
                </h2>
            }
        >
            <Head title={isExitPermitMode ? 'Tambah Order Meal Exit Permit' : 'Tambah Order Meal Umum'} />

            <div className="space-y-6">
                <div className="rounded-2xl border border-cyan-200 bg-gradient-to-r from-cyan-50 via-white to-slate-50 p-5 shadow-sm">
                    <p className="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-700">Canteen Request</p>
                    <p className="mt-2 text-sm text-slate-700">
                        {isExitPermitMode
                            ? 'Order meal khusus Exit Permit. Hanya dapat diajukan setelah verifikasi absensi Sisca dengan hasil matching requestor bernilai Y.'
                            : 'Order meal umum untuk kebutuhan harian karyawan dan additional visitor.'}
                    </p>
                </div>

                {isExitPermitMode && !hasEligiblePermits && (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        {eligibilityWarning ?? 'Belum ada Exit Permit yang memenuhi syarat untuk order meal mode Exit Permit.'}
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
                            <p className="mt-2 text-3xl font-black text-slate-900">{isExitPermitMode ? selectedRequestorCount : 120}</p>
                        </div>
                        {!isExitPermitMode && (
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Status</p>
                                <p className="mt-2 inline-flex rounded-full bg-amber-100 px-3 py-1 text-sm font-semibold text-amber-700">Pending Approval</p>
                            </div>
                        )}
                    </div>

                    <div className="grid gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4 md:grid-cols-3">
                        <div>
                            <label htmlFor="schedule_type" className="text-sm font-semibold text-slate-800">Schedule</label>
                            <select
                                id="schedule_type"
                                className={inputClass}
                                value={data.schedule_type}
                                onChange={(e) => setData('schedule_type', e.target.value)}
                            >
                                <option value="single">Single</option>
                                <option value="daily">Harian</option>
                                <option value="weekly">Mingguan</option>
                            </select>
                            <InputError message={errors.schedule_type} className="mt-2" />
                        </div>

                        <div>
                            <label htmlFor="repeat_count" className="text-sm font-semibold text-slate-800">Jumlah Jadwal</label>
                            <input
                                id="repeat_count"
                                type="number"
                                min="1"
                                max="60"
                                value={data.repeat_count}
                                className={inputClass}
                                disabled={data.schedule_type === 'single'}
                                onChange={(e) => setData('repeat_count', Number(e.target.value))}
                            />
                            <InputError message={errors.repeat_count} className="mt-2" />
                        </div>

                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Info</p>
                            <p className="mt-2 text-sm text-slate-700">
                                {data.schedule_type === 'single'
                                    ? 'Buat 1 order meal pada tanggal terpilih.'
                                    : data.schedule_type === 'daily'
                                        ? 'Buat order meal berulang per hari sesuai jumlah jadwal.'
                                        : 'Buat order meal berulang per minggu sesuai jumlah jadwal.'}
                            </p>
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
                                    {!hasEligiblePermits && <option value="">Belum ada data eligible</option>}
                                    {eligibleExitPermits?.map((permit) => (
                                        <option key={permit.id} value={permit.id}>{permit.label}</option>
                                    ))}
                                </select>
                                <InputError message={errors.exit_permit_id} className="mt-2" />
                            </div>
                        )}

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
                                placeholder="Contoh: Nasi Ayam, Sayur Sop, Buah"
                            />
                            <InputError message={errors.menu_name} className="mt-2" />
                        </div>
                    </div>

                    {isExitPermitMode && selectedPermit && (
                        <div className="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <p className="text-sm font-semibold text-slate-900">Detail Exit Permit Terpilih</p>
                            <div className="grid gap-3 text-sm text-slate-700 md:grid-cols-2">
                                <p><span className="font-semibold">Pemohon:</span> {selectedPermit.owner_name || '-'}</p>
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
                            <label htmlFor="quantity" className="text-sm font-semibold text-slate-800">Paket Dasar Karyawan</label>
                            <input
                                id="quantity"
                                type="number"
                                min="1"
                                value={displayedBaseQuantity}
                                className={inputClass}
                                disabled={isExitPermitMode}
                                onChange={(e) => setData('quantity', Number(e.target.value))}
                            />
                            {isExitPermitMode && (
                                <p className="mt-1 text-xs text-slate-500">Otomatis mengikuti jumlah karyawan di Detail Exit Permit Terpilih.</p>
                            )}
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
                            placeholder="Informasi tambahan untuk tim canteen / approver"
                        />
                        <InputError message={errors.notes} className="mt-2" />
                    </div>

                    <div className="flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:justify-end">
                        <button
                            type="submit"
                            disabled={processing || (isExitPermitMode && !hasEligiblePermits)}
                            className="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            Simpan
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
