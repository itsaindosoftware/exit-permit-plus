import InputError from '@/Components/InputError';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const currencyFormatter = new Intl.NumberFormat('id-ID');

const inputClass =
    'mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-cyan-600 focus:ring-2 focus:ring-cyan-200';

export default function Edit({
    reimbursement,
    canUpdateRequest,
    canApproveManager,
    canApproveMd,
    canSubmitRatna,
    canFinishAccounting,
}) {
    const { data, setData, put, processing, errors } = useForm({
        request_date: reimbursement.request_date ?? '',
        amount: reimbursement.amount ?? 0,
        description: reimbursement.description ?? '',
        status: canApproveManager || canApproveMd ? 'approved' : reimbursement.status,
    });

    const submit = (e) => {
        e.preventDefault();

        if (!canUpdateRequest && !canApproveManager && !canApproveMd && !canSubmitRatna && !canFinishAccounting) {
            return;
        }

        put(route('reimbursements.update', reimbursement.id));
    };

    const formLocked = !canUpdateRequest;

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-bold leading-tight text-slate-800">
                    Detail Reimbursement
                </h2>
            }
        >
            <Head title="Detail Reimbursement" />

            <div className="space-y-6">
                <div className="rounded-2xl border border-cyan-200 bg-gradient-to-r from-cyan-50 via-white to-slate-50 p-5">
                    <p className="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-700">Reimbursement Status</p>
                    <p className="mt-2 inline-flex rounded-full bg-slate-900 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-white">
                        {reimbursement.approval_stage}
                    </p>
                </div>

                <form
                    onSubmit={submit}
                    className="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                >
                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Exit Permit</p>
                            <p className="mt-1 text-sm text-slate-800">{reimbursement.exit_permit_label}</p>
                        </div>
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</p>
                            <p className="mt-1 text-sm text-slate-800">{reimbursement.status}</p>
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label htmlFor="request_date" className="text-sm font-semibold text-slate-800">Tanggal Pengajuan</label>
                            <input
                                id="request_date"
                                type="date"
                                className={inputClass}
                                value={data.request_date}
                                disabled={formLocked}
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
                                disabled={formLocked}
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
                            disabled={formLocked}
                            onChange={(e) => setData('description', e.target.value)}
                        />
                        <InputError message={errors.description} className="mt-2" />
                    </div>

                    {(canApproveManager || canApproveMd || canSubmitRatna || canFinishAccounting) && (
                        <div>
                            <label htmlFor="status" className="text-sm font-semibold text-slate-800">Action</label>
                            <select
                                id="status"
                                className={inputClass}
                                value={data.status}
                                onChange={(e) => setData('status', e.target.value)}
                            >
                                {canApproveManager && (
                                    <>
                                        <option value="approved">Approve (lanjut ke MD)</option>
                                        <option value="rejected">Reject</option>
                                    </>
                                )}
                                {canApproveMd && (
                                    <>
                                        <option value="approved">Approve (lanjut ke Ratna)</option>
                                        <option value="rejected">Reject</option>
                                    </>
                                )}
                                {canSubmitRatna && (
                                    <option value="submitted_to_accounting">Submit ke Accounting</option>
                                )}
                                {canFinishAccounting && (
                                    <option value="finished">Tandai Finish</option>
                                )}
                            </select>
                            <InputError message={errors.status} className="mt-2" />
                        </div>
                    )}

                    <div className="flex flex-col-reverse gap-3 border-t border-slate-200 pt-4 sm:flex-row sm:justify-end">
                        <Link
                            href={route('reimbursements.index')}
                            className="rounded-md border border-slate-300 px-4 py-2 text-center text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                        >
                            Kembali
                        </Link>

                        {(canUpdateRequest || canApproveManager || canApproveMd || canSubmitRatna || canFinishAccounting) && (
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {canUpdateRequest
                                    ? 'Update Reimbursement'
                                    : canApproveManager || canApproveMd
                                        ? 'Submit Approval'
                                        : canSubmitRatna
                                            ? 'Submit Accounting'
                                            : 'Finish Reimbursement'}
                            </button>
                        )}
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
