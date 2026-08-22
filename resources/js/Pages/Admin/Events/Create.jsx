import { AdminMasthead } from '@/Components/Admin/AdminSurface';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { adminStyles } from '@/Support/adminStyles';
import { Head, useForm } from '@inertiajs/react';

export default function Create() {
    const form = useForm({ name: '', slug: '' });

    function submit(event) {
        event.preventDefault();
        form.post(route('admin.events.store'));
    }

    return (
        <AuthenticatedLayout header={<div className="flex items-center gap-2 text-sm"><span className="font-semibold text-muted">Events</span><span aria-hidden="true" className="text-border">/</span><span className="font-semibold text-foreground">Create</span></div>}>
            <Head title="Create event" />
            <main className={adminStyles.page}>
                <div className="mx-auto max-w-3xl space-y-6">
                <AdminMasthead eyebrow="Platform authority" title="Start a SIKLAB edition" description="The sole Global Admin manages every edition. Judge and Tabulator access is assigned after the event is configured." />
                <form onSubmit={submit} className="border border-border bg-surface p-6 sm:p-8">
                    <div className="mt-8 space-y-5">
                        <label className="block">
                            <span className="text-sm font-semibold text-foreground">Event name</span>
                            <input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} className={`mt-2 ${adminStyles.field}`} placeholder="SIKLAB 2026" required />
                            {form.errors.name ? <span className="mt-1 block text-sm text-danger">{form.errors.name}</span> : null}
                        </label>
                        <label className="block">
                            <span className="text-sm font-semibold text-foreground">URL slug</span>
                            <input value={form.data.slug} onChange={(event) => form.setData('slug', event.target.value)} className={`mt-2 ${adminStyles.field}`} placeholder="siklab-2026" />
                        </label>
                    </div>
                    <button disabled={form.processing} className={`mt-8 ${adminStyles.primaryAction}`}>{form.processing ? 'Creating...' : 'Create event shell'}</button>
                </form>
                </div>
            </main>
        </AuthenticatedLayout>
    );
}
