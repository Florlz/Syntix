import React from 'react';

const tones = {
    neutral: 'bg-surface-muted text-muted',
    live: 'bg-accent/20 text-foreground',
    ready: 'bg-primary/10 text-primary',
    danger: 'bg-danger-surface text-danger',
};

export default function OperationalStatus({ label, detail = null, tone = 'neutral', className = '' }) {
    return (
        <div className={`min-w-0 ${className}`}>
            <span className={`inline-flex min-h-7 items-center rounded-full px-2.5 py-1 text-xs font-bold ${tones[tone] ?? tones.neutral}`}>
                {label}
            </span>
            {detail ? <p className={`mt-1.5 text-sm leading-5 ${tone === 'danger' ? 'font-semibold text-danger' : 'text-muted'}`}>{detail}</p> : null}
        </div>
    );
}
