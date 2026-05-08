import { Link, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

export default function AuthenticatedLayout({ header, children }) {
    const user = usePage().props.auth.user;
    const flash = usePage().props.flash;
    const notifications = usePage().props.notifications ?? { unread: [], unread_count: 0 };
    const [showSidebar, setShowSidebar] = useState(false);
    const [showUserMenu, setShowUserMenu] = useState(false);
    const userMenuRef = useRef(null);
    const isRatna = String(user?.email ?? '').toLowerCase() === 'ratna@example.com';
    const isSisca = String(user?.email ?? '').toLowerCase() === 'sisca.dewiyani@example.com';
    const isHr = user?.role?.code === 'hr';
    const isExitPermitApprovalUser = ['manager', 'md', 'hr_manager'].includes(user?.role?.code)
        || (isHr && isSisca);

    const menuItems = [
        { label: 'Dashboard', routeName: 'dashboard' },
        ...(isHr ? [{ label: 'List Exit Permit', routeName: 'exit-permit-list.index' }] : []),
        ...(isRatna ? [{ label: 'Schedule Car', routeName: 'schedule-cars.index' }] : []),
        { label: 'Exit Permit', routeName: 'exit-permits.index' },
        ...(isExitPermitApprovalUser ? [{ label: 'Exit Permit Approval', routeName: 'exit-permit-approvals.index' }] : []),
        ...(isSisca ? [{ label: 'Order Meal Umum', routeName: 'order-meals.index' }] : []),
        { label: 'Reimbursement', routeName: 'reimbursements.index' },
        { label: 'Profile', routeName: 'profile.edit' },
    ];

    const userInitials = (user?.name ?? 'U')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();

    useEffect(() => {
        const onClickAway = (event) => {
            if (userMenuRef.current && !userMenuRef.current.contains(event.target)) {
                setShowUserMenu(false);
            }
        };

        document.addEventListener('mousedown', onClickAway);

        return () => {
            document.removeEventListener('mousedown', onClickAway);
        };
    }, []);

    return (
        <div className="min-h-screen bg-slate-100 transition-colors duration-200 dark:bg-slate-950">
            <div className="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_20%_10%,rgba(14,116,144,0.12),transparent_28%),radial-gradient(circle_at_85%_0%,rgba(30,41,59,0.1),transparent_28%)] dark:bg-[radial-gradient(circle_at_20%_10%,rgba(6,182,212,0.14),transparent_28%),radial-gradient(circle_at_85%_0%,rgba(100,116,139,0.16),transparent_28%)]" />
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
                        <div className="flex items-center gap-3">
                            {user.profile_photo_url ? (
                                <img
                                    src={user.profile_photo_url}
                                    alt={user.name}
                                    className="h-10 w-10 rounded-full border border-slate-600 object-cover"
                                />
                            ) : (
                                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-slate-700 text-xs font-bold text-cyan-100">
                                    {userInitials}
                                </div>
                            )}
                            <div>
                                <p className="font-semibold text-white">{user.name}</p>
                                <p className="text-slate-300">{user.email}</p>
                            </div>
                        </div>
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
                    <header className="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur transition-colors duration-200 dark:border-slate-700 dark:bg-slate-900/90">
                        <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                            <div className="flex items-center gap-3">
                                <button
                                    type="button"
                                    className="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 dark:border-slate-600 dark:text-slate-100 dark:hover:bg-slate-800 lg:hidden"
                                    onClick={() => setShowSidebar(true)}
                                >
                                    Menu
                                </button>
                                {header}
                            </div>

                            <div className="relative flex items-center gap-2" ref={userMenuRef}>
                                <button
                                    type="button"
                                    className="flex items-center gap-3 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
                                    onClick={() => setShowUserMenu((prev) => !prev)}
                                >
                                    {user.profile_photo_url ? (
                                        <img
                                            src={user.profile_photo_url}
                                            alt={user.name}
                                            className="h-8 w-8 rounded-full border border-slate-200 object-cover"
                                        />
                                    ) : (
                                        <span className="flex h-8 w-8 items-center justify-center rounded-full bg-slate-800 text-xs font-bold text-white">
                                            {userInitials}
                                        </span>
                                    )}
                                    <span className="hidden sm:block">{user.name}</span>
                                    <span className="text-xs text-slate-400 dark:text-slate-300">▾</span>
                                </button>

                                {showUserMenu && (
                                    <div className="absolute right-0 z-30 mt-2 w-60 rounded-xl border border-slate-200 bg-white p-2 shadow-lg dark:border-slate-700 dark:bg-slate-900">
                                        <div className="mb-2 flex items-center gap-3 rounded-lg bg-slate-50 p-3 dark:bg-slate-800">
                                            {user.profile_photo_url ? (
                                                <img
                                                    src={user.profile_photo_url}
                                                    alt={user.name}
                                                    className="h-10 w-10 rounded-full border border-slate-200 object-cover"
                                                />
                                            ) : (
                                                <span className="flex h-10 w-10 items-center justify-center rounded-full bg-slate-800 text-sm font-bold text-white">
                                                    {userInitials}
                                                </span>
                                            )}
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">{user.name}</p>
                                                <p className="truncate text-xs text-slate-500 dark:text-slate-300">{user.email}</p>
                                            </div>
                                        </div>

                                        <Link
                                            href={route('profile.edit')}
                                            className="block rounded-md px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 dark:text-slate-100 dark:hover:bg-slate-800"
                                            onClick={() => setShowUserMenu(false)}
                                        >
                                            Profile
                                        </Link>

                                        <Link
                                            href={`${route('profile.edit')}#profile-setting`}
                                            className="block rounded-md px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 dark:text-slate-100 dark:hover:bg-slate-800"
                                            onClick={() => setShowUserMenu(false)}
                                        >
                                            Setting
                                        </Link>

                                        <Link
                                            href={route('logout')}
                                            method="post"
                                            as="button"
                                            className="mt-1 block w-full rounded-md px-3 py-2 text-left text-sm font-semibold text-rose-600 transition hover:bg-rose-50"
                                            onClick={() => setShowUserMenu(false)}
                                        >
                                            Logout
                                        </Link>
                                    </div>
                                )}
                            </div>
                        </div>
                    </header>

                    <main className="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
                        {/* Notifikasi sementara di-hide */}

                        {flash?.success && (
                            <div className="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-500/40 dark:bg-emerald-900/30 dark:text-emerald-100">
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
