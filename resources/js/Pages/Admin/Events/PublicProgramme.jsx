import InputError from '@/Components/InputError';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const fieldClass = 'mt-1 block min-h-11 w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900 shadow-sm focus:border-sky-700 focus:ring-sky-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-700';
const labelClass = 'block text-sm font-medium text-slate-700';

function formatDateTime(value) {
    return value
        ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
        : 'Not set';
}

function toLocalInput(value) {
    if (!value) return '';

    const date = new Date(value);
    const pad = (part) => String(part).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function StateLabel({ children, tone = 'neutral' }) {
    const tones = {
        neutral: 'bg-slate-100 text-slate-700',
        draft: 'bg-amber-100 text-amber-900',
        published: 'bg-emerald-100 text-emerald-800',
        changed: 'bg-sky-100 text-sky-900',
    };

    return <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${tones[tone]}`}>{children}</span>;
}

function SubmitButton({ processing, children, tone = 'primary' }) {
    const toneClass = tone === 'danger'
        ? 'border-red-300 text-red-700 hover:bg-red-50'
        : tone === 'secondary'
            ? 'border-slate-300 text-slate-700 hover:bg-slate-50'
            : 'border-[#0B2E4F] bg-[#0B2E4F] text-white hover:bg-[#164565]';

    return (
        <button
            type="submit"
            disabled={processing}
            className={`inline-flex min-h-11 items-center justify-center rounded-lg border px-4 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-700 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 ${toneClass}`}
        >
            {processing ? 'Saving…' : children}
        </button>
    );
}

function VenueCreateForm({ event }) {
    const form = useForm({ name: '', code: '', location: '', description: '', is_active: true });

    const submit = (eventObject) => {
        eventObject.preventDefault();
        form.post(route('admin.venues.store', event.id), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <form onSubmit={submit} className="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-2">
            <div>
                <label htmlFor="new-venue-name" className={labelClass}>Venue name</label>
                <input id="new-venue-name" name="name" autoComplete="off" value={form.data.name} onChange={(eventObject) => form.setData('name', eventObject.target.value)} className={fieldClass} required />
                <InputError message={form.errors.name} className="mt-2" />
            </div>
            <div>
                <label htmlFor="new-venue-code" className={labelClass}>Short code <span className="text-slate-400">optional</span></label>
                <input id="new-venue-code" name="code" autoComplete="off" value={form.data.code} onChange={(eventObject) => form.setData('code', eventObject.target.value)} className={fieldClass} />
                <InputError message={form.errors.code} className="mt-2" />
            </div>
            <div className="md:col-span-2">
                <label htmlFor="new-venue-location" className={labelClass}>Where it is located</label>
                <input id="new-venue-location" name="location" autoComplete="off" value={form.data.location} onChange={(eventObject) => form.setData('location', eventObject.target.value)} className={fieldClass} placeholder="CSPC Gymnasium, Main Campus" />
                <InputError message={form.errors.location} className="mt-2" />
            </div>
            <div className="md:col-span-2">
                <label htmlFor="new-venue-description" className={labelClass}>Public-facing directions or notes <span className="text-slate-400">optional</span></label>
                <textarea id="new-venue-description" name="description" autoComplete="off" rows="3" value={form.data.description} onChange={(eventObject) => form.setData('description', eventObject.target.value)} className={fieldClass} />
                <InputError message={form.errors.description} className="mt-2" />
            </div>
            <label className="flex min-h-11 items-center gap-3 text-sm text-slate-700">
                <input type="checkbox" name="is_active" checked={form.data.is_active} onChange={(eventObject) => form.setData('is_active', eventObject.target.checked)} className="rounded border-slate-300 text-sky-700 focus-visible:ring-2 focus-visible:ring-sky-700" />
                Available for schedules
            </label>
            <div className="flex justify-end"><SubmitButton processing={form.processing}>Add Venue</SubmitButton></div>
        </form>
    );
}

function VenueEditor({ event, venue, readOnly = false }) {
    const form = useForm({
        name: venue.name,
        code: venue.code ?? '',
        location: venue.location ?? '',
        description: venue.description ?? '',
        is_active: venue.is_active,
    });

    const submit = (eventObject) => {
        eventObject.preventDefault();
        form.patch(route('admin.venues.update', [event.id, venue.id]), { preserveScroll: true });
    };

    if (readOnly) {
        return (
            <div className="flex min-h-16 items-center justify-between gap-4 border-b border-slate-200 py-3 last:border-b-0">
                <span>
                    <span className="block font-semibold text-slate-900">{venue.name}</span>
                    <span className="mt-0.5 block text-sm text-slate-500">{venue.location || 'Location not added'}</span>
                </span>
                <StateLabel tone={venue.is_active ? 'published' : 'neutral'}>{venue.is_active ? 'Active' : 'Inactive'}</StateLabel>
            </div>
        );
    }

    return (
        <details className="group border-b border-slate-200 last:border-b-0">
            <summary className="flex min-h-16 cursor-pointer list-none items-center justify-between gap-4 py-3 marker:hidden focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-700 focus-visible:ring-offset-2">
                <span>
                    <span className="block font-semibold text-slate-900">{venue.name}</span>
                    <span className="mt-0.5 block text-sm text-slate-500">{venue.location || 'Location not added'}</span>
                </span>
                <span className="flex items-center gap-3">
                    <StateLabel tone={venue.is_active ? 'published' : 'neutral'}>{venue.is_active ? 'Active' : 'Inactive'}</StateLabel>
                    <span className="text-sm font-semibold text-sky-800 group-open:hidden">Edit</span>
                </span>
            </summary>
            <form onSubmit={submit} className="grid gap-4 pb-6 pt-2 md:grid-cols-2">
                <div>
                    <label htmlFor={`venue-name-${venue.id}`} className={labelClass}>Venue name</label>
                    <input id={`venue-name-${venue.id}`} name="name" autoComplete="off" value={form.data.name} onChange={(eventObject) => form.setData('name', eventObject.target.value)} className={fieldClass} required />
                    <InputError message={form.errors.name} className="mt-2" />
                </div>
                <div>
                    <label htmlFor={`venue-code-${venue.id}`} className={labelClass}>Short code</label>
                    <input id={`venue-code-${venue.id}`} name="code" autoComplete="off" value={form.data.code} onChange={(eventObject) => form.setData('code', eventObject.target.value)} className={fieldClass} />
                </div>
                <div className="md:col-span-2">
                    <label htmlFor={`venue-location-${venue.id}`} className={labelClass}>Location</label>
                    <input id={`venue-location-${venue.id}`} name="location" autoComplete="off" value={form.data.location} onChange={(eventObject) => form.setData('location', eventObject.target.value)} className={fieldClass} />
                    <InputError message={form.errors.location} className="mt-2" />
                </div>
                <div className="md:col-span-2">
                    <label htmlFor={`venue-description-${venue.id}`} className={labelClass}>Directions or notes</label>
                    <textarea id={`venue-description-${venue.id}`} name="description" autoComplete="off" rows="3" value={form.data.description} onChange={(eventObject) => form.setData('description', eventObject.target.value)} className={fieldClass} />
                </div>
                <label className="flex min-h-11 items-center gap-3 text-sm text-slate-700">
                    <input type="checkbox" name="is_active" checked={form.data.is_active} onChange={(eventObject) => form.setData('is_active', eventObject.target.checked)} className="rounded border-slate-300 text-sky-700 focus-visible:ring-2 focus-visible:ring-sky-700" />
                    Available for schedules
                </label>
                <div className="flex justify-end"><SubmitButton processing={form.processing}>Save Venue</SubmitButton></div>
            </form>
        </details>
    );
}

function ScheduleFields({ form, divisions, venues, statuses, idPrefix }) {
    return (
        <>
            <div>
                <label htmlFor={`${idPrefix}-division`} className={labelClass}>Competition / Division</label>
                <select id={`${idPrefix}-division`} name="competition_division_id" autoComplete="off" value={form.data.competition_division_id} onChange={(eventObject) => form.setData('competition_division_id', eventObject.target.value)} className={fieldClass} required>
                    <option value="">Select a Division</option>
                    {divisions.map((division) => <option key={division.id} value={division.id}>{division.competition} · {division.name}</option>)}
                </select>
                <InputError message={form.errors.competition_division_id} className="mt-2" />
            </div>
            <div>
                <label htmlFor={`${idPrefix}-venue`} className={labelClass}>Venue</label>
                <select id={`${idPrefix}-venue`} name="venue_id" autoComplete="off" value={form.data.venue_id} onChange={(eventObject) => form.setData('venue_id', eventObject.target.value)} className={fieldClass}>
                    <option value="">Venue to be announced</option>
                    {venues.map((venue) => <option key={venue.id} value={venue.id}>{venue.name}{venue.location ? ` · ${venue.location}` : ''}</option>)}
                </select>
                <InputError message={form.errors.venue_id} className="mt-2" />
            </div>
            <div className="md:col-span-2">
                <label htmlFor={`${idPrefix}-title`} className={labelClass}>Programme title</label>
                <input id={`${idPrefix}-title`} name="title" autoComplete="off" value={form.data.title} onChange={(eventObject) => form.setData('title', eventObject.target.value)} className={fieldClass} placeholder="Men’s Basketball Eliminations" required />
                <InputError message={form.errors.title} className="mt-2" />
            </div>
            <div>
                <label htmlFor={`${idPrefix}-starts`} className={labelClass}>Starts</label>
                <input id={`${idPrefix}-starts`} name="starts_at" autoComplete="off" type="datetime-local" value={form.data.starts_at} onChange={(eventObject) => form.setData('starts_at', eventObject.target.value)} className={fieldClass} required />
                <InputError message={form.errors.starts_at} className="mt-2" />
            </div>
            <div>
                <label htmlFor={`${idPrefix}-ends`} className={labelClass}>Ends <span className="text-slate-400">optional</span></label>
                <input id={`${idPrefix}-ends`} name="ends_at" autoComplete="off" type="datetime-local" value={form.data.ends_at} onChange={(eventObject) => form.setData('ends_at', eventObject.target.value)} className={fieldClass} />
                <InputError message={form.errors.ends_at} className="mt-2" />
            </div>
            <div>
                <label htmlFor={`${idPrefix}-status`} className={labelClass}>Status</label>
                <select id={`${idPrefix}-status`} name="status" autoComplete="off" value={form.data.status} onChange={(eventObject) => form.setData('status', eventObject.target.value)} className={fieldClass}>
                    {statuses.map((status) => <option key={status.value} value={status.value}>{status.label}</option>)}
                </select>
                <InputError message={form.errors.status} className="mt-2" />
            </div>
            <div className="md:col-span-2">
                <label htmlFor={`${idPrefix}-notes`} className={labelClass}>Internal notes <span className="text-slate-400">never public</span></label>
                <textarea id={`${idPrefix}-notes`} name="notes" autoComplete="off" rows="3" value={form.data.notes} onChange={(eventObject) => form.setData('notes', eventObject.target.value)} className={fieldClass} />
                <InputError message={form.errors.notes} className="mt-2" />
            </div>
        </>
    );
}

function ScheduleCreateForm({ event, divisions, venues, statuses }) {
    const form = useForm({ competition_division_id: '', venue_id: '', title: '', starts_at: '', ends_at: '', status: 'scheduled', notes: '' });
    const submit = (eventObject) => {
        eventObject.preventDefault();
        form.post(route('admin.schedules.store', event.id), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <form onSubmit={submit} className="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-2">
            <ScheduleFields form={form} divisions={divisions} venues={venues} statuses={statuses} idPrefix="new-schedule" />
            <div className="flex justify-end md:col-span-2"><SubmitButton processing={form.processing}>Create Schedule Draft</SubmitButton></div>
        </form>
    );
}

function WithdrawSchedule({ event, publication }) {
    const form = useForm({ reason: '' });
    const submit = (eventObject) => {
        eventObject.preventDefault();
        if (!window.confirm('Withdraw this schedule from the public programme? The operational draft will remain available.')) return;
        form.post(route('admin.schedule-publications.withdraw', [event.id, publication.id]), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <form onSubmit={submit} className="mt-4 flex flex-col gap-3 border-t border-red-100 pt-4 sm:flex-row sm:items-end">
            <div className="flex-1">
                <label htmlFor={`withdraw-schedule-${publication.id}`} className={labelClass}>Reason to withdraw from public view</label>
                <input id={`withdraw-schedule-${publication.id}`} name="reason" autoComplete="off" value={form.data.reason} onChange={(eventObject) => form.setData('reason', eventObject.target.value)} className={fieldClass} required minLength="5" />
                <InputError message={form.errors.reason || form.errors.schedule} className="mt-2" />
            </div>
            <SubmitButton processing={form.processing} tone="danger">Withdraw</SubmitButton>
        </form>
    );
}

function ScheduleEditor({ event, schedule, divisions, venues, statuses, readOnly = false }) {
    const form = useForm({
        competition_division_id: schedule.competition_division_id,
        venue_id: schedule.venue_id ?? '',
        title: schedule.title,
        starts_at: toLocalInput(schedule.starts_at),
        ends_at: toLocalInput(schedule.ends_at),
        status: schedule.status,
        notes: schedule.notes ?? '',
    });
    const publishForm = useForm({});

    const submit = (eventObject) => {
        eventObject.preventDefault();
        form.patch(route('admin.schedules.update', [event.id, schedule.id]), { preserveScroll: true });
    };

    const publish = () => publishForm.post(route('admin.schedules.publish', [event.id, schedule.id]), { preserveScroll: true });

    if (readOnly) {
        return (
            <article className="flex min-h-20 items-center justify-between gap-4 border-b border-slate-200 py-4 last:border-b-0">
                <div className="min-w-0">
                    <h3 className="truncate font-semibold text-slate-900">{schedule.title}</h3>
                    <p className="mt-1 text-sm text-slate-500">{schedule.competition} · {schedule.division} · {formatDateTime(schedule.starts_at)}</p>
                </div>
                {schedule.publication ? <StateLabel tone="published">Published r{schedule.publication.revision}</StateLabel> : <StateLabel tone="draft">Not public</StateLabel>}
            </article>
        );
    }

    return (
        <details className="group border-b border-slate-200 last:border-b-0">
            <summary className="flex min-h-20 cursor-pointer list-none items-center justify-between gap-4 py-4 marker:hidden focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-700 focus-visible:ring-offset-2">
                <span className="min-w-0">
                    <span className="block truncate font-semibold text-slate-900">{schedule.title}</span>
                    <span className="mt-1 block text-sm text-slate-500">{schedule.competition} · {schedule.division} · {formatDateTime(schedule.starts_at)}</span>
                </span>
                <span className="flex shrink-0 flex-wrap justify-end gap-2">
                    {schedule.publication ? <StateLabel tone="published">Published r{schedule.publication.revision}</StateLabel> : <StateLabel tone="draft">Not public</StateLabel>}
                    {schedule.has_unpublished_changes && schedule.publication ? <StateLabel tone="changed">Changes waiting</StateLabel> : null}
                </span>
            </summary>
            <div className="pb-7 pt-2">
                {schedule.publication ? (
                    <div className="mb-5 border-l-4 border-emerald-500 bg-emerald-50 px-4 py-3 text-sm text-emerald-950">
                        <p className="font-semibold">Public snapshot: {schedule.publication.title}</p>
                        <p className="mt-1 text-emerald-800">{formatDateTime(schedule.publication.starts_at)} · {schedule.publication.venue_name || 'Venue to be announced'}</p>
                    </div>
                ) : null}
                <form onSubmit={submit} className="grid gap-4 md:grid-cols-2">
                    <ScheduleFields form={form} divisions={divisions} venues={venues} statuses={statuses} idPrefix={`schedule-${schedule.id}`} />
                    <div className="flex flex-wrap justify-end gap-3 md:col-span-2">
                        <SubmitButton processing={form.processing} tone="secondary">Save Draft</SubmitButton>
                        <button type="button" onClick={publish} disabled={publishForm.processing || form.isDirty} title={form.isDirty ? 'Save the visible draft changes before publishing.' : undefined} className="inline-flex min-h-11 items-center justify-center rounded-lg bg-emerald-700 px-4 text-sm font-semibold text-white transition-colors hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                            {form.isDirty ? 'Save Draft Before Publishing' : publishForm.processing ? 'Publishing…' : schedule.publication ? 'Republish Saved Draft' : 'Publish Schedule'}
                        </button>
                    </div>
                    <InputError message={publishForm.errors.schedule} className="md:col-span-2" />
                </form>
                {schedule.publication ? <WithdrawSchedule event={event} publication={schedule.publication} /> : null}
            </div>
        </details>
    );
}

function CoverWithdrawForm({ event, cover }) {
    const form = useForm({ reason: '' });
    const submit = (eventObject) => {
        eventObject.preventDefault();
        if (!window.confirm('Withdraw this cover from the public landing page?')) return;
        form.post(route('admin.cover-images.withdraw', [event.id, cover.id]), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <form onSubmit={submit} className="mt-3 flex gap-2">
            <div className="flex-1">
                <label htmlFor={`cover-withdraw-${cover.id}`} className="sr-only">Withdrawal reason</label>
                <input id={`cover-withdraw-${cover.id}`} name="reason" autoComplete="off" value={form.data.reason} onChange={(eventObject) => form.setData('reason', eventObject.target.value)} className={`${fieldClass} mt-0`} placeholder="Reason for withdrawal" required minLength="5" />
                <InputError message={form.errors.reason || form.errors.cover} className="mt-2" />
            </div>
            <SubmitButton processing={form.processing} tone="danger">Withdraw</SubmitButton>
        </form>
    );
}

function CompetitionCoverEditor({ event, competition, readOnly = false }) {
    const form = useForm({ cover: null, alt_text: '' });
    const publishForm = useForm({});
    const [localPreview, setLocalPreview] = useState(null);

    useEffect(() => {
        if (!form.data.cover) {
            setLocalPreview(null);
            return undefined;
        }

        const url = URL.createObjectURL(form.data.cover);
        setLocalPreview(url);

        return () => URL.revokeObjectURL(url);
    }, [form.data.cover]);

    const submit = (eventObject) => {
        eventObject.preventDefault();
        form.post(route('admin.cover-images.store', [event.id, competition.id]), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const draft = competition.draft_cover;
    const published = competition.published_cover;
    const preview = localPreview || draft?.preview_url || published?.public_url;

    return (
        <article className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div className="aspect-[16/9] bg-[#0B2E4F]">
                {preview ? <img src={preview} alt={localPreview ? 'Selected cover preview' : (draft?.alt_text || published?.alt_text)} width={draft?.width || published?.width || 1600} height={draft?.height || published?.height || 900} className="h-full w-full object-cover" /> : (
                    <div className="flex h-full items-end bg-[linear-gradient(135deg,transparent_60%,rgba(213,162,31,0.25)_60%)] p-5 text-white">
                        <p className="text-2xl font-bold tracking-tight">{competition.name}</p>
                    </div>
                )}
            </div>
            <div className="p-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 className="text-lg font-semibold text-slate-900">{competition.name}</h3>
                        <p className="mt-1 text-sm text-slate-500">{competition.divisions.length} Division{competition.divisions.length === 1 ? '' : 's'}</p>
                    </div>
                    <div className="flex gap-2">
                        {published ? <StateLabel tone="published">Published r{published.revision}</StateLabel> : null}
                        {draft ? <StateLabel tone="draft">Draft r{draft.revision}</StateLabel> : null}
                    </div>
                </div>

                {readOnly ? (
                    <p className="mt-5 border-t border-slate-100 pt-4 text-sm text-slate-500">Archived record · uploads and publication changes are disabled.</p>
                ) : <><form onSubmit={submit} className="mt-5 space-y-4 border-t border-slate-100 pt-5">
                    <div>
                        <label htmlFor={`cover-file-${competition.id}`} className={labelClass}>CSPC-owned landscape photo</label>
                        <input id={`cover-file-${competition.id}`} name="cover" type="file" accept="image/jpeg,image/png,image/webp" onChange={(eventObject) => form.setData('cover', eventObject.target.files?.[0] ?? null)} className="mt-1 block w-full text-sm text-slate-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-700 file:mr-4 file:min-h-11 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:text-sm file:font-semibold file:text-slate-800 hover:file:bg-slate-200" required />
                        <p className="mt-2 text-xs text-slate-500">JPG, PNG, or WebP · at least 800 × 450 · up to 5 MB</p>
                        <InputError message={form.errors.cover} className="mt-2" />
                    </div>
                    <div>
                        <label htmlFor={`cover-alt-${competition.id}`} className={labelClass}>Image description</label>
                        <input id={`cover-alt-${competition.id}`} name="alt_text" autoComplete="off" value={form.data.alt_text} onChange={(eventObject) => form.setData('alt_text', eventObject.target.value)} className={fieldClass} placeholder="Describe the sport and venue shown" required minLength="10" maxLength="180" />
                        <InputError message={form.errors.alt_text} className="mt-2" />
                    </div>
                    <SubmitButton processing={form.processing}>Upload Private Draft</SubmitButton>
                </form>

                {draft ? (
                    <div className="mt-4 border-t border-slate-100 pt-4">
                        <p className="text-sm text-slate-600">Draft description: {draft.alt_text}</p>
                        <button type="button" onClick={() => (!published || window.confirm('Replace the cover currently shown on the public landing page?')) && publishForm.post(route('admin.cover-images.publish', [event.id, draft.id]), { preserveScroll: true })} disabled={publishForm.processing} className="mt-3 inline-flex min-h-11 items-center rounded-lg bg-emerald-700 px-4 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2 disabled:opacity-50">
                            {publishForm.processing ? 'Publishing…' : published ? 'Replace Published Cover' : 'Publish Cover'}
                        </button>
                        <InputError message={publishForm.errors.cover} className="mt-2" />
                    </div>
                ) : null}

                {published ? <CoverWithdrawForm event={event} cover={published} /> : null}</>}
            </div>
        </article>
    );
}

export default function PublicProgramme({ event, competitions = [], venues = [], schedules = [], schedule_statuses: statuses = [] }) {
    const divisions = competitions.flatMap((competition) => competition.divisions.map((division) => ({ ...division, competition: competition.name })));
    const activeVenues = venues.filter((venue) => venue.is_active);
    const statusMessage = usePage().props.flash?.status;
    const readOnly = event.archived;

    return (
        <AuthenticatedLayout
            header={(
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.16em] text-sky-700">Public programme</p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight text-slate-900">{event.name}</h1>
                    </div>
                    <Link href={route('dashboard')} className="inline-flex min-h-11 items-center rounded text-sm font-semibold text-sky-800 underline decoration-sky-300 underline-offset-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-700">Back to Dashboard</Link>
                </div>
            )}
        >
            <Head title={`Public Programme · ${event.name}`} />
            <div className="min-h-[calc(100vh-9rem)] bg-slate-100 px-4 py-8 sm:px-6 lg:px-10">
                <div className="mx-auto max-w-7xl space-y-12">
                    {statusMessage ? <div role="status" className="border-l-4 border-emerald-600 bg-white px-5 py-4 text-sm font-medium text-emerald-900 shadow-sm">{statusMessage}</div> : null}
                    {readOnly ? <div role="status" className="border-l-4 border-amber-500 bg-white px-5 py-4 text-sm text-amber-950 shadow-sm"><strong>Archived Event:</strong> this programme is read-only. Published snapshots remain visible for review.</div> : null}

                    <section aria-labelledby="programme-cover-title">
                        <div className="mb-6 max-w-3xl">
                            <p className="text-sm font-semibold text-[#9B741A]">Competition covers</p>
                            <h2 id="programme-cover-title" className="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Give each sport a real CSPC image.</h2>
                            <p className="mt-3 leading-7 text-slate-600">Uploads stay private until you publish them. Replacing a public cover is explicit and audited.</p>
                        </div>
                        {competitions.length ? <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-3">{competitions.map((competition) => <CompetitionCoverEditor key={competition.id} event={event} competition={competition} readOnly={readOnly} />)}</div> : <div className="border-l-4 border-amber-400 bg-white p-5 text-sm text-slate-600">Create competitions first, then return here to add public covers.</div>}
                    </section>

                    <section aria-labelledby="programme-venues-title" className="grid gap-7 lg:grid-cols-[0.8fr_1.2fr]">
                        <div>
                            <p className="text-sm font-semibold text-[#9B741A]">Venues</p>
                            <h2 id="programme-venues-title" className="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Where events happen</h2>
                            <p className="mt-3 max-w-md leading-7 text-slate-600">Venue names and locations are copied into each published schedule snapshot, so later edits never silently rewrite the public programme.</p>
                        </div>
                        <div className="space-y-5">
                            {readOnly ? null : <VenueCreateForm event={event} />}
                            {venues.length ? <div className="rounded-xl border border-slate-200 bg-white px-5 shadow-sm">{venues.map((venue) => <VenueEditor key={venue.id} event={event} venue={venue} readOnly={readOnly} />)}</div> : null}
                        </div>
                    </section>

                    <section aria-labelledby="programme-schedules-title" className="grid gap-7 lg:grid-cols-[0.8fr_1.2fr]">
                        <div>
                            <p className="text-sm font-semibold text-[#9B741A]">Schedules</p>
                            <h2 id="programme-schedules-title" className="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Publish the event programme</h2>
                            <p className="mt-3 max-w-md leading-7 text-slate-600">Save operational changes freely. Visitors keep seeing the last published version until you press Republish.</p>
                        </div>
                        <div className="space-y-5">
                            {readOnly ? null : <ScheduleCreateForm event={event} divisions={divisions} venues={activeVenues} statuses={statuses} />}
                            {schedules.length ? <div className="rounded-xl border border-slate-200 bg-white px-5 shadow-sm">{schedules.map((schedule) => <ScheduleEditor key={schedule.id} event={event} schedule={schedule} divisions={divisions} venues={activeVenues} statuses={statuses} readOnly={readOnly} />)}</div> : <p className="border-l-4 border-slate-300 bg-white p-5 text-sm text-slate-600">No schedule drafts yet.</p>}
                        </div>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
