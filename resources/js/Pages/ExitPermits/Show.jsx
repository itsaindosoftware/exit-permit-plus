import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const currencyFormatter = new Intl.NumberFormat('id-ID');

const exitTypeLabel = {
    business_trip: 'Perjalanan Dinas',
    sick: 'Sakit',
};

const postMdPathLabel = {
    meal: 'Meal',
    reimbursement: 'Reimbursement',
};

function InfoItem({ label, value }) {
    return (
        <div>
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</p>
            <p className="mt-1 text-sm text-slate-800">{value ?? '-'}</p>
        </div>
    );
}

export default function Show({ exitPermit, approvalStage }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-bold leading-tight text-slate-800">
                    Detail Exit Permit
                </h2>
            }
        >
            <Head title="Detail Exit Permit" />

            <div className="space-y-6">
                <div className="rounded-2xl border border-cyan-200 bg-gradient-to-r from-cyan-50 via-white to-slate-50 p-5">
                    <p className="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-700">Exit Permit Detail</p>
                    <p className="mt-2 text-sm text-slate-700">Ringkasan data pengajuan, approval, dan informasi kendaraan.</p>
                    <p className="mt-3 inline-flex rounded-full bg-slate-900 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-white">
                        {approvalStage}
                    </p>
                </div>

                <div className="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-3">
                    <InfoItem label="Karyawan" value={exitPermit.employee_name} />
                    <InfoItem label="Email" value={exitPermit.employee_email} />
                    <InfoItem label="Status" value={exitPermit.status?.toUpperCase()} />
                    <InfoItem label="Tanggal Permit" value={exitPermit.permit_date} />
                    <InfoItem label="Jam Keluar" value={exitPermit.start_time} />
                    <InfoItem label="Jam Kembali" value={exitPermit.end_time} />
                    <InfoItem label="Jenis Exit" value={exitTypeLabel[exitPermit.exit_type] ?? exitPermit.exit_type} />
                    <InfoItem label="Tujuan" value={exitPermit.destination} />
                    <InfoItem label="No. Police Car (1.4)" value={exitPermit.vehicle_plate ?? 'Belum diisi'} />
                    <InfoItem label="Returned To Office" value={exitPermit.returned_to_office ? 'Ya' : 'Tidak'} />
                    <InfoItem label="Eligible Meal" value={exitPermit.eligible_for_meal ? 'Ya' : 'Tidak'} />
                    <InfoItem label="Reimbursement" value={`Rp ${currencyFormatter.format(exitPermit.reimbursement_amount ?? 0)}`} />
                </div>

                <div className="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-2">
                    <InfoItem label="Alasan" value={exitPermit.reason} />
                    <InfoItem label="Catatan" value={exitPermit.notes ?? '-'} />
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Attachment Foto</p>
                    {exitPermit.attachment_url ? (
                        <div className="mt-3 space-y-3">
                            <img
                                src={exitPermit.attachment_url}
                                alt={exitPermit.attachment_original_name ?? 'Attachment Exit Permit'}
                                className="max-h-96 w-full rounded-lg border border-slate-200 object-contain"
                            />
                            <a
                                href={exitPermit.attachment_url}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex rounded-md bg-cyan-700 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-cyan-600"
                            >
                                Buka Ukuran Penuh ({exitPermit.attachment_original_name ?? 'Foto'})
                            </a>
                        </div>
                    ) : (
                        <p className="mt-2 text-sm text-slate-600">Tidak ada attachment.</p>
                    )}
                </div>

                <div className="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-2 xl:grid-cols-3">
                    <InfoItem label="Manager Approved By" value={exitPermit.manager_approved_by_name ?? '-'} />
                    <InfoItem label="Manager Approved At" value={exitPermit.manager_approved_at ?? '-'} />
                    <InfoItem label="MD Approved By" value={exitPermit.md_approved_by_name ?? '-'} />
                    <InfoItem label="MD Approved At" value={exitPermit.md_approved_at ?? '-'} />
                    <InfoItem label="PIC HR" value={exitPermit.hr_approver_name ?? '-'} />
                    <InfoItem label="HR Verified By" value={exitPermit.hr_verified_by_name ?? '-'} />
                    <InfoItem label="HR Verified At" value={exitPermit.hr_verified_at ?? '-'} />
                    <InfoItem label="Attendance Checked By" value={exitPermit.attendance_checked_by_name ?? '-'} />
                    <InfoItem label="Attendance Checked At" value={exitPermit.attendance_checked_at ?? '-'} />
                    <InfoItem label="Has Valid Check-in" value={exitPermit.has_valid_checkin === null ? '-' : (exitPermit.has_valid_checkin ? 'Ya' : 'Tidak')} />
                    <InfoItem label="Post MD Path" value={postMdPathLabel[exitPermit.post_md_path] ?? '-'} />
                </div>

                <div className="flex justify-end">
                    <Link
                        href={route('exit-permits.index')}
                        className="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                    >
                        Kembali
                    </Link>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
