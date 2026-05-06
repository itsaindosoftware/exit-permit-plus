import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Show({ mode, orderMeal, exitPermit, indexRouteName, editRouteName }) {
    const isExitPermitMode = mode === 'exit_permit';

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-bold leading-tight text-slate-800">
                    {isExitPermitMode ? 'Detail Order Meal Exit Permit' : 'Detail Order Meal Umum'}
                </h2>
            }
        >
            <Head title={isExitPermitMode ? 'Detail Order Meal Exit Permit' : 'Detail Order Meal Umum'} />

            <div className="space-y-6">
                <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="grid gap-4 text-sm text-slate-700 md:grid-cols-3">
                        <p><span className="font-semibold">Karyawan:</span> {orderMeal.employee_name || '-'}</p>
                        <p><span className="font-semibold">Email:</span> {orderMeal.employee_email || '-'}</p>
                        <p><span className="font-semibold">Tanggal Makan:</span> {orderMeal.meal_date || '-'}</p>
                        <p><span className="font-semibold">Menu:</span> {orderMeal.menu_name || '-'}</p>
                        <p><span className="font-semibold">Schedule:</span> {orderMeal.schedule_type || '-'}</p>
                        <p><span className="font-semibold">Status:</span> {orderMeal.status || '-'}</p>
                        <p><span className="font-semibold">Paket Disediakan:</span> {orderMeal.quantity ?? 0}</p>
                        <p><span className="font-semibold">Realisasi:</span> {orderMeal.actual_quantity ?? 0}</p>
                        <p><span className="font-semibold">Sisa:</span> {orderMeal.remaining_quantity ?? 0}</p>
                    </div>

                    {orderMeal.notes && (
                        <div className="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                            <p className="font-semibold text-slate-900">Catatan</p>
                            <p className="mt-1 whitespace-pre-line">{orderMeal.notes}</p>
                        </div>
                    )}
                </div>

                {isExitPermitMode && exitPermit && (
                    <div className="space-y-3 rounded-2xl border border-cyan-200 bg-cyan-50 p-6 shadow-sm">
                        <p className="text-sm font-semibold text-cyan-900">Referensi Exit Permit</p>
                        <div className="grid gap-3 text-sm text-cyan-900 md:grid-cols-2">
                            <p><span className="font-semibold">Exit Permit ID:</span> #{exitPermit.id}</p>
                            <p><span className="font-semibold">Permit Date:</span> {exitPermit.permit_date || '-'}</p>
                            <p><span className="font-semibold">Destination:</span> {exitPermit.destination || '-'}</p>
                            <p><span className="font-semibold">Attendance Verified:</span> {exitPermit.attendance_checked_at || '-'}</p>
                            <p><span className="font-semibold">Pemohon:</span> {exitPermit.owner_name || '-'}</p>
                            <p><span className="font-semibold">Email Pemohon:</span> {exitPermit.owner_email || '-'}</p>
                        </div>

                        <div className="overflow-x-auto rounded-md border border-cyan-200 bg-white">
                            <table className="min-w-full border-collapse text-xs">
                                <thead className="bg-cyan-100 text-cyan-900">
                                    <tr>
                                        <th className="border border-cyan-200 px-2 py-1 text-left">No</th>
                                        <th className="border border-cyan-200 px-2 py-1 text-left">Name</th>
                                        <th className="border border-cyan-200 px-2 py-1 text-left">Employee ID</th>
                                        <th className="border border-cyan-200 px-2 py-1 text-left">Position</th>
                                        <th className="border border-cyan-200 px-2 py-1 text-left">Department</th>
                                        <th className="border border-cyan-200 px-2 py-1 text-left">Lunch Box</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(exitPermit.requestors ?? []).map((requestor) => (
                                        <tr key={`show-requestor-${exitPermit.id}-${requestor.row_number}`}>
                                            <td className="border border-cyan-200 px-2 py-1">{requestor.row_number}</td>
                                            <td className="border border-cyan-200 px-2 py-1">{requestor.name || '-'}</td>
                                            <td className="border border-cyan-200 px-2 py-1">{requestor.employee_id || '-'}</td>
                                            <td className="border border-cyan-200 px-2 py-1">{requestor.position || '-'}</td>
                                            <td className="border border-cyan-200 px-2 py-1">{requestor.department || '-'}</td>
                                            <td className="border border-cyan-200 px-2 py-1">{requestor.reimburs_lunch_box || '-'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Link
                        href={route(indexRouteName)}
                        className="rounded-md border border-slate-300 px-4 py-2 text-center text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                    >
                        Kembali
                    </Link>
                    <Link
                        href={route(editRouteName, orderMeal.id)}
                        className="rounded-md bg-slate-900 px-4 py-2 text-center text-sm font-semibold text-white transition hover:bg-slate-700"
                    >
                        Edit
                    </Link>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
