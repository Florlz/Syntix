import React from 'react';

export default function LiveProgress({ label, value, max, detail = null, tone = 'primary' }) {
    const safeMax = Math.max(0, Number(max) || 0);
    const safeValue = Math.min(safeMax, Math.max(0, Number(value) || 0));
    const percentage = safeMax > 0 ? (safeValue / safeMax) * 100 : 0;
    const barTone = tone === 'accent' ? 'bg-accent' : tone === 'danger' ? 'bg-danger' : 'bg-primary';

    return (
        <div
            role="progressbar"
            aria-label={label}
            aria-valuemin="0"
            aria-valuemax={safeMax}
            aria-valuenow={safeValue}
            className="min-w-0"
        >
            <div className="h-1.5 overflow-hidden rounded-full bg-border">
                <div className={`h-full ${barTone}`} style={{ width: `${percentage}%` }} />
            </div>
            <p className="mt-2 text-sm font-semibold text-foreground">{detail ?? `${safeValue} of ${safeMax}`}</p>
        </div>
    );
}
