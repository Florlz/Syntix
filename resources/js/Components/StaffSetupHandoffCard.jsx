import React, { useEffect, useState } from 'react';
import QRCode from 'qrcode';
import PrintPortal from '@/Components/PrintPortal';

function formatExpiry(expiresAt) {
    if (!expiresAt) return '24 hours after issue';
    return new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }).format(new Date(expiresAt));
}

function SetupCard({ eventName, staffName, roleLabel, expiresAt, qrDataUrl, variant }) {
    return <article aria-label="Staff setup handoff card" className={`staff-setup-handoff-card staff-setup-handoff-card--${variant}`}>
        <header className="staff-setup-card-header">
            <strong className="font-serif text-xl tracking-[0.12em]">SYNTIX</strong>
            <span className="text-xs font-bold uppercase tracking-[0.16em]">Staff setup</span>
        </header>
        <div className="staff-setup-card-body">
            <div className="staff-setup-card-details">
                <p className="staff-setup-event-name text-xs font-bold uppercase tracking-[0.14em] text-[#0b536d]">{eventName}</p>
                <h2 className="staff-setup-staff-name">{staffName}</h2>
                <p className="staff-setup-role mt-2 text-sm font-bold">{roleLabel}</p>
                <p className="staff-setup-intro mt-5 text-sm leading-6">Scan the QR code to finish your Syntix account setup.</p>
                <ol className="staff-setup-steps mt-4 space-y-1 text-sm"><li>1. Scan the QR code</li><li>2. Create your password</li><li>3. Sign in</li></ol>
                <p className="staff-setup-expiry mt-5 text-xs font-semibold text-[#b42318]">Expires {formatExpiry(expiresAt)} · One use only</p>
            </div>
            <div className="staff-setup-qr-panel">
                <div className="staff-setup-qr-code"><img src={qrDataUrl} alt="One-time staff setup QR code" className="size-full"/></div>
                <p className="staff-setup-qr-copy mt-3 text-center text-xs leading-5">{variant === 'print' ? 'Keep private. Hand directly to the named staff member.' : `Keep this private. Hand the card directly to ${staffName}.`}</p>
            </div>
        </div>
        <footer className="staff-setup-card-footer">PRIVATE ONE-TIME CREDENTIAL — Do not share or photograph this card.</footer>
    </article>;
}

export default function StaffSetupHandoffCard({ eventName, staffName, roleLabel, expiresAt, setupUrl, onQrStateChange }) {
    const [qrDataUrl, setQrDataUrl] = useState(null);
    const [qrState, setQrState] = useState('generating');

    useEffect(() => {
        let current = true;
        setQrState('generating');
        setQrDataUrl(null);
        onQrStateChange?.('generating');

        QRCode.toDataURL(setupUrl, {
            errorCorrectionLevel: 'M',
            margin: 2,
            width: 640,
            color: { dark: '#000000', light: '#ffffff' },
        }).then((value) => {
            if (!current) return;
            setQrDataUrl(value);
            setQrState('ready');
            onQrStateChange?.('ready');
        }).catch(() => {
            if (!current) return;
            setQrState('error');
            onQrStateChange?.('error');
        });

        return () => { current = false; };
    }, [setupUrl, onQrStateChange]);

    const cardProps = { eventName, staffName, roleLabel, expiresAt, qrDataUrl };

    return <>
        <div className="staff-setup-handoff-preview">
            <div className="staff-setup-print-sheet">
                {qrState === 'ready'
                    ? <SetupCard {...cardProps} variant="preview"/>
                    : <div aria-live="polite" className="staff-setup-qr-status">{qrState === 'error' ? 'QR generation failed. Print a new card or copy the private setup link.' : 'Generating QR…'}</div>}
            </div>
        </div>
        <PrintPortal>{qrState === 'ready' ? <div className="staff-setup-print-sheet"><SetupCard {...cardProps} variant="print"/></div> : null}</PrintPortal>
    </>;
}
