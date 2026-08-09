import InputError from '@/Components/InputError';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

function BrandMark() {
    return (
        <Link
            href={route('landing')}
            className="inline-flex items-center gap-3 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#F1C85F] focus-visible:ring-offset-4 focus-visible:ring-offset-[#0B2E4F]"
        >
            <span className="grid size-11 place-items-center border border-white/20 bg-white/10 p-1.5 sm:size-12">
                <img src="/icons/icon.svg" alt="" width="40" height="40" aria-hidden="true" />
            </span>
            <span>
                <span className="block text-sm font-bold tracking-[0.04em] text-white">CSPC SIKLAB</span>
                <span className="mt-0.5 block text-xs text-white/55">Operations by SYNTIX</span>
            </span>
        </Link>
    );
}

function OperationsPanel() {
    return (
        <aside className="relative hidden min-h-[100dvh] overflow-hidden bg-[#0B2E4F] px-10 py-9 text-white lg:flex lg:flex-col xl:px-14 xl:py-12">
            <div className="absolute inset-0 opacity-70" aria-hidden="true">
                <span className="absolute left-[18%] top-0 h-full border-l border-white/[0.055]" />
                <span className="absolute left-[51%] top-0 h-full border-l border-white/[0.055]" />
                <span className="absolute left-[82%] top-0 h-full border-l border-white/[0.055]" />
                <span className="absolute left-0 top-[28%] w-full border-t border-white/[0.055]" />
                <span className="absolute left-0 top-[72%] w-full border-t border-white/[0.055]" />
                <span className="absolute -bottom-40 -right-36 size-[30rem] rounded-full border-[4rem] border-[#D5A21F]/10" />
            </div>

            <div className="relative">
                <BrandMark />
            </div>

            <div className="relative my-auto max-w-xl py-16">
                <p className="text-sm font-semibold text-[#E4BD54]">Staff operations</p>
                <p className="mt-5 text-balance text-5xl font-bold leading-[1.02] tracking-[-0.05em] xl:text-6xl">
                    Run the event from one accountable desk.
                </p>
                <p className="mt-6 max-w-lg text-base leading-7 text-white/62">
                    Administration, live scoring, results, and publication remain tied to your assigned SIKLAB role.
                </p>
            </div>

            <div className="relative grid grid-cols-3 border-y border-white/12 py-5 text-sm">
                <div>
                    <span className="block text-xs text-white/40">Configure</span>
                    <span className="mt-1 block font-semibold text-white/85">Admin</span>
                </div>
                <div className="border-l border-white/12 pl-5">
                    <span className="block text-xs text-white/40">Record</span>
                    <span className="mt-1 block font-semibold text-white/85">Tabulator</span>
                </div>
                <div className="border-l border-white/12 pl-5">
                    <span className="block text-xs text-white/40">Evaluate</span>
                    <span className="mt-1 block font-semibold text-white/85">Judge</span>
                </div>
            </div>
        </aside>
    );
}

export default function Login({ status, canResetPassword }) {
    const [showPassword, setShowPassword] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (event) => {
        event.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <>
            <Head title="Staff Login">
                <meta name="theme-color" content="#F7F5EF" />
                <meta name="description" content="Sign in to the CSPC SIKLAB staff operations workspace." />
            </Head>

            <main className="grid min-h-[100dvh] bg-[#F7F5EF] font-sans text-[#17212B] lg:grid-cols-[minmax(28rem,0.9fr)_minmax(34rem,1.1fr)]">
                <OperationsPanel />

                <section aria-labelledby="login-title" className="flex min-w-0 flex-col">
                    <div className="bg-[#0B2E4F] px-5 py-5 lg:hidden">
                        <BrandMark />
                    </div>

                    <div className="flex flex-1 items-center px-5 py-10 sm:px-10 sm:py-14 lg:px-14 xl:px-24">
                        <div className="mx-auto w-full max-w-[31rem]">
                            <Link
                                href={route('landing')}
                                className="inline-flex min-h-11 items-center gap-2 rounded text-sm font-semibold text-[#53636C] transition-colors hover:text-[#0B2E4F] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0B2E4F]"
                            >
                                <span aria-hidden="true">←</span>
                                Public Event Board
                            </Link>

                            <div className="mt-8 border-t-[3px] border-[#D5A21F] pt-7 sm:mt-10">
                                <p className="text-sm font-semibold text-[#9B741A]">Invite-only staff access</p>
                                <h1 id="login-title" className="mt-2 text-balance text-4xl font-bold tracking-[-0.045em] text-[#17212B] sm:text-5xl">Sign in to SIKLAB.</h1>
                                <p className="mt-4 max-w-md text-sm leading-6 text-[#68767E]">Use the account issued by your event administrator.</p>
                            </div>

                            {status ? (
                                <div role="status" className="mt-6 border-l-4 border-emerald-600 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">
                                    {status}
                                </div>
                            ) : null}

                            <form onSubmit={submit} className="mt-8" noValidate>
                                <div>
                                    <label htmlFor="email" className="block text-sm font-semibold text-[#30414B]">Email address</label>
                                    <input
                                        id="email"
                                        type="email"
                                        name="email"
                                        value={data.email}
                                        autoComplete="username"
                                        inputMode="email"
                                        spellCheck="false"
                                        aria-invalid={errors.email ? 'true' : 'false'}
                                        aria-describedby={errors.email ? 'email-error' : undefined}
                                        onChange={(event) => setData('email', event.target.value)}
                                        className="mt-2 block min-h-12 w-full rounded-lg border-[#C7CED1] bg-white px-4 text-base text-[#17212B] shadow-sm placeholder:text-[#98A2A7] focus:border-[#0B536D] focus:ring-[#0B536D] focus-visible:outline-none focus-visible:ring-2"
                                        placeholder="staff@cspc.edu.ph"
                                        required
                                    />
                                    <InputError id="email-error" message={errors.email} className="mt-2" />
                                </div>

                                <div className="mt-6">
                                    <div className="flex items-center justify-between gap-4">
                                        <label htmlFor="password" className="block text-sm font-semibold text-[#30414B]">Password</label>
                                        {canResetPassword ? (
                                            <Link
                                                href={route('password.request')}
                                                className="rounded text-sm font-semibold text-[#0B536D] underline decoration-[#D5A21F] decoration-2 underline-offset-4 transition-colors hover:text-[#0B2E4F] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0B536D]"
                                            >
                                                Forgot password?
                                            </Link>
                                        ) : null}
                                    </div>
                                    <div className="relative mt-2">
                                        <input
                                            id="password"
                                            type={showPassword ? 'text' : 'password'}
                                            name="password"
                                            value={data.password}
                                            autoComplete="current-password"
                                            aria-invalid={errors.password ? 'true' : 'false'}
                                            aria-describedby={errors.password ? 'password-error' : undefined}
                                            onChange={(event) => setData('password', event.target.value)}
                                            className="block min-h-12 w-full rounded-lg border-[#C7CED1] bg-white px-4 pr-20 text-base text-[#17212B] shadow-sm focus:border-[#0B536D] focus:ring-[#0B536D] focus-visible:outline-none focus-visible:ring-2"
                                            required
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowPassword((visible) => !visible)}
                                            aria-pressed={showPassword}
                                            className="absolute inset-y-1 right-1 min-w-16 rounded-md px-3 text-sm font-semibold text-[#53636C] transition-colors hover:bg-[#EEF1EF] hover:text-[#0B2E4F] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0B536D]"
                                        >
                                            {showPassword ? 'Hide' : 'Show'}
                                        </button>
                                    </div>
                                    <InputError id="password-error" message={errors.password} className="mt-2" />
                                </div>

                                <label className="mt-5 flex min-h-11 cursor-pointer items-center gap-3 text-sm text-[#53636C]">
                                    <input
                                        type="checkbox"
                                        name="remember"
                                        checked={data.remember}
                                        onChange={(event) => setData('remember', event.target.checked)}
                                        className="size-5 rounded border-[#AEB8BC] text-[#0B536D] focus-visible:ring-2 focus-visible:ring-[#0B536D] focus-visible:ring-offset-2"
                                    />
                                    Keep me signed in on this device
                                </label>

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="mt-6 inline-flex min-h-12 w-full items-center justify-center rounded-lg bg-[#0B2E4F] px-5 text-sm font-bold text-white shadow-[0_10px_24px_rgba(11,46,79,0.14)] transition-colors hover:bg-[#164565] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0B2E4F] focus-visible:ring-offset-2 focus-visible:ring-offset-[#F7F5EF] disabled:cursor-wait disabled:opacity-65"
                                >
                                    {processing ? 'Signing in…' : 'Sign In'}
                                </button>
                            </form>

                            <p className="mt-7 border-t border-[#D9DDDC] pt-5 text-xs leading-5 text-[#7A878D]">
                                Staff access is provisioned by invitation. Account and scoring activity may be recorded in the event audit trail.
                            </p>
                        </div>
                    </div>

                    <footer className="flex flex-wrap items-center justify-between gap-3 border-t border-[#E1E2DE] px-5 py-4 text-xs text-[#7A878D] sm:px-10 lg:px-14 xl:px-24">
                        <span>CSPC SIKLAB · SYNTIX</span>
                        <span>Nabua, Camarines Sur</span>
                    </footer>
                </section>
            </main>
        </>
    );
}
