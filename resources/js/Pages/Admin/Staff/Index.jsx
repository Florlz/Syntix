import AppIcon from '@/Components/AppIcon';
import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Link from '@/Components/PrefetchLink';
import StaffSetupHandoffCard from '@/Components/StaffSetupHandoffCard';
import PeopleSection from '@/Pages/Admin/Staff/PeopleSection';
import AssignmentsSection from '@/Pages/Admin/Staff/AssignmentsSection';
import ReadinessSection from '@/Pages/Admin/Staff/ReadinessSection';
import StaffDrawer from '@/Pages/Admin/Staff/StaffDrawer';
import { adminStyles } from '@/Support/adminStyles';
import { Head, usePage } from '@inertiajs/react';

const action = adminStyles.primaryAction;

function SectionNav({ event, section }) {
    const sections = [['people', 'People'], ['assignments', 'Assignments'], ['readiness', 'Scoring Readiness']];
    return <nav aria-label="Judges and Tabulators sections" className="flex gap-1 overflow-x-auto border-b border-border">{sections.map(([key, label]) => <Link key={key} href={route('admin.staff.index', { event: event.id, section: key })} aria-current={section === key ? 'page' : undefined} className={`min-h-11 shrink-0 border-b-2 px-4 py-3 text-sm font-bold ${section === key ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-foreground'}`}>{label}</Link>)}</nav>;
}

function SetupInvitationNotice({ event, invitation }) {
    const [copyState, setCopyState] = useState('idle');
    const [qrState, setQrState] = useState('generating');
    const setupUrl = invitation.setup_url;
    const printLabel = qrState === 'generating' ? 'Generating QR…' : qrState === 'error' ? 'QR unavailable' : 'Print setup card';

    async function copySetupLink() {
        try {
            if (!navigator.clipboard?.writeText) throw new Error('Clipboard unavailable');
            await navigator.clipboard.writeText(setupUrl);
            setCopyState('copied');
        } catch {
            setCopyState('error');
        }
    }

    return <section aria-label="New setup invitation" className="border border-accent bg-accent/10 p-4"><div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"><div><p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">New setup invitation ready</p><h2 className="mt-1 text-xl font-bold">Private handoff for {invitation.name}</h2><p className="mt-2 text-sm text-muted">Previous unused setup links have been invalidated.</p></div><div className="flex flex-wrap gap-2"><button type="button" onClick={copySetupLink} className={action}>{copyState === 'copied' ? 'Copied' : copyState === 'error' ? 'Copy failed' : 'Copy private link'}</button><button type="button" disabled={qrState !== 'ready'} onClick={() => window.print()} className={adminStyles.secondaryAction}>{printLabel}</button></div></div><p aria-live="polite" className="mt-2 text-xs text-muted">{copyState === 'copied' ? 'Setup link copied.' : copyState === 'error' ? 'Copy failed. Select the private link and copy it manually.' : `Role: ${invitation.role_label}. Expires ${invitation.expires_at ? new Date(invitation.expires_at).toLocaleString() : '24 hours after issue'}.`}</p><label className="mt-4 block"><span className="sr-only">One-time setup link</span><input readOnly value={setupUrl} className={`${adminStyles.field} text-xs`}/></label><StaffSetupHandoffCard eventName={event.name} staffName={invitation.name} roleLabel={invitation.role_label} expiresAt={invitation.expires_at} setupUrl={setupUrl} onQrStateChange={setQrState}/></section>;
}

export default function Index({ event, section = 'people', staff = [], staff_summary: staffSummary = {}, targets = {}, readiness = [] }) {
    const [selectedId, setSelectedId] = useState(null);
    const { flash = {}, errors = {} } = usePage().props;
    const errorText = Object.values(errors).join(' ');
    const selected = staff.find((person) => String(person.id) === String(selectedId)) ?? null;

    return <AuthenticatedLayout header={<div><p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">{event.name}</p><h1 className="font-serif text-2xl font-bold">Judges &amp; Tabulators</h1></div>}>
        <Head title={section === 'readiness' ? 'Scoring Readiness' : 'Judges & Tabulators'}/>
        <main className={adminStyles.page}>
            <div className="mx-auto max-w-[96rem] space-y-6">
                <SectionNav event={event} section={section}/>
                {event.archived ? <div className="rounded-xl border border-accent bg-accent/10 p-4 text-sm"><strong>Archived event.</strong> Staff records and scoring history remain available, but this event can no longer be modified.</div> : null}
                {flash.status ? <div role="status" className="border-l-4 border-primary bg-primary/10 p-4 text-sm">{flash.status}</div> : null}
                {flash.setup_url && flash.setup_invitation ? <SetupInvitationNotice event={event} invitation={{ ...flash.setup_invitation, setup_url: flash.setup_url }}/> : null}
                {errorText ? <div role="alert" className="border-l-4 border-danger bg-danger-surface p-4 text-sm text-danger">{errorText}</div> : null}
                {section === 'assignments' ? <AssignmentsSection staff={staff} event={event} onManage={(person) => setSelectedId(person.id)}/> : section === 'readiness' ? <ReadinessSection readiness={readiness} event={event}/> : <PeopleSection staff={staff} staffSummary={staffSummary} event={event} onManage={(person) => setSelectedId(person.id)}/>}
            </div>
        </main>
        {selected ? <StaffDrawer event={event} person={selected} targets={targets} onClose={() => setSelectedId(null)}/> : null}
    </AuthenticatedLayout>;
}
