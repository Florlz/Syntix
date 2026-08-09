# Admin Frontend

**Design status:** Baseline
**Implementation status:** See [implementation status](../implementation-status.md).
**Product:** SYNTIX

## Sources and Authority

This specification is the focused Admin frontend contract under the [product and domain contract](../cspc-siklab-plan.md). The product contract remains authoritative for role policies, event lifecycle, result lifecycle, private data, approval transactions, realtime behavior, PWA safety, and implementation stack.

- Approved direction: the Admin interface is a CSPC institutional command center, not a generic analytics dashboard.
- Proposal context: `docs/Approved-2025-Intramurals-Proposal.pdf` establishes the 2025 institutional event context and operations, but it is not a source for interface assets or permission shortcuts.
- Repository authority: React 18 with Inertia 2, Tailwind CSS 3, Vite, Laravel sessions, and existing routes and patterns remain in use.
- Realtime target: Laravel Reverb with Echo is the approved target architecture, but it is not installed. No first-slice UI may claim an active realtime connection; HTTP remains the complete fallback before and after installation.
- Visual source: use the existing SYNTIX icon in `public/icons/`; do not extract a low-quality CSPC seal from source PDFs.
- Privacy source: the Data Privacy Act intent in local documents and the product contract requires minimal private data and safe authenticated caching.
- This slice does not add pageant features or imply backend capabilities that do not exist.

## Problem

The current authenticated foundation does not communicate CSPC identity or the operational state of SIKLAB. A generic dashboard with fabricated metrics, placeholder links, or premature controls would mislead Admins and hide the lifecycle and approval work that will matter once event data exists.

## Goals

- Establish a formal, recognizable CSPC institutional shell for desktop and mobile.
- Give Admins a truthful empty state before event data and an operational priority model for future active events.
- Make event lifecycle, connection, freshness, approval, blocked, and correction states explicit.
- Keep every displayed value and mutation under backend authority.
- Meet responsive, keyboard, contrast, focus, motion, and status-announcement requirements.
- Deliver a minimal first frontend slice that preserves existing working routes.

## Non-Goals

- Building event creation, scoring, approval, reporting, notification, or live-operation backends in the first visual slice.
- Installing or representing Laravel Reverb as available in the first visual slice.
- Fabricating zero metrics, example competitions, mock scores, or sample approval rows.
- Adding dead controls, `#` links, a frontend state library, or a new icon dependency.
- Replacing Laravel policies or server validation with client-side hiding.
- A generic card-grid analytics dashboard, gradient hero, automatic carousel, or pageant interface.

## Users and Authorization

- The primary user is an authenticated event Admin operating under event-scoped Admin authorization.
- The shell may be shared by authenticated roles later, but Admin navigation and data must be delivered only when server policies authorize it.
- Navigation visibility is not authorization. Laravel policies and controllers remain responsible for every private read and mutation.
- Assigned Judges and Tabulators must not gain Admin data or actions by visiting an Admin URL directly.
- Connection and status displays must not expose private event details to unauthorized or logged-out users.

## Workflow

### First Frontend Slice

1. Admin signs in and reaches the existing authenticated dashboard route.
2. The responsive CSPC shell presents product identity, page title, and only the existing Dashboard, Profile edit, password change, and Logout interactions.
3. Before any privileged use, the self-service hard-delete account control and route exposure are removed; privileged accounts use a future Admin-controlled disablement workflow instead.
4. With no event backend data, the page shows `Admin overview`, `No active SIKLAB event`, the lifecycle rail at Preparation, and a setup-oriented checklist.
5. The page explains that live operations and approvals appear only after an event is configured.
6. No creation action appears until a real route and authorized backend behavior exist.

### Future Active Event

1. The backend supplies event context, lifecycle, connection freshness, work queues, and authorized action URLs.
2. The dashboard prioritizes Official Contest Outcome submissions and final Division placements awaiting their separate approvals, live unofficial contests, blocked or correction-required submissions, today's schedule, and configuration warnings, in that order.
3. Admin selects `Review outcome` or `Review placement` and moves to the corresponding dedicated review route.
4. Outcome approval cannot imply final placement approval or create championship points. Approval, rejection, correction, and void actions occur only within deliberate server-authoritative workflows, not one-click dashboard actions.

## Domain/Data Requirements

### Visual Tokens

| Role | Value | Use |
|---|---|---|
| CSPC Navy | `#0B2E4F` | Sidebar, institutional surfaces, strong headings |
| Institutional Blue | `#175D8D` | Links, selected navigation, primary interactive states |
| CSPC Gold | `#D5A21F` | Current lifecycle marker, focus accents, high-value actions |
| Canvas | `#F4F6F8` | Main workspace background |
| Surface | `#FFFFFF` | Panels, forms, menus, tables |
| Ink | `#17212B` | Primary text and high-contrast data |

- Use Archivo SemiCondensed for page and major section headings, Source Sans 3 for body and controls, and IBM Plex Mono for changing numeric data. If three families create measurable cost, omit IBM Plex Mono and use Source Sans 3 with tabular numerals.
- Prefer self-hosted font files with documented licenses and no third-party font request. If fonts are not yet locally available, use a privacy-safe system fallback rather than adding an unapproved external dependency.
- Use the existing SYNTIX icon as the initial product mark and provide an accessible product name.
- The event lifecycle is `Preparation -> Configuration -> Live Operations -> Closed -> Archived`. The current phase uses a gold marker plus text; meaning cannot depend on color.
- The frontend view model must provide authoritative event context, lifecycle phase, status counts, timestamps, connection state, permissions, and route URLs. The client must not derive approval authority or official totals.
- Shareable filters and event context belong in the URL where useful.

### Shell and Responsive Behavior

- Desktop uses an approximately 256px fixed Navy sidebar, a white utility bar, and a pale canvas workspace.
- Future navigation groups are `Operate`, `Configure`, and `Govern`, but until those modules exist only Dashboard, Profile edit, password change, and Logout render as interactions.
- Tablet uses an accessible collapsed rail or drawer. Mobile uses a compact top bar and navigation drawer.
- The lifecycle rail becomes a compact vertical progress list on narrow screens.
- Wide operational tables become labeled stacked rows on mobile instead of forcing page-level horizontal scrolling.
- Interactive targets are at least 44 by 44 CSS pixels.

### Required States

| State | Required presentation |
|---|---|
| Loading | Structural skeleton matching final layout without avoidable shift. |
| Empty | Honest explanation and legitimate next step without fake data. |
| Error | Inline explanation, recovery guidance, and safe retry where available. |
| Disconnected | Persistent status with last successful authoritative update. |
| Stale | Visible stale label and authoritative timestamp. |
| Reconnecting | Non-blocking status announcement. |
| Correction | Original and revised result are distinguishable with review reason. |
| Unauthorized | Clear access message without private event details. |

## Invariants

- Backend responses are authoritative for identity, permissions, lifecycle, official status, totals, and action availability.
- The UI never fabricates operational data or enables a control without a real authorized route.
- Pre-approval values are labeled `Unofficial` wherever displayed.
- Gold and semantic colors are never the only status signal.
- Live updates do not steal focus, dismiss forms, or reorder a row under active review without user control.
- Approval, rejection, correction, and voiding require dedicated review and confirmation behavior.
- Authenticated Inertia responses and private data are not broadly cached by the PWA.
- Existing Dashboard, Profile edit, password change, and Logout behavior remains functional in the first slice; privileged self-service hard deletion does not.

## Edge and Failure Cases

- No event exists: show the approved empty state, not blank panels or zero metrics.
- Event route is not implemented: omit its navigation and controls entirely.
- Realtime is unavailable or the future Reverb target disconnects: retain the last authoritative data, mark it stale or disconnected, and offer HTTP refresh where safe. Reverb must not be shown as connected before it is installed and configured.
- Authorization changes during a session: the next server request decides access; show Unauthorized without retaining private details.
- Approval item changes while open: present a revision conflict and require reload or explicit review, never silently submit against stale data.
- Long competition or delegation names: wrap or truncate with accessible full text without breaking actions.
- Mobile table density: use labeled rows and preserve the approval queue before secondary information.
- Font load fails: readable fallback fonts preserve hierarchy and layout.
- Reduced motion requested: disable nonessential transitions and avoid motion-only status cues.
- Drawer or dialog closes: restore focus to its trigger; Escape closes it; focus cannot escape while open.

## Functional Requirements

| ID | Requirement |
|---|---|
| ADM-FR-001 | The authenticated shell uses the approved CSPC colors, hierarchy, and existing SYNTIX icon. |
| ADM-FR-002 | Fonts use the approved hierarchy with self-hosted or privacy-safe fallback delivery. |
| ADM-FR-003 | Desktop, tablet, and mobile shells preserve navigation, page context, connection state, and account access. |
| ADM-FR-004 | Navigation renders only real routes authorized by the backend. |
| ADM-FR-005 | The lifecycle rail presents Preparation, Configuration, Live Operations, Closed, and Archived with text labels. |
| ADM-FR-006 | With no event data, the dashboard shows the approved setup-oriented empty state and no fake metrics or unavailable controls. |
| ADM-FR-007 | The future active dashboard prioritizes separate contest-outcome and final Division placement approval work, live contests, blocked submissions, schedule, and configuration warnings in that order. |
| ADM-FR-008 | The approval queue identifies outcome versus placement and shows Competition family, score-bearing Division, contest or entry, submitter, age, status, warning, and the matching review action. |
| ADM-FR-009 | Dashboard approval work opens a dedicated review route and never performs destructive one-click actions. |
| ADM-FR-010 | The interface implements loading, empty, error, disconnected, stale, reconnecting, correction, and unauthorized states. |
| ADM-FR-011 | Future Reverb-delivered changes retain HTTP fallback and do not disrupt focus or active review; the UI does not claim Reverb availability before installation. |
| ADM-FR-012 | Mobile tables become labeled stacked rows and controls retain at least 44 by 44 pixel targets. |
| ADM-FR-013 | The shell provides semantic landmarks, skip link, one page `h1`, visible focus, accessible names, and keyboard operation. |
| ADM-FR-014 | Status and changing numeric data use text labels, stable widths where relevant, and tabular numerals. |
| ADM-FR-015 | Motion is restrained to meaningful transitions and respects `prefers-reduced-motion`. |
| ADM-FR-016 | The first slice changes only the shell, dashboard empty state, product mark, visual tokens, fonts, minimal global styles, coherent theme colors, and privileged hard-delete removal while preserving Dashboard, Profile edit, password change, and Logout. |

## Acceptance Criteria

- The first dashboard contains `Admin overview`, `No active SIKLAB event`, the lifecycle rail at Preparation, and the preparation checklist described by the product contract.
- It contains no fabricated metric, score, event count, competition row, `#` link, or creation control without a backend route.
- Dashboard, Profile edit, password change, Logout, account-menu behavior, and existing authenticated feature tests continue to work.
- No privileged page, menu, form, or route exposed to the frontend offers self-service hard deletion before privileged use begins.
- Before Reverb is installed, the dashboard uses HTTP state and never displays a false connected-realtime state.
- Desktop shows the institutional sidebar and workspace; mobile shows an accessible top bar, drawer, vertical lifecycle list, and no page-level table overflow.
- Keyboard users can reach a skip link, all navigation and account controls, and visible focus in a logical order.
- Drawers and dialogs trap and restore focus and close with Escape.
- Empty, error, disconnected, stale, reconnecting, correction, and unauthorized presentations communicate state with text, not color alone.
- Reduced-motion preference removes nonessential transitions.
- No authenticated private response is available through public runtime cache behavior.
- A future approval row identifies whether it is an outcome or final Division placement and can only navigate to the matching review; it cannot approve or void from the dashboard.

## Testing

- Visual and responsive checks at mobile, tablet, desktop, and large desktop widths.
- Keyboard tests for skip link, navigation, account menu, drawer, focus trapping, focus restoration, Escape, and visible focus.
- Automated accessibility checks plus manual heading, landmark, accessible-name, contrast, live-region, and zoom review.
- State tests for loading, empty, error, disconnected, stale, reconnecting, correction, and unauthorized views.
- Route tests proving no dead link and no unauthorized Admin destination is rendered.
- PWA tests proving authenticated responses and private data are not public-cache accessible.
- Frontend production build and existing authenticated dashboard feature tests through Docker where backend verification is required.
- Future active-dashboard tests for priority order, stable Reverb-targeted live updates, HTTP fallback, truthful pre-install connection state, and dedicated approval navigation.

## Decision Register

Admin-frontend publication and institutional presentation blockers are centralized in [`../open-decisions.md`](../open-decisions.md), items 54-64 where applicable. This specification remains the technical contract for the shell, states, accessibility, and server-authoritative navigation.
