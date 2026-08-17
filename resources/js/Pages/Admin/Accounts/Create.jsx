import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import { Head, useForm, usePage } from '@inertiajs/react';

export default function Create({ event, targets }) {
    const form = useForm({
        name: '',
        email: '',
        role: 'judge',
        scope_type: 'competition_division',
        target_id: targets.competition_division[0]?.id ?? '',
    });
    const setupUrl = usePage().props.flash?.setup_url;

    const scopes = form.data.role === 'judge'
        ? [
            { value: 'competition_division', label: 'Entire competition division' },
            { value: 'entry_scorecard', label: 'One exact scorecard' },
        ]
        : [
            { value: 'competition_division', label: 'Entire competition division' },
            { value: 'contest', label: 'One exact contest' },
        ];

    function selectRole(role) {
        const scope = 'competition_division';
        form.setData((data) => ({
            ...data,
            role,
            scope_type: scope,
            target_id: targets[scope][0]?.id ?? '',
        }));
    }

    function selectScope(scope) {
        form.setData((data) => ({
            ...data,
            scope_type: scope,
            target_id: targets[scope][0]?.id ?? '',
        }));
    }

    function submit(submitEvent) {
        submitEvent.preventDefault();
        form.post(route('admin.accounts.store', event.id));
    }

    return (
        <AuthenticatedLayout header={<h1 className="text-2xl font-semibold tracking-tight text-slate-900">Provision account</h1>}>
            <Head title="Provision account" />
            <main className="min-h-[calc(100vh-9rem)] bg-[#f4f6f8] px-4 py-8 sm:px-6 lg:px-10">
                <form onSubmit={submit} className="mx-auto max-w-2xl rounded-3xl border border-slate-200 bg-white p-6 shadow-xs sm:p-8">
                    <p className="text-xs font-semibold uppercase tracking-[0.18em] text-[#0b2e4f]">Event-scoped access</p>
                    <h2 className="mt-2 font-serif text-3xl font-semibold tracking-tight">Invite a Judge or Tabulator</h2>
                    <p className="mt-3 text-sm leading-6 text-slate-600">{event.name}. Choose the role and exact work area before creating the 24-hour setup link.</p>
                    {setupUrl ? (
                        <div className="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                            <p className="font-semibold">Setup invitation created</p>
                            <p className="mt-1 break-all">Share this one-time link with the invited account:</p>
                            <a href={setupUrl} className="mt-2 block font-medium underline underline-offset-4">{setupUrl}</a>
                        </div>
                    ) : null}
                    <div className="mt-8 space-y-5">
                        <label className="block"><span className="text-sm font-medium text-slate-700">Name</span><input name="name" autoComplete="name" value={form.data.name} onChange={(input) => form.setData('name', input.target.value)} className="mt-2 w-full rounded-xl border-slate-300 focus:border-[#d5a21f] focus:ring-[#d5a21f]" required /><InputError message={form.errors.name} className="mt-2" /></label>
                        <label className="block"><span className="text-sm font-medium text-slate-700">Institutional Email</span><input name="email" autoComplete="email" spellCheck={false} type="email" value={form.data.email} onChange={(input) => form.setData('email', input.target.value)} className="mt-2 w-full rounded-xl border-slate-300 focus:border-[#d5a21f] focus:ring-[#d5a21f]" required /><InputError message={form.errors.email} className="mt-2" /></label>
                        <fieldset>
                            <legend className="text-sm font-medium text-slate-700">Event Role</legend>
                            <div className="mt-2 grid gap-3 sm:grid-cols-2">
                                {['judge', 'tabulator'].map((role) => (
                                    <label key={role} className={`cursor-pointer rounded-xl border p-4 ${form.data.role === role ? 'border-[#d5a21f] bg-amber-50' : 'border-slate-200'}`}>
                                        <input className="mr-2 text-[#0b2e4f] focus:ring-[#d5a21f]" type="radio" name="role" value={role} checked={form.data.role === role} onChange={() => selectRole(role)} />
                                        <span className="font-semibold capitalize text-slate-900">{role}</span>
                                    </label>
                                ))}
                            </div>
                            <InputError message={form.errors.role} className="mt-2" />
                        </fieldset>
                        <label className="block">
                            <span className="text-sm font-medium text-slate-700">Assignment coverage</span>
                            <select name="scope_type" value={form.data.scope_type} onChange={(input) => selectScope(input.target.value)} className="mt-2 w-full rounded-xl border-slate-300 focus:border-[#d5a21f] focus:ring-[#d5a21f]">
                                {scopes.map((scope) => <option key={scope.value} value={scope.value}>{scope.label}</option>)}
                            </select>
                            <InputError message={form.errors.scope_type} className="mt-2" />
                        </label>
                        <label className="block">
                            <span className="text-sm font-medium text-slate-700">Exact assignment</span>
                            <select name="target_id" value={form.data.target_id} onChange={(input) => form.setData('target_id', input.target.value)} className="mt-2 w-full rounded-xl border-slate-300 focus:border-[#d5a21f] focus:ring-[#d5a21f]" required>
                                <option value="" disabled>Select a configured target</option>
                                {targets[form.data.scope_type].map((target) => <option key={target.id} value={target.id}>{target.label}</option>)}
                            </select>
                            {targets[form.data.scope_type].length === 0 ? <p className="mt-2 text-sm text-amber-700">Configure this work area before provisioning the account.</p> : null}
                            <InputError message={form.errors.target_id} className="mt-2" />
                        </label>
                    </div>
                    <button disabled={form.processing || !form.data.target_id} className="mt-8 rounded-full bg-[#0b2e4f] px-6 py-3 text-sm font-semibold text-white disabled:opacity-50">{form.processing ? 'Creating Invitation…' : 'Create Scoped Invitation'}</button>
                </form>
            </main>
        </AuthenticatedLayout>
    );
}
