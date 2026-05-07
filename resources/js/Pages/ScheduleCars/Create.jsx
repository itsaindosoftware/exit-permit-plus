import InputError from '@/Components/InputError';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Create({ targets = [], carOptions = [], driverOptions = [] }) {
    const { data, setData, post, processing, errors } = useForm({
        exit_permit_id: targets[0]?.id ?? '',
        car_id: '',
        driver_id: '',
    });

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
                    <form onSubmit={submit} className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                        <div>
                            <label htmlFor="exit_permit_id" className="text-sm font-semibold text-slate-800">Exit Permit</label>
                            <select
                                id="exit_permit_id"
                                className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                value={data.exit_permit_id}
                                onChange={(e) => setData('exit_permit_id', e.target.value ? Number(e.target.value) : '')}
                                required
                            >
                                {targets.map((target) => (
                                    <option key={target.id} value={target.id}>{target.label}</option>
                                ))}
                            </select>
                            <InputError message={errors.exit_permit_id} className="mt-1" />
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
