import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';

export default function Create({ event }) {
    const form = useForm({ name: '', email: '' });
    const setupUrl = usePage().props.flash?.setup_url;

    function submit(submitEvent) {
        submitEvent.preventDefault();
        form.post(route('admin.accounts.store', event.id));
    }

    return (
        <AuthenticatedLayout header={<h1 className="text-2xl font-semibold tracking-tight text-slate-900">Provision account</h1>}>
            <Head title="Provision account" />
            <main className="min-h-[calc(100vh-9rem)] bg-[#f4f6f8] px-4 py-8 sm:px-6 lg:px-10">
                <form onSubmit={submit} className="mx-auto max-w-2xl rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                    <p className="text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">Closed provisioning</p>
                    <h2 className="mt-2 text-3xl font-semibold tracking-tight">Invite an institutional account</h2>
                    <p className="mt-3 text-sm leading-6 text-slate-500">The setup link expires in 24 hours. No reusable plaintext password is created or displayed.</p>
                    {setupUrl ? (
                        <div className="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                            <p className="font-semibold">Setup invitation created</p>
                            <p className="mt-1 break-all">Share this one-time link with the invited account:</p>
                            <a href={setupUrl} className="mt-2 block font-medium underline underline-offset-4">{setupUrl}</a>
                        </div>
                    ) : null}
                    <div className="mt-8 space-y-5">
                        <label className="block"><span className="text-sm font-medium text-slate-700">Name</span><input value={form.data.name} onChange={(input) => form.setData('name', input.target.value)} className="mt-2 w-full rounded-xl border-slate-300 focus:border-sky-600 focus:ring-sky-600" required /></label>
                        <label className="block"><span className="text-sm font-medium text-slate-700">Institutional email</span><input type="email" value={form.data.email} onChange={(input) => form.setData('email', input.target.value)} className="mt-2 w-full rounded-xl border-slate-300 focus:border-sky-600 focus:ring-sky-600" required /></label>
                    </div>
                    <button disabled={form.processing} className="mt-8 rounded-full bg-[#0b2e4f] px-6 py-3 text-sm font-semibold text-white disabled:opacity-50">Create setup invitation</button>
                </form>
            </main>
        </AuthenticatedLayout>
    );
}
