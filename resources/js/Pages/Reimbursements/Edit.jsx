import InputError from '@/Components/InputError';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect } from 'react';

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
    const defaultActionStatus = canApproveManager || canApproveMd
        ? 'approved'
        : canSubmitRatna
            ? 'submitted_to_accounting'
            : canFinishAccounting
                ? 'finished'
                : reimbursement.status;

    const { data, setData, put, processing, errors } = useForm({
        request_date: reimbursement.request_date ?? '',
        paid_to: reimbursement.paid_to ?? '',
        amount: reimbursement.amount ?? 0,
        amount_order_meal: reimbursement.amount_order_meal ?? reimbursement.amount ?? 0,
        amount_fuel: reimbursement.amount_fuel ?? 0,
        amount_toll: reimbursement.amount_toll ?? 0,
        amount_in_words: reimbursement.amount_in_words ?? '',
        expense_type: reimbursement.expense_type ?? '',
        purpose: reimbursement.purpose ?? '',
        ref_document: reimbursement.ref_document ?? '',
        documents: (reimbursement.documents ?? []).length > 0
            ? reimbursement.documents.map((doc) => ({
                id: doc.id,
                ref_document: doc.ref_document ?? '',
                attachment_file: null,
                existing_attachment_original_name: doc.attachment_original_name ?? null,
                existing_attachment_url: doc.attachment_url ?? null,
            }))
            : [{ id: null, ref_document: reimbursement.ref_document ?? '', attachment_file: null, existing_attachment_original_name: null, existing_attachment_url: null }],
        description: reimbursement.description ?? '',
        attachment_file: null,
        status: defaultActionStatus,
    });

    const submit = (e) => {
        e.preventDefault();

        if (!canUpdateRequest && !canApproveManager && !canApproveMd && !canSubmitRatna && !canFinishAccounting) {
            return;
        }

        put(route('reimbursements.update', reimbursement.id), { forceFormData: true });
    };

    const formLocked = !canUpdateRequest;

    const addDocumentRow = () => {
        if (formLocked) {
            return;
        }

        setData('documents', [
            ...(data.documents ?? []),
            { id: null, ref_document: '', attachment_file: null, existing_attachment_original_name: null, existing_attachment_url: null },
        ]);
    };

    const removeDocumentRow = (index) => {
        if (formLocked) {
            return;
        }

        const currentRows = data.documents ?? [];

        if (currentRows.length <= 1) {
            return;
        }

        setData('documents', currentRows.filter((_, rowIndex) => rowIndex !== index));
    };

    const updateDocumentRef = (index, value) => {
        if (formLocked) {
            return;
        }

        const nextRows = [...(data.documents ?? [])];
        nextRows[index] = {
            ...nextRows[index],
            ref_document: value,
        };
        setData('documents', nextRows);
    };

    const updateDocumentFile = (index, file) => {
        if (formLocked) {
            return;
        }

        const nextRows = [...(data.documents ?? [])];
        nextRows[index] = {
            ...nextRows[index],
            attachment_file: file,
        };
        setData('documents', nextRows);
    };

    useEffect(() => {
        const totalAmount = Number(data.amount_order_meal || 0)
            + Number(data.amount_fuel || 0)
            + Number(data.amount_toll || 0);

        if (Number(data.amount || 0) !== totalAmount) {
            setData('amount', totalAmount);
        }
    }, [data.amount_order_meal, data.amount_fuel, data.amount_toll]);

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
                            <label htmlFor="request_date" className="text-sm font-semibold text-slate-800">Tgl Bayar / Payment Date</label>
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
                            <label htmlFor="paid_to" className="text-sm font-semibold text-slate-800">Dibayar Kepada / Paid To</label>
                            <input
                                id="paid_to"
                                type="text"
                                className={inputClass}
                                value={data.paid_to}
                                disabled={formLocked}
                                onChange={(e) => setData('paid_to', e.target.value)}
                                required
                            />
                            <InputError message={errors.paid_to} className="mt-2" />
                        </div>

                        <div>
                            <label htmlFor="amount_order_meal" className="text-sm font-semibold text-slate-800">Biaya Order Meal</label>
                            <input
                                id="amount_order_meal"
                                type="number"
                                min="0"
                                className={inputClass}
                                value={data.amount_order_meal}
                                disabled={formLocked}
                                onChange={(e) => setData('amount_order_meal', Number(e.target.value || 0))}
                                required
                            />
                            <InputError message={errors.amount_order_meal} className="mt-2" />
                        </div>

                        <div>
                            <label htmlFor="amount_fuel" className="text-sm font-semibold text-slate-800">Biaya Bensin</label>
                            <input
                                id="amount_fuel"
                                type="number"
                                min="0"
                                className={inputClass}
                                value={data.amount_fuel}
                                disabled={formLocked}
                                onChange={(e) => setData('amount_fuel', Number(e.target.value || 0))}
                                required
                            />
                            <InputError message={errors.amount_fuel} className="mt-2" />
                        </div>

                        <div>
                            <label htmlFor="amount_toll" className="text-sm font-semibold text-slate-800">Biaya Tol</label>
                            <input
                                id="amount_toll"
                                type="number"
                                min="0"
                                className={inputClass}
                                value={data.amount_toll}
                                disabled={formLocked}
                                onChange={(e) => setData('amount_toll', Number(e.target.value || 0))}
                                required
                            />
                            <InputError message={errors.amount_toll} className="mt-2" />
                        </div>

                        <div>
                            <label htmlFor="amount" className="text-sm font-semibold text-slate-800">Jumlah / Amount (Total Otomatis)</label>
                            <input
                                id="amount"
                                type="number"
                                min="0"
                                className={inputClass}
                                value={data.amount}
                                readOnly
                                disabled
                            />
                            <p className="mt-1 text-xs text-slate-500">Rp {currencyFormatter.format(data.amount ?? 0)}</p>
                            <InputError message={errors.amount} className="mt-2" />
                        </div>

                        <div>
                            <label htmlFor="amount_in_words" className="text-sm font-semibold text-slate-800">Terbilang / Stated</label>
                            <input
                                id="amount_in_words"
                                type="text"
                                className={inputClass}
                                value={data.amount_in_words}
                                disabled={formLocked}
                                onChange={(e) => setData('amount_in_words', e.target.value)}
                                required
                            />
                            <InputError message={errors.amount_in_words} className="mt-2" />
                        </div>
                    </div>

                    <div>
                        <label htmlFor="expense_type" className="text-sm font-semibold text-slate-800">Jenis Biaya / Expense Type</label>
                        <input
                            id="expense_type"
                            type="text"
                            className={inputClass}
                            value={data.expense_type}
                            disabled={formLocked}
                            onChange={(e) => setData('expense_type', e.target.value)}
                            required
                        />
                        <InputError message={errors.expense_type} className="mt-2" />
                    </div>

                    <div>
                        <label htmlFor="purpose" className="text-sm font-semibold text-slate-800">Tujuan / Purpose</label>
                        <textarea
                            id="purpose"
                            rows="3"
                            className={inputClass}
                            value={data.purpose}
                            disabled={formLocked}
                            onChange={(e) => setData('purpose', e.target.value)}
                            required
                        />
                        <InputError message={errors.purpose} className="mt-2" />
                    </div>

                    <div className="space-y-3">
                        <div className="flex items-center justify-between">
                            <p className="text-sm font-semibold text-slate-800">Dok Ref / Attachment</p>
                            {!formLocked && (
                                <button
                                    type="button"
                                    onClick={addDocumentRow}
                                    className="rounded-md border border-cyan-300 bg-cyan-50 px-3 py-1.5 text-xs font-semibold text-cyan-800 transition hover:bg-cyan-100"
                                >
                                    + Tambah Baris
                                </button>
                            )}
                        </div>

                        {(data.documents ?? []).map((doc, index) => (
                            <div key={`doc-row-${doc.id ?? 'new'}-${index}`} className="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 md:grid-cols-[1fr_1fr_auto]">
                                <div>
                                    <label htmlFor={`documents-${index}-ref`} className="text-xs font-semibold uppercase tracking-wider text-slate-600">Dok Ref</label>
                                    <input
                                        id={`documents-${index}-ref`}
                                        type="text"
                                        className={inputClass}
                                        value={doc.ref_document ?? ''}
                                        disabled={formLocked}
                                        onChange={(e) => updateDocumentRef(index, e.target.value)}
                                    />
                                    <InputError message={errors[`documents.${index}.ref_document`]} className="mt-2" />
                                </div>

                                <div>
                                    <label htmlFor={`documents-${index}-file`} className="text-xs font-semibold uppercase tracking-wider text-slate-600">Attachment (JPG/PNG/PDF)</label>
                                    {doc.existing_attachment_original_name && doc.existing_attachment_url && (
                                        <p className="mt-1 text-xs text-slate-600">
                                            File saat ini:{' '}
                                            <a href={doc.existing_attachment_url} className="font-semibold text-cyan-700 underline" target="_blank" rel="noreferrer">
                                                {doc.existing_attachment_original_name}
                                            </a>
                                        </p>
                                    )}
                                    <input
                                        id={`documents-${index}-file`}
                                        type="file"
                                        accept=".jpg,.jpeg,.png,.pdf"
                                        className={inputClass}
                                        disabled={formLocked}
                                        onChange={(e) => updateDocumentFile(index, e.target.files?.[0] ?? null)}
                                    />
                                    <InputError message={errors[`documents.${index}.attachment_file`]} className="mt-2" />
                                </div>

                                <div className="flex items-end">
                                    {!formLocked && (
                                        <button
                                            type="button"
                                            onClick={() => removeDocumentRow(index)}
                                            disabled={(data.documents ?? []).length <= 1}
                                            className="rounded-md bg-rose-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-rose-500 disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            Hapus
                                        </button>
                                    )}
                                </div>
                            </div>
                        ))}

                        <InputError message={errors.documents} className="mt-2" />
                    </div>

                    <div>
                        <label htmlFor="description" className="text-sm font-semibold text-slate-800">Catatan Tambahan</label>
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
                                    <option value="submitted_to_accounting">Check &amp; Submit ke Accounting</option>
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
