import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Create() {
    const form = useForm({ name: '', slug: '' });

    function submit(event) {
        event.preventDefault();
        form.post(route('admin.events.store'));
    }

    return (
        <AuthenticatedLayout header={<h1 className="text-2xl font-semibold tracking-tight text-slate-900">Create event shell</h1>}>
            <Head title="Create event" />
            <main className="min-h-[calc(100vh-9rem)] bg-[#f4f6f8] px-4 py-8 sm:px-6 lg:px-10">
                <form onSubmit={submit} className="mx-auto max-w-2xl rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                    <p className="text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">Platform authority</p>
                    <h2 className="mt-2 text-3xl font-semibold tracking-tight">Start a SIKLAB edition</h2>
                    <p className="mt-3 text-sm leading-6 text-slate-500">The sole Global Admin manages every edition. Judge and Tabulator access is assigned after the Event is configured.</p>
                    <div className="mt-8 space-y-5">
                        <label className="block">
                            <span className="text-sm font-medium text-slate-700">Event name</span>
                            <input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} className="mt-2 w-full rounded-xl border-slate-300 focus:border-sky-600 focus:ring-sky-600" placeholder="SIKLAB 2026" required />
                            {form.errors.name ? <span className="mt-1 block text-sm text-red-700">{form.errors.name}</span> : null}
                        </label>
                        <label className="block">
                            <span className="text-sm font-medium text-slate-700">URL slug</span>
                            <input value={form.data.slug} onChange={(event) => form.setData('slug', event.target.value)} className="mt-2 w-full rounded-xl border-slate-300 focus:border-sky-600 focus:ring-sky-600" placeholder="siklab-2026" />
                        </label>
                    </div>
                    <button disabled={form.processing} className="mt-8 rounded-full bg-[#0b2e4f] px-6 py-3 text-sm font-semibold text-white transition hover:bg-sky-900 disabled:opacity-50">{form.processing ? 'Creating...' : 'Create event shell'}</button>
                </form>
            </main>
        </AuthenticatedLayout>
    );
}
