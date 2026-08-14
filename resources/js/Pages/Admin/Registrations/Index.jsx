import ParticipantDirectory from './ParticipantDirectory';

export default function RegistrationIndex({
    event,
    filters = {},
    delegations = [],
    competitions = [],
    directory_summary: directorySummary = { departments: [], totals: {} },
    selection = {},
}) {
    return <ParticipantDirectory
        event={event}
        departments={delegations}
        competitions={competitions}
        directory_summary={directorySummary}
        selection={selection}
        initialView={filters.view || 'players'}
        filters={filters}
    />;
}
