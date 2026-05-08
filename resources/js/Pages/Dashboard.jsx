import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

const currencyFormatter = new Intl.NumberFormat('id-ID');

export default function Dashboard({ stats, mealTrend, canViewMealAnalytics = false, canAccessExitPermitApproval = false }) {
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

            <div className="grid gap-6 xl:grid-cols-4">
                <Link
                    href={route('exit-permits.index')}
                    className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-md hover:ring-cyan-300"
                >
                    <p className="text-sm font-medium text-slate-500">Total Exit Permit</p>
                    <p className="mt-3 text-4xl font-bold text-slate-900">
                        {stats.exitPermitCount}
                    </p>
                </Link>

                {canAccessExitPermitApproval && (
                    <Link
                        href={route('exit-permit-approvals.index')}
                        className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-md hover:ring-cyan-300"
                    >
                        <p className="text-sm font-medium text-slate-500">Exit Permit Approval</p>
                        <p className="mt-3 text-4xl font-bold text-slate-900">
                            {stats.exitPermitApprovalCount}
                        </p>
                    </Link>
                )}

                {canViewMealAnalytics && (
                    <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                        <p className="text-sm font-medium text-slate-500">Exit Permit Eligible Meal</p>
                        <p className="mt-3 text-4xl font-bold text-slate-900">
                            {stats.eligibleMealCount}
                        </p>
                    </div>
                )}

                <Link
                    href={route('reimbursements.index')}
                    className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-md hover:ring-cyan-300"
                >
                    <p className="text-sm font-medium text-slate-500">Total Reimbursement Approved</p>
                    <p className="mt-3 text-4xl font-bold text-slate-900">
                        Rp {currencyFormatter.format(stats.reimbursementTotal)}
                    </p>
                </Link>

                {canViewMealAnalytics && (
                    <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                        <p className="text-sm font-medium text-slate-500">Sisa Paket Makan Siang</p>
                        <p className="mt-3 text-4xl font-bold text-emerald-600">
                            {stats.remainingMealCount}
                        </p>
                    </div>
                )}

                {canViewMealAnalytics && (
                    <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200 xl:col-span-2">
                        <p className="text-sm font-medium text-slate-500">Ringkasan Lunch Pack</p>
                        <div className="mt-4 grid gap-4 sm:grid-cols-3">
                            <div className="rounded-lg bg-slate-50 p-4">
                                <p className="text-sm text-slate-500">Disediakan</p>
                                <p className="mt-2 text-3xl font-bold text-slate-900">{stats.providedMealCount}</p>
                            </div>
                            <div className="rounded-lg bg-slate-50 p-4">
                                <p className="text-sm text-slate-500">Realisasi</p>
                                <p className="mt-2 text-3xl font-bold text-slate-900">{stats.actualMealCount}</p>
                            </div>
                            <div className="rounded-lg bg-emerald-50 p-4">
                                <p className="text-sm text-emerald-700">Sisa</p>
                                <p className="mt-2 text-3xl font-bold text-emerald-700">{stats.remainingMealCount}</p>
                            </div>
                        </div>
                    </div>
                )}

                {canViewMealAnalytics && (
                    <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200 xl:col-span-2">
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <p className="text-sm font-medium text-slate-500">Trend Order Meal</p>
                                <p className="mt-1 text-sm text-slate-700">
                                    Perbandingan paket disediakan, realita makan, dan sisa paket per hari.
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-4 text-xs text-slate-600">
                                <span className="flex items-center gap-2"><span className="h-2.5 w-2.5 rounded-full bg-slate-900" />Disediakan</span>
                                <span className="flex items-center gap-2"><span className="h-2.5 w-2.5 rounded-full bg-sky-500" />Realisasi</span>
                                <span className="flex items-center gap-2"><span className="h-2.5 w-2.5 rounded-full bg-emerald-500" />Sisa</span>
                            </div>
                        </div>
                    

                    {mealTrend.length === 0 ? (
                        <div className="mt-6 rounded-lg border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-500">
                            Belum ada data order meal untuk ditampilkan di grafik.
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
                                                        label: 'Disediakan',
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
                                                        label: 'Realisasi',
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
                                                        label: 'Sisa',
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
            </div>
        </AuthenticatedLayout>
    );
}
