import InputError from '@/Components/InputError';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Create({ targets = [], carOptions = [], driverOptions = [] }) {
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

    const { data, setData, post, processing, errors } = useForm({
        exit_permit_id: targets[0]?.id ?? '',
        car_id: '',
        driver_id: '',
        arrange_template: normalizeTemplate(targets[0]?.template),
    });

    const updateArrangeTemplate = (field, value) => {
        setData('arrange_template', {
            ...data.arrange_template,
            [field]: value,
        });
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('schedule-cars.store'));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-slate-800">Create Arrange Order Car</h2>}>
            <Head title="Create Arrange Order Car" />

            <div className="space-y-6">
                <div className="rounded-xl border border-indigo-200 bg-indigo-50 p-5 shadow-sm">
                    <p className="text-sm text-indigo-900">
                        Pilih Exit Permit yang Order Car = Yes lalu tentukan no police car dan supir.
                    </p>
                </div>

                {targets.length === 0 ? (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                        Tidak ada Exit Permit yang belum di-arrange.
                    </div>
                ) : (
                    <form onSubmit={submit} className="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div>
                            <label htmlFor="exit_permit_id" className="text-sm font-semibold text-slate-800">Exit Permit</label>
                            <select
                                id="exit_permit_id"
                                className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                value={data.exit_permit_id}
                                onChange={(e) => {
                                    const nextId = e.target.value ? Number(e.target.value) : '';
                                    const nextTarget = targets.find((target) => Number(target.id) === Number(nextId));

                                    setData('exit_permit_id', nextId);
                                    setData('arrange_template', normalizeTemplate(nextTarget?.template ?? null));
                                }}
                                required
                            >
                                {targets.map((target) => (
                                    <option key={target.id} value={target.id}>{target.label}</option>
                                ))}
                            </select>
                            <InputError message={errors.exit_permit_id} className="mt-1" />
                        </div>

                        <div className="rounded-lg border border-cyan-200 bg-cyan-50 p-4">
                            <p className="text-xs font-semibold uppercase tracking-wider text-cyan-700">Format Arrange Car (Ratna)</p>
                            <p className="mt-1 text-xs text-cyan-700">Data otomatis terisi dari Exit Permit, namun tetap bisa diedit manual jika ada kekeliruan.</p>

                            <div className="mt-3 grid gap-4 md:grid-cols-2">
                                <div>
                                    <label htmlFor="arrange_tanggal_dinas_luar" className="text-sm font-semibold text-slate-800">Tanggal Dinas Luar</label>
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
                                    <label htmlFor="arrange_estimasi_jam" className="text-sm font-semibold text-slate-800">Estimasi Jam berangkat &amp; Pulang</label>
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
                                    <label htmlFor="arrange_nama_pt_tujuan" className="text-sm font-semibold text-slate-800">Nama PT Tujuan</label>
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
                                    <label htmlFor="arrange_lokasi_pt_tujuan" className="text-sm font-semibold text-slate-800">Lokasi PT Tujuan</label>
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
                                    <label htmlFor="arrange_user_yang_pergi" className="text-sm font-semibold text-slate-800">User yang pergi</label>
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
                                    <label htmlFor="arrange_alasan_pergi" className="text-sm font-semibold text-slate-800">Alasan Pergi (Meeting / Delivery) dengan detail reason</label>
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
                                    <label htmlFor="arrange_detail_barang_delivery" className="text-sm font-semibold text-slate-800">Detail Barang Yang Dibawa jika Delivery (Part / Tooling)</label>
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
                                    <label htmlFor="arrange_permintaan_kurangi_catering" className="text-sm font-semibold text-slate-800">Permintaan untuk kurangin order catering</label>
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
                                <label htmlFor="car_id" className="text-sm font-semibold text-slate-800">No Police Car</label>
                                <select
                                    id="car_id"
                                    className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                    value={data.car_id}
                                    onChange={(e) => setData('car_id', e.target.value ? Number(e.target.value) : '')}
                                    required
                                >
                                    <option value="">Pilih Mobil</option>
                                    {carOptions.map((car) => (
                                        <option key={car.id} value={car.id}>{car.police_no} - {car.spesification}</option>
                                    ))}
                                </select>
                                <InputError message={errors.car_id} className="mt-1" />
                            </div>

                            <div>
                                <label htmlFor="driver_id" className="text-sm font-semibold text-slate-800">Supir</label>
                                <select
                                    id="driver_id"
                                    className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                    value={data.driver_id}
                                    onChange={(e) => setData('driver_id', e.target.value ? Number(e.target.value) : '')}
                                    required
                                >
                                    <option value="">Pilih Supir</option>
                                    {driverOptions.map((driver) => (
                                        <option key={driver.id} value={driver.id}>{driver.name}</option>
                                    ))}
                                </select>
                                <InputError message={errors.driver_id} className="mt-1" />
                            </div>
                        </div>

                        <div className="flex justify-end gap-2">
                            <Link href={route('schedule-cars.index')} className="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                                Kembali
                            </Link>
                            <button type="submit" disabled={processing} className="rounded-md bg-indigo-700 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600 disabled:opacity-60">
                                Simpan Arrange
                            </button>
                        </div>
                    </form>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
