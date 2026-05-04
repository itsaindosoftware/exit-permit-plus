import InputError from '@/Components/InputError';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const currencyFormatter = new Intl.NumberFormat('id-ID');

const inputClass =
    'mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-cyan-600 focus:ring-2 focus:ring-cyan-200';

function SectionHeading({ number, title }) {
    return (
        <div className="flex items-center border-b border-slate-800 bg-slate-100">
            <div className="w-12 border-r border-slate-800 px-2 py-1 text-center text-sm font-semibold text-slate-900">
                {number}
            </div>
            <div className="px-3 py-1 text-sm font-semibold uppercase tracking-wide text-slate-900">{title}</div>
        </div>
    );
}

export default function Edit({
    exitPermit,
    canUpdateRequest,
    canSubmitApproval,
    canArrangeCar,
    canVerifyAttendance,
    viewerRole,
    approvalStage,
    exitTypes,
    reimbursementAmounts,
}) {
    const { data, setData, put, processing, errors } = useForm({
        permit_date: exitPermit.permit_date ?? '',
        start_time: exitPermit.start_time ?? '',
        end_time: exitPermit.end_time ?? '',
        destination: exitPermit.destination ?? '',
        exit_type: exitPermit.exit_type ?? exitTypes[0] ?? 'sick',
        vehicle_plate: exitPermit.vehicle_plate ?? '',
        returned_to_office: exitPermit.returned_to_office ?? false,
        reimbursement_amount: exitPermit.reimbursement_amount ?? reimbursementAmounts[0] ?? 12000,
        reason: exitPermit.reason ?? '',
        notes: exitPermit.notes ?? '',
        attachment_photo: null,
        has_valid_checkin: exitPermit.has_valid_checkin ?? true,
        status: canSubmitApproval ? 'approved' : (exitPermit.status ?? 'pending'),
    });

    const submit = (e) => {
        e.preventDefault();

        if (!canUpdateRequest && !canSubmitApproval && !canArrangeCar && !canVerifyAttendance) {
            return;
        }

        put(route('exit-permits.update', exitPermit.id), { forceFormData: true });
    };

    const formLocked = !canUpdateRequest;
    const vehiclePlateLocked = !(canUpdateRequest || canArrangeCar);
    const isRecapViewer = ['hr', 'admin'].includes(viewerRole);

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-bold leading-tight text-slate-800">
                    Exit Permit Form
                </h2>
            }
        >
            <Head title="Edit Exit Permit" />

            <div className="space-y-6">
                <div className="grid gap-4 rounded-2xl border border-cyan-200 bg-gradient-to-r from-cyan-50 via-white to-slate-50 p-5 md:grid-cols-3">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-700">Current Eligibility</p>
                        <p className="mt-2 text-sm text-slate-700">
                            {exitPermit.eligible_for_meal ? 'Ya, memenuhi ketentuan meal' : 'Belum memenuhi ketentuan meal'}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-700">Reimbursement</p>
                        <p className="mt-2 text-sm text-slate-700">Rp {currencyFormatter.format(data.reimbursement_amount)}</p>
                    </div>
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-700">Approval Stage</p>
                        <p className="mt-2 inline-flex rounded-full bg-slate-900 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-white">
                            {approvalStage}
                        </p>
                    </div>
                </div>

                {canSubmitApproval && (
                    <div className="rounded-lg border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm font-medium text-cyan-900">
                        {viewerRole === 'manager'
                            ? 'Dokumen ini menunggu approval Manager. Jika approve, akan lanjut ke MD.'
                            : 'Dokumen ini menunggu approval MD. Setelah approve MD, dokumen dicatat sebagai diketahui HR.'}
                    </div>
                )}

                {isRecapViewer && (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900">
                        Mode rekap HR/Administrator: data ditampilkan read-only untuk monitoring alur approval.
                    </div>
                )}

                {canArrangeCar && (
                    <div className="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm font-medium text-indigo-900">
                        Tugas arrange mobil/supir: silakan isi field 1.4 No. Police Car, lalu klik tombol simpan.
                    </div>
                )}

                {canVerifyAttendance && (
                    <div className="rounded-lg border border-violet-200 bg-violet-50 px-4 py-3 text-sm font-medium text-violet-900">
                        Verifikasi absensi Sisca: tentukan apakah ada absensi masuk karyawan. Sistem akan otomatis mengarahkan jalur Meal atau Reimbursement.
                    </div>
                )}

                <form
                    onSubmit={submit}
                    className="overflow-hidden rounded-2xl border-2 border-slate-800 bg-white shadow-[0_10px_40px_-20px_rgba(2,6,23,0.65)]"
                >
                    <div className="border-b-2 border-slate-800 bg-slate-100 px-6 py-4 text-center">
                        <p className="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Official Document</p>
                        <h3 className="mt-1 text-3xl font-black uppercase tracking-wider text-slate-900">Exit Permit Form</h3>
                    </div>

                    <SectionHeading number="1" title="For Requestor" />

                    <div className="space-y-5 px-4 py-5 md:px-6">
                        <div className="overflow-x-auto rounded-lg border border-slate-300">
                            <table className="min-w-full border-collapse text-xs md:text-sm">
                                <thead className="bg-slate-100 text-slate-700">
                                    <tr>
                                        <th className="border border-slate-300 px-3 py-2 text-left font-semibold">NO</th>
                                        <th className="border border-slate-300 px-3 py-2 text-left font-semibold">NAME</th>
                                        <th className="border border-slate-300 px-3 py-2 text-left font-semibold">EMPLOYEE ID</th>
                                        <th className="border border-slate-300 px-3 py-2 text-left font-semibold">POSITION</th>
                                        <th className="border border-slate-300 px-3 py-2 text-left font-semibold">DEPARTMENT</th>
                                        <th className="border border-slate-300 px-3 py-2 text-left font-semibold">REIMBURS LUNCH BOX (Y/N)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td className="border border-slate-300 px-3 py-2 align-top">1.</td>
                                        <td className="border border-slate-300 px-3 py-2">
                                            <p className="font-medium text-slate-700">Pemohon (akun terkait)</p>
                                        </td>
                                        <td className="border border-slate-300 px-3 py-2 text-slate-500">Isi dari data HRIS</td>
                                        <td className="border border-slate-300 px-3 py-2 text-slate-500">Isi dari data HRIS</td>
                                        <td className="border border-slate-300 px-3 py-2 text-slate-500">Isi dari data HRIS</td>
                                        <td className="border border-slate-300 px-3 py-2">
                                            <span
                                                className={
                                                    `inline-flex rounded-full px-3 py-1 text-xs font-semibold ` +
                                                    (exitPermit.eligible_for_meal
                                                        ? 'bg-emerald-100 text-emerald-700'
                                                        : 'bg-slate-100 text-slate-600')
                                                }
                                            >
                                                {exitPermit.eligible_for_meal ? 'Y' : 'N'}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div className="grid gap-4 rounded-lg border border-slate-200 bg-slate-50 p-4 md:grid-cols-2">
                            <div>
                                <label htmlFor="permit_date" className="text-sm font-semibold text-slate-800">1.2 Request permit date</label>
                                <input
                                    id="permit_date"
                                    type="date"
                                    className={inputClass}
                                    value={data.permit_date}
                                    required
                                    disabled={formLocked}
                                    onChange={(e) => setData('permit_date', e.target.value)}
                                />
                                <InputError message={errors.permit_date} className="mt-2" />
                            </div>

                            <div>
                                <label className="text-sm font-semibold text-slate-800">Reason</label>
                                <div className="mt-1 grid grid-cols-2 gap-2 text-sm">
                                    <button
                                        type="button"
                                        disabled={formLocked}
                                        className={
                                            `rounded-md border px-3 py-2 text-left transition disabled:cursor-not-allowed disabled:opacity-60 ` +
                                            (data.exit_type === 'sick'
                                                ? 'border-cyan-600 bg-cyan-50 text-cyan-700'
                                                : 'border-slate-300 bg-white text-slate-600 hover:border-slate-400')
                                        }
                                        onClick={() => setData('exit_type', 'sick')}
                                    >
                                        Sick / Personal
                                    </button>
                                    <button
                                        type="button"
                                        disabled={formLocked}
                                        className={
                                            `rounded-md border px-3 py-2 text-left transition disabled:cursor-not-allowed disabled:opacity-60 ` +
                                            (data.exit_type === 'business_trip'
                                                ? 'border-cyan-600 bg-cyan-50 text-cyan-700'
                                                : 'border-slate-300 bg-white text-slate-600 hover:border-slate-400')
                                        }
                                        onClick={() => setData('exit_type', 'business_trip')}
                                    >
                                        Assignment / Company
                                    </button>
                                </div>
                                <InputError message={errors.exit_type} className="mt-2" />
                            </div>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label htmlFor="destination" className="text-sm font-semibold text-slate-800">Destination</label>
                                <input
                                    id="destination"
                                    type="text"
                                    className={inputClass}
                                    value={data.destination}
                                    required
                                    disabled={formLocked}
                                    onChange={(e) => setData('destination', e.target.value)}
                                />
                                <InputError message={errors.destination} className="mt-2" />
                            </div>

                            <div>
                                <label htmlFor="reimbursement_amount" className="text-sm font-semibold text-slate-800">Reimbursement</label>
                                <select
                                    id="reimbursement_amount"
                                    className={inputClass}
                                    value={data.reimbursement_amount}
                                    required
                                    disabled={formLocked}
                                    onChange={(e) => setData('reimbursement_amount', Number(e.target.value))}
                                >
                                    {reimbursementAmounts.map((amount) => (
                                        <option key={amount} value={amount}>
                                            Rp {currencyFormatter.format(amount)}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.reimbursement_amount} className="mt-2" />
                            </div>
                        </div>

                        <p className="text-xs text-slate-500">
                            Please attach related document (Invitation, email, atau dokumen pendukung lain).
                        </p>

                        <div className="grid gap-4 md:grid-cols-3">
                            <div>
                                <label htmlFor="start_time" className="text-sm font-semibold text-slate-800">Exit from factory on time</label>
                                <input
                                    id="start_time"
                                    type="time"
                                    className={inputClass}
                                    value={data.start_time}
                                    required
                                    disabled={formLocked}
                                    onChange={(e) => setData('start_time', e.target.value)}
                                />
                                <InputError message={errors.start_time} className="mt-2" />
                            </div>
                            <div>
                                <label htmlFor="end_time" className="text-sm font-semibold text-slate-800">Plan back time</label>
                                <input
                                    id="end_time"
                                    type="time"
                                    className={inputClass}
                                    value={data.end_time}
                                    required
                                    disabled={formLocked}
                                    onChange={(e) => setData('end_time', e.target.value)}
                                />
                                <InputError message={errors.end_time} className="mt-2" />
                            </div>
                            <label className="flex items-center gap-3 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700">
                                <input
                                    type="checkbox"
                                    className="rounded border-slate-300 text-cyan-700 focus:ring-cyan-500"
                                    checked={data.returned_to_office}
                                    disabled={formLocked}
                                    onChange={(e) => setData('returned_to_office', e.target.checked)}
                                />
                                Comeback to factory today
                            </label>
                        </div>

                        {data.exit_type === 'business_trip' && (
                            <div>
                                <label htmlFor="vehicle_plate" className="text-sm font-semibold text-slate-800">1.3 No. Police Car</label>
                                <input
                                    id="vehicle_plate"
                                    type="text"
                                    className={`${inputClass} uppercase`}
                                    value={data.vehicle_plate}
                                    disabled={vehiclePlateLocked}
                                    onChange={(e) => setData('vehicle_plate', e.target.value.toUpperCase())}
                                />
                                <InputError message={errors.vehicle_plate} className="mt-2" />
                            </div>
                        )}

                        <div>
                            <label htmlFor="reason" className="text-sm font-semibold text-slate-800">1.4 Detail the reasons</label>
                            <textarea
                                id="reason"
                                className={inputClass}
                                rows="4"
                                value={data.reason}
                                required
                                disabled={formLocked}
                                onChange={(e) => setData('reason', e.target.value)}
                            />
                            <InputError message={errors.reason} className="mt-2" />
                        </div>

                        <div>
                            <label htmlFor="notes" className="text-sm font-semibold text-slate-800">1.5 Permitted by (Department/Section Head & HR/GA)</label>
                            <textarea
                                id="notes"
                                className={inputClass}
                                rows="3"
                                value={data.notes}
                                required
                                disabled={formLocked}
                                onChange={(e) => setData('notes', e.target.value)}
                            />
                            <InputError message={errors.notes} className="mt-2" />
                        </div>

                        <div>
                            <label htmlFor="attachment_photo" className="text-sm font-semibold text-slate-800">Attachment Foto (Opsional, Max 2MB)</label>

                            {exitPermit.attachment_original_name && exitPermit.attachment_url && (
                                <p className="mt-1 text-xs text-slate-600">
                                    File saat ini:{' '}
                                    <a href={exitPermit.attachment_url} className="font-semibold text-cyan-700 underline" target="_blank" rel="noreferrer">
                                        {exitPermit.attachment_original_name}
                                    </a>
                                </p>
                            )}

                            <input
                                id="attachment_photo"
                                type="file"
                                accept="image/*"
                                className={inputClass}
                                disabled={formLocked}
                                onChange={(e) => setData('attachment_photo', e.target.files?.[0] ?? null)}
                            />
                            <p className="mt-1 text-xs text-slate-500">Upload file baru untuk mengganti attachment lama. Maksimal 2MB.</p>
                            <InputError message={errors.attachment_photo} className="mt-2" />
                        </div>

                        {canSubmitApproval && (
                            <div>
                                <label htmlFor="status" className="text-sm font-semibold text-slate-800">Approval Status</label>
                                <select
                                    id="status"
                                    className={inputClass}
                                    value={data.status}
                                    onChange={(e) => setData('status', e.target.value)}
                                >
                                    <option value="approved">Approved</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                                <InputError message={errors.status} className="mt-2" />
                            </div>
                        )}

                        {canVerifyAttendance && (
                            <div>
                                <label htmlFor="has_valid_checkin" className="text-sm font-semibold text-slate-800">Verifikasi Absensi Masuk</label>
                                <select
                                    id="has_valid_checkin"
                                    className={inputClass}
                                    value={data.has_valid_checkin ? '1' : '0'}
                                    onChange={(e) => setData('has_valid_checkin', e.target.value === '1')}
                                >
                                    <option value="1">Ada absensi masuk (jalur meal jika kembali kantor)</option>
                                    <option value="0">Tidak ada absensi masuk (jalur reimbursement)</option>
                                </select>
                                <InputError message={errors.has_valid_checkin} className="mt-2" />

                                {exitPermit.attendance_checked_at && (
                                    <p className="mt-2 text-xs text-slate-500">
                                        Terakhir diverifikasi: {exitPermit.attendance_checked_at}
                                    </p>
                                )}
                            </div>
                        )}

                        <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900">
                            Please fill in properly, this document is attachment for gasoline, toll, parking and lunch reimbursement.
                        </div>
                    </div>

                    <SectionHeading number="2" title="Special Fulfill by Security" />

                    <div className="grid gap-4 px-4 py-5 md:grid-cols-2 md:px-6">
                        <div className="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                            <p className="font-semibold text-slate-900">2.1 Exit from factory time</p>
                            <p className="mt-1">Diisi oleh petugas security saat karyawan keluar gate.</p>
                        </div>
                        <div className="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                            <p className="font-semibold text-slate-900">2.2 Comeback to factory time</p>
                            <p className="mt-1">Diisi oleh petugas security saat karyawan kembali ke area pabrik.</p>
                        </div>
                    </div>

                    <div className="flex flex-col-reverse gap-3 border-t border-slate-200 bg-slate-50 px-4 py-4 sm:flex-row sm:justify-end md:px-6">
                        <Link
                            href={route('exit-permits.index')}
                            className="rounded-md border border-slate-300 px-4 py-2 text-center text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                        >
                            Kembali
                        </Link>
                        {(canUpdateRequest || canSubmitApproval || canArrangeCar || canVerifyAttendance) && (
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {canSubmitApproval
                                    ? 'Submit Approval'
                                    : canArrangeCar
                                        ? 'Simpan Arrange Car'
                                        : canVerifyAttendance
                                            ? 'Simpan Verifikasi Absensi'
                                        : 'Update Exit Permit'}
                            </button>
                        )}
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
