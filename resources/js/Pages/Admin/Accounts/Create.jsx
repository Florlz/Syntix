import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import StaffSetupHandoffCard from '@/Components/StaffSetupHandoffCard';
import { Head, useForm, usePage } from '@inertiajs/react';

function roleLabel(invitation, fallback) {
    return invitation.role_label ?? (invitation.role === 'tabulator' ? 'Tabulator' : invitation.role === 'judge' ? 'Judge' : fallback);
}

export default function Create({ event }) {
    const form = useForm({ name: '', email: '', role: 'judge' });
    const { flash = {} } = usePage().props;
    const setupUrl = flash.setup_url;
    const setupInvitation = flash.setup_invitation ?? {};
    const [copyState, setCopyState] = useState('idle');

    function submit(submitEvent) {
        submitEvent.preventDefault();
        form.post(route('admin.accounts.store', event.id));
    }

    async function copySetupLink() {
        try {
            if (!navigator.clipboard?.writeText) throw new Error('Clipboard unavailable');
            await navigator.clipboard.writeText(setupUrl);
            setCopyState('copied');
        } catch {
            setCopyState('error');
        }
    }

    return <AuthenticatedLayout header={<div><p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">{event.name}</p><h1 className="font-serif text-2xl font-bold">Invite event staff</h1></div>}>
        <Head title="Invite event staff" />
        <main className="min-h-[calc(100vh-4rem)] bg-background px-4 py-8 text-foreground sm:px-6 lg:px-10">
            <div className="mx-auto grid max-w-5xl gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <form onSubmit={submit} className="rounded-2xl border border-border bg-surface p-6 shadow-sm sm:p-8">
                    <p className="text-xs font-bold uppercase tracking-[0.18em] text-primary">Account and event role</p>
                    <h2 className="mt-2 font-serif text-3xl font-bold">Invite event staff</h2>
                    {event.archived ? <div className="mt-4 rounded-xl border border-accent bg-accent/10 p-4 text-sm"><strong>Archived event.</strong> This event is read-only and cannot accept new staff invitations.</div> : null}
                    <p className="mt-3 max-w-2xl text-sm leading-6 text-muted">Create the account first. Judges receive work through judging panels. Tabulators receive Division or Contest assignments from the staff workspace.</p>
                    <fieldset disabled={event.archived} className="contents">
                    <div className="mt-8 space-y-5">
                        <label className="block"><span className="text-sm font-semibold">Name</span><input name="name" autoComplete="name" value={form.data.name} onChange={(input) => form.setData('name', input.target.value)} className="mt-2 w-full rounded-xl border-border bg-surface text-foreground focus:border-primary focus:ring-primary" required/><InputError message={form.errors.name} className="mt-2"/></label>
                        <label className="block"><span className="text-sm font-semibold">Institutional email</span><input name="email" autoComplete="email" spellCheck={false} type="email" value={form.data.email} onChange={(input) => form.setData('email', input.target.value)} className="mt-2 w-full rounded-xl border-border bg-surface text-foreground focus:border-primary focus:ring-primary" required/><InputError message={form.errors.email} className="mt-2"/></label>
                        <fieldset><legend className="text-sm font-semibold">Event role</legend><div className="mt-2 grid gap-3 sm:grid-cols-2">{[['judge', 'Judge', 'Scores entries assigned through a judging panel.'], ['tabulator', 'Tabulator', 'Records or finalizes results for assigned targets.']].map(([value, label, detail]) => <label key={value} className={`cursor-pointer rounded-xl border p-4 ${form.data.role === value ? 'border-accent bg-accent/10' : 'border-border bg-surface'}`}><span className="flex items-center gap-2"><input className="text-primary focus:ring-accent" type="radio" name="role" value={value} checked={form.data.role === value} onChange={() => form.setData('role', value)}/><span className="font-bold">{label}</span></span><span className="mt-2 block text-xs leading-5 text-muted">{detail}</span></label>)}</div><InputError message={form.errors.role} className="mt-2"/></fieldset>
                    </div>
                    <button type="submit" disabled={event.archived || form.processing} className="mt-8 min-h-11 rounded-lg bg-primary px-6 text-sm font-bold text-primary-foreground disabled:opacity-50">{form.processing ? 'Creating invitation…' : 'Create invitation'}</button>
                    </fieldset>
                </form>

                <aside className="rounded-2xl border border-border bg-surface p-5 shadow-sm"><p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">Secure handoff</p>{setupUrl ? <div className="mt-4"><h2 className="font-serif text-xl font-bold">Setup invitation ready</h2><p className="mt-2 text-sm leading-6 text-muted">Copy the private link or print one card and hand it directly to the named staff member.</p><label className="mt-4 block"><span className="sr-only">One-time setup link</span><input readOnly value={setupUrl} className="w-full rounded-lg border-border bg-surface-muted text-xs text-foreground"/></label><div className="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-1"><button type="button" onClick={copySetupLink} className="min-h-11 rounded-lg bg-primary px-4 text-sm font-bold text-primary-foreground">{copyState === 'copied' ? 'Copied' : copyState === 'error' ? 'Copy failed' : 'Copy setup link'}</button><button type="button" onClick={() => window.print()} className="min-h-11 rounded-lg border border-border px-4 text-sm font-bold">Print setup card</button></div><p aria-live="polite" className="mt-2 text-xs text-muted">{copyState === 'copied' ? 'Setup link copied.' : copyState === 'error' ? 'Copy failed. Select the private link and copy it manually.' : ''}</p><p className="mt-4 rounded-lg bg-danger-surface p-3 text-xs leading-5 text-danger">The link and QR are bearer credentials until used or expired. Do not post, photograph, or leave the card unattended.</p></div> : <div className="mt-4 rounded-xl border border-dashed border-border bg-surface-muted p-5"><h2 className="font-serif text-lg font-bold">No active setup link</h2><p className="mt-2 text-sm leading-6 text-muted">Create an invitation to reveal its one-time link and printable handoff card.</p></div>}</aside>
            </div>
            {setupUrl ? <StaffSetupHandoffCard eventName={event.name} staffName={setupInvitation.name ?? form.data.name} roleLabel={roleLabel(setupInvitation, form.data.role === 'tabulator' ? 'Tabulator' : 'Judge')} expiresAt={setupInvitation.expires_at} setupUrl={setupUrl}/> : null}
        </main>
    </AuthenticatedLayout>;
}
