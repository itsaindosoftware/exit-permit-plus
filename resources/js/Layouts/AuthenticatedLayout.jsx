import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function AuthenticatedLayout({ header, children }) {
    const user = usePage().props.auth.user;
    const flash = usePage().props.flash;
    const notifications = usePage().props.notifications ?? { unread: [], unread_count: 0 };
    const [showSidebar, setShowSidebar] = useState(false);

    const menuItems = [
        { label: 'Dashboard', routeName: 'dashboard' },
        { label: 'Exit Permit', routeName: 'exit-permits.index' },
        { label: 'Order Meal Umum', routeName: 'order-meals.index' },
        { label: 'Order Meal Exit Permit', routeName: 'exit-permit-meals.index' },
        { label: 'Reimbursement', routeName: 'reimbursements.index' },
        { label: 'Profile', routeName: 'profile.edit' },
    ];

    return (
        <div className="min-h-screen bg-slate-100">
            <div className="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_20%_10%,rgba(14,116,144,0.12),transparent_28%),radial-gradient(circle_at_85%_0%,rgba(30,41,59,0.1),transparent_28%)]" />
            <div className="flex min-h-screen">
                <aside
                    className={
                        `fixed inset-y-0 left-0 z-40 w-72 transform border-r border-slate-700/70 bg-slate-900 p-6 text-slate-100 transition-transform duration-200 lg:static lg:translate-x-0 ` +
                        (showSidebar ? 'translate-x-0' : '-translate-x-full')
                    }
                >
                    <div className="mb-10 border-b border-slate-700 pb-6">
                        <p className="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-300">Internal System</p>
                        <h1 className="mt-2 text-2xl font-black tracking-wide text-white">Exit Permit+</h1>
                        <p className="mt-1 text-sm text-slate-300">Operational Management Portal</p>
                    </div>

                    <nav className="space-y-2">
                        {menuItems.map((item) => {
                            const isActive = route().current(item.routeName);

                            return (
                                <Link
                                    key={item.routeName}
                                    href={route(item.routeName)}
                                    className={
                                        `block rounded-lg border px-4 py-2 text-sm font-semibold transition ` +
                                        (isActive
                                            ? 'border-cyan-400/50 bg-cyan-500/20 text-white'
                                            : 'border-transparent text-slate-200 hover:border-slate-600 hover:bg-slate-800')
                                    }
                                    onClick={() => setShowSidebar(false)}
                                >
                                    {item.label}
                                </Link>
                            );
                        })}
                    </nav>

                    <div className="mt-10 rounded-xl border border-slate-700 bg-slate-800/80 p-4 text-sm backdrop-blur">
                        <p className="font-semibold text-white">{user.name}</p>
                        <p className="text-slate-300">{user.email}</p>
                        <p className="mt-3 inline-block rounded-full bg-slate-700 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wider text-cyan-100">
                            {user.role?.name ?? 'No Role'}
                        </p>
                    </div>
                </aside>

                {showSidebar && (
                    <button
                        type="button"
                        className="fixed inset-0 z-30 bg-black/40 lg:hidden"
                        onClick={() => setShowSidebar(false)}
                    />
                )}

                <div className="relative flex-1 lg:ml-0">
                    <header className="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
                        <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                            <div className="flex items-center gap-3">
                                <button
                                    type="button"
                                    className="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 lg:hidden"
                                    onClick={() => setShowSidebar(true)}
                                >
                                    Menu
                                </button>
                                {header}
                            </div>

                            <Link
                                href={route('logout')}
                                method="post"
                                as="button"
                                className="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700"
                            >
                                Log Out
                            </Link>
                        </div>
                    </header>

                    <main className="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
                        {notifications.unread_count > 0 && (
                            <div className="mb-4 rounded-lg border border-cyan-200 bg-cyan-50 px-4 py-3">
                                <p className="text-sm font-semibold text-cyan-900">
                                    Notifikasi ({notifications.unread_count})
                                </p>
                                <div className="mt-2 space-y-2">
                                    {notifications.unread.map((notification) => (
                                        <div key={notification.id} className="rounded-md border border-cyan-200 bg-white px-3 py-2">
                                            <p className="text-sm font-semibold text-slate-900">{notification.title}</p>
                                            <p className="text-sm text-slate-700">{notification.message}</p>
                                            <p className="mt-1 text-xs text-slate-500">{notification.created_at}</p>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {flash?.success && (
                            <div className="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                                {flash.success}
                            </div>
                        )}

                        {children}
                    </main>
                </div>
            </div>
        </div>
    );
}
