import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children, variant = 'compact' }) {
    if (variant === 'wide') {
        return (
            <div className="relative min-h-screen overflow-hidden bg-slate-100">
                <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_12%_20%,rgba(14,116,144,0.18),transparent_34%),radial-gradient(circle_at_90%_10%,rgba(30,41,59,0.16),transparent_30%)]" />
                <div className="relative mx-auto flex min-h-screen w-full max-w-6xl items-center justify-center p-4 sm:p-8">
                    {children}
                </div>
            </div>
        );
    }

    return (
        <div className="flex min-h-screen flex-col items-center bg-gray-100 pt-6 sm:justify-center sm:pt-0">
            <div>
                <Link href="/">
                    <ApplicationLogo className="h-20 w-20 fill-current text-gray-500" />
                </Link>
            </div>

            <div className="mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg">
                {children}
            </div>
        </div>
    );
}
