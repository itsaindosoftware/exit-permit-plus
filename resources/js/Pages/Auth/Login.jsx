import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout variant="wide">
            <Head title="Log in" />

            <div className="grid w-full overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-[0_20px_70px_-25px_rgba(15,23,42,0.45)] lg:grid-cols-5">
                <div className="relative hidden overflow-hidden bg-gradient-to-br from-slate-900 via-cyan-900 to-cyan-700 p-8 text-white lg:col-span-2 lg:block">
                    <div className="pointer-events-none absolute -left-20 -top-20 h-56 w-56 rounded-full border border-white/20" />
                    <div className="pointer-events-none absolute -bottom-24 -right-16 h-64 w-64 rounded-full bg-white/10" />

                    <p className="text-xs font-semibold uppercase tracking-[0.32em] text-cyan-100">Corporate Portal</p>
                    <h1 className="mt-4 text-3xl font-black leading-tight">
                        Exit Permit
                        <br />
                        and Meal System
                    </h1>
                    <p className="mt-4 text-sm text-cyan-100/90">
                        Platform internal perusahaan untuk pengajuan izin keluar, order meal,
                        serta approval operasional lintas departemen.
                    </p>

                    <div className="mt-8 space-y-3 text-sm text-cyan-50/90">
                        <p className="rounded-xl border border-white/15 bg-white/5 px-4 py-3">Approval berjenjang Manager, MD, dan HR.</p>
                        <p className="rounded-xl border border-white/15 bg-white/5 px-4 py-3">Monitoring realtime untuk reimbursement dan lunch pack.</p>
                    </div>
                </div>

                <div className="p-6 sm:p-8 lg:col-span-3 lg:p-10">
                    <div className="mb-8">
                        <p className="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-700">Secure Access</p>
                        <h2 className="mt-2 text-3xl font-black text-slate-900">Welcome Back</h2>
                        <p className="mt-2 text-sm text-slate-600">Masuk menggunakan akun perusahaan untuk melanjutkan pekerjaan Anda.</p>
                    </div>

                    {status && (
                        <div className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                            {status}
                        </div>
                    )}

                    <form onSubmit={submit} className="space-y-4">
                        <div>
                            <label htmlFor="email" className="text-sm font-semibold text-slate-700">Email</label>
                            <TextInput
                                id="email"
                                type="email"
                                name="email"
                                value={data.email}
                                className="mt-1 block w-full rounded-xl border-slate-300 px-4 py-3 text-slate-900 shadow-sm focus:border-cyan-600 focus:ring-cyan-600"
                                autoComplete="username"
                                isFocused={true}
                                onChange={(e) => setData('email', e.target.value)}
                                placeholder="name@company.com"
                            />
                            <InputError message={errors.email} className="mt-2" />
                        </div>

                        <div>
                            <label htmlFor="password" className="text-sm font-semibold text-slate-700">Password</label>
                            <TextInput
                                id="password"
                                type="password"
                                name="password"
                                value={data.password}
                                className="mt-1 block w-full rounded-xl border-slate-300 px-4 py-3 text-slate-900 shadow-sm focus:border-cyan-600 focus:ring-cyan-600"
                                autoComplete="current-password"
                                onChange={(e) => setData('password', e.target.value)}
                                placeholder="Masukkan password"
                            />
                            <InputError message={errors.password} className="mt-2" />
                        </div>

                        <div className="flex flex-wrap items-center justify-between gap-3 pt-1">
                            <label className="flex items-center gap-2 text-sm text-slate-600">
                                <Checkbox
                                    name="remember"
                                    checked={data.remember}
                                    onChange={(e) => setData('remember', e.target.checked)}
                                />
                                Remember me
                            </label>

                            {canResetPassword && (
                                <Link
                                    href={route('password.request')}
                                    className="text-sm font-medium text-cyan-700 transition hover:text-cyan-600"
                                >
                                    Forgot your password?
                                </Link>
                            )}
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="mt-2 inline-flex w-full items-center justify-center rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold uppercase tracking-wide text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            Log in
                        </button>
                    </form>
                </div>
            </div>
        </GuestLayout>
    );
}
