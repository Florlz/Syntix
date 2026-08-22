import React from 'react';

function time(startsAt) {
    if (!startsAt) return 'Unscheduled';

    return new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(startsAt));
}

function date(startsAt) {
    if (!startsAt) return 'Date pending';

    return new Intl.DateTimeFormat(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    }).format(new Date(startsAt));
}

export default function ScheduleTime({ startsAt, align = 'left' }) {
    return (
        <div className={align === 'right' ? 'text-left sm:text-right' : 'text-left'}>
            <p className="font-condensed text-lg font-bold leading-none tabular-nums text-foreground">{time(startsAt)}</p>
            <p className="mt-1 text-xs text-muted">{date(startsAt)}</p>
        </div>
    );
}
