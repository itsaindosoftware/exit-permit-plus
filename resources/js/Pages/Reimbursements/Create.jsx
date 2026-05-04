import InputError from '@/Components/InputError';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const currencyFormatter = new Intl.NumberFormat('id-ID');

const inputClass =
    'mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-cyan-600 focus:ring-2 focus:ring-cyan-200';

export default function Create({ eligibleExitPermits }) {
    const firstPermitId = eligibleExitPermits?.[0]?.id ?? '';

    const { data, setData, post, processing, errors } = useForm({
        exit_permit_id: firstPermitId,
        request_date: new Date().toISOString().slice(0, 10),
        amount: 0,
        description: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('reimbursements.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-bold leading-tight text-slate-800">
                    Ajukan Reimbursement
                </h2>
            }
        >
            <Head title="Ajukan Reimbursement" />

            <div className="space-y-6">
                <div className="rounded-2xl border border-cyan-200 bg-gradient-to-r from-cyan-50 via-white to-slate-50 p-5">
                    <p className="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-700">Reimbursement Form</p>
                    <p className="mt-2 text-sm text-slate-700">
                        Pilih Exit Permit dengan jalur reimbursement untuk mengajukan claim ke alur approval.
                    </p>
                </div>

                {!eligibleExitPermits?.length && (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        Tidak ada Exit Permit yang memenuhi syarat reimbursement saat ini.
                    </div>
                )}

                <form
                    onSubmit={submit}
                    className="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                >
                    <div>
                        <label htmlFor="exit_permit_id" className="text-sm font-semibold text-slate-800">Exit Permit</label>
                        <select
                            id="exit_permit_id"
                            className={inputClass}
                            value={data.exit_permit_id}
                            disabled={!eligibleExitPermits?.length}
                            onChange={(e) => setData('exit_permit_id', Number(e.target.value))}
                            required
                        >
                            {eligibleExitPermits?.map((permit) => (
                                <option key={permit.id} value={permit.id}>{permit.label}</option>
                            ))}
                        </select>
                        <InputError message={errors.exit_permit_id} className="mt-2" />
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label htmlFor="request_date" className="text-sm font-semibold text-slate-800">Tanggal Pengajuan</label>
                            <input
                                id="request_date"
                                type="date"
                                className={inputClass}
                                value={data.request_date}
                                onChange={(e) => setData('request_date', e.target.value)}
                                required
                            />
                            <InputError message={errors.request_date} className="mt-2" />
                        </div>

                        <div>
                            <label htmlFor="amount" className="text-sm font-semibold text-slate-800">Nominal</label>
                            <input
                                id="amount"
                                type="number"
                                min="0"
                                className={inputClass}
                                value={data.amount}
                                onChange={(e) => setData('amount', Number(e.target.value || 0))}
                                required
                            />
                            <p className="mt-1 text-xs text-slate-500">Rp {currencyFormatter.format(data.amount ?? 0)}</p>
                            <InputError message={errors.amount} className="mt-2" />
                        </div>
                    </div>

                    <div>
                        <label htmlFor="description" className="text-sm font-semibold text-slate-800">Keterangan</label>
                        <textarea
                            id="description"
                            rows="4"
                            className={inputClass}
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                        />
                        <InputError message={errors.description} className="mt-2" />
                    </div>

                    <div className="flex flex-col-reverse gap-3 border-t border-slate-200 pt-4 sm:flex-row sm:justify-end">
                        <Link
                            href={route('reimbursements.index')}
                            className="rounded-md border border-slate-300 px-4 py-2 text-center text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                        >
                            Kembali
                        </Link>
                        <button
                            type="submit"
                            disabled={processing || !eligibleExitPermits?.length}
                            className="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            Submit Reimbursement
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
