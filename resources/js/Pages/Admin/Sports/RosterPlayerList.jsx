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
    return <section className={`overflow-hidden bg-white ${emphasis ? 'rounded-xl border-2 border-[#0B536D] shadow-[0_10px_30px_rgba(11,83,109,0.08)]' : 'border border-[#CFD6D3]'}`} aria-labelledby={`${title.toLowerCase().replaceAll(' ', '-')}-title`}>
        <div className={`flex flex-wrap items-start justify-between gap-4 px-5 py-4 ${members.length ? 'border-b border-[#CFD6D3]' : ''}`}>
            <div>
                <div className="flex flex-wrap items-baseline gap-2"><h3 id={`${title.toLowerCase().replaceAll(' ', '-')}-title`} className="font-serif text-xl font-bold">{title}</h3>{countLabel ? <span className="text-sm font-semibold text-[#68767E]">{countLabel}</span> : null}</div>
                <p className="mt-1 text-sm text-[#68767E]">{description}</p>
            </div>
            {action}
        </div>
        <div className="divide-y divide-[#E6EAE8]">
            {members.map((participant) => {
                const role = participant.membership?.role;
                const id = String(participant.id);
                return <div key={id} className={row}>
                    {selectable ? <input
                        aria-label={`Select ${participant.display_name}`}
                        type="checkbox"
                        value={id}
                        checked={selectedIds.has(id)}
                        onChange={() => onToggle(participant.id)}
                        disabled={disabled}
                        className="size-4 rounded border-[#B8C3C0] text-[#0B536D] focus:ring-[#D5A21F]"
                    /> : null}
                    <span className="min-w-0 flex-1">
                        <strong className="block text-sm text-[#17212B]">{participant.display_name}</strong>
                        <span className="mt-1 block text-xs capitalize text-[#68767E]">
                            {roleLabel(role)}{participant.exception ? ` / ${roleLabel(participant.exception.type)}` : ''}
                        </span>
                    </span>
                    {onManage ? <button type="button" onClick={() => onManage(participant)} disabled={disabled} className="text-xs font-bold text-[#0B536D] underline underline-offset-4 disabled:cursor-not-allowed disabled:opacity-50">Manage</button> : null}
                </div>;
            })}
            {members.length === 0 ? <div className="border-t border-[#CFD6D3] px-5 py-6"><p className="text-sm text-[#68767E]">{emptyMessage}</p>{emptyAction ? <div className="mt-4">{emptyAction}</div> : null}</div> : null}
        </div>
    </section>;
}
