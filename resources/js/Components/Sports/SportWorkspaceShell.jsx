import React from 'react';
import DivisionSwitcher from './DivisionSwitcher';
import SportBreadcrumb from './SportBreadcrumb';
import SportIdentity from './SportIdentity';
import SportWorkflowNav from './SportWorkflowNav';

export default function SportWorkspaceShell({ event, sport, division = null, divisions = [], activeSection = 'overview', notice = null, children }) {
    return <div data-testid="sport-workspace-shell" className="mx-auto flex w-full max-w-[120rem] flex-col gap-4">
        <SportBreadcrumb event={event} sport={sport} division={division} activeSection={activeSection} />
        <SportIdentity sport={sport} division={division} />
        <DivisionSwitcher event={event} sport={sport} divisions={divisions} division={division} activeSection={activeSection} />
        <SportWorkflowNav event={event} sport={sport} division={division} activeSection={activeSection} />
        {notice}
        <div className="min-w-0">{children}</div>
    </div>;
}

