import React, { useEffect, useState } from 'react';
import QRCode from 'qrcode';

function formatExpiry(expiresAt) {
    if (!expiresAt) return '24 hours after issue';
    return new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }).format(new Date(expiresAt));
}

export default function StaffSetupHandoffCard({ eventName, staffName, roleLabel, expiresAt, setupUrl }) {
    const [qrDataUrl, setQrDataUrl] = useState(null);
    const [qrState, setQrState] = useState('generating');

    useEffect(() => {
        let current = true;
        setQrState('generating');
        setQrDataUrl(null);
        QRCode.toDataURL(setupUrl, {
            errorCorrectionLevel: 'M',
            margin: 2,
            width: 640,
            color: { dark: '#000000', light: '#ffffff' },
        }).then((value) => {
            if (!current) return;
            setQrDataUrl(value);
            setQrState('ready');
        }).catch(() => {
            if (current) setQrState('error');
        });

        return () => { current = false; };
    }, [setupUrl]);

    return <div id="staff-setup-print-sheet" className="mt-8 flex justify-center">
        <article id="staff-setup-print-card" aria-label="Staff setup handoff card" className="w-full max-w-[30rem] overflow-hidden border border-[#17212b] bg-white text-[#17212b] shadow-xl">
            <header className="flex items-center justify-between border-b-4 border-[#d5a21f] bg-white px-6 py-5">
                <strong className="font-serif text-xl tracking-[0.12em]">SYNTIX</strong>
                <span className="text-xs font-bold uppercase tracking-[0.16em]">Staff setup</span>
            </header>
            <div className="p-6 sm:p-8">
                <p className="text-xs font-bold uppercase tracking-[0.14em] text-[#0b536d]">{eventName}</p>
                <h2 className="mt-3 break-words font-serif text-3xl font-bold">{staffName}</h2>
                <p className="mt-2 text-sm font-bold">{roleLabel}</p>
                <div className="mt-6 flex justify-center">
                    <div className="grid size-52 shrink-0 place-items-center border-2 border-[#17212b] bg-white p-3 sm:size-56">
                        {qrState === 'ready' ? <img src={qrDataUrl} alt="One-time staff setup QR code" className="size-full"/> : qrState === 'error' ? <p className="px-3 text-center text-xs font-semibold text-[#b42318]">QR generation failed. Print a new card or copy the private setup link.</p> : <p className="text-xs text-[#68767e]">Generating QR…</p>}
                    </div>
                </div>
                <p className="mt-5 text-center text-sm leading-6">Scan to finish your Syntix account.</p>
                <ol className="mx-auto mt-4 max-w-xs space-y-1 text-sm"><li>1. Scan the QR code</li><li>2. Create your password</li><li>3. Sign in</li></ol>
                <p className="mt-5 text-center text-xs font-semibold text-[#b42318]">Expires {formatExpiry(expiresAt)} · One use only</p>
            </div>
            <footer className="border-t border-[#cfd6d3] px-6 py-4 text-xs"><strong className="block uppercase tracking-[0.12em]">PRIVATE ONE-TIME CREDENTIAL</strong><span className="mt-1 block">Do not share this card. Hand it directly to the named staff member.</span></footer>
        </article>
    </div>;
}
