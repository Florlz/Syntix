import React, { useEffect, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import { Head, useForm, usePage } from '@inertiajs/react';
import QRCode from 'qrcode';

export default function Create({ event }) {
    const form = useForm({
        name: '',
        email: '',
        role: 'judge',
    });
    const { flash = {} } = usePage().props;
    const setupUrl = flash.setup_url;
    const setupInvitation = flash.setup_invitation ?? {};
    const [qrDataUrl, setQrDataUrl] = useState(null);
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        let current = true;

        if (!setupUrl) {
            setQrDataUrl(null);
            return undefined;
        }

        QRCode.toDataURL(setupUrl, {
            errorCorrectionLevel: 'M',
            margin: 1,
            width: 320,
            color: { dark: '#17212b', light: '#ffffff' },
        }).then((value) => {
            if (current) setQrDataUrl(value);
        });

        return () => {
            current = false;
        };
    }, [setupUrl]);

    function submit(submitEvent) {
        submitEvent.preventDefault();
        form.post(route('admin.accounts.store', event.id));
    }

    async function copySetupLink() {
        await navigator.clipboard.writeText(setupUrl);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 2000);
    }

    const expiresAt = setupInvitation.expires_at
        ? new Intl.DateTimeFormat(undefined, {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        }).format(new Date(setupInvitation.expires_at))
        : '24 hours after issue';

    return (
        <AuthenticatedLayout header={<div><p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">{event.name}</p><h1 className="font-serif text-2xl font-bold">Invite event staff</h1></div>}>
            <Head title="Invite event staff" />
            <style>{`@page { size: 3.5in 2in; margin: 0; }
                @media print {
                    html, body, #app { width: 3.5in; height: 2in; min-height: 2in !important; margin: 0 !important; padding: 0 !important; overflow: hidden !important; }
                    #app > div { width: 3.5in !important; height: 2in !important; min-height: 2in !important; overflow: hidden !important; }
                    body * { visibility: hidden !important; }
                    #setup-card, #setup-card * { visibility: visible !important; }
                    #app > div > a,
                    #app > div > aside,
                    #app > div > header,
                    #app > div > [role="dialog"] { display: none !important; }
                    #main-content { display: block !important; width: 3.5in !important; height: 2in !important; margin: 0 !important; overflow: hidden !important; }
                    #main-content > main { display: block !important; width: 3.5in !important; height: 2in !important; min-height: 2in !important; margin: 0 !important; padding: 0 !important; overflow: hidden !important; }
                    #main-content > main > div:first-child { display: none !important; }
                    #main-content > main > #setup-card { display: flex !important; }
                    #setup-card {
                        position: fixed !important;
                        inset: 0 auto auto 0 !important;
                        width: 3.5in !important;
                        height: 2in !important;
                        min-height: 2in !important;
                        max-width: none !important;
                        margin: 0 !important;
                        box-sizing: border-box !important;
                        overflow: hidden !important;
                        border: 0.5pt solid #d8dedc !important;
                        border-radius: 0 !important;
                        box-shadow: none !important;
                        background: #ffffff !important;
                        color: #17212b !important;
                        display: flex !important;
                        flex-direction: column !important;
                        print-color-adjust: exact !important;
                        -webkit-print-color-adjust: exact !important;
                    }
                    #setup-card > header {
                        flex: 0 0 0.31in !important;
                        box-sizing: border-box !important;
                        padding: 0.08in 0.13in !important;
                        background: #082944 !important;
                    }
                    #setup-card > header strong { font-size: 10pt !important; line-height: 1 !important; }
                    #setup-card > header span { font-size: 5pt !important; line-height: 1 !important; }
                    #setup-card > div {
                        flex: 1 1 auto !important;
                        min-height: 0 !important;
                        box-sizing: border-box !important;
                        display: grid !important;
                        grid-template-columns: minmax(0, 1fr) 0.72in !important;
                        gap: 0.1in !important;
                        padding: 0.1in 0.13in !important;
                    }
                    #setup-card > div > div:first-child { min-width: 0 !important; }
                    #setup-card > div > div:first-child > p:first-child { margin: 0 !important; font-size: 5pt !important; line-height: 1 !important; }
                    #setup-card h2 { margin: 0.04in 0 0 !important; font-size: 13pt !important; line-height: 1 !important; white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important; }
                    #setup-card > div > div:first-child > p:nth-of-type(2) { margin: 0.03in 0 0 !important; font-size: 6pt !important; line-height: 1 !important; }
                    #setup-card > div > div:first-child > p:nth-of-type(3) { margin: 0.08in 0 0 !important; font-size: 5.5pt !important; line-height: 1.25 !important; }
                    #setup-card > div > div:first-child > p:nth-of-type(4) { margin: auto 0 0 !important; font-size: 5pt !important; line-height: 1 !important; }
                    #setup-card > div > div:last-child { width: 0.72in !important; height: 0.72in !important; padding: 0.04in !important; border-radius: 0.05in !important; align-self: center !important; }
                    #setup-card > div > div:last-child img { width: 100% !important; height: 100% !important; }
                    #setup-card > footer { flex: 0 0 0.2in !important; box-sizing: border-box !important; padding: 0.04in 0.13in !important; font-size: 4.5pt !important; line-height: 1 !important; }
                }`}</style>
            <main className="min-h-[calc(100vh-4rem)] bg-background px-4 py-8 text-foreground sm:px-6 lg:px-10">
                <div className="mx-auto grid max-w-5xl gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <form onSubmit={submit} className="rounded-2xl border border-border bg-surface p-6 shadow-sm sm:p-8">
                        <p className="text-xs font-bold uppercase tracking-[0.18em] text-primary">Account and event role</p>
                        <h2 className="mt-2 font-serif text-3xl font-bold">Invite event staff</h2>
                        <p className="mt-3 max-w-2xl text-sm leading-6 text-muted">
                            Create the account first. Judges receive work through judging panels; Tabulators receive division or contest assignments from the staff workspace.
                        </p>

                        <div className="mt-8 space-y-5">
                            <label className="block">
                                <span className="text-sm font-semibold">Name</span>
                                <input
                                    name="name"
                                    autoComplete="name"
                                    value={form.data.name}
                                    onChange={(input) => form.setData('name', input.target.value)}
                                    className="mt-2 w-full rounded-xl border-border bg-surface text-foreground focus:border-primary focus:ring-primary"
                                    required
                                />
                                <InputError message={form.errors.name} className="mt-2" />
                            </label>
                            <label className="block">
                                <span className="text-sm font-semibold">Institutional email</span>
                                <input
                                    name="email"
                                    autoComplete="email"
                                    spellCheck={false}
                                    type="email"
                                    value={form.data.email}
                                    onChange={(input) => form.setData('email', input.target.value)}
                                    className="mt-2 w-full rounded-xl border-border bg-surface text-foreground focus:border-primary focus:ring-primary"
                                    required
                                />
                                <InputError message={form.errors.email} className="mt-2" />
                            </label>
                            <fieldset>
                                <legend className="text-sm font-semibold">Event role</legend>
                                <div className="mt-2 grid gap-3 sm:grid-cols-2">
                                    {[
                                        ['judge', 'Judge', 'Scores only the entries assigned through a judging panel.'],
                                        ['tabulator', 'Tabulator', 'Records or finalizes results for assigned contests.'],
                                    ].map(([role, label, detail]) => (
                                        <label key={role} className={`cursor-pointer rounded-xl border p-4 ${form.data.role === role ? 'border-accent bg-accent/10' : 'border-border bg-surface'}`}>
                                            <span className="flex items-center gap-2">
                                                <input
                                                    className="text-primary focus:ring-accent"
                                                    type="radio"
                                                    name="role"
                                                    value={role}
                                                    checked={form.data.role === role}
                                                    onChange={() => form.setData('role', role)}
                                                />
                                                <span className="font-bold">{label}</span>
                                            </span>
                                            <span className="mt-2 block text-xs leading-5 text-muted">{detail}</span>
                                        </label>
                                    ))}
                                </div>
                                <InputError message={form.errors.role} className="mt-2" />
                            </fieldset>
                        </div>

                        <button disabled={form.processing} className="mt-8 min-h-11 rounded-lg bg-primary px-6 text-sm font-bold text-primary-foreground disabled:opacity-50">
                            {form.processing ? 'Creating invitation…' : 'Create invitation'}
                        </button>
                    </form>

                    <aside className="rounded-2xl border border-border bg-surface p-5 shadow-sm">
                        <p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">Secure handoff</p>
                        {setupUrl ? (
                            <div className="mt-4">
                                <h2 className="font-serif text-xl font-bold">Setup invitation ready</h2>
                                <p className="mt-2 text-sm leading-6 text-muted">Copy the link through a private channel or print one card and hand it directly to the named staff member.</p>
                                <label className="mt-4 block">
                                    <span className="sr-only">One-time setup link</span>
                                    <input readOnly value={setupUrl} className="w-full rounded-lg border-border bg-surface-muted text-xs text-foreground" />
                                </label>
                                <div className="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-1">
                                    <button type="button" onClick={copySetupLink} className="min-h-11 rounded-lg bg-primary px-4 text-sm font-bold text-primary-foreground">
                                        {copied ? 'Copied' : 'Copy setup link'}
                                    </button>
                                    <button type="button" onClick={() => window.print()} disabled={!qrDataUrl} className="min-h-11 rounded-lg border border-border px-4 text-sm font-bold disabled:opacity-50">
                                        Print setup card
                                    </button>
                                </div>
                                <p className="mt-4 rounded-lg bg-danger-surface p-3 text-xs leading-5 text-danger">
                                    The link and QR are bearer credentials until used or expired. Do not post, photograph, or leave the card unattended.
                                </p>
                            </div>
                        ) : (
                            <div className="mt-4 rounded-xl border border-dashed border-border bg-surface-muted p-5">
                                <h2 className="font-serif text-lg font-bold">No active setup link</h2>
                                <p className="mt-2 text-sm leading-6 text-muted">Create an invitation to reveal its one-time link and printable handoff card.</p>
                            </div>
                        )}
                    </aside>
                </div>

                {setupUrl ? (
                    <section id="setup-card" data-testid="setup-card" data-print-size="3.5in × 2in" className="mx-auto mt-8 max-w-2xl overflow-hidden rounded-2xl border border-border bg-surface shadow-xl">
                        <header className="flex items-center justify-between bg-sidebar px-6 py-5 text-white">
                            <strong className="font-serif text-xl tracking-[0.12em]">SYNTIX</strong>
                            <span className="text-xs font-bold uppercase tracking-[0.16em] text-accent">Staff setup</span>
                        </header>
                        <div className="grid items-center gap-6 p-6 sm:grid-cols-[1fr_12rem] sm:p-8">
                            <div>
                                <p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">{event.name}</p>
                                <h2 className="mt-2 font-serif text-3xl font-bold">{setupInvitation.name ?? form.data.name}</h2>
                                <p className="mt-2 text-sm font-bold capitalize text-muted">{setupInvitation.role ?? form.data.role}</p>
                                <p className="mt-6 text-sm leading-6 text-muted">Scan privately to create your password. This card sets up the account only; scoring assignments are managed separately.</p>
                                <p className="mt-4 text-xs font-semibold text-danger">Expires {expiresAt}. One use only.</p>
                            </div>
                            <div className="grid aspect-square place-items-center rounded-xl border border-border bg-white p-3">
                                {qrDataUrl ? <img src={qrDataUrl} alt="One-time staff setup QR code" className="size-full" /> : <span className="text-xs text-muted">Preparing QR…</span>}
                            </div>
                        </div>
                        <footer className="border-t border-border px-6 py-4 text-xs text-muted">Hand directly to the named staff member. Destroy after use or expiry.</footer>
                    </section>
                ) : null}
            </main>
        </AuthenticatedLayout>
    );
}
