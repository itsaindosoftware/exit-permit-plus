import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Transition } from '@headlessui/react';
import { Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
    className = '',
}) {
    const user = usePage().props.auth.user;
    const { data, setData, post, errors, processing, recentlySuccessful } =
        useForm({
            name: user.name,
            email: user.email,
            profile_photo: null,
            _method: 'patch',
        });
    const [previewPhotoUrl, setPreviewPhotoUrl] = useState(user.profile_photo_url ?? null);

    useEffect(() => {
        if (!(data.profile_photo instanceof File)) {
            setPreviewPhotoUrl(user.profile_photo_url ?? null);
            return undefined;
        }

        const objectUrl = URL.createObjectURL(data.profile_photo);
        setPreviewPhotoUrl(objectUrl);

        return () => {
            URL.revokeObjectURL(objectUrl);
        };
    }, [data.profile_photo, user.profile_photo_url]);

    const initials = (user?.name ?? 'U')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();

    const submit = (e) => {
        e.preventDefault();

        post(route('profile.update'), {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    return (
        <section className={className}>
            <header className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-lg font-medium text-gray-900 dark:text-slate-100">
                        Profile Information
                    </h2>

                    <p className="mt-1 text-sm text-gray-600 dark:text-slate-300">
                        Update your account's profile information and email address.
                    </p>
                </div>

            </header>

            <form onSubmit={submit} className="mt-6 space-y-6">
                <div>
                    <InputLabel htmlFor="profile_photo" value="Profile Photo" className="dark:text-slate-200" />

                    <div className="mt-3 flex items-center gap-4">
                        {previewPhotoUrl ? (
                            <img
                                src={previewPhotoUrl}
                                alt="Profile"
                                className="h-16 w-16 rounded-full border border-slate-200 object-cover"
                            />
                        ) : (
                            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-slate-800 text-lg font-bold text-white">
                                {initials}
                            </div>
                        )}

                        <input
                            id="profile_photo"
                            type="file"
                            accept="image/*"
                            className="block w-full text-sm text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-slate-900 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-slate-700 dark:text-slate-200 dark:file:bg-cyan-700 dark:hover:file:bg-cyan-600"
                            onChange={(e) => setData('profile_photo', e.target.files?.[0] ?? null)}
                        />
                    </div>

                    <p className="mt-1 text-xs text-gray-500 dark:text-slate-400">Format gambar: JPG/PNG, maksimal 2MB.</p>
                    <InputError className="mt-2 dark:text-rose-300" message={errors.profile_photo} />
                </div>

                <div>
                    <InputLabel htmlFor="name" value="Name" className="dark:text-slate-200" />

                    <TextInput
                        id="name"
                        className="mt-1 block w-full dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder-slate-400 dark:focus:border-cyan-500 dark:focus:ring-cyan-500"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        isFocused
                        autoComplete="name"
                    />

                    <InputError className="mt-2 dark:text-rose-300" message={errors.name} />
                </div>

                <div>
                    <InputLabel htmlFor="email" value="Email" className="dark:text-slate-200" />

                    <TextInput
                        id="email"
                        type="email"
                        className="mt-1 block w-full dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder-slate-400 dark:focus:border-cyan-500 dark:focus:ring-cyan-500"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        autoComplete="username"
                    />

                    <InputError className="mt-2 dark:text-rose-300" message={errors.email} />
                </div>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div>
                        <p className="mt-2 text-sm text-gray-800 dark:text-slate-200">
                            Your email address is unverified.
                            <Link
                                href={route('verification.send')}
                                method="post"
                                as="button"
                                className="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-slate-300 dark:hover:text-slate-100 dark:focus:ring-cyan-500 dark:focus:ring-offset-slate-900"
                            >
                                Click here to re-send the verification email.
                            </Link>
                        </p>

                        {status === 'verification-link-sent' && (
                            <div className="mt-2 text-sm font-medium text-green-600 dark:text-emerald-300">
                                A new verification link has been sent to your
                                email address.
                            </div>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-4">
                    <PrimaryButton className="dark:bg-cyan-700 dark:hover:bg-cyan-600 dark:focus:bg-cyan-600 dark:active:bg-cyan-800 dark:focus:ring-cyan-500 dark:focus:ring-offset-slate-900" disabled={processing}>Save</PrimaryButton>

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm text-gray-600 dark:text-slate-300">
                            Saved.
                        </p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
