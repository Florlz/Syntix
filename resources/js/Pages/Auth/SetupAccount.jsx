import { Head, useForm } from '@inertiajs/react';

export default function SetupAccount({ valid, email }) {
    const form = useForm({ password: '', password_confirmation: '' });

    function submit(event) {
        event.preventDefault();
        form.post(window.location.pathname);
    }

    return (
        <main className="grid min-h-screen place-items-center bg-[#0b2e4f] px-5 py-10 text-slate-900">
            <Head title="Set up Syntix account" />
            <form onSubmit={submit} className="w-full max-w-lg rounded-3xl bg-white p-7 shadow-2xl sm:p-10">
                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">Syntix account setup</p>
                <h1 className="mt-3 text-3xl font-semibold tracking-tight">{valid ? 'Create your password' : 'Setup link unavailable'}</h1>
                {valid ? <p className="mt-3 text-sm text-slate-500">Finish setup for {email}. This link can be used once.</p> : <p className="mt-3 text-sm text-red-700">Ask the Global Admin to issue a new invitation.</p>}
                {valid ? <>
                    <label className="mt-8 block"><span className="text-sm font-medium">Password</span><input type="password" value={form.data.password} onChange={(input) => form.setData('password', input.target.value)} className="mt-2 w-full rounded-xl border-slate-300" required /></label>
                    <label className="mt-5 block"><span className="text-sm font-medium">Confirm password</span><input type="password" value={form.data.password_confirmation} onChange={(input) => form.setData('password_confirmation', input.target.value)} className="mt-2 w-full rounded-xl border-slate-300" required /></label>
                    <button disabled={form.processing} className="mt-8 rounded-full bg-[#f97316] px-6 py-3 text-sm font-semibold text-white disabled:opacity-50">Finish setup</button>
                </> : null}
            </form>
        </main>
    );
}
