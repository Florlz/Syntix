import { useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import AppIcon from '@/Components/AppIcon';

const primary = 'inline-flex min-h-10 items-center justify-center gap-2 rounded-md bg-[#0B536D] px-4 text-sm font-bold text-white transition hover:bg-[#083e52] disabled:cursor-not-allowed disabled:opacity-50';
const quiet = 'inline-flex min-h-10 items-center justify-center gap-2 rounded-md border border-[#B8C3C0] bg-white px-4 text-sm font-bold text-[#0B536D] transition hover:border-[#0B536D] disabled:cursor-not-allowed disabled:opacity-50';
const input = 'mt-1 block w-full rounded-md border border-[#B8C3C0] bg-white px-3 py-2.5 text-sm text-[#17212B] focus:border-[#0B536D] focus:ring-[#0B536D]';
const playerRoles = new Set(['student_athlete', 'reserve']);

function ErrorText({ value }) { return value ? <p className="mt-1 text-xs font-semibold text-red-700">{value}</p> : null; }

function ErrorSummary({ errors }) {
    const messages = [...new Set(Object.values(errors || {}).filter(Boolean))];
    return messages.length ? <section role="alert" className="border border-red-200 bg-red-50 p-3 text-sm text-red-800"><p className="font-bold">Players were not added.</p><ul className="mt-1 list-disc space-y-1 pl-5">{messages.map((message) => <li key={message}>{message}</li>)}</ul></section> : null;
}

export default function RosterAddPlayers({ event, entry, departmentId, participants = [], roles = [], archived, onClose }) {
    const page = usePage();
    const imported = page.props.flash?.selected_participant_ids || page.props.selected_participant_ids || [];
    const [query, setQuery] = useState('');
    const [selected, setSelected] = useState(() => new Set(imported.map(String)));
    const [roleByParticipant, setRoleByParticipant] = useState({});
    const [showQuickCreate, setShowQuickCreate] = useState(false);
    const [showCsv, setShowCsv] = useState(false);
    const [csvFile, setCsvFile] = useState(null);
    const [csvResult, setCsvResult] = useState(null);
    const [csvMapping, setCsvMapping] = useState({});
    const [addSuccess, setAddSuccess] = useState(false);

    const available = useMemo(() => participants.filter((person) => {
        const haystack = `${person.display_name} ${person.student_number || ''}`.toLowerCase();
        return !person.membership?.is_active && haystack.includes(query.trim().toLowerCase());
    }), [participants, query]);

    useEffect(() => {
        const availableIds = new Set(available.map((person) => String(person.id)));
        setSelected((current) => new Set([...current, ...imported.map(String)].filter((id) => availableIds.has(id))));
    }, [available, imported.join(',')]);

    const memberForm = useForm({ members: [] });
    const quickForm = useForm({ entry_id: entry?.id || '', event_delegation_id: departmentId || '', display_name: '', given_name: '', family_name: '', student_number: '', email: '', phone: '', private_notes: '', is_active: true, is_competitor: true });
    const toggle = (id) => setSelected((current) => { const next = new Set(current); const key = String(id); if (next.has(key)) next.delete(key); else next.add(key); return next; });
    const addSelected = (eventObject) => {
        eventObject.preventDefault();
        setAddSuccess(false);
        const availableIds = new Set(available.map((person) => String(person.id)));
        const members = [...selected].filter((id) => availableIds.has(String(id))).map((id) => ({ participant_id: Number(id), role: roleByParticipant[id] || 'student_athlete' }));
        if (!members.length) return;
        memberForm.clearErrors();
        memberForm.transform(() => ({ members })).put(route('admin.entry-members.batch', [event.id, entry.id]), { preserveScroll: true, onSuccess: () => { setSelected(new Set()); setAddSuccess(true); } });
    };
    const createPlayer = (eventObject) => { eventObject.preventDefault(); quickForm.post(route('admin.participants.store', event.id), { preserveScroll: true, onSuccess: () => setShowQuickCreate(false) }); };
    const inspectCsv = async () => {
        if (!csvFile) return;
        const formData = new FormData(); formData.append('file', csvFile); formData.append('department_id', departmentId); formData.append('entry_id', entry?.id || '');
        const response = await fetch(route('admin.participant-import.inspect', event.id), { method: 'POST', body: formData, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' } });
        const result = await response.json(); setCsvResult(result); if (result.mapping) setCsvMapping(result.mapping);
    };
    const confirmCsv = () => {
        if (!csvFile || !csvResult || csvResult.errors?.length) return;
        const form = new FormData(); form.append('file', csvFile); form.append('department_id', departmentId); form.append('entry_id', entry?.id || ''); Object.entries(csvMapping).forEach(([target, source]) => form.append(`mapping[${target}]`, source || ''));
        const request = new XMLHttpRequest(); request.open('POST', route('admin.participant-import.confirm', event.id)); request.setRequestHeader('X-Requested-With', 'XMLHttpRequest'); request.setRequestHeader('Accept', 'application/json'); request.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]')?.content || '');
        request.onload = () => { if (request.status >= 200 && request.status < 300) { const result = JSON.parse(request.responseText); setCsvResult(result); } }; request.send(form);
    };
    const sourceHeaders = csvResult?.headers || [];

    return <div className="space-y-5">
        <div className="flex flex-wrap items-end justify-between gap-3 border-b border-[#CFD6D3] pb-4"><div><p className="text-xs font-bold uppercase tracking-[0.14em] text-[#0B536D]">Add players</p><h3 className="mt-1 font-serif text-2xl font-bold">Build this team sheet</h3><p className="mt-1 text-sm text-[#68767E]">Active players on the team sheet are cleared to compete when the roster is approved.</p></div><button type="button" className={quiet} onClick={onClose}>Done</button></div>
        <label className="block"><span className="text-sm font-bold">Search department players</span><div className="relative"><AppIcon name="search" className="pointer-events-none absolute left-3 top-3 size-4 text-[#68767E]" /><input value={query} onChange={(eventObject) => setQuery(eventObject.target.value)} className={`${input} pl-9`} placeholder="Name or student number" /></div></label>
        <div className="divide-y divide-[#E6EAE8] border-y border-[#CFD6D3]">{available.length ? available.map((person) => <label key={person.id} className="flex items-center gap-3 py-3"><input type="checkbox" value={String(person.id)} checked={selected.has(String(person.id))} onChange={() => toggle(person.id)} className="size-4 rounded border-[#B8C3C0] text-[#0B536D] focus:ring-[#D5A21F]" /><span className="min-w-0 flex-1"><strong className="block text-sm">{person.display_name}</strong><span className="block text-xs text-[#68767E]">{person.student_number || 'No student number'} · {person.membership ? 'Inactive history · can restore' : 'Not on roster'}</span></span><select aria-label={`Role for ${person.display_name}`} value={roleByParticipant[person.id] || 'student_athlete'} onChange={(eventObject) => setRoleByParticipant((current) => ({ ...current, [person.id]: eventObject.target.value }))} className="rounded-md border-[#B8C3C0] py-2 text-xs" disabled={!selected.has(String(person.id))}>{roles.filter((role) => playerRoles.has(role.value)).map((role) => <option key={role.value} value={role.value}>{role.label}</option>)}</select></label>) : <p className="py-8 text-center text-sm text-[#68767E]">No unassigned department players match this search.</p>}</div>
        <form onSubmit={addSelected} className="space-y-3"><div className="flex flex-wrap items-center justify-between gap-3"><p className="text-sm text-[#68767E]"><strong className="text-[#17212B]">{selected.size}</strong> selected</p><button type="submit" className={primary} disabled={archived || !selected.size || memberForm.processing}>{memberForm.processing ? 'Adding players…' : 'Add selected to roster'}</button></div><ErrorSummary errors={memberForm.errors} />{addSuccess ? <p role="status" className="text-sm font-semibold text-emerald-700">Players added. The roster is ready for review.</p> : null}</form>
        <div className="grid gap-3 border-t border-[#CFD6D3] pt-5 sm:grid-cols-2"><button type="button" className={quiet} onClick={() => setShowQuickCreate((value) => !value)} disabled={archived}>Create a new player</button><button type="button" className={quiet} onClick={() => setShowCsv((value) => !value)} disabled={archived}>Import CSV</button></div>
        {showQuickCreate ? <form onSubmit={createPlayer} className="space-y-4 border border-[#CFD6D3] bg-[#FBFCFA] p-4"><p className="text-sm font-bold">New player in this department</p><p className="text-xs leading-5 text-[#68767E]">This creates the shared event profile first. You will still add them to this roster separately.</p><label className="block text-sm font-semibold">Display name<input required value={quickForm.data.display_name} onChange={(eventObject) => quickForm.setData('display_name', eventObject.target.value)} className={input} disabled={archived} /></label><div className="grid gap-3 sm:grid-cols-2"><label className="block text-sm font-semibold">Student number<input value={quickForm.data.student_number} onChange={(eventObject) => quickForm.setData('student_number', eventObject.target.value)} className={input} disabled={archived} /></label><label className="block text-sm font-semibold">Email<input type="email" value={quickForm.data.email} onChange={(eventObject) => quickForm.setData('email', eventObject.target.value)} className={input} disabled={archived} /></label></div><button type="submit" className={primary} disabled={archived || quickForm.processing}>Create player profile</button><ErrorText value={quickForm.errors.display_name || quickForm.errors.student_number || quickForm.errors.event_delegation_id} /></form> : null}
        {showCsv ? <section className="space-y-4 border border-[#CFD6D3] bg-[#FBFCFA] p-4"><div><p className="text-sm font-bold">Guided CSV import</p><p className="mt-1 text-xs leading-5 text-[#68767E]">Import profiles only. Nothing is added to the roster until you select them above.</p></div><a href="data:text/csv;charset=utf-8,department_code,student_number,display_name,given_name,family_name,email,phone,private_notes,active%0A" download="syntix-players-template.csv" className="text-sm font-bold text-[#0B536D] underline">Download template</a><input type="file" accept=".csv,text/csv" onChange={(eventObject) => { setCsvFile(eventObject.target.files?.[0] || null); setCsvResult(null); }} className="block w-full text-sm" /><div className="flex flex-wrap gap-2"><button type="button" className={quiet} onClick={inspectCsv} disabled={!csvFile}>Inspect file</button><button type="button" className={primary} onClick={confirmCsv} disabled={!csvFile || !csvResult || csvResult.errors?.length}>Confirm import</button></div>{csvResult ? <div className="space-y-3 text-sm"><p className="font-semibold">{csvResult.errors?.length ? `${csvResult.errors.length} issue${csvResult.errors.length === 1 ? '' : 's'} found` : `${csvResult.rows?.length || 0} rows ready`}</p>{sourceHeaders.length ? <div className="grid gap-2 sm:grid-cols-2">{Object.keys(csvResult.mapping || {}).map((target) => <label key={target} className="text-xs font-semibold capitalize">{target.replaceAll('_', ' ')}<select value={csvMapping[target] || ''} onChange={(eventObject) => setCsvMapping((current) => ({ ...current, [target]: eventObject.target.value }))} className={input}><option value="">Not mapped</option>{sourceHeaders.map((header) => <option key={header} value={header}>{header}</option>)}</select></label>)}</div> : null}{csvResult.errors?.length ? <ul className="list-disc space-y-1 pl-5 text-xs text-red-700">{csvResult.errors.slice(0, 8).map((error, index) => <li key={index}>{error.message || error}</li>)}</ul> : null}</div> : null}</section> : null}
    </div>;
}
