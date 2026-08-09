# SYNTIX Product Requirements Document

**Status:** Approved — consolidated product, domain, and architecture contract
**Product:** SYNTIX / CSPC SIKLAB intramurals operations platform
**Last consolidated:** August 9, 2026

This is the single product, domain, architecture, and specification contract
for SYNTIX. It incorporates the durable content formerly split across ADRs,
focused specifications, the domain glossary, and the institutional decision
register. The accompanying system implementation plan is the only other
Markdown documentation authority for implementation and verification.

## Source and Authority

Use the following order when sources disagree:

1. Product-owner decisions recorded in the project conversation.
2. [`docs/Approved-2025-Intramurals-Proposal.pdf`](../Approved-2025-Intramurals-Proposal.pdf)
   for approved 2025 competition rules and point schedules.
3. [`docs/MANUSCRIPT FINAL 123 PDF COPY.pdf`](../MANUSCRIPT%20FINAL%20123%20PDF%20COPY.pdf)
   for objectives, reporting, evaluation, and offline intent.
4. [`docs/SYNTIX.docx`](../SYNTIX.docx) for intended role workflows.
5. The repository implementation for established Laravel, schema, policy, and
   frontend conventions.

The single implementation and verification record is
[`docs/plans/2026-08-09-syntix-system.md`](../plans/2026-08-09-syntix-system.md).
An unresolved decision in this PRD blocks only its affected activation,
approval, registration, correction, report, or publication path.

## Problem Statement

CSPC SIKLAB intramurals currently require fast, accurate coordination across
Admins, Judges, Tabulators, tournament officials, and spectators. Paper-based
scorecards, manual bracket updates, and hand-calculated delegation totals make
it difficult to:

- keep live performance separate from approved official results;
- apply different scoring models across team, individual, judged, timed,
  measured, aggregate, and tournament competitions;
- preserve the exact rules and revisions used for an approval;
- recover safely from duplicate submissions, stale screens, corrections, or
  intermittent connectivity;
- expose useful public information without leaking participant or staff data;
  and
- reproduce official reports and championship standings after later changes.

SYNTIX must improve speed and transparency while preserving the authority of
Judges, referees, tournament managers, Tabulators, and the adjudication
committee. It is an operations and evidence system, not an automated
replacement for institutional decisions.

## Users and Roles

### Platform administration and event roles

- **Global Admin:** the installation has exactly one active Global Admin. This
  account administers every Event without an Event membership and is the only
  account allowed to create Events, configure the proposal programme, provision
  staff, grant or revoke roles and assignments, generate and publish brackets,
  review submissions, approve outcomes and placements, manage corrections, and
  access private reports.
- **Judge:** creates and submits private criterion-based scorecards only within
  an active Event Role and an active exact matching assignment.
- **Tabulator:** records objective contest performance only within an active
  Event Role and an active exact matching assignment.
- **Public:** anonymous, read-only access. It is not stored as an Event Role.

`event_creator` and Event Admin are not active product roles. Legacy grants are
revoked during migration and cannot authorize new requests. Global Admin
authority permits administration and review across the platform but does not
permit the account to submit a Judge scorecard or Tabulator result without the
same explicit scorer role and assignment required of every scorer.

Referees, tournament managers, and committees retain real-world authority. An
authorized Global Admin records their rulings in SYNTIX; the system does not invent
their decisions.

## Goals

1. Support multiple independent SIKLAB Event editions without hard-coding the
   2025 dates, delegations, rules, or point schedules.
2. Enforce one platform-wide Global Admin together with event-scoped Judge and
   Tabulator roles, exact scoring assignments, prompt revocation, and auditable
   administration.
3. Support configurable Competition families, score-bearing Divisions,
   Disciplines, Contests, Entries, rosters, schedules, criteria, formats, and
   point rules.
4. Keep contest performance, Division Sub-Points, and delegation Championship
   Points as separate scoring layers.
5. Make approvals, corrections, voids, brackets, and ledger effects
   transactional, idempotent, and historically reproducible.
6. Give public viewers useful schedules, brackets, live unofficial values, and
   approved standings through sanitized read-only views.
7. Provide mobile-friendly Judge and Tabulator workflows and a truthful Admin
   command center.
8. Add offline synchronization, realtime delivery, reports, and archive
   capabilities without creating a second source of truth.
9. Automatically create auditable randomized draws, tournament structures,
   match standings, final placements, and department championship totals from
   eligible entries and approved results.

## Non-Goals

The initial product does not include:

- student self-service registration;
- medical certificates, parent consent, or eligibility-document uploads;
- ticketing, budgeting, procurement, honoraria, or liquidation management;
- video streaming, automated judging, or referee-decision automation;
- protest-fee collection or a fully automated protest adjudication workflow;
- pageant scoring, including Mr. and Ms. Intramurals;
- public accounts, comments, reactions, or score mutations;
- medal tally reporting until CSPC approves an independent medal model; or
- an authoritative physical game clock or hardware scoreboard in the first
  Basketball slice.

Manual Global Admin roster encoding remains in scope even though public registration
does not.

## Context and Assumptions

### Authoritative stack

- Laravel 13 and PHP 8.3+ provide the server and application behavior.
- PostgreSQL is the application authority for identity, authorization,
  configuration, scoring, approvals, brackets, standings, and ledger totals.
- React 18 with Inertia 2, Tailwind CSS 3, and Vite provide the frontend.
- Laravel session authentication remains the initial authentication model.
- Laravel Reverb/Echo is a future delivery transport, not a current authority
  or current dependency.

Browser state, IndexedDB, PWA caching, queues, and realtime transports are
delivery mechanisms. None may become an alternate source of truth.

### Domain hierarchy

```text
Organizational Unit (department or campus)
  -> Event-scoped Delegation
      -> Division Entry (team, athlete, pair, or relay)

Event
  -> Competition
      -> Division
          -> Entry
          -> Discipline
          -> Tournament
              -> Contest
```

- **Event:** one SIKLAB edition.
- **Organizational Unit:** reusable college, campus, or participating
  institution.
- **Delegation:** an Organizational Unit's representation in one Event.
- **Competition:** an activity family such as Basketball, Athletics, or Pop
  Solo.
- **Division:** the score-bearing category, including an explicit Open or
  default Division when a Competition has no named category.
- **Discipline:** a Division sub-event such as a race or weight class.
- **Contest:** one match, heat, race, round, or judged performance.
- **Entry:** a team, individual, pair, relay, or other delegation
  representative.
- **Department Team:** the Division Entry representing one Event Delegation in
  a team Division. It is not an unrelated free-standing team identity.
- **Draw:** the immutable randomized ordering of eligible Division Entries used
  by a versioned bracket-generation algorithm.

### Canonical terminology

| Term | Canonical meaning |
|---|---|
| Participant | A person registered privately in one Event and one Event Delegation who may join one or more Entries subject to eligibility and roster rules. |
| Roster Member | The event participant's membership in one Entry as a student-athlete, reserve, student coach, or faculty coach. Membership is deactivated or superseded rather than hard-deleted after use. |
| Eligibility Record | The Event- and Entry-scoped decision that a Participant is pending, eligible, ineligible, withdrawn, or disqualified, with checking actor, time, and reason where applicable. |
| Tournament | A Division's format instance connecting eligible Entries, frozen rules, Draw, bracket or round-robin structure, Contests, and final placement derivation. |
| Draw Order | The private, cryptographically randomized, saved order of eligible Entry identifiers used as deterministic generation input. |
| BYE | An auditable automatic advancement from an empty bracket slot; it is not a Contest, win, loss, score, forfeit, or point effect. |
| Walkover | An officially confirmed advancement because an expected opponent could not contest before play; it requires an authorized ruling. |
| Forfeit | An officially confirmed Contest loss imposed for non-appearance or a rule violation; it is distinct from a BYE. |
| Live Score | Mutable Contest performance that remains explicitly unofficial. |
| Scorecard | One Judge's private criterion-level evaluation of one Entry under a frozen rule version. |
| Result Submission | A locked, revision-specific scorer snapshot awaiting Global Admin review. |
| Official Contest Outcome | The approved outcome of one Contest; it may advance a bracket or change internal standings but never directly awards Championship Points. |
| Division Placement | The final ordered result for one score-bearing Division, derived from official evidence and separately approved by the Global Admin. |
| Point Rule Version | The immutable placement-to-Championship-Points mapping and eligibility conditions bound to a Division's official records. |
| Sub-Points | Internal aggregate values from Discipline placements; they rank a Division and never enter the championship ledger directly. |
| Championship Points | Event-level Delegation points created only by approval of an eligible final Division Placement. |
| Score Ledger Entry | An append-only signed award, reversal, or replacement linked to its placement, official revision, and frozen Point Rule Version. |
| Standings | A derived ordered view from approved outcomes, placements, or the signed ledger sum; it is never a manually edited total. |
| Scoring Command | A versioned, idempotent mutation envelope scoped to one authenticated actor and Event. |
| Command Receipt | The durable terminal server disposition for a Scoring Command, returned for an exact authorized retry without reapplying the mutation. |
| Revision Conflict | A rejected mutation whose base revision is stale; it requires reload and explicit human resolution rather than automatic merge. |
| Correction | An authorized new revision that preserves prior official evidence and appends compensating effects when necessary. |

### 2025 reference configuration

The approved 2025 materials seed reusable configuration, not permanent code
branches. The seven reusable Organizational Units and proposal colors are:

| Team | Full organizational unit | Color |
|---|---|---|
| Buhi | CSPC Buhi Campus | Fuchsia Pink |
| CAS | College of Arts and Sciences | Red |
| CCS | College of Computer Studies | Yellow |
| CHS | College of Health Sciences | Purple |
| CEA | College of Engineering and Architecture | Gray |
| CTDE | College of Technological and Developmental Education | Blue |
| CTHBM | College of Tourism, Hospitality and Business Management | Green |

Every participating unit receives one Event Delegation. A team Division allows
at most one eligible team Entry per Delegation. Individual, pair, relay, and
weight-class limits remain governed by the proposal-derived rule version.

The proposal sports configuration is:

| Sport | Divisions | Tournament or ranking format | Championship template |
|---|---|---|---|
| Basketball | Men, Women | Single elimination | Major |
| Volleyball | Men, Women | Single elimination | Major |
| Badminton | Men, Women | Double elimination | Standard |
| Table Tennis | Men, Women | Double elimination | Standard |
| Lawn Tennis | Men, Women | Double elimination | Standard |
| Sepak Takraw | Men | Single elimination | Standard |
| Chess | Men, Women | Round robin | Intermediate |
| Taekwondo | Men, Women weight classes | Single elimination and aggregate placement | Major |
| Arnis | Men, Women weight classes | Single elimination and aggregate placement | Major |
| Athletics | Men, Women track, field, and relay disciplines | Aggregate placement; source conflict remains visible | Major |

The dashboard seeds and displays these source-verified judged profiles:

| Competitions | Criteria weights |
|---|---|
| Extemporaneous Speaking, Dagliang Talumpati, Story Telling, Pagkukwento | Content/organization 35; delivery 35; pronunciation/diction 20; stage presence 10 |
| Radio Drama | Script 30; technical quality 30; vocal quality 30; overall appeal 10 |
| Pop Solo, Kundiman | Tone quality 40; musicianship 40; deportment 20 |
| Vocal Duet | Vocal technique 30; blending/harmony 30; musicianship 30; deportment 10 |
| Instrumental Solo variants | Technique 30; mastery 30; interpretation/expression 30; stage deportment 10 |
| Folk Dance | Performance 40; interpretation 30; costume/music/equipment 20; overall impact 10 |
| Hip Hop Dance | Choreography 20; rhythm/timing 20; costume 10; technique 25; performance 25 |
| Contemporary Dance | Choreography/composition 30; performance 30; technique 20; overall impact 20 |
| Charcoal Rendering, Pencil Drawing, Painting, Poster Making, Photography | Concept 35; technique 35; composition 30 |

Essay Writing and Pagsulat ng Sanaysay remain visible but blocked because the
listed criteria total 95 while the source prints 100. Dance Sports remains
blocked because it supplies names without weights. Cheer Dance remains blocked
because its printed Overall Impact value makes the total invalid. The system
does not silently repair source documents.

Initial placement templates are:

| Template | Champion | 1st Runner-Up | 2nd Runner-Up | Participation |
|---|---:|---:|---:|---:|
| Major | 25 | 20 | 15 | 5 |
| Standard | 20 | 15 | 10 | 5 |
| Individual | 5 | 4 | 3 | 1 |
| Intermediate | 8 | 6 | 4 | 2 |

Normal Athletics, Arnis, and Taekwondo discipline placement uses `5/4/3/1`;
Athletics relays use `10/8/6/2`. The exact event-year exceptions, participation
eligibility, precision, ties, and source defects remain versioned configuration
and may block only the affected activation or approval.

## Product Principles

1. **Server authority:** Laravel and PostgreSQL decide authorization,
   validation, ordering, revisions, officiality, standings, and ledger totals.
2. **Least privilege:** Event roles describe responsibility; Scoring
   Assignments describe exact scoring scope.
3. **Separate officiality:** A live score, Result Submission, Official Contest
   Outcome, and final Division Placement are different facts.
4. **Append-only evidence:** Corrections and voids preserve prior revisions and
   add compensating effects; they do not silently overwrite history.
5. **Explicit uncertainty:** Unresolved institutional rules block affected
   paths instead of being guessed in code.
6. **Public minimization:** Public DTOs are purpose-built allow-lists and never
   expose private operational records through serialization or caching.
7. **Human authority:** SYNTIX records authorized human rulings; it does not
   replace referees, Judges, tournament officials, or committees.

## Consolidated Architecture Decisions

### AD-001 — Authoritative application stack

Laravel and PostgreSQL are the sole application and data authorities. Inertia
React and Tailwind render server-authorized interfaces. Realtime, queues,
IndexedDB, PWA caches, and browser previews are delivery mechanisms only.
Firebase, MySQL, Bootstrap, Laravel 12, and any second authoritative backend are
rejected because they conflict with the checked-in stack or create competing
truth.

### AD-002 — Global administration and scorer authorization

Exactly one active Global Admin administers every Event without Event
membership. Judge and Tabulator are the only active Event Roles. Every scoring
read and mutation requires both the matching active Event Role and at least one
matching active Scoring Assignment. Assignment scope is closed to
`competition_division`, `contest`, and `entry_scorecard`; only a Division
assignment reaches its current and future direct Contests. Narrow assignments
never expand to parents, siblings, or unrelated records. Overlapping
assignments grant their union and remain independently revocable.

Event Admin, `event_creator`, first-Event-Admin bootstrap, and open privileged
registration are retired alternatives and authorize nothing. Global Admin
authority does not grant Judge or Tabulator scoring access.

### AD-003 — Results, placements, and signed ledger

Contest state, Result Submission, Official Contest Outcome, Division Placement,
and Score Ledger Entry are separate records with separate transitions. Only
approval of an eligible final Division Placement under its frozen Point Rule
Version creates Championship Points. Match outcomes and Discipline Sub-Points
may change internal tournament state but never enter the championship ledger.

Corrections preserve previous revisions. A changed championship effect appends
negative reversals and positive replacements in one transaction. At every
committed cutoff, a Delegation total is `SUM(amount)` across all committed
signed ledger entries; editable or denormalized totals cannot override it.

### AD-004 — Idempotent online and offline scoring commands

Stable online command semantics precede offline support. Each mutation carries
`command_uuid`, schema version, actor/Event scope, target, payload,
`base_revision`, and optional `depends_on_command_uuid`. A terminal Command
Receipt stores the actor scope and hash of the canonical envelope. An exact
same-scope, same-envelope retry returns that receipt and never reapplies the
mutation; UUID reuse with different scope or content returns a non-disclosing
`idempotency_key_reused` failure.

Dependent commands run sequentially from the predecessor's applied resulting
revision. Rejected or conflicted predecessors stop successors. Completion and
submission require an online close handshake. IndexedDB may hold private
pending intents but never authoritative scores or standings, and automatic
last-write-wins or field merging is prohibited.

### AD-005 — Randomized, versioned tournament structures

The Global Admin initiates a cryptographically secure random Draw from the full
locked eligible Entry pool. The system stores private reproducibility material,
ordered Entry identifiers, algorithm version, actor, time, and command UUID.
An exact replay returns the original Draw. An explicit redraw creates a new
audited preview only before publication.

Single elimination, double elimination, and round robin are versioned directed
structures. BYEs resolve automatically without fake Contests or points. Only
approved Official Contest Outcomes route winners or losers. Published Draw and
topology are immutable; a later correction preserves prior versions and uses a
protected descendant-impact workflow. Manual organizer-entered Draw Order and
the claim that SYNTIX must never randomize are superseded product decisions.

### Consolidation audit

| Former document | Disposition |
|---|---|
| ADR 0001 — Authoritative stack | Relevant and retained in AD-001. |
| ADR 0002 — Roles and assignments | Assignment containment retained in AD-002; Event Admin, `event_creator`, and first-Admin bootstrap paths discarded as outdated. |
| ADR 0003 — Results, placements, and ledger | Relevant and retained in AD-003 and the officiality/ledger requirements. |
| ADR 0004 — Offline scoring commands | Relevant future contract retained in AD-004 and OFF requirements. |
| ADR 0005 — Tournament bracketing | Topology, BYE, approval, and correction rules retained in AD-005; manual-draw prohibition discarded as outdated. |
| Identity and RBAC specification | Closed provisioning, session safety, assignment containment, cache controls, and audit rules retained; multi-Admin/event-creator assumptions discarded. |
| Competition scoring specification | Relevant rule lifecycle, three-layer scoring, precision, blockers, and signed-ledger rules retained; duplicated prose removed. |
| Tournament bracketing specification | Relevant format and correction contracts retained; manual Draw input and staged “single elimination first” assumptions discarded as outdated. |
| Basketball tracer specification | Roster, score-state, outcome validation, officiality, and timing boundaries retained; manual Draw and obsolete first-slice sequencing discarded. |
| Judged scoring specification | Private Judge drafts, criteria semantics, aggregation blockers, correction, and report traceability retained. |
| Athletics aggregation specification | Units, ranking, Sub-Points, statuses, and source blockers retained; no disputed format is silently activated. |
| Offline synchronization specification | Command, receipt, dependency, conflict, outbox, close-handshake, and cache rules retained as later-release requirements. |
| Admin frontend specification | Truthful state, authorization, accessibility, and CSPC identity retained; Event-Admin primary user, visual-only empty slice, and unavailable-control assumptions discarded. |
| Public scoreboard specification | Anonymous allow-list, unofficiality, freshness, accessibility, cache, and publication requirements retained. |
| Reports and archive specification | Immutable manifest, cutoff, hash, privacy, correction, and archive requirements retained as later-release requirements. |

The former ADR and specification files have no remaining authority after this
consolidation. Source conflicts or deferred implementation status are expressed
directly in this PRD and the single system implementation plan.

## User Workflows

### 1. Bootstrap and privileged provisioning

1. A one-time bootstrap creates the sole active Global Admin. Development setup
   seeds `admin@syntix.test` with the local-only initial password `password`;
   non-local environments require deployment-supplied credentials.
2. PostgreSQL and the application prevent a second Global Admin, prevent
   deletion or demotion of the sole Global Admin, and make bootstrap reruns
   idempotent.
3. The Global Admin creates Event shells and provisions Judge or Tabulator
   accounts through a closed account workflow.
4. Provisioning records the target Event Role and one or more exact Scoring
   Assignments; an invitation without an authorized role and assignment cannot
   open a scoring workspace.
5. Disablement, role revocation, assignment revocation, session revocation,
   and security-sensitive changes are audited.

### 2. Event configuration and readiness

1. Global Admin creates an Event edition and applies the 2025 SIKLAB programme.
   The system creates one event-scoped Delegation for each selected proposal
   Organizational Unit.
2. Global Admin configures Competition families, required score-bearing Divisions,
   Disciplines, Entries, rosters, venues, schedules, criteria, formats, and
   point rules.
3. The server validates rule consistency, required precision, criteria totals,
   participation conditions, tie behavior, and source traceability.
4. Global Admin locks eligible department Entries and records readiness warnings
   before scoring.
5. The governing rule version becomes frozen when the first contest starts or
   the first score is recorded.

### 2A. Admin participant and roster registration

1. The Global Admin opens the selected Event's Registration Desk and filters
   by Delegation, Competition, Division, Entry mode, roster status, or
   eligibility status.
2. The Global Admin creates or edits a private Participant profile containing
   Event Delegation, display and legal-name fields, optional student number and
   contact fields, active state, and private notes.
3. The Global Admin creates or selects the appropriate Division Entry and adds
   the Participant as a student-athlete, reserve, student coach, or faculty
   coach. Team, individual, pair, relay, and mixed Entries use the same
   authoritative containment checks.
4. The server validates Event and Delegation consistency, one current team
   Entry per Delegation and Division, duplicate roster membership, governing
   roster limits, role limits, and participant competition limits.
5. The Global Admin records eligibility as pending, eligible, ineligible,
   withdrawn, or disqualified with the checking actor, timestamp, and reason
   when required.
6. Before Entry lock or draw publication, authorized edits update the current
   registration and remain audited. After lock, removal or ineligibility uses
   explicit deactivate, withdrawal, disqualification, unlock/redraw, or
   correction workflows; official evidence is never hard-deleted.
7. Student self-service registration remains outside this approved slice.
   Participants do not receive accounts merely because the Global Admin
   registers them.

### 3. Tournament and objective scoring

1. Global Admin requests an automatic draw for a Division after confirming its
   eligible department Entries.
2. The server uses a cryptographically secure random source to shuffle the
   eligible Entries and stores the generated Draw Order, random seed or
   equivalent reproducibility material, algorithm version, actor, Event,
   Division, timestamp, and idempotency key.
3. The server automatically creates a single-elimination, double-elimination,
   or round-robin preview from that immutable draw, including BYEs, rests,
   winner/loser routes, third-place contests, and reset finals as applicable.
4. Replaying the same command returns the same draw and topology. A new random
   draw is an explicit audited Global Admin action allowed only before
   publication.
5. Global Admin publishes the bracket; published topology and draw are
   immutable.
6. An assigned Tabulator records live performance with an expected revision and
   idempotency key.
7. Live values may be public only as `Unofficial` with an authoritative update
   timestamp.
8. Tabulator completes and submits a locked result snapshot.
9. Global Admin approves or rejects the Official Contest Outcome with any required
   ruling and advancement disposition.
10. The server derives the winner, loser, win/loss/draw record, advancement,
    round-robin standing, and placement candidates from the approved result.
    Scorers cannot manually advance bracket slots or edit derived standings.
11. Match approval creates no Championship Points. Separate final placement
    approval awards the proposal template and refreshes department totals.

### 3A. Proposal sport scoring

- Basketball derives the winner from validated team totals, with overtime or
  an authorized ruling required for a tie.
- Volleyball derives set winners and the match winner from the proposal's
  best-of-three structure: the first two sets target 20 points and the third
  targets 15, subject to the configured winning-margin rule.
- Sepak Takraw derives set and match wins from best-of-three sets, target 15,
  and the proposal's deuce/cap rules.
- Badminton derives rubber and team-match wins from the ordered
  Singles/Doubles/Singles tie and its best-of-three 21-point games.
- Table Tennis derives rubber and team-match wins from the ordered
  Singles/Doubles/Singles tie and 11-point games; the first department to win
  two rubbers wins the team match.
- Lawn Tennis derives game and team-match wins from the ordered
  Singles/Doubles/Singles tie, straight-eight, no-advantage rules.
- Chess records a win as `1`, a draw as `0.5`, and a loss as `0` for internal
  round-robin standings. Every eligible Entry faces every other Entry once;
  tie-break configuration must be complete before final placement approval.
- Taekwondo derives round and match wins from best-of-three rounds and preserves
  proposal scoring of body kick `2`, head kick `3`, turning body kick `4`, and
  turning head kick `5`, together with approved Gam-jeom and superiority rules.
- Arnis derives round and match wins from its two-out-of-three structure and
  preserves validated strike points and authorized draw rulings.
- Athletics ranks approved track times ascending and field distances descending,
  then applies configured discipline Sub-Points.

Raw points, sets, rubbers, rounds, wins, losses, draws, BYEs, and Sub-Points are
competition-internal facts. Only an approved final Division Placement produces
department Championship Points.

### 4. Judged scoring

1. Global Admin configures frozen criteria with exact labels, raw ranges, and whether
   each source number is a maximum or a percentage weight.
2. Each assigned Judge independently creates a private scorecard draft for an
   Entry.
3. The server validates values, deductions, precision, and calculation inputs;
   browser calculations are previews only.
4. A Judge submits a complete scorecard, which becomes a locked snapshot.
5. Peer Judge values remain private until the relevant workflow permits Global Admin
   review.
6. The server aggregates scorecards using the approved method and blocks
   unresolved criteria, aggregation, rounding, or tie decisions.

### 5. Athletics and aggregate scoring

1. Global Admin configures candidate Men and Women Divisions, disciplines, stages,
   units, precision, qualification, roster constraints, and sub-point rules.
2. Tabulators record valid measurements and explicit statuses such as did not
   start, did not finish, no mark, disqualified, withdrawn, cancelled, or
   unplayed.
3. The server normalizes units, ranks times ascending and distances descending,
   and advances only from approved outcomes.
4. Approved discipline placements award Sub-Points under the frozen individual
   or relay mapping.
5. The server derives aggregate Division placement from approved Sub-Points;
   Sub-Points never enter the Championship ledger directly.

Athletics activation remains blocked until CSPC resolves its format conflict,
Men/Women aggregation, roster interpretation, precision, tie, relay, and
discipline-rule questions.

### 6. Final placement and championship standings

1. After all required official contest or discipline outcomes exist, the server
   derives a final Division Placement candidate.
2. Global Admin separately reviews and approves the final Division Placement.
3. In the same transaction, the server appends eligible signed Score Ledger
   Entries under the Division's frozen Point Rule Version.
4. Overall standings are derived from all committed ledger amounts, including
   awards, reversals, and replacements.

### 7. Public broadcast

1. An anonymous visitor opens the public landing page or a canonical Event,
   Competition, Division, Contest, bracket, placement, or standings URL.
2. The server returns a sanitized snapshot with publication state, officiality,
   authoritative update time, and version/ETag metadata.
3. Live performance is labeled `Unofficial`; approved Official Contest Outcomes
   and final Division Placements are shown separately.
4. Realtime, once installed, delivers only post-commit sanitized updates.
5. If realtime fails, HTTP refresh remains complete behavior and stale or
   disconnected status is visible.

The public landing slice also has an Admin-controlled publication workflow:

1. The Global Admin opens the Public Programme Desk for an Event.
2. The Global Admin creates or edits venues, schedule drafts, and Competition cover
   images inside that Event.
3. Draft changes remain private until the Global Admin explicitly publishes them.
4. Publishing creates a revisioned public snapshot. Later operational edits do
   not silently rewrite an already published snapshot.
5. The Global Admin may withdraw the current published schedule or cover with a
   reason; the historical publication record and audit entry remain available.
6. Archived Events remain visible to authorized Admins for review but are
   read-only.

The landing page presents the latest-updated live Contest as the primary
broadcast surface, exposes every current live Contest through an accessible
carousel, shows all Event Delegations' signed Championship totals without
numeric ranks, and presents only published Competition covers and programme
snapshots.

### 8. Offline command synchronization

1. Online scoring uses a versioned command envelope from the first mutation.
2. When delivery is uncertain, the client retries the same `command_uuid`.
3. Eligible disconnected commands are stored in an account- and Event-scoped
   IndexedDB outbox as pending, never as official scores or standings.
4. Dependent commands wait for the predecessor's applied receipt and resulting
   revision.
5. The server reauthorizes every replay and returns a durable Command Receipt.
6. Conflicts stop dependent commands and require human review; no automatic
   merge or last-write-wins behavior is permitted.
7. Completing or submitting a result requires an online close handshake.

### 9. Reports, correction, and archive

1. The Global Admin requests a report for an Event, type, filter, and
   immutable official cutoff.
2. The server captures an immutable source manifest and committed-transaction
   cutoff before generation.
3. A worker renders the report from frozen rules, approved revisions,
   corrections, approvals, and signed ledger entries.
4. Original artifacts are content-hashed and immutable; regeneration creates a
   new artifact record.
5. A correction appends replacement revisions and ledger reversals/replacements
   while preserving earlier reports and evidence.
6. Public reports, if approved, are separate sanitized projections and never
   private artifacts with hidden columns.

## Functional Requirements

### Identity and authorization

- **ID-001:** The system shall expose no anonymous privileged-account
  registration action.
- **ID-002:** The system shall enforce exactly one active Global Admin across
  the installation through a PostgreSQL uniqueness constraint and guarded
  application transitions.
- **ID-003:** Only the Global Admin may create or administer Events, provision
  privileged accounts, configure the programme, grant assignments, approve
  official records, generate or publish brackets, and access private reports.
- **ID-004:** Judge and Tabulator shall be the only active Event Roles and shall
  be event-scoped, separately revocable records. `event_creator` and Event Admin
  shall not grant authorization after migration.
- **ID-005:** Judge and Tabulator scoring reads and mutations shall require the
  matching active Event Role and an active matching Scoring Assignment.
- **ID-006:** Scoring Assignment scope shall be exactly
  `competition_division`, `contest`, or `entry_scorecard`; narrow scopes shall
  not inherit to parents, siblings, or unrelated targets.
- **ID-007:** Disabled accounts, revoked roles, and revoked assignments shall
  fail on the next request and during queued-command replay.
- **ID-008:** Session invalidation, role changes, account changes, provisioning,
  assignments, and approval actions shall be auditable.
- **ID-009:** Inertia authentication props shall be explicit DTOs and shall not
  serialize unrestricted User models, private relationships, or credentials.
- **ID-010:** The active Global Admin shall pass administrative authorization
  for every non-archived Event without an Event Role; Global Admin authority
  shall not grant Judge/Tabulator scoring access or bypass exact assignments.
- **ID-011:** Development setup shall idempotently seed the sole Global Admin
  and expose its local credentials in setup documentation; non-local bootstrap
  shall require deployment-supplied credentials and shall not use the local
  password.
- **ID-012:** Account provisioning shall atomically associate the account with
  its selected Event Role and exact scoring assignment, or fail without leaving
  a partially authorized account.
- **ID-013:** Privileged accounts shall have no self-service hard-delete path;
  disablement shall revoke sessions while preserving historical actor links.
- **ID-014:** Password reset, account disablement, and explicit session
  revocation shall invalidate applicable sessions and persistent-login tokens;
  authorization shall be reevaluated on every request.
- **ID-015:** Active role memberships and exact assignments shall be protected
  from duplicate concurrent grants, and overlapping assignments shall preserve
  access until every independently matching grant is revoked.

### Event configuration and scoring

- **SCR-001:** Each Competition shall own one or more explicit score-bearing
  Divisions; a family-level implicit placement is not permitted.
- **SCR-002:** Rule versions shall be versioned configuration with lifecycle
  `draft -> activated_editable -> frozen -> superseded -> archived`.
- **SCR-003:** Rules shall freeze before scoring and remain bound to all related
  contest, result, placement, and ledger records.
- **SCR-004:** Criteria weights, maxima, verified totals, precision, rounding,
  deductions, participation conditions, and tie behavior shall validate before
  activation.
- **SCR-005:** Contest performance, Division Sub-Points, and Championship
  Points shall remain separate layers.
- **SCR-006:** The four 2025 placement templates and Athletics/aggregate
  Sub-Point templates shall be data-driven and versioned.
- **SCR-007:** Only configured, authorized penalties and deductions may be
  applied; raw values, deductions, and net values remain traceable.
- **SCR-008:** Unresolved ties, missing required results, and unresolved rule
  semantics shall block the affected approval path.
- **SCR-009:** Participation Points shall be awarded only under an approved
  status policy; withdrawals, no-shows, forfeits, disqualifications, and
  cancellations shall not be inferred to qualify.
- **SCR-010:** The 2025 programme shall seed the seven proposal Organizational
  Units, event Delegations, sports, Divisions, formats, roster limits, weight
  classes, Athletics disciplines, verified judged criteria, and placement or
  Sub-Point rules with source-page traceability.
- **SCR-011:** A team Division shall allow no more than one Entry from the same
  Event Delegation; every Entry shall belong to a Delegation from the same
  Event as its Division.
- **SCR-012:** Approved sport performance shall automatically derive the
  applicable game, set, rubber, round, match, win/loss/draw, and internal
  standing values under the frozen sport rule version.
- **SCR-013:** The system shall automatically derive final placement candidates
  after all required official outcomes exist and shall award Major
  `25/20/15/5`, Standard `20/15/10/5`, Individual `5/4/3/1`, or Intermediate
  `8/6/4/2` only after separate Global Admin approval.

### Tournament automation and sport outcomes

- **TBR-001:** Global Admin shall initiate an automatic random draw from the
  complete set of locked eligible department Entries for a Division; manual
  ordering shall not be required.
- **TBR-002:** The system shall use a cryptographically secure random shuffle
  and persist sufficient seed/material, ordered Entry identifiers, algorithm
  version, actor, timestamp, and idempotency data to reproduce and audit the
  draw. Identical command replay shall return the original draw.
- **TBR-003:** A deliberate redraw shall create a new audited preview and shall
  be allowed only before publication. Published draws and topology shall never
  be silently rerandomized.
- **TBR-004:** Elimination generation shall use the next power-of-two bracket
  size and a versioned topology algorithm; round robin shall schedule every
  eligible Entry against every other Entry once.
- **TBR-005:** BYEs shall be automatic auditable resolutions, not contests,
  wins, losses, forfeits, scores, or points; BYE-versus-BYE nodes are forbidden.
- **TBR-006:** Zero-entry generation shall be blocked and one-entry Divisions
  shall require an authorized uncontested ruling.
- **TBR-007:** Single elimination shall include a third-place playoff whenever
  two semifinal losers exist.
- **TBR-008:** Double elimination shall support proposal-sized fields through
  signed versioned 2-, 4-, and 8-slot routing maps, including winner routes,
  loser routes, BYE behavior, second-loss elimination, placement extraction,
  and a conditional reset final.
- **TBR-009:** Only approved Official Contest Outcomes may advance a bracket;
  Tabulators cannot manually advance slots.
- **TBR-010:** Approved outcomes shall automatically update win/loss/draw
  records, elimination state, round-robin points, tie-break inputs, and final
  placement candidates without creating Championship Points.
- **TBR-011:** A post-publication correction shall preview every affected
  descendant and shall change the corrected outcome and permitted descendants
  only inside the approving transaction.
- **TBR-012:** A correction affecting a live, submitted, or approved descendant
  shall block until an authorized resolution is recorded; failure shall roll
  back the outcome revision and every descendant change.
- **TBR-013:** Post-publication withdrawal, no-show, walkover, forfeit,
  disqualification, late entry, or cancellation shall preserve topology and
  require the applicable authorized ruling instead of silent reseeding.
- **TBR-014:** Directed bracket data shall prevent cycles, cross-Tournament
  routing, duplicate slot occupancy, self-edges, and phantom loser routing from
  BYEs.
- **BTR-001:** Basketball shall use one configurable Competition family with
  separate Men and Women team Divisions and a maximum of 15 student-athletes
  per Division Entry.
- **BTR-002:** The first Basketball slice shall record nonnegative integer team
  totals and phase/overtime context with expected revisions and idempotency;
  timing remains non-authoritative metadata.
- **BTR-003:** Basketball result types shall validate required, forbidden, and
  conditional scores, winners, rulings, and advancement dispositions.
- **BTR-004:** Match approval shall create no Championship Points; separate
  final Division-placement approval shall create the configured Major effects
  exactly once.
- **SPT-001:** Volleyball, Sepak Takraw, Badminton, Table Tennis, Lawn Tennis,
  Chess, Taekwondo, Arnis, and Athletics shall use the proposal sport-outcome
  profiles defined in workflow 3A rather than a generic manually selected
  winner when their required score detail is available.
- **SPT-002:** A tied knockout result, incomplete best-of series, unconfirmed
  forfeit, disqualification, withdrawal, or walkover shall block automatic
  advancement until the frozen rule or an authorized ruling resolves it.

### Judged and Athletics workflows

- **JSC-001:** Judges shall score only their own private drafts within assigned
  scope and shall not see peer drafts before submitting their own.
- **JSC-002:** The server shall distinguish point maxima from percentage weights
  and prevent double-weighting source criteria.
- **JSC-003:** Submitted scorecards shall lock; rejection shall create an
  auditable correction path rather than overwrite the submitted revision.
- **JSC-004:** Judged aggregation shall use the frozen institutional method and
  block when required scorecards, precision, deductions, or ties are unresolved.
- **ATH-001:** Athletics shall use explicit units, fixed precision, sort
  direction, stages, statuses, and qualification rules per Discipline.
- **ATH-002:** Valid time and relay values shall rank ascending; valid field
  distances shall rank descending after unit normalization.
- **ATH-003:** Approved individual and relay discipline placements shall award
  only their configured Sub-Points; unplayed and cancelled disciplines award
  none.
- **ATH-004:** Athletics activation shall remain blocked until the institutional
  format, Men/Women aggregation, roster, precision, tie, relay, and affected
  discipline decisions are recorded.

### Admin, public, and reports

- **ADM-001:** The Admin shell shall show truthful event lifecycle, work queues,
  permissions, connection, freshness, correction, empty, and unauthorized
  states without fabricated metrics or dead controls.
- **ADM-002:** Approval work shall distinguish Official Contest Outcomes from
  final Division Placements and route each to a dedicated review workflow.
- **ADM-003:** The Global Admin shall manage Event-scoped venues, schedule
  drafts, and Competition cover-image drafts from the Public Programme Desk;
  an archived Event shall be reviewable but read-only.
- **ADM-004:** Publishing or withdrawing a schedule or cover shall be explicit,
  revisioned, audited, and transactional; a later draft edit shall not change
  the current public snapshot until republished.
- **ADM-005:** An active Global Admin shall be authorized to administer every
  Event without an Event Role membership; this privilege shall not grant Judge
  or Tabulator scoring access or bypass exact Scoring Assignments.
- **ADM-006:** The Admin dashboard shall provide an explicit Event selector and
  show all seven proposal Delegations, programme readiness, sports and formats,
  Divisions, criteria, source blockers, accounts, roles, exact assignments,
  pending approvals, live contests, and championship totals for the selected
  Event.
- **ADM-007:** The Tournament Desk shall let the Global Admin inspect eligible
  department teams, generate or redraw an auditable random preview, inspect
  BYEs and routes, and publish an immutable bracket.
- **ADM-008:** The Programme Matrix shall display verified judging criteria and
  weights, and shall visibly block activation for Essay Writing's 95-percent
  total, missing Dance Sports weights, invalid Cheer Dance total, conflicting
  Athletics format, and other proposal contradictions instead of guessing.
- **ADM-009:** The Global Admin shall have an Event-selected Registration Desk
  for creating, editing, searching, filtering, activating, and deactivating
  private Participants.
- **ADM-010:** The Global Admin shall create or select Division Entries and add,
  edit, reorder, or deactivate roster memberships with explicit
  student-athlete, reserve, student-coach, or faculty-coach roles.
- **ADM-011:** The Registration Desk shall manage pending, eligible, ineligible,
  withdrawn, and disqualified decisions with actor, timestamp, and reason while
  enforcing Event, Delegation, Division, roster, and role-limit invariants.
- **ADM-012:** Registration changes before lock shall be directly editable and
  audited; after lock or publication, the interface shall use explicit
  unlock/redraw, withdrawal, disqualification, deactivation, or correction
  actions without deleting historical evidence.
- **ADM-013:** Admin navigation shall expose only real authorized routes for
  Overview, Registrations, Programme, People & Access, Tournaments, Approvals,
  Publishing, and Reports; controls unavailable in the selected lifecycle
  shall explain why rather than appear dead.
- **ADM-014:** Student self-registration shall remain unavailable unless a
  later approved requirement defines identity verification, consent, privacy,
  duplicate handling, review, and rejection workflows.
- **PUB-001:** Anonymous users shall read only explicitly published, sanitized
  data and shall have no mutation capability or stored Event Role.
- **PUB-002:** Public live values and provisional rankings shall be visibly
  labeled `Unofficial` and include an authoritative update time.
- **PUB-003:** Participant names shall remain hidden by default and appear only
  under an approved publication setting after the applicable approval.
- **PUB-004:** Public schedules, brackets, results, placements, and standings
  shall use purpose-built allow-listed DTOs and canonical HTTPS URLs.
- **PUB-005:** The public landing page shall truthfully identify the current
  published Event, provide one clear live-broadcast entry point, expose
  published competition boards, and remain useful when there is no live Event.
- **PUB-006:** The landing DTO shall include every Event Delegation with only
  its public identifier, name, optional abbreviation, and signed ledger total;
  it shall not include numeric rank, Entry data, participant data, or private
  scoring metadata.
- **PUB-007:** The landing page shall place the latest-updated live Contest
  first, make every current live Contest reachable through an accessible
  carousel, and preserve the selected Contest by identifier across successful
  polling refreshes when it still exists.
- **PUB-008:** Carousel rotation shall default to eight seconds only when
  multiple live Contests exist, pause for manual interaction, focus, hover,
  hidden documents, and reduced-motion preferences, and never steal focus.
- **PUB-009:** The landing page shall show contextual `Refreshing`, `Stale`, or
  `Disconnected` status with the last successful snapshot when needed, while a
  healthy connection remains visually quiet.
- **PUB-010:** The Competition index shall show only published cover images and
  published schedule snapshots, with a truthful compact empty state when no
  programme is published; private storage paths, notes, and audit metadata
  shall never enter the public payload.
- **PUB-011:** The public landing layout shall provide keyboard-visible focus,
  44-pixel controls, responsive ordering, remote-asset fallbacks, and an
  editorial header, Competition index, and footer without adding a new runtime
  dependency or generated artwork.
- **RPT-001:** Admins shall request event-scoped reports from frozen rules,
  approved revisions, corrections, approvals, and signed ledger entries.
- **RPT-002:** Every report shall capture an immutable source manifest and
  committed-transaction cutoff; original artifacts shall be immutable and
  content-hashed.
- **RPT-003:** Regeneration and correction shall create new artifacts or
  revisions without replacing historical evidence.
- **RPT-004:** Private reports shall never appear in anonymous URLs, public
  broadcasts, or PWA caches; public reports shall be separate sanitized
  projections.
- **RPT-005:** The official overall output shall be named `General Championship
  Point Tally` until CSPC approves a separate medal domain.

### Offline and realtime

- **OFF-001:** Every scoring mutation shall use a versioned command envelope
  containing a client UUID, Event/target identifiers, expected base revision,
  payload, and optional direct dependency.
- **OFF-002:** The server shall persist a durable receipt, actor scope, and
  canonical envelope hash for every terminal command disposition.
- **OFF-003:** Exact same-scope, same-envelope retries shall return the original
  receipt without reapplication; mismatched UUID reuse shall disclose nothing
  about the original command.
- **OFF-004:** Successor commands shall wait for an applied predecessor receipt
  and use its resulting revision; conflicts shall stop dependent replay.
- **OFF-005:** Completion and submission shall require an online close
  handshake with no unresolved related outbox commands.
- **RT-001:** Realtime events, once installed, shall dispatch only after the
  originating transaction commits and shall carry sanitized version metadata.
- **RT-002:** Every realtime view shall retain a complete HTTP snapshot and
  retry fallback before, during, and after transport failure.

## Authorization and Privacy

- Public access is anonymous and read-only; no public account, Event Role, or
  Scoring Assignment is created.
- Exactly one Global Admin account administers every Event, including Events
  where it has no Event Role membership. There is no active Event Admin or
  `event_creator` authorization path. Global administration does not grant
  Judge/Tabulator scoring authority or replace exact scoring assignments.
- Judge and Tabulator authorization is the intersection of active account,
  matching Event Role, active exact assignment, target Event, and valid target
  lifecycle.
- Navigation visibility is never an authorization boundary. Policies and
  server-side queries enforce every protected read and mutation.
- Authenticated Inertia documents, JSON mutations, private downloads, and
  validation/error responses are `private, no-store` as appropriate.
- Public payloads exclude student numbers, contacts, private rosters,
  eligibility notes, Judge drafts, peer scores, assignments, audit metadata,
  internal correction reasons, queued commands, sessions, and private report
  data.
- Participant labels default to Delegation identity. Participant names require
  an approved event publication setting and the applicable official approval.
- Operational schedule notes, private cover paths, uploader identities,
  publication audit fields, and withdrawal reasons remain Admin-only even when
  a corresponding public snapshot exists.
- Service-worker runtime caching is limited to public shell/assets and safe GET
  snapshots with freshness/offline labels.

## Domain and Data Requirements

### Officiality and lifecycle

Contest performance and official records are separate state machines:

```text
Contest: scheduled -> live -> completed
             |          |
             v          v
          cancelled  suspended

Submission: draft -> completed -> submitted -> approved
                                      |
                                      v
                                   rejected -> draft

Official outcome: approved current -> superseded by correction
                         |
                         v
                       voided

Division placement: candidate -> submitted -> approved current
                                    |
                                    v
                                  rejected -> candidate
```

Live and completed data may remain unofficial. A rejected submission requires
a reason and a new editable revision. Contest approval may advance a bracket
but cannot award Championship Points. Final Division-placement approval is the
only path that may create ledger effects.

### Participant, Entry, roster, and eligibility data

- A Participant belongs to exactly one Event and one Event Delegation. A
  non-null student number is unique within that Event after normalization.
- An Entry belongs to one Division and one Event Delegation from the same Event.
  A team Division has at most one current draft, active, or locked Entry for a
  Delegation.
- A Roster Member joins one Participant to one Entry with one role, display
  order, active state, and optional private note. The same Participant cannot
  have duplicate current membership in the same Entry.
- Eligibility is evaluated for the Participant and Entry within the same Event.
  `eligible`, `ineligible`, `withdrawn`, and `disqualified` decisions record the
  Global Admin actor and checking time; adverse states require a reason.
- Governing rules validate minimum/maximum roster size, role limits,
  participant mode, competition limits, and any approved discipline limits.
- Participant contact details, student numbers, private notes, rosters, and
  eligibility reasons are private. Public projections use Delegation or Entry
  labels unless a later approved publication setting permits participant names.
- Registration records that have influenced a Draw, Contest, submission,
  outcome, placement, or report are never hard-deleted. Corrections deactivate
  or supersede membership and eligibility facts with an audit reason.

### Tournament structure and correction data

- A Tournament belongs to one Division and frozen rule version. A Draw Record
  preserves command UUID, private encrypted random seed or equivalent material,
  ordered Entry identifiers, source, algorithm version, actor, and timestamp.
- A Bracket Version contains directed Bracket Nodes, Bracket Slots, and
  Advancement Rules. Edges identify source node, source result kind, target
  node, and target slot and cannot form cycles, self-edges, duplicate slot
  occupancy, or cross-Tournament routes.
- Every supported double-elimination topology includes the first grand final
  and a reset-final node. The reset is initially inactive, becomes
  `not_required` when the undefeated finalist wins, and becomes active when
  that finalist takes a first loss.
- Odd round robin uses an explicit rest assignment, not a played BYE Contest.
- A correction stores the prior outcome revision, impact preview, selected
  descendant resolution, approving actor, and reason. Draft or submitted
  corrections mutate no descendant; approval applies all permitted changes in
  one transaction.

### Basketball outcome validation

Scores forbidden below must be null rather than synthetic zeroes. Every
non-played outcome requires an authorized ruling, and every approved outcome
requires an explicit `advance_winner` or `no_advancement` disposition.

| Outcome | Final scores | Winner | Required ruling and advancement |
|---|---|---|---|
| `played` | Required nonnegative integers for both Entries | Must match the higher total unless an exceptional ruling explains the difference | Ruling optional normally; `advance_winner` required |
| `walkover` | Forbidden | Required | Authority and reason required; `advance_winner` |
| `forfeit` | Optional only if play occurred; otherwise forbidden | Required for one-sided ruling; null for documented both-side ruling | Forfeiting side and authority required; `advance_winner` or `no_advancement` as ruled |
| `no_show` | Forbidden | Required for one-sided ruling; null when both sides are ruled absent | Absent side and authority required; `advance_winner` or `no_advancement` as ruled |
| `withdrawal` | Optional only if play began; otherwise forbidden | Required only when an opponent advances | Timing, side, authority, and ruled disposition required |
| `disqualification` | Optional only if play began; otherwise forbidden | Required only when an opponent advances | Disqualified side, authority, and ruled disposition required |

An `advance_winner` outcome must name a winner; a `no_advancement` outcome must
not. None of these outcome types implies participation-point eligibility. The
Basketball rule version stores four 10-minute quarters, 30-second timeout
metadata, 5-minute and 3-minute overtime metadata, and subsequent
first-to-score sudden death, but SYNTIX is not the authoritative game clock.

### Scoring layers and ledger

1. **Layer 1 — Contest Performance:** match points, periods, sets, rounds,
   measurements, Judge values, deductions, or status.
2. **Layer 2 — Division Sub-Points:** internal aggregate values from Discipline
   placements. They rank a Division and never enter the general ledger
   directly.
3. **Layer 3 — Championship Points:** delegation points from an approved final
   Division Placement under its frozen Point Rule Version.

At any committed cutoff:

```text
delegation Championship Points = SUM(amount)
```

The sum includes every committed signed award, reversal, and replacement entry
for the Event and Delegation. No active/inactive ledger filter or editable total
may override it.

### Public programme publication

Operational `Schedule` and `CompetitionCoverImage` records are not themselves
public records. A schedule publication stores the public-facing Competition,
Division, title, time, status, and venue snapshot. A cover publication stores a
validated landscape image, public alt text, and a public path distinct from its
private upload path.

Each publication belongs to one Event-scoped parent and carries a monotonically
increasing revision. At most one current `published` snapshot exists for a
schedule or Competition. Publishing supersedes the prior snapshot inside a
transaction; withdrawal records a reason and removes the public projection
without deleting historical evidence. Public DTOs may expose only the current
published snapshot.

### Reports and archive data

- The initial report set comprises detailed Judge score breakdown, Division
  result summary, sports match/bracket summary, aggregate Discipline/Sub-Point
  summary, General Championship Point Tally, and correction/approval history.
- Each report record identifies Event, report UUID/type/schema, privacy class,
  filters, requester, immutable committed cutoff, source revision manifest,
  renderer version, status, storage reference, byte size, content hash, and any
  supersession relationship.
- The source manifest references the frozen rules, criteria, bracket versions,
  scorecards, official outcomes, final placements, approvals, corrections, and
  signed ledger rows used by the artifact.
- Original report bytes and manifests are immutable. Regeneration creates a new
  record even for the same input set. A storage hash or ledger reconciliation
  failure blocks official download and records an integrity incident.
- Private artifacts require Global Admin authorization at request and download
  time and are never public-cacheable. A public report is a separately
  generated sanitized projection, not a private report with hidden columns.

### Immutability and correction

- A frozen or historically referenced rule version is immutable.
- Approved submissions, outcomes, placements, scorecards, bracket versions,
  reports, and ledger entries are preserved.
- A correction references the prior revision, records a reason and actor, and
  creates a new revision.
- If a final placement changes, the system appends negative reversal and
  positive replacement ledger entries atomically.
- Published bracket topology is immutable; approved corrections may update
  permitted resolution and downstream state through a protected transaction.
- No correction silently reseeds, overwrites, or deletes historical evidence.

## Realtime and PWA Requirements

- Normal HTTP reads and writes are complete behavior; realtime is an optional
  after-commit delivery layer.
- Public views show `Loading`, `Empty`, `Live`, `Official`, `Stale`,
  `Disconnected`, `Reconnecting`, `Corrected`, `Voided`, and `Error` states
  with text, not color alone.
- Older/out-of-order public versions are ignored or reconciled through an HTTP
  snapshot.
- Public cached snapshots show the snapshot time and offline/stale status;
  they never imply current realtime data.
- IndexedDB outbox records are partitioned by authenticated account and Event.
  Account switching cannot expose or replay another account's commands.
- Background Sync may improve delivery but is never required for correctness.
- Lost responses are recovered by exact command UUID retry. A stale revision,
  revoked assignment, missing dependency, quota failure, or device loss has an
  explicit visible recovery state.

## UX Requirements

### Shared requirements

- Responsive operation at mobile, tablet, desktop, large display, 200% zoom,
  keyboard-only, high-contrast, and reduced-motion settings.
- Semantic landmarks, skip links, one page `h1`, visible focus, accessible
  names, and minimum 44×44 CSS-pixel interactive targets.
- Changing numeric data uses stable layout and tabular numerals where useful.
- Live updates do not steal focus, dismiss forms, or reorder the item currently
  under review without user control.
- Dates, times, units, precision, officiality, and freshness are explicit and
  locale-aware.

### Admin

- The first slice is a truthful CSPC institutional command center, not a
  generic analytics dashboard.
- The sole Global Admin sees an explicit Event selector and a SIKLAB readiness
  rail: `Event -> Programme -> Registrations -> Assignments -> Draws -> Live ->
  Official`.
- The responsive operations shell groups real routes as Overview,
  Registrations, Programme, People & Access, Tournaments, Approvals,
  Publishing, and Reports. Desktop uses a disciplined sidebar or rail and
  data-dense tables; mobile uses a compact drawer or section switcher with
  labeled stacked records.
- The main workspace contains a proposal-backed Programme Matrix,
  Registration Desk, People & Access panel, Tournament Desk, approval queues,
  and department Championship standings. Sports, divisions, formats, criteria,
  scoring profiles, rosters, eligibility, and source blockers are inspectable
  and editable where the lifecycle permits.
- The Registration Desk uses a searchable master list and focused edit panel,
  not a grid of unrelated cards. Its signature path is `Delegation ->
  Participant -> Entry -> Eligible -> Locked`, with counts and blockers derived
  from server data.
- The Tournament Desk provides `Generate random draw`, `Redraw`, `Preview`, and
  `Publish` controls with clear confirmation, reproducibility metadata, BYE
  labels, and published immutability. Redraw is unavailable after publication.
- The People & Access workflow provisions only Judge or Tabulator accounts and
  requires Event plus exact assignment selection.
- Lifecycle is shown as `Preparation -> Configuration -> Live Operations ->
  Closed -> Archived`.
- With no event data, show `Admin overview`, `No active SIKLAB event`, a
  Preparation marker, and a setup-oriented checklist—never fake metrics.
- Future active-event priorities are separate outcome approvals, final
  placement approvals, live unofficial contests, blocked/correction work,
  today's schedule, and configuration warnings.

### Public landing page

The public landing page is the broadcast entry point, not a staff dashboard. Its
single job is to help a spectator answer: “What is happening now, and where do I
go for the official event board?”

The approved current slice uses an editorial event-program direction:

- A CSPC Gold rule, local SYNTIX mark, useful in-page links, and secondary
  staff access establish identity without competing with live content.
- A deep-navy live desk pairs the dominant live-score carousel with a complete
  Championship standings stack. The scoreboard comes first in document order
  on mobile and for assistive technology.
- The carousel uses server ordering, eight-second rotation, explicit previous,
  next, selector, and pause controls, and stable polling behavior. It shows
  only public Contest fields and labels all pre-approval values `Unofficial`.
- Standings include every Event Delegation and signed total. Tied totals have
  equal visual treatment; unresolved tie rules prevent numeric rank labels.
- The Competition area is a numbered editorial index with published cover
  images, current public schedule snapshots, bracket links, and a compact
  `00 published boards` empty state.
- The footer provides CSPC identity, public navigation, Event Board access,
  staff access, and Nabua/Camarines Sur context without duplicating the hero.
- Healthy connection state is quiet. Refreshing, stale, and disconnected
  states appear contextually inside the score surface with the last successful
  snapshot. No-live, no-Contest, asset-failure, mobile, keyboard, zoom, and
  reduced-motion states remain truthful and usable.
- The page may use approved local or published CSPC imagery, but it does not
  add generated raster artwork, a carousel package, a new design-system
  runtime, Reverb/Echo, or a second public source of truth.

## Acceptance Criteria

### Authority and identity

- Given an empty installation, when bootstrap runs, then exactly one Global
  Admin is created; rerunning bootstrap returns the same account and a concurrent
  second-admin attempt fails at the database boundary.
- Given the sole Global Admin, when they access any non-archived Event, then all
  administrative operations are authorized without Event membership.
- Given a legacy Event Admin or `event_creator` grant after migration, when it
  is used for administration, then authorization is denied.
- Given a development checkout after setup, when the operator signs in with
  `admin@syntix.test` and `password`, then the sole Global Admin dashboard opens.
- Given a Judge or Tabulator without a matching assignment, when they access or
  mutate a scoring target, then the request is denied even if their Event Role
  is active.
- Given a revoked role or assignment, when the user makes the next request or
  replays a queued command, then authorization fails immediately.

### Registration and rosters

- Given the Global Admin selects an Event, when they create a Participant for
  one of its Delegations, then the Participant appears in the private
  Registration Desk and no login account is created.
- Given a Participant and Division belong to the same Event, when the Global
  Admin adds the Participant to the Delegation's Entry, then Event containment,
  duplicate membership, roster size, role limits, and competition limits are
  validated transactionally.
- Given a Basketball Entry with 15 active student-athletes, when another
  student-athlete is added, then readiness and lock are blocked while permitted
  coach roles remain counted separately.
- Given an eligibility decision, when the Global Admin marks the Participant
  eligible, ineligible, withdrawn, or disqualified, then actor, time, status,
  and required reason are retained and unauthorized/public payloads reveal none
  of the private details.
- Given an Entry has not been locked or published, when the Global Admin edits
  its roster, then the current registration updates and is audited.
- Given an Entry influenced a published Draw or official record, when a roster
  change is required, then the system offers only the applicable explicit
  withdrawal, disqualification, deactivation, unlock/redraw, or correction
  workflow and never hard-deletes the evidence.
- Given an anonymous participant, when they attempt to register themselves,
  then no self-service mutation route is available in this approved slice.

### Scoring and officiality

- Given a live score, when it is displayed publicly, then it is labeled
  `Unofficial` and includes the authoritative update timestamp.
- Given a submitted but unapproved result, when a bracket or public view is
  read, then it does not advance or become official by elapsed time.
- Given an approved contest outcome, when the transaction commits, then the
  bracket may advance but the championship ledger is unchanged.
- Given an eligible final Division Placement approval, when the transaction
  commits, then configured signed ledger effects are created exactly once.
- Given a corrected final placement, when the correction commits, then prior
  records remain available and balancing ledger effects are appended.
- Given unresolved criteria, precision, tie, participation, or required-result
  rules, when activation or approval is attempted, then the affected path is
  blocked with an actionable reason.

### Tournament and sport slices

- Given the seven proposal department teams are eligible, when the Global Admin
  generates a tournament, then all seven appear exactly once in a securely
  randomized saved Draw Order and the eighth slot becomes one automatic BYE.
- Given the same generation command is retried, when the server processes it,
  then it returns the original Draw and bracket; an explicit pre-publication
  redraw creates a separately audited randomized version.
- Given 3, 5, 7, or 8 eligible entries and a saved random Draw, when a
  single-elimination preview is generated, then the next power-of-two structure,
  expected BYEs, and routes are reproducible and contain no BYE-versus-BYE
  contest.
- Given a supported double-elimination field, when approved outcomes are
  recorded, then the first loss routes to the losers bracket, the second loss
  eliminates the Entry, and the reset final activates only when required.
- Given a completed Chess round-robin result, when it is approved, then wins,
  draws, losses, `1/0.5/0` points, and standings update automatically.
- Given an approved sport score, when the frozen outcome profile resolves a
  winner, then win/loss records and the next bracket slots update automatically
  without manual advancement.
- Given two semifinal losers, when semifinal outcomes are approved, then a
  third-place playoff is populated.
- Given one eligible entry, when generation is requested, then the Division is
  marked uncontested and no automatic Champion or points are created.
- Given a Basketball entry with 15 student-athletes, when readiness is checked,
  then it passes; with 16, it fails.
- Given a tied Basketball game, when completion is attempted, then the recorded
  overtime sequence or authorized ruling resolves it before approval.
- Given an Athletics unplayed or cancelled Discipline, when aggregation runs,
  then it contributes zero Sub-Points and no fabricated performance.
- Given a corrected outcome would affect a live, submitted, or approved
  descendant Contest, when approval is attempted without an authorized
  descendant resolution, then the correction is blocked and no bracket node is
  changed.

### Public and PWA behavior

- Given no live Event, when an anonymous visitor opens `/`, then the page shows
  a truthful empty state with no fabricated score, standings, or event context.
- Given a published live Event, when an anonymous visitor opens `/`, then they
  receive only public DTOs and can reach the full Event Board and published
  bracket without authentication.
- Given a public snapshot older than the configured freshness threshold, when it
  is displayed, then the UI shows `Stale`, the snapshot time, and a refresh path.
- Given a private authenticated response, when service-worker caching is used,
  then the response is not stored or served from the public runtime cache.
- Given a QR target is unpublished or removed, when it is opened, then the
  visitor sees a safe event/not-found state and no private existence details.
- Given the Global Admin edits a schedule after publishing it, when an anonymous
  visitor reads the landing page before republish, then the previous public
  snapshot remains visible; after republish, the new snapshot is visible.
- Given the Global Admin withdraws a published cover or schedule with a reason, when an
  anonymous visitor reads the landing page, then the item is absent or uses its
  truthful fallback and the withdrawal remains audited.
- Given multiple live Contests, when an anonymous visitor opens the landing
  page, then the latest-updated Contest is first, every Contest is selectable,
  and rotation never moves focus or ignores reduced-motion preferences.
- Given equal signed Championship totals, when the standings stack renders,
  then both delegations receive equal prominence and neither receives a
  numeric rank.

### Reports and archive

- Given a report request, when generation begins, then the server records an
  immutable input manifest and committed cutoff before the worker reads data.
- Given later rule changes or corrections, when an old report is downloaded,
  then its source manifest and original bytes remain unchanged.
- Given a ledger reconciliation or storage hash failure, when an official
  report is requested, then generation/download is blocked and the incident is
  visible to the authorized Admin.

## Testing and Observability

### Required verification

- Feature tests for closed provisioning, role and assignment isolation,
  participant/roster/eligibility management, approval transitions, public DTO
  allow-lists, corrections, and cache headers.
- Domain tests for rule lifecycle, precision, criteria, deduction, tie,
  placement, ledger, bracket, BYE, round-robin, Athletics, and outcome-matrix
  behavior.
- PostgreSQL integration tests for row locking, concurrent approvals,
  duplicate commands, stale revisions, correction races, and ledger
  reconciliation. SQLite is not sufficient evidence for PostgreSQL locking.
- Browser tests for mobile scoring, public display, keyboard operation, zoom,
  reduced motion, stale/disconnected states, and service-worker boundaries.
- Golden-data tests for proposal-derived judged, bracket, Athletics, and Major
  point examples.
- Security tests for direct URL access, private payload leakage, account
  switching, session revocation, cross-Event participant/Entry mutations, and
  public cache separation.

### Audit and operational evidence

Every security-sensitive or official mutation records actor, Event, target,
timestamp, revision, action, reason where required, and before/after values
appropriate to the privacy class. Operational monitoring should expose failed
commands, unresolved conflicts, stale public snapshots, report failures,
integrity incidents, and delayed or failed public delivery without exposing
private data.

The current worktree verification baseline is recorded in the
[single system implementation plan](../plans/2026-08-09-syntix-system.md).

## Rollout and Migration

### Phase 0 — Decisions and contract alignment

- Adopt the sole-Global-Admin authorization model, automatic auditable random
  draws, proposal department model, and automatic sport outcome calculations
  recorded in this PRD.
- Keep unresolved configurations visibly blocked.
- Keep this consolidated PRD and the single system implementation plan as the
  only Markdown documentation authorities; source PDF/DOCX artifacts are
  evidence, not executable requirements.

### Phase 1 — Event readiness and configuration

- Complete Admin configuration and readiness workflows around the existing
  domain foundation.
- Migrate legacy Event Admin and `event_creator` authorization, enforce one
  Global Admin, seed the seven department/campus units and Event Delegations,
  and finish the proposal programme and criteria configuration.
- Deliver the Event-selected Registration Desk for Global Admin participant,
  Entry, roster, and eligibility management before treating registration
  readiness as complete.

### Phase 2 — Tournament and scoring automation

- Deliver automatic random draws, single and double elimination, round robin,
  BYEs, advancement, sport outcome profiles, win/loss/draw standings, final
  placement derivation, and proposal championship-point effects.
- Prove assignment-scoped Tabulator scoring, revision/idempotency, submission,
  Global Admin approval, reproducible bracket routing, ledger effects, public
  unofficial display, and correction behavior.
- Keep game-clock and advanced statistics claims out of scope.

### Phase 3 — Public and operational hardening

- Stabilize sanitized HTTP public contracts, PWA safety, accessibility, QR
  deep links, freshness behavior, and the approved landing-page redesign.
- Deliver the Admin Public Programme Desk and revisioned cover/schedule
  publication workflow described in the system implementation plan
  [`docs/plans/2026-08-09-syntix-system.md`](../plans/2026-08-09-syntix-system.md).
- Install Reverb/Echo only after HTTP payloads and fallback behavior are stable.

### Phase 4 — Offline synchronization

- Complete account-partitioned outbox lifecycle, online close handshake, queue
  retention, device-loss contingency, conflict review, and PostgreSQL concurrency
  coverage.

### Phase 5 — Reports and archive

- Add immutable source manifests, queued generation, content hashes, PDF/archive
  artifacts, certification metadata, retention workflows, and sanitized public
  projections.

No migration may reinterpret historical approved records under a later rule
version. New schema fields or seed data must preserve event, Division, rule,
revision, approval, correction, and ledger references.

## Open Decisions

These are the only canonical unresolved institutional decisions. Each approved
decision must record authority, approval date, affected Event edition, effective
rule version, and required migration/test changes. It blocks only its affected
path.

### Identity, registration, and governance

- **OD-001:** Define non-development Global Admin bootstrap, credential
  rotation, and disaster-recovery authority without permitting a second active
  Global Admin.
- **OD-002:** Decide privileged email-verification requirements and whether the
  institutional mail channel is reliable enough to require verification.
- **OD-003:** Decide whether a result submitter or latest correction actor may
  approve that same result and define any reasoned exceptional override.
- **OD-004:** Define whether one person may simultaneously hold Judge and
  Tabulator roles in the same Event.
- **OD-005:** Define session idle and absolute lifetimes, including any stricter
  Global Admin timeout.
- **OD-006:** Define account, participant, registration, and security-audit
  retention after an Event is archived.
- **OD-007:** Decide whether institutional SSO belongs in a later release.
- **OD-008:** Decide whether students will ever receive a self-service
  registration workflow. Until approved, registration is Global-Admin-only and
  creates no student account.

### Competition rules, judging, and points

- **OD-009:** Define participation-point eligibility for withdrawals, no-shows,
  walkovers, forfeits, disqualifications, incomplete entries, and cancelled
  competitions.
- **OD-010:** Define official tie-break sequences for every Division, aggregate
  Division, and the overall championship.
- **OD-011:** Define input scale, intermediate precision, final precision,
  rounding mode, and rounding stage for judged and aggregate totals.
- **OD-012:** Define multi-Judge aggregation: average, sum, rank aggregation,
  dropped values, or another approved method.
- **OD-013:** Decide whether negative judged totals floor at zero and at which
  calculation stage.
- **OD-014:** Decide whether protests require a dedicated adjudication workflow
  or remain authorized corrections.
- **OD-015:** Decide whether reports need an independent medal domain; until
  approved, the official output is `General Championship Point Tally`.
- **OD-016:** Correct the 2025 Essay Writing and Pagsulat ng Sanaysay criteria,
  whose displayed values total 95 percent.
- **OD-017:** Correct the 2025 Cheer Dance criteria and confirm its placement
  template.
- **OD-018:** Provide the missing Dance Sports weights and confirm category
  Divisions and point templates.
- **OD-019:** Resolve the Arnis roster conflict between the general table and
  detailed rules.
- **OD-020:** Resolve the Mobile Legends roster conflict between five members
  total and five players plus one reserve.
- **OD-021:** Provide a valid seven-team Esports opponent/playoff schedule.
- **OD-022:** Provide complete Call of Duty rules.
- **OD-023:** Correct source event-year and submission-date text that still
  states 2024.

### Basketball, tournaments, and rulings

- **OD-024:** Define Basketball participation eligibility and the evidence for
  exceptional or tied-result rulings.
- **OD-025:** Clarify whether running time applies through finals and define
  sudden-death reset or score-recording conventions.
- **OD-026:** Define 2nd Runner-Up handling when fewer than four Entries produce
  no two-semifinal-loser playoff or losers-bracket-final candidate.
- **OD-027:** Identify who may authorize walkovers, forfeits, no-shows,
  withdrawals, disqualifications, cancellations, and post-approval corrections.
- **OD-028:** Decide whether a later release needs an authoritative integrated
  game clock and hardware controls.
- **OD-029:** Define whether and when a published bracket may be formally
  unpublished before any Contest activity and what committee approval is
  required.
- **OD-030:** Define sport-specific walkover, no-show, forfeit, withdrawal, and
  disqualification effects not already settled by the generic outcome matrix.
- **OD-031:** Define round-robin tie-break sequences. Chess values remain the
  approved `1/0.5/0` for win/draw/loss.

### Athletics

- **OD-032:** Confirm Athletics aggregate placement instead of the proposal's
  conflicting single-elimination label.
- **OD-033:** Confirm canonical names for the printed `400m x 100m` and `400m x
  400m` relay entries.
- **OD-034:** Confirm Men/Women Divisions for long jump and triple jump.
- **OD-035:** Define heat sizes, qualification standards/counts, lane rules,
  attempt counts, and heat-to-final procedures.
- **OD-036:** Decide whether Men and Women are separate score-bearing Divisions
  or feed one combined aggregate Division.
- **OD-037:** Define input, storage, comparison, and display precision for every
  time and distance Discipline.
- **OD-038:** Define Athletics tie-break sequences for track, field, relay,
  aggregate, and final placement.
- **OD-039:** Define fourth-place, non-medaling, did-not-finish, disqualified,
  withdrawn, and no-show Sub-Point and participation eligibility.
- **OD-040:** Identify who may authorize time-trial or semifinal results for an
  unfinished final.
- **OD-041:** Confirm whether 10 Men and 10 Women are exact roster sizes or
  maxima and define alternates and relay replacements.
- **OD-042:** Define maximum relay changes, the comparison baseline, and when a
  qualification becomes final.
- **OD-043:** Define the 3000m overlap meaning, removal order, timing/status
  behavior, and behavior with six or fewer starters.
- **OD-044:** Define counting semantics for athlete and Delegation Discipline
  limits across entry, start, substitution, and qualification.

### Offline operations

- **OD-045:** Define which Judge and Tabulator command types may be queued
  offline.
- **OD-046:** Decide whether logout with pending commands is blocked or permits
  explicit local deletion after identity confirmation and incident logging.
- **OD-047:** Define retention for applied, rejected, conflicted, and abandoned
  outbox commands and receipts.
- **OD-048:** Define event-day contingency for IndexedDB failure, quota
  exhaustion, device loss, and unsynchronized commands.
- **OD-049:** Decide whether managed devices require remote wipe, extra local
  encryption, or other controls.
- **OD-050:** Define authorized conflict-resolution choices and required
  reasons.

### Public, reports, and archive

- **OD-051:** Decide whether participant names may be published after approval,
  for which Divisions, and who approves the setting.
- **OD-052:** Decide which provisional Division rankings may be public in
  addition to raw performance.
- **OD-053:** Define publication timing for schedules, brackets, corrections,
  voids, archived Events, and detailed reports.
- **OD-054:** Define freshness thresholds for live and non-live public
  snapshots.
- **OD-055:** Decide whether archived pages retain approved participant names
  or anonymize them after retention.
- **OD-056:** Identify public resources requiring printed QR codes and the
  owner of link verification.
- **OD-057:** Define retention and disposition for participant data, private
  reports, generated artifacts, audit evidence, and official results.
- **OD-058:** Define report signatures, certification blocks, report numbers,
  paper size, branding, PDF/A, digital signatures, and timestamp requirements.
- **OD-059:** Define whether any staff other than the Global Admin may access
  limited reports and the exact assignment boundary.
- **OD-060:** Define append-only correction policy after an Event is Closed or
  Archived.
- **OD-061:** Decide whether reports for contradictory 2025 rules must be
  withheld until corrected rule versions are approved.

## Review Questions

The sole Global Admin, seven department teams, Global-Admin-only participant
registration, automatic random Draw, 2/4/8 double-elimination baseline,
automatic tournament progression, Chess `1/0.5/0`, sport-result calculations,
and proposal point tables are approved product decisions. The remaining review
question is whether student self-service registration should ever be added;
until answered, it remains out of scope under OD-008. Source defects above stay
visibly blocked and do not block unaffected sports or criteria.
