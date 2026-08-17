import React from 'react';

const row = 'flex items-center gap-3 px-5 py-3';

function roleLabel(role) {
    return String(role || '').replaceAll('_', ' ');
}

export default function RosterPlayerList({
    title,
    description,
    members = [],
    selectedIds = new Set(),
    onToggle,
    onManage,
    selectable = false,
    disabled = false,
    emptyMessage = 'No people are in this section yet.',
    countLabel = null,
    action = null,
    emptyAction = null,
    emphasis = false,
}) {
    return <section className={`overflow-hidden bg-surface ${emphasis ? 'rounded-xl border-2 border-primary shadow-xs' : 'border border-border'}`} aria-labelledby={`${title.toLowerCase().replaceAll(' ', '-')}-title`}>
        <div className={`flex flex-wrap items-start justify-between gap-4 px-5 py-4 ${members.length ? 'border-b border-border' : ''}`}>
            <div>
                <div className="flex flex-wrap items-baseline gap-2"><h3 id={`${title.toLowerCase().replaceAll(' ', '-')}-title`} className="font-serif text-xl font-bold text-foreground">{title}</h3>{countLabel ? <span className="text-sm font-semibold text-muted">{countLabel}</span> : null}</div>
                <p className="mt-1 text-sm text-muted">{description}</p>
            </div>
            {action}
        </div>
        <div className="divide-y divide-[#E6EAE8]">
            {members.map((participant) => {
                const role = participant.membership?.role;
                const id = String(participant.id);
                const canOpen = Boolean(onManage) && participant.capabilities?.can_manage !== false;
                const actionLabel = participant.capabilities?.can_edit_membership === true ? 'Manage' : 'View status';
                return <div key={id} className={row}>
                    {selectable ? <input
                        aria-label={`Select ${participant.display_name}`}
                        type="checkbox"
                        value={id}
                        checked={selectedIds.has(id)}
                        onChange={() => onToggle(participant.id)}
                        disabled={disabled}
                        className="size-4 rounded-sm border-border text-primary focus:ring-accent"
                    /> : null}
                    <span className="min-w-0 flex-1">
                        <strong className="block text-sm text-foreground">{participant.display_name}</strong>
                        <span className="mt-1 block text-xs capitalize text-muted">
                            {roleLabel(role)}{participant.exception ? ` / ${roleLabel(participant.exception.type)}` : ''}
                        </span>
                    </span>
                    {canOpen ? <button type="button" onClick={() => onManage(participant)} className="text-xs font-bold text-primary underline underline-offset-4">{actionLabel}</button> : null}
                </div>;
            })}
            {members.length === 0 ? <div className="border-t border-border px-5 py-6"><p className="text-sm text-muted">{emptyMessage}</p>{emptyAction ? <div className="mt-4">{emptyAction}</div> : null}</div> : null}
        </div>
    </section>;
}
