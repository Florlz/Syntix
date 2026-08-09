# Public Scoreboard

**Design status:** Baseline
**Implementation status:** See [implementation status](../implementation-status.md).
**Product:** SYNTIX

## Sources and Authority

This specification refines public viewing under the [product and domain contract](../cspc-siklab-plan.md). The product contract controls official status, approval, privacy, realtime, PWA safety, and anonymous access.

- Confirmed product decisions: Public is anonymous and read-only, not a stored event role; live scores may be public only as `Unofficial`; approved Official Contest Outcomes affect only contest or internal Division state, while separately approved final Division placements create championship points.
- Proposal source: `docs/Approved-2025-Intramurals-Proposal.pdf` supplies event names, schedules, formats, and approved 2025 result rules but does not authorize publication of private roster data.
- Supporting intent: `docs/MANUSCRIPT FINAL 123 PDF COPY.pdf` and `docs/SYNTIX.docx` call for a live student-facing leaderboard and QR access. The product contract narrows this to sanitized public data with explicit status and freshness.
- Realtime target: Laravel Reverb with Echo is the approved target, but it is not installed. Public HTTP snapshots and refresh remain complete behavior and must not depend on Reverb availability.
- Privacy authority: participant names are hidden by default. Publication after approval requires an approved event setting. Pageants remain outside initial product scope.

## Problem

Students and spectators need timely event information without accounts, but a live display can easily blur unofficial performance with official results, leak participant data, go stale without warning, or expose authenticated responses through unsafe PWA caching.

## Goals

- Publish anonymous, read-only schedules, brackets, live performance, Official Contest Outcomes, Division standings, and championship standings through sanitized DTOs.
- Distinguish live unofficial values from approved official results at every level.
- Define a publication matrix by lifecycle and data type rather than broadcasting internal models.
- Deliver realtime updates with normal HTTP retrieval as a complete fallback.
- Make stale, disconnected, empty, and error states explicit.
- Support public-safe PWA caching, QR deep links, mobile use, and large-display accessibility.

## Current Public Landing Surface

The root route (`/`) is the public broadcast entry point. `LandingController` selects the latest live Event and serializes only the public fields needed by the Inertia landing page.

- The featured board shows the most recently updated live Contest for the latest live Event.
- Additional live Contests appear in a concurrent-match lane without exposing Entry members or private roster data.
- The Competition directory exposes published bracket availability and links to the existing public scoreboard and bracket routes.
- A no-live-Event response is truthful and contains no fabricated scores, standings, or event context.
- Anonymous visitors receive public DTOs only. Authenticated staff may see a single header action for `Open Dashboard`; public flash and validation data remain absent.
- The page uses the official CSPC logo and facilities image URLs currently approved for the broadcast surface. Remote asset failure must leave the page usable.
- The footer contains public resources and event-board navigation, not a second staff-login action.

The current implementation is in `app/Http/Controllers/PublicArea/LandingController.php` and `resources/js/Pages/Welcome.jsx`; privacy and selection behavior are covered by `tests/Feature/Public/PublicLandingTest.php`.

## Non-Goals

- Public accounts, comments, reactions, registration, protests, or score mutations.
- Publishing Judge drafts, private scorecards, internal correction reasons, student numbers, contacts, eligibility notes, or private rosters.
- Treating a live, completed, submitted, or rejected result as official.
- Caching authenticated pages or private endpoints for public offline use.
- Medal tally, pageant content, or video streaming.

## Users and Authorization

- Any anonymous visitor may read only event data explicitly enabled by the event, Competition, and Division publication configuration.
- Anonymous requests create no `event_user_roles` or scoring assignments.
- Admin controls publication configuration before scoring and separately approves Official Contest Outcomes and final Division placements through the private workflow. Publication settings cannot bypass either required approval.
- Public routes, DTOs, broadcasts, QR targets, caches, and generated metadata must contain only public-safe fields.
- Rate limits and output escaping apply without requiring authentication.

## Workflow

1. Visitor opens an event URL or scans a QR code for an event, Competition family, Division, schedule, bracket, contest, outcome, placement, or standings view.
2. The HTTP response supplies a sanitized snapshot, publication state, official or unofficial label, authoritative update timestamp, and realtime connection information.
3. Once the Reverb target is installed and configured, realtime messages update only the corresponding sanitized public DTO after the originating transaction commits.
4. Live performance remains visibly `Unofficial`. Completed or submitted internal data does not become official by elapsed time or public display.
5. Approval of an Official Contest Outcome may publish that current outcome or advancement if enabled, but creates no championship points. Separate approval of the current final Division placement may publish that placement and update championship standings if enabled.
6. If realtime fails, the page retains the last snapshot, marks disconnection or staleness, and retrieves current data over HTTP on user retry or safe polling.
7. Public-safe cached content may support a readable shell and last published snapshot, always with freshness and offline labels.

## Domain/Data Requirements

### Publication Matrix

| Data | Draft | Live | Completed | Submitted | Rejected | Approved current | Corrected or voided |
|---|---|---|---|---|---|---|---|
| Event identity, published delegations, schedule, and venue | Public only when its independent publication setting is enabled; scoring state does not publish it implicitly | Same, with public status | Same | Same | Same | Same | Historical public state retained only as configured |
| Bracket topology and contest state | Draft topology private; explicitly published bracket structure may be public | Published topology plus sanitized contest performance labeled `Unofficial` | Completed outcome private by default | Submitted outcome private | Rejected outcome and reason private | Current Official Contest Outcome and approved advancement public only when bracket/result publication is enabled | Current corrected approved topology/outcome public when enabled; internal reason private; void exposes only configured safe status |
| Contest performance or judged score | Private | Sanitized value may be public when enabled and is always `Unofficial` | Private by default | Private | Private | Current approved Official Contest Outcome public only when enabled | Current corrected approved outcome public when enabled; void exposes only configured safe status |
| Division provisional ranking or final placement | Private | Provisional ranking may be public when enabled and is always `Unofficial` | Private by default | Private | Private | Current approved final Division placement public only when enabled | Current corrected approved placement public when enabled; void removes the official effect and exposes only configured safe status |
| Championship standings | No unpublished or draft point effect | Effective total at the committed cutoff with `Official as of` timestamp | Same; completed private work contributes nothing | Same; submitted private work contributes nothing | Same; rejected work contributes nothing | Same, including newly committed final-placement awards | Recomputed at a new committed cutoff from signed awards, reversals, and replacements |
| Participant names | Private | Private | Private | Private | Private | Hidden by default; public only under approved name visibility | Follow current approved visibility and retention policy |
| Judge values and private review data | Private | Private | Private | Private | Private | Private unless a separate approved sanitized report policy permits release | Private |

- Public DTOs must be purpose-built and allow-listed. They must not serialize Eloquent models, private relations, policy metadata, internal IDs where a public opaque identifier suffices, or internal reason text.
- Every snapshot and realtime event must identify event, public resource identifier, lifecycle or publication status, officiality label, authoritative `updated_at`, and data version or ETag.
- Public schedules include published Competition family, score-bearing Division, venue, start time, and public status.
- Public brackets include published nodes, delegation or approved entry labels, BYEs, approved advancement, and public contest state.
- Public result DTOs distinguish current approved Official Contest Outcomes from separately approved final Division placements.
- At a committed cutoff, each delegation's effective championship total is `SUM(amount)` over every committed ledger entry for that delegation at or before the cutoff. Amounts are signed and include awards, reversals, and replacements; no ledger-status filter applies.
- Participant display names are a separate optional field controlled by approved event publication settings; delegation labels remain the default public identity.
- QR codes encode canonical HTTPS deep links, not private tokens, session data, mutable API payloads, or internal-only identifiers.
- Public caches may include the app shell, static assets, manifest, icons, and safe GET snapshots. Cached responses retain timestamp, version, and offline status.

## Invariants

- Public access is anonymous, read-only, and receives no event role.
- Every pre-approval score, performance, or provisional rank shown publicly is labeled `Unofficial`.
- Official Division standings use current approved placements. Championship standings use the signed committed-ledger `SUM` invariant at their stated cutoff.
- Participant names are not public before approval and remain hidden after approval unless the event has explicit approved name visibility.
- Private participant, Judge, assignment, audit, protest, and internal correction data never appears in public DTOs, channels, logs intended for clients, or caches.
- Broadcasts occur only after successful database commit.
- HTTP retrieval remains functional before Reverb is installed and whenever Reverb is unavailable.
- Cached public data never presents itself as current without a freshness timestamp and stale or offline state.
- Public URLs and QR codes cannot grant mutation or privileged access.

## Edge and Failure Cases

- No event or no published content: show a truthful empty state and event context, not zero standings or mock rows.
- Realtime disconnected: keep readable data, show `Disconnected`, last update, and HTTP retry.
- Snapshot older than the configured freshness threshold: show `Stale` even if the network appears connected.
- Out-of-order broadcast: ignore an older data version and fetch the current HTTP snapshot if versions skip.
- Public endpoint error: preserve the last safe snapshot where available and show retry without exposing exception details.
- Live result rejected: remove any misleading final-looking provisional state and continue to label the last valid public live snapshot unofficial.
- Official result corrected: display the current approved revision, update standings after commit, and show a public-safe correction marker without internal reason.
- Official result voided: remove its official point effect from standings and show a public-safe void or no-result status.
- Event archived: use historical approved data and frozen public settings; no live connection is required.
- QR target unpublished or removed: route to a safe event page or not-found state without revealing private existence.
- Shared display reconnects: avoid rapid animation, focus changes, or unbounded stale content.

## Functional Requirements

| ID | Requirement |
|---|---|
| PUB-FR-001 | Visitors can open public event pages without an account or stored event role. |
| PUB-FR-002 | Public endpoints and broadcasts use allow-listed sanitized DTOs. |
| PUB-FR-003 | Visitors can view published schedules, venues, brackets, contest states, Official Contest Outcomes, Division standings, and championship standings. |
| PUB-FR-004 | Every published pre-approval performance or provisional ranking is labeled `Unofficial`. |
| PUB-FR-005 | Official contest and Division views derive from their current approved revisions; each championship total is the sum of all committed signed award, reversal, and replacement ledger amounts at the stated cutoff. |
| PUB-FR-006 | Participant names remain hidden by default and cannot appear before the applicable current outcome or placement approval. |
| PUB-FR-007 | Approved participant names appear only when the event's approved publication setting permits them. |
| PUB-FR-008 | Once Reverb is installed, realtime updates are delivered after commit and include version and freshness metadata. |
| PUB-FR-009 | Every realtime view has a normal HTTP snapshot and retry fallback. |
| PUB-FR-010 | The UI exposes loading, empty, live, official, stale, disconnected, reconnecting, error, corrected, and voided states. |
| PUB-FR-011 | PWA runtime caching is restricted to the public shell, static assets, and safe read-only public responses. |
| PUB-FR-012 | Cached snapshots display last-updated and offline or stale status and never imply current realtime data. |
| PUB-FR-013 | Admin can generate canonical public QR deep links for published resources. |
| PUB-FR-014 | QR links contain no secret and resolve safely when the target is unavailable or unpublished. |
| PUB-FR-015 | Public views support mobile, desktop, large display, keyboard, zoom, reduced motion, and WCAG AA contrast. |
| PUB-FR-016 | Changing scores and times use tabular numerals, stable layout, textual status, and non-disruptive announcements. |

## Acceptance Criteria

- An anonymous visitor can read a published event without a database role and cannot call a mutation endpoint.
- A live score is visible with `Unofficial` and an authoritative last-updated timestamp.
- Completing, submitting, or rejecting a private result does not publish it by default or make it official publicly.
- Approving a contest outcome may publish only that outcome when enabled and does not change championship standings; separate final Division placement approval updates the public placement and signed-ledger standings only after commit and when publication is enabled.
- Public payload inspection finds no student number, contact detail, eligibility note, Judge draft, peer score, assignment, internal correction reason, or session data.
- Participant names are absent before approval and remain absent after approval when name visibility is off.
- Before Reverb installation or after a Reverb disconnect, the page remains usable and provides HTTP refresh with stale or disconnected labeling.
- A cached public page clearly states it is offline or stale and shows the snapshot time.
- A correction or void updates the current official view and ledger-derived standings without deleting historical server records.
- An event, Competition, Division, or contest QR code opens the canonical public page on mobile without authentication or privileged token.
- At 200 percent zoom and common mobile widths, status, score, schedule, and navigation remain operable without loss of content.
- A large display does not rely on color alone and does not animate continuously or steal focus on updates.

## Testing

- Feature tests for anonymous access, no role creation, read-only methods, publication configuration, officiality transitions, name visibility, corrections, and voids.
- Serialization and security tests that allow-list every public DTO field and scan broadcasts and caches for private data.
- Reverb-target tests, once installed, for after-commit dispatch, out-of-order versions, disconnect, reconnect, and HTTP fallback; pre-install tests prove no false realtime state.
- PWA tests for safe public caching, stale timestamps, offline shell, service-worker update, and exclusion of authenticated responses.
- Browser tests at mobile, desktop, large-display, 200 percent zoom, keyboard-only, high contrast, and reduced-motion settings.
- QR tests for canonical HTTPS links, unpublished targets, no embedded secret, and deep-link routing.
- Load tests for popular event and standings reads plus broadcast fan-out without weakening HTTP fallback.
- User acceptance simulation with spectators and event staff comparing live unofficial and approved official transitions.

## Decision Register

Public publication blockers are centralized in [`../open-decisions.md`](../open-decisions.md), items 54-59. This specification remains the technical contract for anonymous DTOs, officiality labels, freshness, caching, QR links, and the public landing surface.
