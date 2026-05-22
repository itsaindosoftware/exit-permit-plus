import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

const currencyFormatter = new Intl.NumberFormat('en-US');
const shortDateFormatter = new Intl.DateTimeFormat('en-US', {
    weekday: 'short',
    day: '2-digit',
    month: 'short',
});

const stageCardClass = {
    manager: 'border-amber-200 bg-amber-50 text-amber-800',
    md: 'border-sky-200 bg-sky-50 text-sky-800',
    hr_manager: 'border-violet-200 bg-violet-50 text-violet-800',
};

const statusPillClass = {
    approved: 'bg-emerald-100 text-emerald-800',
    rejected: 'bg-rose-100 text-rose-800',
    pending: 'bg-amber-100 text-amber-800',
};

const formatShortDate = (value) => {
    if (!value) {
        return '-';
    }

    const parsed = new Date(`${value}T00:00:00`);

    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    return shortDateFormatter.format(parsed);
};

export default function Dashboard({
    stats,
    mealTrend,
    approvalStageCounts = { manager: 0, md: 0, hr_manager: 0 },
    recentExitPermits = [],
    canViewMealAnalytics = false,
    canAccessExitPermitApproval = false,
}) {
    const [hoveredBar, setHoveredBar] = useState(null);
    const chartWidth = 760;
    const chartHeight = 260;
    const chartPadding = 28;
    const chartMax = Math.max(1, ...mealTrend.flatMap((item) => [item.provided, item.actual, item.remaining]));
    const plotWidth = chartWidth - chartPadding * 2;
    const plotHeight = chartHeight - chartPadding * 2;
    const groupWidth = mealTrend.length > 0 ? plotWidth / mealTrend.length : 0;
    const barWidth = Math.min(18, groupWidth / 4);
    const barGap = 4;

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-slate-800">
                    Dashboard
                </h2>
            }
        >
            <Head title="Dashboard" />

            <div className="space-y-6">
                <section className="relative overflow-hidden rounded-2xl border border-cyan-200 bg-gradient-to-r from-cyan-50 via-white to-slate-50 p-6 shadow-sm">
                    <div className="absolute -right-12 -top-12 h-32 w-32 rounded-full bg-cyan-200/40 blur-2xl" />
                    <div className="absolute -bottom-16 right-24 h-36 w-36 rounded-full bg-sky-200/40 blur-2xl" />
                    <div className="relative flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-[0.26em] text-cyan-700">Personal Dashboard</p>
                            <h3 className="mt-2 text-2xl font-black text-slate-900">Your Activity Summary</h3>
                            <p className="mt-2 text-sm text-slate-600">
                                Track exit permit requests, approval progress, and reimbursements in one view.
                            </p>
                        </div>
                        <div className="rounded-xl border border-slate-200 bg-white/90 px-4 py-3 text-right shadow-sm">
                            <p className="text-xs font-semibold uppercase tracking-widest text-slate-500">Period</p>
                            <p className="mt-1 text-sm font-bold text-slate-800">{stats.monthLabel || '-'}</p>
                        </div>
                    </div>
                </section>

                <section className="grid gap-6 xl:grid-cols-4">
                <Link
                    href={route('exit-permits.index')}
                    className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-md hover:ring-cyan-300"
                >
                    <p className="text-sm font-medium text-slate-500">Total Exit Permit</p>
                    <p className="mt-3 text-4xl font-bold text-slate-900">
                        {stats.exitPermitCount}
                    </p>
                </Link>

                <Link
                    href={route('exit-permits.index', { month: new Date().getMonth() + 1, year: new Date().getFullYear() })}
                    className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-md hover:ring-cyan-300"
                >
                    <p className="text-sm font-medium text-slate-500">Requests This Month</p>
                    <p className="mt-3 text-4xl font-bold text-slate-900">
                        {stats.exitPermitThisMonthCount}
                    </p>
                </Link>

                <Link
                    href={route('exit-permits.index', { status: 'pending' })}
                    className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-md hover:ring-amber-300"
                >
                    <p className="text-sm font-medium text-slate-500">My Pending Approvals</p>
                    <p className="mt-3 text-4xl font-bold text-amber-600">
                        {stats.pendingApprovalMyCount}
                    </p>
                </Link>

                <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <p className="text-sm font-medium text-slate-500">Reimbursement Pending</p>
                    <p className="mt-3 text-4xl font-bold text-slate-900">
                        {stats.reimbursementPendingCount}
                    </p>
                </div>

                <Link
                    href={route('reimbursements.index')}
                    className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-md hover:ring-cyan-300 xl:col-span-2"
                >
                    <p className="text-sm font-medium text-slate-500">Total Reimbursement Approved</p>
                    <p className="mt-3 text-4xl font-bold text-slate-900">
                        Rp {currencyFormatter.format(stats.reimbursementTotal)}
                    </p>
                    <p className="mt-2 text-xs text-slate-500">
                        Approved this month: Rp {currencyFormatter.format(stats.reimbursementThisMonthApprovedTotal || 0)}
                    </p>
                </Link>

                {canAccessExitPermitApproval && (
                    <Link
                        href={route('exit-permit-approvals.index')}
                        className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-md hover:ring-cyan-300 xl:col-span-2"
                    >
                        <p className="text-sm font-medium text-slate-500">Exit Permit Approval</p>
                        <p className="mt-3 text-4xl font-bold text-slate-900">
                            {stats.exitPermitApprovalCount}
                        </p>
                    </Link>
                )}
                </section>

                <section className="grid gap-6 xl:grid-cols-3">
                    <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200 xl:col-span-2">
                        <div className="flex items-center justify-between gap-3">
                            <p className="text-sm font-semibold text-slate-700">My Approval Progress</p>
                            <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                Total: {stats.pendingApprovalMyCount}
                            </span>
                        </div>
                        <div className="mt-4 grid gap-3 sm:grid-cols-3">
                            <div className={`rounded-lg border px-4 py-3 ${stageCardClass.manager}`}>
                                <p className="text-xs font-semibold uppercase tracking-wider">Waiting Manager</p>
                                <p className="mt-2 text-3xl font-black">{approvalStageCounts.manager || 0}</p>
                            </div>
                            <div className={`rounded-lg border px-4 py-3 ${stageCardClass.md}`}>
                                <p className="text-xs font-semibold uppercase tracking-wider">Waiting MD</p>
                                <p className="mt-2 text-3xl font-black">{approvalStageCounts.md || 0}</p>
                            </div>
                            <div className={`rounded-lg border px-4 py-3 ${stageCardClass.hr_manager}`}>
                                <p className="text-xs font-semibold uppercase tracking-wider">Waiting HR Manager</p>
                                <p className="mt-2 text-3xl font-black">{approvalStageCounts.hr_manager || 0}</p>
                            </div>
                        </div>
                    </div>

                    <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                        <p className="text-sm font-semibold text-slate-700">Quick Actions</p>
                        <div className="mt-4 space-y-2">
                            <Link
                                href={route('exit-permits.create')}
                                className="block rounded-md bg-cyan-700 px-3 py-2 text-sm font-semibold text-white transition hover:bg-cyan-600"
                            >
                                + Create Exit Permit
                            </Link>
                            <Link
                                href={route('reimbursements.create')}
                                className="block rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                            >
                                + Create Reimbursement
                            </Link>
                            <Link
                                href={route('exit-permits.index')}
                                className="block rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                            >
                                View All Requests
                            </Link>
                        </div>
                    </div>
                </section>

                <section className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p className="text-sm font-semibold text-slate-700">Latest Exit Permit Activity</p>
                            <p className="mt-1 text-xs text-slate-500">Showing your latest 6 requests.</p>
                        </div>
                        <Link
                            href={route('exit-permits.index')}
                            className="text-sm font-semibold text-cyan-700 hover:text-cyan-600"
                        >
                            View all
                        </Link>
                    </div>

                    {recentExitPermits.length === 0 ? (
                        <div className="mt-4 rounded-lg border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-500">
                            No exit permit activity yet.
                        </div>
                    ) : (
                        <div className="mt-4 overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="text-left text-xs uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th className="px-2 py-2">Date</th>
                                        <th className="px-2 py-2">Requestor</th>
                                        <th className="px-2 py-2">Destination</th>
                                        <th className="px-2 py-2">Stage</th>
                                        <th className="px-2 py-2">Status</th>
                                        <th className="px-2 py-2">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 text-slate-700">
                                    {recentExitPermits.map((item) => (
                                        <tr key={item.id}>
                                            <td className="px-2 py-3">{formatShortDate(item.permit_date)}</td>
                                            <td className="px-2 py-3">{item.requestor_name || '-'}</td>
                                            <td className="px-2 py-3">{item.destination || '-'}</td>
                                            <td className="px-2 py-3">{item.stage || '-'}</td>
                                            <td className="px-2 py-3">
                                                <span className={`rounded-full px-2 py-1 text-xs font-semibold ${statusPillClass[item.status] || 'bg-slate-100 text-slate-700'}`}>
                                                    {(item.status || 'pending').toUpperCase()}
                                                </span>
                                            </td>
                                            <td className="px-2 py-3">
                                                <Link href={route('exit-permits.show', item.id)} className="text-cyan-700 hover:text-cyan-600">
                                                    Detail
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <section className="grid gap-6 xl:grid-cols-4">

                {canViewMealAnalytics && (
                    <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                        <p className="text-sm font-medium text-slate-500">Exit Permit Eligible Meal</p>
                        <p className="mt-3 text-4xl font-bold text-slate-900">
                            {stats.eligibleMealCount}
                        </p>
                    </div>
                )}

                {canViewMealAnalytics && (
                    <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                        <p className="text-sm font-medium text-slate-500">Remaining Lunch Packs</p>
                        <p className="mt-3 text-4xl font-bold text-emerald-600">
                            {stats.remainingMealCount}
                        </p>
                    </div>
                )}

                {canViewMealAnalytics && (
                    <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200 xl:col-span-2">
                        <p className="text-sm font-medium text-slate-500">Lunch Pack Summary</p>
                        <div className="mt-4 grid gap-4 sm:grid-cols-3">
                            <div className="rounded-lg bg-slate-50 p-4">
                                <p className="text-sm text-slate-500">Provided</p>
                                <p className="mt-2 text-3xl font-bold text-slate-900">{stats.providedMealCount}</p>
                            </div>
                            <div className="rounded-lg bg-slate-50 p-4">
                                <p className="text-sm text-slate-500">Actual</p>
                                <p className="mt-2 text-3xl font-bold text-slate-900">{stats.actualMealCount}</p>
                            </div>
                            <div className="rounded-lg bg-emerald-50 p-4">
                                <p className="text-sm text-emerald-700">Remaining</p>
                                <p className="mt-2 text-3xl font-bold text-emerald-700">{stats.remainingMealCount}</p>
                            </div>
                        </div>
                    </div>
                )}

                {canViewMealAnalytics && (
                    <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200 xl:col-span-2">
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <p className="text-sm font-medium text-slate-500">Meal Order Trend</p>
                                <p className="mt-1 text-sm text-slate-700">
                                    Comparison of provided packs, actual meals, and remaining packs per day.
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-4 text-xs text-slate-600">
                                <span className="flex items-center gap-2"><span className="h-2.5 w-2.5 rounded-full bg-slate-900" />Provided</span>
                                <span className="flex items-center gap-2"><span className="h-2.5 w-2.5 rounded-full bg-sky-500" />Actual</span>
                                <span className="flex items-center gap-2"><span className="h-2.5 w-2.5 rounded-full bg-emerald-500" />Remaining</span>
                            </div>
                        </div>


                    {mealTrend.length === 0 ? (
                        <div className="mt-6 rounded-lg border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-500">
                            No meal order data to display in the chart.
                        </div>
                    ) : (
                        <div className="mt-6 overflow-x-auto">
                            <svg viewBox={`0 0 ${chartWidth} ${chartHeight}`} className="min-w-[680px]">
                                {[0, 0.25, 0.5, 0.75, 1].map((step) => {
                                    const y = chartPadding + plotHeight * step;

                                    return (
                                        <line
                                            key={step}
                                            x1={chartPadding}
                                            x2={chartWidth - chartPadding}
                                            y1={y}
                                            y2={y}
                                            stroke="#e2e8f0"
                                            strokeDasharray="4 4"
                                        />
                                    );
                                })}

                                {mealTrend.map((item, index) => {
                                    const groupStart = chartPadding + groupWidth * index;
                                    const totalBarWidth = barWidth * 3 + barGap * 2;
                                    const barsStart = groupStart + (groupWidth - totalBarWidth) / 2;
                                    const labelY = chartHeight - 8;
                                    const makeHeight = (value) => (value / chartMax) * plotHeight;
                                    const providedHeight = makeHeight(item.provided);
                                    const actualHeight = makeHeight(item.actual);
                                    const remainingHeight = makeHeight(item.remaining);
                                    const barTopY = (height) => chartPadding + plotHeight - height;

                                    const tooltip = hoveredBar?.index === index
                                        ? {
                                              x: Math.min(
                                                  chartWidth - 48,
                                                  Math.max(48, hoveredBar.x),
                                              ),
                                              y: Math.max(chartPadding + 12, hoveredBar.y - 18),
                                          }
                                        : null;

                                    return (
                                        <g key={item.date}>
                                            <rect
                                                x={barsStart}
                                                y={barTopY(providedHeight)}
                                                width={barWidth}
                                                height={providedHeight}
                                                fill="#0f172a"
                                                rx="3"
                                                className="cursor-pointer"
                                                onMouseEnter={() =>
                                                    setHoveredBar({
                                                        index,
                                                        label: 'Provided',
                                                        value: item.provided,
                                                        x: barsStart + barWidth / 2,
                                                        y: barTopY(providedHeight),
                                                    })
                                                }
                                                onMouseLeave={() => setHoveredBar(null)}
                                            />
                                            <rect
                                                x={barsStart + barWidth + barGap}
                                                y={barTopY(actualHeight)}
                                                width={barWidth}
                                                height={actualHeight}
                                                fill="#0ea5e9"
                                                rx="3"
                                                className="cursor-pointer"
                                                onMouseEnter={() =>
                                                    setHoveredBar({
                                                        index,
                                                        label: 'Actual',
                                                        value: item.actual,
                                                        x: barsStart + barWidth + barGap + barWidth / 2,
                                                        y: barTopY(actualHeight),
                                                    })
                                                }
                                                onMouseLeave={() => setHoveredBar(null)}
                                            />
                                            <rect
                                                x={barsStart + (barWidth + barGap) * 2}
                                                y={barTopY(remainingHeight)}
                                                width={barWidth}
                                                height={remainingHeight}
                                                fill="#10b981"
                                                rx="3"
                                                className="cursor-pointer"
                                                onMouseEnter={() =>
                                                    setHoveredBar({
                                                        index,
                                                        label: 'Remaining',
                                                        value: item.remaining,
                                                        x: barsStart + (barWidth + barGap) * 2 + barWidth / 2,
                                                        y: barTopY(remainingHeight),
                                                    })
                                                }
                                                onMouseLeave={() => setHoveredBar(null)}
                                            />

                                            {tooltip && (
                                                <g>
                                                    <rect
                                                        x={tooltip.x - 42}
                                                        y={tooltip.y - 18}
                                                        width="84"
                                                        height="20"
                                                        rx="6"
                                                        fill="#0f172a"
                                                        opacity="0.92"
                                                    />
                                                    <text
                                                        x={tooltip.x}
                                                        y={tooltip.y - 5}
                                                        textAnchor="middle"
                                                        className="fill-white text-[10px] font-semibold"
                                                    >
                                                        {hoveredBar.label}: {hoveredBar.value}
                                                    </text>
                                                </g>
                                            )}

                                            <text
                                                x={groupStart + groupWidth / 2}
                                                y={labelY}
                                                textAnchor="middle"
                                                className="fill-slate-500 text-xs"
                                            >
                                                {item.date}
                                            </text>
                                        </g>
                                    );
                                })}
                            </svg>
                        </div>
                    )}
                    </div>
                )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
