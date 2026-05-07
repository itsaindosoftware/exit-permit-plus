import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

const exitTypeLabel = {
    business_trip: 'Perjalanan Dinas',
    sick: 'Sakit',
};

const hourLabels = Array.from({ length: 24 }, (_, hour) => `${String(hour).padStart(2, '0')}:00`);

function localDateString(date = new Date()) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function toMinutes(value) {
    if (!value) {
        return 0;
    }

    const [hour, minute] = value.split(':').map(Number);
    return hour * 60 + minute;
}

function startOfWeek(date) {
    const current = new Date(`${date}T00:00:00`);
    const day = current.getDay();
    const diff = day === 0 ? -6 : 1 - day;
    current.setDate(current.getDate() + diff);
    return current;
}

function formatDate(date) {
    return localDateString(date);
}

export default function Index({ events, filters, arrangeItems = [] }) {
    const selectedView = filters?.view ?? 'week';
    const selectedDate = filters?.date ?? localDateString();
    const selectedFilterDate = filters?.filter_date ?? '';
    const selectedFilterDay = filters?.filter_day ?? '';
    const selectedFilterHour = filters?.filter_hour ?? '';

    const updateFilter = (field, value) => {
        router.get(
            route('schedule-cars.index'),
            {
                view: field === 'view' ? value : selectedView,
                date: field === 'date' ? value : selectedDate,
                filter_date: field === 'filter_date' ? value : selectedFilterDate,
                filter_day: field === 'filter_day' ? value : selectedFilterDay,
                filter_hour: field === 'filter_hour' ? value : selectedFilterHour,
            },
            { preserveState: true, replace: true },
        );
    };

    const weekStart = startOfWeek(selectedDate);
    const dayColumns = selectedView === 'day'
        ? [new Date(`${selectedDate}T00:00:00`)]
        : Array.from({ length: 7 }, (_, index) => {
            const day = new Date(weekStart);
            day.setDate(weekStart.getDate() + index);
            return day;
        });

    const eventsByDate = events.reduce((carry, item) => {
        if (!carry[item.permit_date]) {
            carry[item.permit_date] = [];
        }
        carry[item.permit_date].push(item);
        return carry;
    }, {});

    const monthDate = new Date(`${selectedDate}T00:00:00`);
    const monthStart = new Date(monthDate.getFullYear(), monthDate.getMonth(), 1);
    const monthEnd = new Date(monthDate.getFullYear(), monthDate.getMonth() + 1, 0);
    const monthGridStart = startOfWeek(formatDate(monthStart));

    const monthCells = Array.from({ length: 42 }, (_, index) => {
        const day = new Date(monthGridStart);
        day.setDate(monthGridStart.getDate() + index);
        return day;
    });

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-slate-800">Schedule Car</h2>}
        >
            <Head title="Schedule Car" />

            <div className="space-y-6">
                <div className="rounded-xl border border-indigo-200 bg-indigo-50 p-5 shadow-sm">
                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-700">Arrange Order Car</p>
                    <p className="mt-2 text-sm text-indigo-900">
                        Hanya Exit Permit tipe company dengan Order Car = Yes. Buat jadwal baru lewat halaman Create, lalu revisi lewat halaman Edit.
                    </p>

                    <div className="mt-3 flex justify-end">
                        <Link
                            href={route('schedule-cars.create')}
                            className="rounded-md bg-indigo-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-600"
                        >
                            Create Arrange Order Car
                        </Link>
                    </div>

                    {arrangeItems.length === 0 ? (
                        <p className="mt-3 rounded-md border border-indigo-200 bg-white px-3 py-2 text-sm text-indigo-800">
                            Tidak ada Exit Permit yang perlu di-arrange saat ini.
                        </p>
                    ) : (
                        <div className="mt-4 space-y-2">
                            {arrangeItems.map((item) => (
                                <div key={item.id} className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-indigo-200 bg-white px-3 py-2">
                                    <div>
                                        <p className="text-sm font-semibold text-slate-900">{item.label}</p>
                                        <span className={
                                            `inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ` +
                                            (item.is_arranged ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700')
                                        }>
                                            {item.is_arranged ? 'Sudah Arrange' : 'Belum Arrange'}
                                        </span>
                                    </div>
                                    <Link
                                        href={route('schedule-cars.edit', item.id)}
                                        className="rounded-md border border-indigo-300 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100"
                                    >
                                        Edit Arrange
                                    </Link>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex flex-wrap items-end gap-3">
                        <div>
                            <label className="text-xs font-semibold uppercase tracking-wider text-slate-500">Mode</label>
                            <div className="mt-2 flex gap-2">
                                {[
                                    { key: 'day', label: 'Harian' },
                                    { key: 'week', label: 'Mingguan' },
                                    { key: 'month', label: 'Bulanan' },
                                ].map((view) => (
                                    <button
                                        key={view.key}
                                        type="button"
                                        onClick={() => updateFilter('view', view.key)}
                                        className={
                                            `rounded-md border px-3 py-1.5 text-sm font-semibold transition ` +
                                            (selectedView === view.key
                                                ? 'border-slate-900 bg-slate-900 text-white'
                                                : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100')
                                        }
                                    >
                                        {view.label}
                                    </button>
                                ))}
                            </div>
                        </div>

                        <div>
                            <label htmlFor="focus-date" className="text-xs font-semibold uppercase tracking-wider text-slate-500">Tanggal Fokus</label>
                            <input
                                id="focus-date"
                                type="date"
                                className="mt-2 rounded-md border border-slate-300 px-3 py-1.5 text-sm"
                                value={selectedDate}
                                onChange={(e) => updateFilter('date', e.target.value)}
                            />
                        </div>

                        <div>
                            <label htmlFor="filter-date" className="text-xs font-semibold uppercase tracking-wider text-slate-500">Filter Tanggal</label>
                            <input
                                id="filter-date"
                                type="date"
                                className="mt-2 rounded-md border border-slate-300 px-3 py-1.5 text-sm"
                                value={selectedFilterDate}
                                onChange={(e) => updateFilter('filter_date', e.target.value)}
                            />
                        </div>

                        <div>
                            <label htmlFor="filter-day" className="text-xs font-semibold uppercase tracking-wider text-slate-500">Filter Hari</label>
                            <select
                                id="filter-day"
                                className="mt-2 rounded-md border border-slate-300 px-3 py-1.5 text-sm"
                                value={selectedFilterDay}
                                onChange={(e) => updateFilter('filter_day', e.target.value)}
                            >
                                <option value="">Semua Hari</option>
                                <option value="1">Senin</option>
                                <option value="2">Selasa</option>
                                <option value="3">Rabu</option>
                                <option value="4">Kamis</option>
                                <option value="5">Jumat</option>
                                <option value="6">Sabtu</option>
                                <option value="7">Minggu</option>
                            </select>
                        </div>

                        <div>
                            <label htmlFor="filter-hour" className="text-xs font-semibold uppercase tracking-wider text-slate-500">Filter Jam</label>
                            <select
                                id="filter-hour"
                                className="mt-2 rounded-md border border-slate-300 px-3 py-1.5 text-sm"
                                value={selectedFilterHour}
                                onChange={(e) => updateFilter('filter_hour', e.target.value)}
                            >
                                <option value="">Semua Jam</option>
                                {hourLabels.map((hourLabel, hour) => (
                                    <option key={hourLabel} value={hour}>{hourLabel}</option>
                                ))}
                            </select>
                        </div>
                    </div>
                </div>

                {selectedView === 'month' ? (
                    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="grid grid-cols-7 gap-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
                            {['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'].map((day) => (
                                <div key={day} className="px-2 py-1">{day}</div>
                            ))}
                        </div>
                        <div className="mt-2 grid grid-cols-7 gap-2">
                            {monthCells.map((day) => {
                                const dayKey = formatDate(day);
                                const dayEvents = eventsByDate[dayKey] ?? [];
                                const isCurrentMonth = day.getMonth() === monthDate.getMonth();
                                const isToday = dayKey === localDateString();

                                return (
                                    <div
                                        key={dayKey}
                                        className={
                                            `min-h-28 rounded-lg border p-2 ` +
                                            (isCurrentMonth ? 'border-slate-200 bg-white' : 'border-slate-100 bg-slate-50')
                                        }
                                    >
                                        <p className={
                                            `text-xs font-semibold ` + (isToday ? 'text-cyan-700' : 'text-slate-600')
                                        }>
                                            {day.getDate()}
                                        </p>
                                        <div className="mt-1 space-y-1">
                                            {dayEvents.slice(0, 3).map((event) => (
                                                <Link
                                                    key={event.id}
                                                    href={route('exit-permits.show', event.id)}
                                                    className="block rounded bg-cyan-100 px-2 py-1 text-[11px] text-cyan-900 transition hover:bg-cyan-200"
                                                >
                                                    <span>{event.start_time ?? '--:--'} {event.vehicle_plate ?? '-'}</span>
                                                    <span className={
                                                        `ml-1 inline-flex rounded-full px-1.5 py-0.5 text-[10px] font-semibold ` +
                                                        (event.is_arranged ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700')
                                                    }>
                                                        {event.is_arranged ? 'Sudah' : 'Belum'}
                                                    </span>
                                                </Link>
                                            ))}
                                            {dayEvents.length > 3 && (
                                                <p className="text-[11px] font-semibold text-slate-500">+{dayEvents.length - 3} lainnya</p>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                ) : (
                    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="overflow-auto">
                            <div className="min-w-[920px]">
                                <div className="grid" style={{ gridTemplateColumns: `90px repeat(${dayColumns.length}, minmax(180px, 1fr))` }}>
                                    <div className="border-b border-slate-200 px-2 py-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Jam</div>
                                    {dayColumns.map((day) => {
                                        const key = formatDate(day);
                                        const dayLabel = day.toLocaleDateString('id-ID', { weekday: 'short', day: '2-digit', month: 'short' });

                                        return (
                                            <div key={key} className="border-b border-slate-200 px-2 py-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
                                                {dayLabel}
                                            </div>
                                        );
                                    })}
                                </div>

                                <div className="grid" style={{ gridTemplateColumns: `90px repeat(${dayColumns.length}, minmax(180px, 1fr))` }}>
                                    <div className="relative border-r border-slate-200">
                                        {hourLabels.map((label, index) => (
                                            <div key={label} className="h-14 border-b border-slate-100 px-2 pt-0.5 text-[11px] text-slate-500">
                                                {index === 0 ? '00:00' : label}
                                            </div>
                                        ))}
                                    </div>

                                    {dayColumns.map((day) => {
                                        const dayKey = formatDate(day);
                                        const dayEvents = eventsByDate[dayKey] ?? [];

                                        return (
                                            <div key={dayKey} className="relative border-r border-slate-100">
                                                {hourLabels.map((label) => (
                                                    <div key={`${dayKey}-${label}`} className="h-14 border-b border-slate-100" />
                                                ))}

                                                {dayEvents.map((event) => {
                                                    const startMinute = toMinutes(event.start_time);
                                                    const endMinute = Math.max(startMinute + 30, toMinutes(event.end_time));
                                                    const top = (startMinute / 60) * 56;
                                                    const height = ((endMinute - startMinute) / 60) * 56;

                                                    return (
                                                        <Link
                                                            key={event.id}
                                                            href={route('exit-permits.show', event.id)}
                                                            className="absolute left-1 right-1 rounded-lg border border-cyan-300 bg-cyan-100/95 px-2 py-1 text-[11px] text-cyan-900 shadow-sm"
                                                            style={{ top, height }}
                                                        >
                                                            <p className="font-semibold">{event.start_time ?? '--:--'} - {event.end_time ?? '--:--'}</p>
                                                            <p className="truncate">{event.destination}</p>
                                                            <p className="truncate">{event.vehicle_plate ?? '-'} | {event.driver_name ?? '-'}</p>
                                                            <p className={
                                                                `inline-flex rounded-full px-1.5 py-0.5 text-[10px] font-semibold ` +
                                                                (event.is_arranged ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700')
                                                            }>
                                                                {event.is_arranged ? 'Sudah Arrange' : 'Belum Arrange'}
                                                            </p>
                                                            <p className="truncate">{exitTypeLabel[event.exit_type] ?? event.exit_type}</p>
                                                        </Link>
                                                    );
                                                })}
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
