import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';

export default function SetupAccount({
    valid,
    email,
    conflict = false,
    authenticatedEmail = null,
    switchUrl = null,
}) {
    const form = useForm({ password: '', password_confirmation: '' });

    function submit(event) {
        event.preventDefault();
        form.post(window.location.pathname);
    }

    return (
        <main className="grid min-h-screen place-items-center bg-sidebar px-5 py-10 text-foreground">
            <Head title="Set up Syntix account" />
            <section className="w-full max-w-lg rounded-3xl border border-border bg-surface p-7 shadow-2xl sm:p-10">
                <p className="text-xs font-bold uppercase tracking-[0.18em] text-primary">Syntix staff access</p>
                {conflict ? (
                    <>
                        <h1 className="mt-3 font-serif text-3xl font-bold">Finish setup as the invited staff member</h1>
                        <p className="mt-3 text-sm leading-6 text-muted">
                            You are currently signed in as {authenticatedEmail}. This invitation belongs to {email}.
                            Sign out before continuing, or open the setup link in a private browser window.
                        </p>
                        <Link
                            href={switchUrl}
                            method="post"
                            as="button"
                            className="mt-7 inline-flex min-h-11 items-center justify-center rounded-lg bg-primary px-5 text-sm font-bold text-primary-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent"
                        >
                            Sign out and continue
                        </Link>
                    </>
                ) : valid ? (
                    <form onSubmit={submit}>
                        <h1 className="mt-3 font-serif text-3xl font-bold">Create your password</h1>
                        <p className="mt-3 text-sm leading-6 text-muted">
                            Finish setup for {email}. This one-time invitation is consumed when your password is saved.
                        </p>
                        <label className="mt-8 block">
                            <span className="text-sm font-semibold">Password</span>
                            <input
                                aria-label="Password"
                                type="password"
                                value={form.data.password}
                                onChange={(input) => form.setData('password', input.target.value)}
                                className="mt-2 w-full rounded-xl border-border bg-surface text-foreground focus:border-primary focus:ring-primary"
                                autoComplete="new-password"
                                required
                            />
                        </label>
                        <label className="mt-5 block">
                            <span className="text-sm font-semibold">Confirm password</span>
                            <input
                                aria-label="Confirm password"
                                type="password"
                                value={form.data.password_confirmation}
                                onChange={(input) => form.setData('password_confirmation', input.target.value)}
                                className="mt-2 w-full rounded-xl border-border bg-surface text-foreground focus:border-primary focus:ring-primary"
                                autoComplete="new-password"
                                required
                            />
                        </label>
                        {form.errors.password ? <p className="mt-2 text-sm text-danger">{form.errors.password}</p> : null}
                        {form.errors.token ? <p className="mt-2 text-sm text-danger">{form.errors.token}</p> : null}
                        <button
                            disabled={form.processing}
                            className="mt-8 min-h-11 rounded-lg bg-primary px-6 text-sm font-bold text-primary-foreground disabled:opacity-50"
                        >
                            {form.processing ? 'Finishing setup…' : 'Finish setup'}
                        </button>
                    </form>
                ) : (
                    <>
                        <h1 className="mt-3 font-serif text-3xl font-bold">Setup link unavailable</h1>
                        <p className="mt-3 text-sm leading-6 text-danger">
                            This invitation is expired, has already been used, or was replaced. Ask the Global Admin to issue a new setup link.
                        </p>
                        <Link href={route('login')} className="mt-7 inline-flex min-h-11 items-center text-sm font-bold text-primary">
                            Return to staff login
                        </Link>
                    </>
                )}
            </section>
        </main>
    );
}
