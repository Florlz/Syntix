const workflowOrder = ['overview', 'teams', 'bracket', 'schedule', 'results'];

export const sportWorkflow = {
    overview: 'Overview',
    teams: 'Teams & Rosters',
    bracket: 'Bracket',
    schedule: 'Schedule',
    results: 'Results',
};

export function normalizeWorkflow(section) {
    return workflowOrder.includes(section) ? section : 'overview';
}

function appendQuery(url, values) {
    const query = new URLSearchParams();
    Object.entries(values).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') query.set(key, String(value));
    });
    const serialized = query.toString();
    return serialized ? `${url}${url.includes('?') ? '&' : '?'}${serialized}` : url;
}

export function sportWorkspaceUrl(eventId, sportId, options = {}) {
    const { section = 'overview', division = null, department = null } = options;
    const workflow = normalizeWorkflow(section);

    if (workflow === 'bracket' && division) {
        return route('admin.sports.tournament', [eventId, division]);
    }

    if (workflow === 'schedule') {
        return appendQuery(route('admin.sports.schedules', [eventId]), { competition: sportId, division });
    }

    if (workflow === 'results') {
        return appendQuery(route('admin.approvals.index', [eventId]), { competition: sportId, division });
    }

    return appendQuery(route('admin.sports.show', [eventId, sportId]), {
        tab: workflow === 'teams' ? 'rosters' : null,
        division,
        department: workflow === 'teams' ? department : null,
    });
}

export function sportWorkspaceDivisionUrl(eventId, sportId, divisionId, section = 'overview') {
    return sportWorkspaceUrl(eventId, sportId, { section, division: divisionId });
}
