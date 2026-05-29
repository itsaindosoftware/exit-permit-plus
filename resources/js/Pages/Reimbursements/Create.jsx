import InputError from '@/Components/InputError';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect } from 'react';

const currencyFormatter = new Intl.NumberFormat('en-US');

const toTitleCaseWords = (text) => {
    return String(text)
        .toLowerCase()
        .split(/\s+/)
        .filter(Boolean)
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};

const numberToEnglishWords = (value) => {
    const angka = Math.floor(Math.abs(Number(value) || 0));

    const toWords = (n) => {
        const ones = [
            '',
            'one',
            'two',
            'three',
            'four',
            'five',
            'six',
            'seven',
            'eight',
            'nine',
            'ten',
            'eleven',
            'twelve',
            'thirteen',
            'fourteen',
            'fifteen',
            'sixteen',
            'seventeen',
            'eighteen',
            'nineteen',
        ];
        const tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

        if (n < 20) {
            return ones[n];
        }

        if (n < 100) {
            const ten = Math.floor(n / 10);
            const rest = n % 10;
            return `${tens[ten]}${rest ? ` ${ones[rest]}` : ''}`.trim();
        }

        if (n < 1000) {
            const hundred = Math.floor(n / 100);
            const rest = n % 100;
            return `${ones[hundred]} hundred${rest ? ` ${toWords(rest)}` : ''}`.trim();
        }

        if (n < 1000000) {
            const thousand = Math.floor(n / 1000);
            const rest = n % 1000;
            return `${toWords(thousand)} thousand${rest ? ` ${toWords(rest)}` : ''}`.trim();
        }

        if (n < 1000000000) {
            const million = Math.floor(n / 1000000);
            const rest = n % 1000000;
            return `${toWords(million)} million${rest ? ` ${toWords(rest)}` : ''}`.trim();
        }

        if (n < 1000000000000) {
            const billion = Math.floor(n / 1000000000);
            const rest = n % 1000000000;
            return `${toWords(billion)} billion${rest ? ` ${toWords(rest)}` : ''}`.trim();
        }

        if (n < 1000000000000000) {
            const trillion = Math.floor(n / 1000000000000);
            const rest = n % 1000000000000;
            return `${toWords(trillion)} trillion${rest ? ` ${toWords(rest)}` : ''}`.trim();
        }

        return 'too large';
    };

    if (angka === 0) {
        return toTitleCaseWords('zero rupiah');
    }

    return toTitleCaseWords(`${toWords(angka).replace(/\s+/g, ' ').trim()} rupiah`);
};

const inputClass =
    'mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-cyan-600 focus:ring-2 focus:ring-cyan-200';

export default function Create({ eligibleExitPermits, formSource = 'internal', blockedMessage = '' }) {
    const isFromExitPermit = formSource === 'exit_permit';
    const firstPermitId = isFromExitPermit ? (eligibleExitPermits?.[0]?.id ?? '') : '';
    const selectedPermit = isFromExitPermit
        ? (eligibleExitPermits?.find((permit) => String(permit.id) === String(firstPermitId)) ?? null)
        : null;

    const { data, setData, post, processing, errors } = useForm({
        source: formSource,
        exit_permit_id: firstPermitId,
        request_date: selectedPermit?.permit_date ?? new Date().toISOString().slice(0, 10),
        paid_to: selectedPermit?.paid_to_default ?? 'Internal Request',
        amount: Number(selectedPermit?.suggested_amount ?? 0),
        amount_order_meal: Number(selectedPermit?.amount_order_meal_default ?? selectedPermit?.suggested_amount ?? 0),
        amount_fuel: Number(selectedPermit?.amount_fuel_default ?? 0),
        amount_toll: Number(selectedPermit?.amount_toll_default ?? 0),
        amount_in_words: '',
        expense_type: selectedPermit?.expense_type_default ?? (isFromExitPermit ? '' : 'ITSA Goods Request'),
        purpose: selectedPermit?.purpose_default ?? '',
        ref_document: selectedPermit?.ref_document_default ?? '',
        documents: [
            {
                ref_document: selectedPermit?.ref_document_default ?? '',
                attachment_file: null,
            },
        ],
        attachment_file: null,
        description: selectedPermit?.description_default ?? '',
    });

    const activePermit = eligibleExitPermits?.find((permit) => String(permit.id) === String(data.exit_permit_id)) ?? null;

    useEffect(() => {
        if (!isFromExitPermit) {
            return;
        }

        if (!activePermit) {
            return;
        }

        setData('request_date', activePermit.permit_date ?? new Date().toISOString().slice(0, 10));
        setData('paid_to', activePermit.paid_to_default ?? '');
        setData('amount_order_meal', Number(activePermit.amount_order_meal_default ?? activePermit.suggested_amount ?? 0));
        setData('amount_fuel', Number(activePermit.amount_fuel_default ?? 0));
        setData('amount_toll', Number(activePermit.amount_toll_default ?? 0));
        setData('amount', Number(activePermit.suggested_amount ?? 0));
        setData('expense_type', activePermit.expense_type_default ?? 'Reimbursement Exit Permit');
        setData('purpose', activePermit.purpose_default ?? '');
        setData('ref_document', activePermit.ref_document_default ?? '');
        setData('documents', [
            {
                ref_document: activePermit.ref_document_default ?? '',
                attachment_file: null,
            },
        ]);
        setData('description', activePermit.description_default ?? '');
    }, [isFromExitPermit, data.exit_permit_id, eligibleExitPermits]);

    useEffect(() => {
        const totalAmount = Number(data.amount_order_meal || 0)
            + Number(data.amount_fuel || 0)
            + Number(data.amount_toll || 0);

        if (Number(data.amount || 0) !== totalAmount) {
            setData('amount', totalAmount);
        }

        const words = numberToEnglishWords(totalAmount);
        if ((data.amount_in_words ?? '') !== words) {
            setData('amount_in_words', words);
        }
    }, [data.amount_order_meal, data.amount_fuel, data.amount_toll]);

    const submit = (e) => {
        e.preventDefault();
        post(route('reimbursements.store'), { forceFormData: true });
    };

    const addDocumentRow = () => {
        const nextRows = [
            ...(data.documents ?? []),
            { ref_document: '', attachment_file: null },
        ];

        setData('documents', nextRows);
    };

    const removeDocumentRow = (index) => {
        const currentRows = data.documents ?? [];

        if (currentRows.length <= 1) {
            return;
        }

        setData('documents', currentRows.filter((_, rowIndex) => rowIndex !== index));
    };

    const updateDocumentRef = (index, value) => {
        const nextRows = [...(data.documents ?? [])];
        nextRows[index] = {
            ...nextRows[index],
            ref_document: value,
        };
        setData('documents', nextRows);
    };

    const updateDocumentFile = (index, file) => {
        const nextRows = [...(data.documents ?? [])];
        nextRows[index] = {
            ...nextRows[index],
            attachment_file: file,
        };
        setData('documents', nextRows);
    };

    const updateInternalAttachment = (file) => {
        setData('attachment_file', file);
    };

    if (blockedMessage) {
        return (
            <AuthenticatedLayout
                header={
                    <h2 className="text-xl font-bold leading-tight text-slate-800">
                        {isFromExitPermit ? 'From Exit Permit' : 'Create New Reimbursement'}
                    </h2>
                }
            >
                <Head title={isFromExitPermit ? 'From Exit Permit' : 'Create New Reimbursement'} />

                <div className="space-y-6">
                    <div className="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-rose-800 shadow-sm">
                        <p className="text-xs font-semibold uppercase tracking-[0.24em] text-rose-700">Reimbursement Form</p>
                        <p className="mt-2 text-sm font-medium">{blockedMessage}</p>
                    </div>

                    <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <Link
                            href={route('reimbursements.index')}
                            className="inline-flex rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700"
                        >
                            Back to Reimbursement List
                        </Link>
                    </div>
                </div>
            </AuthenticatedLayout>
        );
    }

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-bold leading-tight text-slate-800">
                    {isFromExitPermit ? 'From Exit Permit' : 'Create New Reimbursement'}
                </h2>
            }
        >
            <Head title={isFromExitPermit ? 'From Exit Permit' : 'Create New Reimbursement'} />

            <div className="space-y-6">
                <div className="rounded-2xl border border-cyan-200 bg-gradient-to-r from-cyan-50 via-white to-slate-50 p-5">
                    <p className="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-700">Reimbursement Form</p>
                    <p className="mt-2 text-sm text-slate-700">
                        {isFromExitPermit
                            ? 'Select an Exit Permit with status Checked By HR: Sisca to process reimbursement from the Exit Permit flow.'
                            : 'Use this form for internal reimbursement requests for ITSA items/needs outside the Exit Permit flow.'}
                    </p>
                </div>

                {blockedMessage && (
                    <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        {blockedMessage}
                    </div>
                )}

                {isFromExitPermit && !eligibleExitPermits?.length && (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        No Exit Permit currently qualifies for reimbursement.
                    </div>
                )}

                <form
                    onSubmit={submit}
                    className="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                >
                    {isFromExitPermit && activePermit && (
                        <div className="rounded-xl border border-cyan-200 bg-cyan-50 p-4 text-sm text-cyan-900">
                            <p className="font-semibold">Lunch Box Conversion Draft</p>
                            <p className="mt-1">
                                Requestor Y: <span className="font-semibold">{activePermit.requestor_count ?? 0}</span> people,
                                Unit amount: <span className="font-semibold">Rp {currencyFormatter.format(activePermit.unit_amount ?? 0)}</span>,
                                Suggested total: <span className="font-semibold">Rp {currencyFormatter.format(activePermit.suggested_amount ?? 0)}</span>.
                            </p>
                        </div>
                    )}

                    {isFromExitPermit && (
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
                    )}

                    {isFromExitPermit ? (
                        <>
                            <div className="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label htmlFor="request_date" className="text-sm font-semibold text-slate-800">Payment Date</label>
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
                                    <label htmlFor="paid_to" className="text-sm font-semibold text-slate-800">Paid To</label>
                                    <input
                                        id="paid_to"
                                        type="text"
                                        className={inputClass}
                                        value={data.paid_to}
                                        onChange={(e) => setData('paid_to', e.target.value)}
                                        required
                                    />
                                    <InputError message={errors.paid_to} className="mt-2" />
                                </div>

                                <div>
                                    <label htmlFor="amount_order_meal" className="text-sm font-semibold text-slate-800">Meal Order Cost</label>
                                    <input
                                        id="amount_order_meal"
                                        type="number"
                                        min="0"
                                        className={inputClass}
                                        value={data.amount_order_meal}
                                        onChange={(e) => setData('amount_order_meal', Number(e.target.value || 0))}
                                        required
                                    />
                                    <InputError message={errors.amount_order_meal} className="mt-2" />
                                </div>

                                <div>
                                    <label htmlFor="amount_fuel" className="text-sm font-semibold text-slate-800">Fuel Cost</label>
                                    <input
                                        id="amount_fuel"
                                        type="number"
                                        min="0"
                                        className={inputClass}
                                        value={data.amount_fuel}
                                        onChange={(e) => setData('amount_fuel', Number(e.target.value || 0))}
                                        required
                                    />
                                    <InputError message={errors.amount_fuel} className="mt-2" />
                                </div>

                                <div>
                                    <label htmlFor="amount_toll" className="text-sm font-semibold text-slate-800">Toll Cost</label>
                                    <input
                                        id="amount_toll"
                                        type="number"
                                        min="0"
                                        className={inputClass}
                                        value={data.amount_toll}
                                        onChange={(e) => setData('amount_toll', Number(e.target.value || 0))}
                                        required
                                    />
                                    <InputError message={errors.amount_toll} className="mt-2" />
                                </div>

                                <div>
                                    <label htmlFor="amount" className="text-sm font-semibold text-slate-800">Amount (Auto Total)</label>
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
                                    <label htmlFor="amount_in_words" className="text-sm font-semibold text-slate-800">Amount in Words</label>
                                    <input
                                        id="amount_in_words"
                                        type="text"
                                        className={inputClass}
                                        value={data.amount_in_words}
                                        readOnly
                                        disabled
                                        required
                                    />
                                    <InputError message={errors.amount_in_words} className="mt-2" />
                                </div>

                                <div>
                                    <label htmlFor="cost_center_name" className="text-sm font-semibold text-slate-800">Cost Center (Department)</label>
                                    <input
                                        id="cost_center_name"
                                        type="text"
                                        className={inputClass}
                                        value={isFromExitPermit ? (activePermit?.cost_center_name ?? '-') : '-'}
                                        readOnly
                                        disabled
                                    />
                                </div>
                            </div>

                            <div>
                                <label htmlFor="expense_type" className="text-sm font-semibold text-slate-800">Expense Type</label>
                                <input
                                    id="expense_type"
                                    type="text"
                                    className={inputClass}
                                    value={data.expense_type}
                                    onChange={(e) => setData('expense_type', e.target.value)}
                                    required
                                />
                                <InputError message={errors.expense_type} className="mt-2" />
                            </div>

                            <div>
                                <label htmlFor="purpose" className="text-sm font-semibold text-slate-800">Purpose</label>
                                <textarea
                                    id="purpose"
                                    rows="3"
                                    className={inputClass}
                                    value={data.purpose}
                                    onChange={(e) => setData('purpose', e.target.value)}
                                    required
                                />
                                <InputError message={errors.purpose} className="mt-2" />
                            </div>

                            <div className="space-y-3">
                                <div className="flex items-center justify-between">
                                    <p className="text-sm font-semibold text-slate-800">Reference Docs / Attachments</p>
                                    <button
                                        type="button"
                                        onClick={addDocumentRow}
                                        className="rounded-md border border-cyan-300 bg-cyan-50 px-3 py-1.5 text-xs font-semibold text-cyan-800 transition hover:bg-cyan-100"
                                    >
                                        + Add Row
                                    </button>
                                </div>

                                {(data.documents ?? []).map((doc, index) => (
                                    <div key={`doc-row-${index}`} className="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 md:grid-cols-[1fr_1fr_auto]">
                                        <div>
                                            <label htmlFor={`documents-${index}-ref`} className="text-xs font-semibold uppercase tracking-wider text-slate-600">Reference Doc</label>
                                            <input
                                                id={`documents-${index}-ref`}
                                                type="text"
                                                className={inputClass}
                                                value={doc.ref_document ?? ''}
                                                onChange={(e) => updateDocumentRef(index, e.target.value)}
                                            />
                                            <InputError message={errors[`documents.${index}.ref_document`]} className="mt-2" />
                                        </div>

                                        <div>
                                            <label htmlFor={`documents-${index}-file`} className="text-xs font-semibold uppercase tracking-wider text-slate-600">Attachment (JPG/PNG/PDF)</label>
                                            <input
                                                id={`documents-${index}-file`}
                                                type="file"
                                                accept=".jpg,.jpeg,.png,.pdf"
                                                className={inputClass}
                                                onChange={(e) => updateDocumentFile(index, e.target.files?.[0] ?? null)}
                                            />
                                            <InputError message={errors[`documents.${index}.attachment_file`]} className="mt-2" />
                                        </div>

                                        <div className="flex items-end">
                                            <button
                                                type="button"
                                                onClick={() => removeDocumentRow(index)}
                                                disabled={(data.documents ?? []).length <= 1}
                                                className="rounded-md bg-rose-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-rose-500 disabled:cursor-not-allowed disabled:opacity-50"
                                            >
                                                Remove
                                            </button>
                                        </div>
                                    </div>
                                ))}

                                <InputError message={errors.documents} className="mt-2" />
                            </div>

                            <div>
                                <label htmlFor="description" className="text-sm font-semibold text-slate-800">Additional Notes</label>
                                <textarea
                                    id="description"
                                    rows="4"
                                    className={inputClass}
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                />
                                <InputError message={errors.description} className="mt-2" />
                            </div>
                        </>
                    ) : (
                        <>
                            <div className="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label htmlFor="amount_order_meal" className="text-sm font-semibold text-slate-800">Request Amount</label>
                                    <input
                                        id="amount_order_meal"
                                        type="number"
                                        min="0"
                                        className={inputClass}
                                        value={data.amount_order_meal}
                                        onChange={(e) => {
                                            const value = Number(e.target.value || 0);
                                            setData('amount_order_meal', value);
                                            setData('amount_fuel', 0);
                                            setData('amount_toll', 0);
                                        }}
                                        required
                                    />
                                    <InputError message={errors.amount_order_meal} className="mt-2" />
                                </div>

                                <div>
                                    <label htmlFor="purpose" className="text-sm font-semibold text-slate-800">Requested Item</label>
                                    <input
                                        id="purpose"
                                        type="text"
                                        className={inputClass}
                                        value={data.purpose}
                                        onChange={(e) => setData('purpose', e.target.value)}
                                        required
                                    />
                                    <InputError message={errors.purpose} className="mt-2" />
                                </div>

                                <div className="md:col-span-2">
                                    <label htmlFor="attachment_file" className="text-sm font-semibold text-slate-800">Payment Receipt Attachment</label>
                                    <input
                                        id="attachment_file"
                                        type="file"
                                        accept=".jpg,.jpeg,.png,.pdf"
                                        className={inputClass}
                                        onChange={(e) => updateInternalAttachment(e.target.files?.[0] ?? null)}
                                        required
                                    />
                                    <InputError message={errors.attachment_file} className="mt-2" />
                                </div>
                            </div>
                        </>
                    )}

                    <div className="flex flex-col-reverse gap-3 border-t border-slate-200 pt-4 sm:flex-row sm:justify-end">
                        <Link
                            href={route('reimbursements.index')}
                            className="rounded-md border border-slate-300 px-4 py-2 text-center text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                        >
                            Back
                        </Link>
                        <button
                            type="submit"
                            disabled={processing || (isFromExitPermit && !eligibleExitPermits?.length)}
                            className="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {isFromExitPermit ? 'Submit from Exit Permit' : 'Submit New Request'}
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
