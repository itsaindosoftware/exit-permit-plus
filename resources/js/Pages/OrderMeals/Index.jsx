import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

const currencyFormatter = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

const parseDateLabel = (value) => {
    if (!value) {
        return null;
    }

    const text = String(value).trim();
    const dateMatch = text.match(/^(\d{4})-(\d{2})-(\d{2})/);

    if (dateMatch) {
        const year = Number(dateMatch[1]);
        const month = Number(dateMatch[2]) - 1;
        const day = Number(dateMatch[3]);
        return new Date(year, month, day);
    }

    const parsed = new Date(text);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
};

const formatShortIdDate = (value) => {
    const date = parseDateLabel(value);

    if (!date) {
        return String(value ?? '-');
    }

    const dayName = dayNames[date.getDay()] ?? '';
    const day = String(date.getDate()).padStart(2, '0');
    const monthName = monthNames[date.getMonth()] ?? '';

    return `${dayName}, ${day} ${monthName}`.trim();
};

const formatWeeklyLabel = (value) => {
    const text = String(value ?? '');
    const match = text.match(/^(\d{4})-W(\d{1,2})$/i);

    if (!match) {
        return text || '-';
    }

    return `Week ${Number(match[2])}, ${match[1]}`;
};

const formatMonthlyLabel = (value) => {
    const text = String(value ?? '');
    const match = text.match(/^(\d{4})-(\d{2})$/);

    if (!match) {
        return text || '-';
    }

    const monthIndex = Number(match[2]) - 1;
    const monthName = monthNames[monthIndex] ?? match[2];

    return `${monthName} ${match[1]}`;
};

const formatChartLabel = (group, value) => {
    if (group === 'weekly') {
        return formatWeeklyLabel(value);
    }

    if (group === 'monthly') {
        return formatMonthlyLabel(value);
    }

    return formatShortIdDate(value);
};

const getBarTone = (ratio) => {
    if (ratio <= 0.33) {
        return {
            bar: 'bg-emerald-500',
            value: 'text-emerald-700',
            percent: 'text-emerald-600',
        };
    }

    if (ratio <= 0.66) {
        return {
            bar: 'bg-amber-500',
            value: 'text-amber-700',
            percent: 'text-amber-600',
        };
    }

    return {
        bar: 'bg-rose-500',
        value: 'text-rose-700',
        percent: 'text-rose-600',
    };
};

const getRemainingTone = (remaining, provided) => {
    const safeProvided = Math.max(1, Number(provided ?? 0));
    const ratio = Math.max(0, Number(remaining ?? 0)) / safeProvided;

    if (ratio <= 0.1) {
        return {
            badge: 'bg-emerald-100 text-emerald-700',
            label: 'Efisien',
        };
    }

    if (ratio <= 0.3) {
        return {
            badge: 'bg-amber-100 text-amber-700',
            label: 'Waspada',
        };
    }

    return {
        badge: 'bg-rose-100 text-rose-700',
        label: 'Tinggi',
    };
};

function NotEatenChart({ title, points, group }) {
    const maxValue = Math.max(1, ...(points ?? []).map((item) => item.remaining ?? 0));

    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p className="text-sm font-semibold text-slate-900">{title}</p>
            <p className="mt-1 text-xs text-slate-500">Meals ordered but not eaten</p>

            {!points?.length && (
                <div className="mt-4 rounded-lg border border-dashed border-slate-300 px-3 py-8 text-center text-xs text-slate-500">
                    No data yet.
                </div>
            )}

            {!!points?.length && (
                <div className="mt-4 space-y-2">
                    {points.map((item) => {
                        const currentValue = Number(item.remaining ?? 0);
                        const ratio = Math.max(0, Math.min(1, currentValue / maxValue));
                        const percent = Math.round(ratio * 100);
                        const tone = getBarTone(ratio);

                        return (
                        <div key={item.label} className="space-y-1">
                            <div className="flex items-center justify-between text-xs">
                                <span className="font-medium text-slate-600">{formatChartLabel(group, item.label)}</span>
                                <div className="flex items-center gap-2">
                                    <span className={`font-semibold ${tone.value}`}>{currentValue}</span>
                                    <span className={`text-[11px] font-medium ${tone.percent}`}>{percent}%</span>
                                </div>
                            </div>
                            <div className="h-2 rounded-full bg-slate-100">
                                <div
                                    className={`h-2 rounded-full ${tone.bar}`}
                                    style={{ width: `${Math.max(6, Math.round(((item.remaining ?? 0) / maxValue) * 100))}%` }}
                                />
                            </div>
                        </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

export default function Index({ orderMeals, summary, notEatenCharts, mode, createRouteName, showRouteName, editRouteName, destroyRouteName, indexRouteName, printRouteName, printItemRouteName, filters, checkMealFormula }) {
    const isExitPermitMode = mode === 'exit_permit';
    const [search, setSearch] = useState(filters?.search ?? '');
    const [showCheckMealModal, setShowCheckMealModal] = useState(false);
    const [menuFilter, setMenuFilter] = useState(filters?.menu ?? '');
    const [shiftFilter, setShiftFilter] = useState(filters?.shift ?? '');
    const [dateFrom, setDateFrom] = useState(filters?.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters?.date_to ?? '');
    const firstRender = useRef(true);
    const skipAutoFilter = useRef(false);

    const todayLabel = new Intl.DateTimeFormat('en-US', {
        weekday: 'long',
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    }).format(new Date());

    const totalRows = orderMeals?.total ?? orderMeals?.data?.length ?? 0;
    const providedTotal = Number(summary?.provided_total ?? 0);
    const actualTotal = Number(summary?.actual_total ?? 0);
    const utilizationPct = providedTotal > 0
        ? Math.round((actualTotal / providedTotal) * 1000) / 10
        : 0;
    const wastePct = Math.max(0, Math.round((100 - utilizationPct) * 10) / 10);

    const hasActiveFilter = Boolean(search || dateFrom || dateTo || menuFilter || shiftFilter);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }

        if (skipAutoFilter.current) {
            skipAutoFilter.current = false;
            return;
        }

        const timeoutId = setTimeout(() => {
            router.get(route(indexRouteName), {
                search: search || undefined,
                menu: menuFilter || undefined,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
                shift: shiftFilter || undefined,
            }, {
                preserveState: true,
                replace: true,
                preserveScroll: true,
            });
        }, 350);

        return () => clearTimeout(timeoutId);
    }, [indexRouteName, search, menuFilter, dateFrom, dateTo, shiftFilter]);

    const resetFilters = () => {
        skipAutoFilter.current = true;
        setSearch('');
        setMenuFilter('');
        setDateFrom('');
        setDateTo('');
        setShiftFilter('');

        router.get(route(indexRouteName), {}, {
            preserveState: true,
            replace: true,
        });
    };

    const handleDelete = (id) => {
        if (confirm('Delete this meal order?')) {
            router.delete(route(destroyRouteName, id));
        }
    };

    const buildPrintHref = (period) => {
        const params = new URLSearchParams();

        params.set('period', period);

        if (search) {
            params.set('search', search);
        }

        if (menuFilter) {
            params.set('menu', menuFilter);
        }

        if (dateFrom) {
            params.set('date_from', dateFrom);
        }

        if (dateTo) {
            params.set('date_to', dateTo);
        }

        if (shiftFilter) {
            params.set('shift', shiftFilter);
        }

        return `${route(printRouteName)}?${params.toString()}`;
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-bold leading-tight text-slate-800">
                    {isExitPermitMode ? 'Order Meal Exit Permit' : 'Order Meal'}
                </h2>
            }
        >
            <Head title={isExitPermitMode ? 'Order Meal Exit Permit' : 'Order Meal'} />

            <div className="space-y-6">
                <div className="relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="pointer-events-none absolute -right-16 -top-16 h-48 w-48 rounded-full bg-cyan-100/50" />
                    <div className="pointer-events-none absolute -bottom-20 -left-12 h-52 w-52 rounded-full bg-emerald-100/40" />
                    <div className="relative flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-700">Meal Operations</p>
                            <h3 className="mt-2 text-3xl font-black tracking-tight text-slate-900">
                                {isExitPermitMode ? 'Order Meal Exit Permit' : 'Order Meal'}
                            </h3>
                            <p className="mt-2 text-sm text-slate-600">
                                {isExitPermitMode
                                    ? 'Only for employees who have passed the Exit Permit flow and Sisca attendance verification.'
                                    : 'Track general pack distribution, actual consumption, and additional visitors in one view.'}
                            </p>
                        </div>
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <div className="rounded-xl border border-slate-200 bg-white/90 px-4 py-3 text-sm shadow-sm backdrop-blur">
                                <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Today</p>
                                <p className="mt-1 font-semibold text-slate-700">{todayLabel}</p>
                            </div>
                            <div className="grid grid-cols-3 gap-2">
                                <a
                                    href={buildPrintHref('daily')}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100"
                                >
                                    Print Daily
                                </a>
                                <a
                                    href={buildPrintHref('weekly')}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100"
                                >
                                    Print Weekly
                                </a>
                                <a
                                    href={buildPrintHref('monthly')}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100"
                                >
                                    Print Monthly
                                </a>
                            </div>
                            <button
                                type="button"
                                onClick={() => setShowCheckMealModal(true)}
                                className="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-indigo-500"
                            >
                                Check Order Meal
                            </button>
                            <Link
                                href={route(createRouteName)}
                                className="inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-700"
                            >
                                + Add Order Meal
                            </Link>
                        </div>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Provided Packs</p>
                        <p className="mt-3 text-4xl font-black text-slate-900">{summary.provided_total}</p>
                    </div>
                    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Actual Meals</p>
                        <p className="mt-3 text-4xl font-black text-cyan-700">{summary.actual_total}</p>
                    </div>
                    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Remaining Packs</p>
                        <p className="mt-3 text-4xl font-black text-emerald-700">{summary.remaining_total}</p>
                    </div>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Distribution Efficiency</p>
                            <p className="mt-1 text-sm text-slate-600">Used packs compared to provided packs.</p>
                        </div>
                        <div className="text-right">
                            <p className="text-3xl font-black text-cyan-700">{utilizationPct}%</p>
                            <p className="text-xs text-slate-500">Waste {wastePct}%</p>
                        </div>
                    </div>
                    <div className="mt-4 h-3 overflow-hidden rounded-full bg-slate-100">
                        <div
                            className="h-3 rounded-full bg-cyan-600"
                            style={{ width: `${Math.max(2, Math.min(100, utilizationPct))}%` }}
                        />
                    </div>
                    <p className="mt-2 text-xs text-slate-500">
                        {actualTotal} of {providedTotal} packs have been consumed.
                    </p>
                </div>

                <div className="grid gap-4 xl:grid-cols-3">
                    <NotEatenChart title="Daily Chart" points={notEatenCharts?.daily ?? []} group="daily" />
                    <NotEatenChart title="Weekly Chart" points={notEatenCharts?.weekly ?? []} group="weekly" />
                    <NotEatenChart title="Monthly Chart" points={notEatenCharts?.monthly ?? []} group="monthly" />
                </div>

                <div className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-5 py-4">
                        <div>
                            <p className="text-sm font-semibold text-slate-900">Order Meal List</p>
                            <p className="text-xs text-slate-500">Total records: {totalRows}</p>
                            {!isExitPermitMode && (
                                <p className="mt-1 text-xs text-slate-500">
                                    Amount is calculated from provided packs (quantity) x unit price + tax, not from actual meals.
                                </p>
                            )}
                        </div>
                        <div className="grid w-full gap-2 md:w-auto md:grid-cols-7">
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search employee/menu"
                                className="rounded-md border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700"
                            />
                            <input
                                type="date"
                                value={dateFrom}
                                onChange={(e) => setDateFrom(e.target.value)}
                                className="rounded-md border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700"
                            />
                            <input
                                type="date"
                                value={dateTo}
                                onChange={(e) => setDateTo(e.target.value)}
                                className="rounded-md border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700"
                            />
                            <select
                                value={menuFilter}
                                onChange={(e) => setMenuFilter(e.target.value)}
                                className="rounded-md border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700"
                            >
                                <option value="">All Menus</option>
                                {(orderMeals?.data ?? [])
                                    .map((item) => String(item.menu_name ?? '').trim())
                                    .filter(Boolean)
                                    .filter((menu, index, all) => all.indexOf(menu) === index)
                                    .map((menu) => (
                                        <option key={menu} value={menu}>{menu}</option>
                                    ))}
                            </select>
                            <select
                                value={shiftFilter}
                                onChange={(e) => setShiftFilter(e.target.value)}
                                className="rounded-md border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700"
                            >
                                <option value="">All Shifts</option>
                                {isExitPermitMode ? (
                                    <>
                                        <option value="single">Single</option>
                                        <option value="daily">Daily</option>
                                        <option value="weekly">Weekly</option>
                                    </>
                                ) : (
                                    <>
                                        <option value="day">Day Shift</option>
                                        <option value="ot_day">OT Day</option>
                                        <option value="night">Night Shift</option>
                                        <option value="ot_night">OT Night</option>
                                    </>
                                )}
                            </select>
                            <div className="flex items-center rounded-md border border-cyan-200 bg-cyan-50 px-3 py-2 text-xs font-semibold text-cyan-700">
                                Auto Filter On
                            </div>
                            <button
                                type="button"
                                onClick={resetFilters}
                                className="rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100"
                            >
                                Reset
                            </button>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-100">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Employee</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Date</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Menu</th>
                                    {isExitPermitMode ? (
                                        <>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Schedule</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Visitor</th>
                                        </>
                                    ) : (
                                        <>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Day Shift</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">OT Day</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Night Shift</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">OT Night</th>
                                        </>
                                    )}
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Provided</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Actual</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Remaining</th>
                                    {!isExitPermitMode && <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Amount</th>}
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {(orderMeals?.data ?? []).map((item) => {
                                    const remaining = Number(item.remaining_quantity ?? 0);
                                    const provided = Number(item.quantity ?? 0);
                                    const remainingTone = getRemainingTone(remaining, provided);

                                    return (
                                    <tr key={item.id} className="transition hover:bg-slate-50">
                                        <td className="px-4 py-3 font-semibold text-slate-800">{item.employee_name}</td>
                                        <td className="px-4 py-3 text-slate-700">{formatShortIdDate(item.meal_date)}</td>
                                        <td className="px-4 py-3 text-slate-700">{item.menu_name}</td>
                                        {isExitPermitMode ? (
                                            <>
                                                <td className="px-4 py-3 text-slate-700">{item.schedule_type ?? 'single'}</td>
                                                <td className="px-4 py-3 text-slate-700">{item.visitor_count ?? 0}</td>
                                            </>
                                        ) : (
                                            <>
                                                <td className="px-4 py-3 text-slate-700">{item.day_shift_qty ?? 0}</td>
                                                <td className="px-4 py-3 text-slate-700">{item.overtime_day_shift_qty ?? 0}</td>
                                                <td className="px-4 py-3 text-slate-700">{item.night_shift_qty ?? 0}</td>
                                                <td className="px-4 py-3 text-slate-700">{item.overtime_night_shift_qty ?? 0}</td>
                                            </>
                                        )}
                                        <td className="px-4 py-3 text-slate-700">{item.quantity}</td>
                                        <td className="px-4 py-3 text-slate-700">{item.actual_quantity}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-2">
                                                <span className="font-semibold text-slate-800">{remaining}</span>
                                                <span className={`inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ${remainingTone.badge}`}>
                                                    {remainingTone.label}
                                                </span>
                                            </div>
                                        </td>
                                        {!isExitPermitMode && (
                                            <td className="px-4 py-3 text-slate-700">
                                                <p>{currencyFormatter.format(item.total_amount ?? 0)}</p>
                                                {Number(item.actual_quantity ?? 0) === 0 && (
                                                    <p className="text-[11px] text-slate-500">Actual 0, cost is still based on provided packs.</p>
                                                )}
                                            </td>
                                        )}
                                        <td className="px-4 py-3">
                                            <div className="flex flex-nowrap items-center gap-2">
                                                <Link
                                                    href={route(showRouteName, item.id)}
                                                    className="inline-flex h-9 min-w-[78px] items-center justify-center whitespace-nowrap rounded bg-slate-700 px-3 text-center text-xs font-semibold leading-none text-white transition hover:bg-slate-600"
                                                >
                                                    Detail
                                                </Link>
                                                <a
                                                    href={route(printItemRouteName, item.id)}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="inline-flex h-9 min-w-[88px] items-center justify-center whitespace-nowrap rounded bg-indigo-700 px-3 text-center text-xs font-semibold leading-none text-white transition hover:bg-indigo-600"
                                                >
                                                    Print PDF
                                                </a>
                                                <Link
                                                    href={route(editRouteName, item.id)}
                                                    className="inline-flex h-9 min-w-[78px] items-center justify-center whitespace-nowrap rounded bg-cyan-700 px-3 text-center text-xs font-semibold leading-none text-white transition hover:bg-cyan-600"
                                                >
                                                    Edit
                                                </Link>
                                                <button
                                                    type="button"
                                                    onClick={() => handleDelete(item.id)}
                                                    className="inline-flex h-9 min-w-[78px] items-center justify-center whitespace-nowrap rounded bg-rose-600 px-3 text-center text-xs font-semibold leading-none text-white transition hover:bg-rose-500"
                                                >
                                                    Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {!(orderMeals?.data ?? []).length && (
                        <div className="px-4 py-10 text-center text-sm text-slate-500">No order meal data yet.</div>
                    )}

                    {hasActiveFilter && (
                        <div className="border-t border-slate-200 px-4 py-3 text-xs text-slate-500">
                            Active filters apply across all data and pagination.
                        </div>
                    )}

                    {orderMeals.links && orderMeals.links.length > 3 && (
                        <div className="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 px-4 py-4">
                            {orderMeals.links.map((link) => {
                                if (!link.url) {
                                    return (
                                        <span
                                            key={link.label}
                                            className="rounded-md border border-slate-200 px-3 py-1.5 text-xs text-slate-400"
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    );
                                }

                                return (
                                    <Link
                                        key={link.label}
                                        href={link.url}
                                        className={
                                            `rounded-md border px-3 py-1.5 text-xs font-medium transition ` +
                                            (link.active
                                                ? 'border-slate-900 bg-slate-900 text-white'
                                                : 'border-slate-300 bg-white text-slate-600 hover:bg-slate-100')
                                        }
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>

            {showCheckMealModal && checkMealFormula && (
                <div className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto overflow-x-hidden bg-slate-900/50 p-4 backdrop-blur-sm">
                    <div className="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                        <h3 className="mb-4 text-xl font-bold text-slate-900">Check Order Meal Calculation</h3>
                        {checkMealFormula.attendance_warning && (
                            <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                {checkMealFormula.attendance_warning}
                            </div>
                        )}
                        <div className="space-y-3 text-sm text-slate-700">
                            <div className="overflow-hidden rounded-lg border border-slate-200">
                                <table className="min-w-full text-sm">
                                    <tbody className="divide-y divide-slate-200">
                                        <tr>
                                            <td className="px-3 py-2 font-medium text-slate-700">Man Power (Total Karyawan)</td>
                                            <td className="px-3 py-2 text-right font-semibold text-slate-900">{checkMealFormula.total_karyawan}</td>
                                        </tr>
                                        <tr>
                                            <td className="px-3 py-2 font-medium text-slate-700">Karyawan yang Hadir (Absensi)</td>
                                            <td className="px-3 py-2 text-right font-semibold text-slate-900">{checkMealFormula.karyawan_hadir_absensi}</td>
                                        </tr>
                                        <tr>
                                            <td className="px-3 py-2 font-medium text-slate-700">Not Clock In (Exit Permit)</td>
                                            <td className="px-3 py-2 text-right font-semibold text-emerald-600">{checkMealFormula.karyawan_hadir_plan_check_in}</td>
                                        </tr>
                                        <tr>
                                            <td className="px-3 py-2 font-medium text-slate-700">Karyawan yang Hadir (Adjusted)</td>
                                            <td className="px-3 py-2 text-right font-semibold text-slate-900">{checkMealFormula.karyawan_hadir}</td>
                                        </tr>
                                        <tr>
                                            <td className="px-3 py-2 font-medium text-slate-700">Overtime Day Shift</td>
                                            <td className="px-3 py-2 text-right font-semibold text-slate-500">-</td>
                                        </tr>
                                        <tr>
                                            <td className="px-3 py-2 font-medium text-slate-700">Night Shift</td>
                                            <td className="px-3 py-2 text-right font-semibold text-slate-500">-</td>
                                        </tr>
                                        <tr>
                                            <td className="px-3 py-2 font-medium text-slate-700">Overtime Night Shift</td>
                                            <td className="px-3 py-2 text-right font-semibold text-slate-500">-</td>
                                        </tr>
                                        <tr>
                                            <td className="px-3 py-2 font-medium text-slate-700">Karyawan yang Absen</td>
                                            <td className="px-3 py-2 text-right font-semibold text-rose-600">{checkMealFormula.karyawan_absen}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div className="mt-4 rounded-xl bg-slate-50 p-4">
                                <p className="mb-2 text-xs font-semibold uppercase text-slate-500">Formula</p>
                                <p className="rounded border border-slate-200 bg-white p-2 font-mono text-sm text-slate-700">
                                    {checkMealFormula.total_karyawan} - (({checkMealFormula.exit_permit} - {checkMealFormula.karyawan_hadir_plan_check_in}) + {checkMealFormula.karyawan_absen})
                                </p>
                                <div className="mt-3 flex items-center justify-between border-t border-slate-200 pt-3">
                                    <span className="font-bold text-slate-900">Total Makan di Kantin</span>
                                    <span className="text-2xl font-black text-cyan-700">{checkMealFormula.check_order_meal}</span>
                                </div>
                            </div>
                        </div>
                        <div className="mt-6 flex justify-end">
                            <button
                                type="button"
                                onClick={() => setShowCheckMealModal(false)}
                                className="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
