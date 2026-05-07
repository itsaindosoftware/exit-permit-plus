import InputError from '@/Components/InputError';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Edit({ scheduleItem, carOptions = [], driverOptions = [], history = [] }) {
    const selectedCar = carOptions.find((car) => car.police_no === scheduleItem.vehicle_plate);
    const selectedDriver = driverOptions.find((driver) => driver.name === scheduleItem.driver_name);

    const { data, setData, put, processing, errors } = useForm({
        car_id: selectedCar?.id ?? '',
        driver_id: selectedDriver?.id ?? '',
    });

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
                </div>

                <form onSubmit={submit} className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
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
                            Update Arrange
                        </button>
                    </div>
                </form>

                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-700">Audit Trail Arrange</h3>
                    {history.length === 0 ? (
                        <p className="mt-2 text-sm text-slate-500">Belum ada histori arrange.</p>
                    ) : (
                        <div className="mt-3 overflow-x-auto">
                            <table className="min-w-full border-collapse text-sm">
                                <thead>
                                    <tr className="bg-slate-100 text-slate-700">
                                        <th className="border border-slate-200 px-3 py-2 text-left">Waktu</th>
                                        <th className="border border-slate-200 px-3 py-2 text-left">Oleh</th>
                                        <th className="border border-slate-200 px-3 py-2 text-left">Aksi</th>
                                        <th className="border border-slate-200 px-3 py-2 text-left">No Police</th>
                                        <th className="border border-slate-200 px-3 py-2 text-left">Supir</th>
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
