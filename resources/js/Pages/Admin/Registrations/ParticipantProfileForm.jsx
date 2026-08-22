import { useForm } from '@inertiajs/react';
import { adminStyles } from '@/Support/adminStyles';

const primary = adminStyles.primaryAction;
const input = `mt-1 ${adminStyles.field}`;

export default function ParticipantProfileForm({ event, departments = [], participant = null, fixedDepartmentId = null, archived = false, onClose }) {
    const form = useForm({
        event_delegation_id: fixedDepartmentId || participant?.event_delegation_id || departments[0]?.id || '',
        display_name: participant?.display_name || '',
        given_name: participant?.given_name || '',
        family_name: participant?.family_name || '',
        student_number: participant?.student_number || '',
        email: participant?.email || '',
        phone: participant?.phone || '',
        private_notes: participant?.private_notes || '',
        is_active: participant?.is_active ?? true,
    });
    const submit = (eventObject) => {
        eventObject.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };
        if (participant) form.patch(route('admin.participants.update', [event.id, participant.id]), options);
        else form.post(route('admin.participants.store', event.id), options);
    };
    return <form onSubmit={submit} className="space-y-4"><p className="text-sm leading-6 text-muted">This shared profile is used anywhere this player joins an event sport.</p><label className="block text-sm font-bold">Department{fixedDepartmentId ? <p className="mt-1 border border-border bg-surface-muted px-3 py-2.5 text-sm font-normal">{departments.find((department) => department.id === String(fixedDepartmentId))?.name || 'Selected department'}</p> : <select value={form.data.event_delegation_id} onChange={(eventObject) => form.setData('event_delegation_id', eventObject.target.value)} className={input} disabled={Boolean(participant)}>{departments.map((department) => <option key={department.id} value={department.id}>{department.name}</option>)}</select>}</label><label className="block text-sm font-bold">Display name<input required value={form.data.display_name} onChange={(eventObject) => form.setData('display_name', eventObject.target.value)} className={input} /></label><div className="grid gap-3 sm:grid-cols-2"><label className="block text-sm font-bold">Student number<input value={form.data.student_number} onChange={(eventObject) => form.setData('student_number', eventObject.target.value)} className={input} /></label><label className="block text-sm font-bold">Email<input type="email" value={form.data.email} onChange={(eventObject) => form.setData('email', eventObject.target.value)} className={input} /></label></div><details><summary className="cursor-pointer text-sm font-semibold text-primary">Other details</summary><div className="mt-3 grid gap-3 sm:grid-cols-2"><label className="block text-sm font-bold">Given name<input value={form.data.given_name} onChange={(eventObject) => form.setData('given_name', eventObject.target.value)} className={input} /></label><label className="block text-sm font-bold">Family name<input value={form.data.family_name} onChange={(eventObject) => form.setData('family_name', eventObject.target.value)} className={input} /></label><label className="block text-sm font-bold">Phone<input value={form.data.phone} onChange={(eventObject) => form.setData('phone', eventObject.target.value)} className={input} /></label><label className="block text-sm font-bold sm:col-span-2">Private notes<textarea rows="3" value={form.data.private_notes} onChange={(eventObject) => form.setData('private_notes', eventObject.target.value)} className={input} /></label></div></details><button type="submit" className={primary} disabled={archived || form.processing}>{participant ? 'Save profile' : 'Create profile'}</button>{Object.values(form.errors).map((error) => <p key={error} className="text-sm font-semibold text-danger">{error}</p>)}</form>;
}
