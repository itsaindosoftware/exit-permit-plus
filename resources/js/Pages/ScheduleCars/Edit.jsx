import InputError from '@/Components/InputError';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Edit({ scheduleItem, carOptions = [], driverOptions = [], history = [] }) {
    const sanitizeTemplateValue = (value) => {
        if (!value || value === '-') {
            return '';
        }

        return String(value).trim();
    };

    const normalizeTemplate = (template = null) => ({
        tanggal_dinas_luar: sanitizeTemplateValue(template?.tanggal_dinas_luar),
        estimasi_jam: sanitizeTemplateValue(template?.estimasi_jam),
        nama_pt_tujuan: sanitizeTemplateValue(template?.nama_pt_tujuan),
        lokasi_pt_tujuan: sanitizeTemplateValue(template?.lokasi_pt_tujuan),
        user_yang_pergi: sanitizeTemplateValue(template?.user_yang_pergi),
        budget_dept_cost_center: sanitizeTemplateValue(template?.budget_dept_cost_center),
        alasan_pergi: sanitizeTemplateValue(template?.alasan_pergi),
        detail_barang_delivery: sanitizeTemplateValue(template?.detail_barang_delivery),
        permintaan_kurangi_catering: sanitizeTemplateValue(template?.permintaan_kurangi_catering),
    });

    const selectedCar = carOptions.find((car) => car.police_no === scheduleItem.vehicle_plate);
    const selectedDriver = driverOptions.find((driver) => driver.name === scheduleItem.driver_name);

    const { data, setData, put, processing, errors } = useForm({
        car_id: selectedCar?.id ?? '',
        driver_id: selectedDriver?.id ?? '',
        arrange_template: normalizeTemplate(scheduleItem.template),
    });

    const updateArrangeTemplate = (field, value) => {
        setData('arrange_template', {
            ...data.arrange_template,
            [field]: value,
        });
    };

    const submit = (e) => {
        e.preventDefault();
        put(route('schedule-cars.update', scheduleItem.id));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-slate-800">Edit Arrange Order Car</h2>}>
            <Head title="Edit Arrange Order Car" />

            <div className="space-y-6">
                <div className="rounded-xl border border-indigo-200 bg-indigo-50 p-5 shadow-sm">
                    <p className="text-sm text-indigo-900">
                        Exit Permit #{scheduleItem.id} | {scheduleItem.permit_date} | {scheduleItem.start_time ?? '-'}-{scheduleItem.end_time ?? '-'} | {scheduleItem.destination}
                    </p>
                    <p className="mt-1 text-sm text-indigo-900">
                        Cost Center (Department): {scheduleItem.cost_center_name || '-'}
                    </p>
                </div>

                <form onSubmit={submit} className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                    <div className="rounded-lg border border-cyan-200 bg-cyan-50 p-4">
                        <p className="text-xs font-semibold uppercase tracking-wider text-cyan-700">Arrange Car Template (Ratna)</p>
                        <p className="mt-1 text-xs text-cyan-700">This data can be edited manually by Ratna if there are mistakes.</p>

                        <div className="mt-3 grid gap-4 md:grid-cols-2">
                            <div>
                                <label htmlFor="arrange_tanggal_dinas_luar" className="text-sm font-semibold text-slate-800">Out-of-Office Date</label>
                                <input
                                    id="arrange_tanggal_dinas_luar"
                                    type="text"
                                    className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                    value={data.arrange_template.tanggal_dinas_luar}
                                    onChange={(e) => updateArrangeTemplate('tanggal_dinas_luar', e.target.value)}
                                />
                                <InputError message={errors['arrange_template.tanggal_dinas_luar']} className="mt-1" />
                            </div>

                            <div>
                                <label htmlFor="arrange_estimasi_jam" className="text-sm font-semibold text-slate-800">Estimated Departure &amp; Return Time</label>
                                <input
                                    id="arrange_estimasi_jam"
                                    type="text"
                                    className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                    value={data.arrange_template.estimasi_jam}
                                    onChange={(e) => updateArrangeTemplate('estimasi_jam', e.target.value)}
                                />
                                <InputError message={errors['arrange_template.estimasi_jam']} className="mt-1" />
                            </div>

                            <div>
                                <label htmlFor="arrange_nama_pt_tujuan" className="text-sm font-semibold text-slate-800">Destination Company Name</label>
                                <input
                                    id="arrange_nama_pt_tujuan"
                                    type="text"
                                    className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                    value={data.arrange_template.nama_pt_tujuan}
                                    onChange={(e) => updateArrangeTemplate('nama_pt_tujuan', e.target.value)}
                                />
                                <InputError message={errors['arrange_template.nama_pt_tujuan']} className="mt-1" />
                            </div>

                            <div>
                                <label htmlFor="arrange_lokasi_pt_tujuan" className="text-sm font-semibold text-slate-800">Destination Location</label>
                                <input
                                    id="arrange_lokasi_pt_tujuan"
                                    type="text"
                                    className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                    value={data.arrange_template.lokasi_pt_tujuan}
                                    onChange={(e) => updateArrangeTemplate('lokasi_pt_tujuan', e.target.value)}
                                />
                                <InputError message={errors['arrange_template.lokasi_pt_tujuan']} className="mt-1" />
                            </div>
                        </div>

                        <div className="mt-4 space-y-4">
                            <div>
                                <label htmlFor="arrange_user_yang_pergi" className="text-sm font-semibold text-slate-800">Traveling User(s)</label>
                                <input
                                    id="arrange_user_yang_pergi"
                                    type="text"
                                    className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                    value={data.arrange_template.user_yang_pergi}
                                    onChange={(e) => updateArrangeTemplate('user_yang_pergi', e.target.value)}
                                />
                                <InputError message={errors['arrange_template.user_yang_pergi']} className="mt-1" />
                            </div>

                            <div>
                                <label htmlFor="arrange_budget_dept_cost_center" className="text-sm font-semibold text-slate-800">Budget Dept &amp; Cost Center</label>
                                <input
                                    id="arrange_budget_dept_cost_center"
                                    type="text"
                                    className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                    value={data.arrange_template.budget_dept_cost_center}
                                    onChange={(e) => updateArrangeTemplate('budget_dept_cost_center', e.target.value)}
                                />
                                <InputError message={errors['arrange_template.budget_dept_cost_center']} className="mt-1" />
                            </div>

                            <div>
                                <label htmlFor="arrange_alasan_pergi" className="text-sm font-semibold text-slate-800">Reason for Trip (Meeting / Delivery) with details</label>
                                <textarea
                                    id="arrange_alasan_pergi"
                                    rows="3"
                                    className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                    value={data.arrange_template.alasan_pergi}
                                    onChange={(e) => updateArrangeTemplate('alasan_pergi', e.target.value)}
                                />
                                <InputError message={errors['arrange_template.alasan_pergi']} className="mt-1" />
                            </div>

                            <div>
                                <label htmlFor="arrange_detail_barang_delivery" className="text-sm font-semibold text-slate-800">Delivery Item Details (Part / Tooling)</label>
                                <textarea
                                    id="arrange_detail_barang_delivery"
                                    rows="3"
                                    className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                    value={data.arrange_template.detail_barang_delivery}
                                    onChange={(e) => updateArrangeTemplate('detail_barang_delivery', e.target.value)}
                                />
                                <InputError message={errors['arrange_template.detail_barang_delivery']} className="mt-1" />
                            </div>

                            <div>
                                <label htmlFor="arrange_permintaan_kurangi_catering" className="text-sm font-semibold text-slate-800">Request to reduce catering order</label>
                                <textarea
                                    id="arrange_permintaan_kurangi_catering"
                                    rows="3"
                                    className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                    value={data.arrange_template.permintaan_kurangi_catering}
                                    onChange={(e) => updateArrangeTemplate('permintaan_kurangi_catering', e.target.value)}
                                />
                                <InputError message={errors['arrange_template.permintaan_kurangi_catering']} className="mt-1" />
                            </div>
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label htmlFor="car_id" className="text-sm font-semibold text-slate-800">License Plate</label>
                            <select
                                id="car_id"
                                className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                value={data.car_id}
                                onChange={(e) => setData('car_id', e.target.value ? Number(e.target.value) : '')}
                                required
                            >
                                <option value="">Select Car</option>
                                {carOptions.map((car) => (
                                    <option key={car.id} value={car.id}>{car.police_no} - {car.spesification}</option>
                                ))}
                            </select>
                            <InputError message={errors.car_id} className="mt-1" />
                        </div>

                        <div>
                            <label htmlFor="driver_id" className="text-sm font-semibold text-slate-800">Driver</label>
                            <select
                                id="driver_id"
                                className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                value={data.driver_id}
                                onChange={(e) => setData('driver_id', e.target.value ? Number(e.target.value) : '')}
                                required
                            >
                                <option value="">Select Driver</option>
                                {driverOptions.map((driver) => (
                                    <option key={driver.id} value={driver.id}>{driver.name}</option>
                                ))}
                            </select>
                            <InputError message={errors.driver_id} className="mt-1" />
                        </div>
                    </div>

                    <div className="flex justify-end gap-2">
                        <Link href={route('schedule-cars.index')} className="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                            Back
                        </Link>
                        <button type="submit" disabled={processing} className="rounded-md bg-indigo-700 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600 disabled:opacity-60">
                            Update Arrangement
                        </button>
                    </div>
                </form>

                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-700">Arrangement Audit Trail</h3>
                    {history.length === 0 ? (
                        <p className="mt-2 text-sm text-slate-500">No arrangement history yet.</p>
                    ) : (
                        <div className="mt-3 overflow-x-auto">
                            <table className="min-w-full border-collapse text-sm">
                                <thead>
                                    <tr className="bg-slate-100 text-slate-700">
                                        <th className="border border-slate-200 px-3 py-2 text-left">Time</th>
                                        <th className="border border-slate-200 px-3 py-2 text-left">By</th>
                                        <th className="border border-slate-200 px-3 py-2 text-left">Action</th>
                                        <th className="border border-slate-200 px-3 py-2 text-left">No Police</th>
                                        <th className="border border-slate-200 px-3 py-2 text-left">Driver</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {history.map((item) => (
                                        <tr key={item.id}>
                                            <td className="border border-slate-200 px-3 py-2">{item.arranged_at ?? '-'}</td>
                                            <td className="border border-slate-200 px-3 py-2">{item.arranger_name ?? '-'}</td>
                                            <td className="border border-slate-200 px-3 py-2 uppercase">{item.action}</td>
                                            <td className="border border-slate-200 px-3 py-2">{item.vehicle_plate ?? '-'}</td>
                                            <td className="border border-slate-200 px-3 py-2">{item.driver_name ?? '-'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
