# Admin Roster Workflow Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the participant-first registration desk with a sport/division/department roster workspace, a department-grouped participant directory, guided CSV import inside Add players, and a compact Sports & Events submenu.

**Architecture:** Keep Participant as the shared event identity and Entry as the division/department roster container. Add focused read models to the existing Sport and Registration controllers, compose batch mutations from the existing domain actions inside outer transactions, and render roster operations inline in the existing sport workspace. Preserve existing mutation route names while adding only the batch, import, and quick-roster endpoints required by the new flow.

**Tech Stack:** PHP 8.4, Laravel 13, Eloquent, Inertia 2, React 18, Tailwind CSS 3, Headless UI 2, PHPUnit 12, Vite 8.

**Design spec:** `docs/superpowers/specs/2026-08-13-admin-roster-workflow-design.md`

**Implementation status (2026-08-13):** Complete. The backend roster read model, shared readiness service, quick roster creation, transactional membership/eligibility batches, private guided CSV profile import, inline department team sheets, shared profile panels, grouped participant directory, Sports & Events submenu, compact sport directory, and actionable dashboard links are implemented. The project app container (PHP 8.4.24) passes the full Laravel suite and the focused CSV/roster acceptance tests; the Vite production build, PHP syntax checks, diff check, and desktop browser walkthrough also pass. Participant documents and medical permits remain explicitly deferred as agreed.

## Global Constraints

- Use the current codebase and `docs/superpowers/specs/2026-08-13-admin-roster-workflow-design.md` as the source of truth; do not consult the outdated product PRD.
- Keep the primary path `Sport -> Division -> Department roster -> Players`.
- Keep Participant event-scoped and shared across sports; never duplicate Participant records per sport.
- Use `department` (`EventDelegation` ID), not Entry ID, as the stable roster URL selection because a Not started department has no Entry.
- User-facing copy says “department roster” or “team sheet”; `Entry` remains internal terminology.
- Every participant response remains `Cache-Control: private, no-store`; no participant data enters public pages.
- Archived events remain readable and reject every roster, profile, eligibility, CSV, lock, and reopen mutation.
- CSV import exists only inside a selected roster's Add players panel; source files are never persisted.
- Student number is mandatory for CSV import and is normalized with `mb_strtoupper(trim($value))`.
- CSV import never overwrites an existing Participant or moves one between departments.
- New roster memberships begin with pending eligibility; there is no automatic eligibility approval.
- Current rules define athlete minimum/maximum and per-role maximums, not required coach-role minimums. Do not invent required coach rules; label readiness as roster limits and role-limit compliance.
- Keep the approved team-sheet visual direction: flat working surfaces, firm `#CFD6D3` dividers, restrained department color strips, plain status text, minimal rounding, and no metric-card or pill-badge wall.
- Participant documents and medical permits remain a separate future feature and are not implemented in this plan.
- Do not add a CSV/spreadsheet dependency; use native PHP CSV parsing for the bounded format.

---

## File and interface map

### New backend units

- `app/Services/RosterReadModel.php` — builds the selected division's department summaries, selected roster detail, department participant picker, options, and exact readiness blockers.
- `app/Services/ParticipantDirectoryReadModel.php` — builds alphabetical department sections and shared participant assignment summaries for the event directory.
- `app/Services/ParticipantCsvImporter.php` — owns canonical fields, header suggestions, CSV parsing, mapping validation, row classification, and confirmed profile creation.
- `app/Actions/Registrations/CreateDepartmentRoster.php` — creates or returns the current Entry for one validated division/department pair using governing rule data.
- `app/Actions/Registrations/SaveRosterMembershipBatch.php` — calls `SaveRosterMembership::handle()` for every requested participant inside one outer transaction.
- `app/Actions/Registrations/SetEligibilityBatch.php` — calls `SetEligibility::handle()` for every requested participant inside one outer transaction.

### New frontend units

- `resources/js/Components/SlideOver.jsx` — accessible desktop side panel/full-screen mobile dialog using Headless UI.
- `resources/js/Pages/Admin/Sports/Rosters.jsx` — inline team-sheet workspace: department index, roster detail, readiness, Add players, profile editor, lock/reopen controls.
- `resources/js/Pages/Admin/Sports/RosterAddPlayers.jsx` — search, multi-select, per-person roles, quick-create, CSV mapping/preview/confirm, and final batch membership UI.
- `resources/js/Pages/Admin/Registrations/ParticipantDirectory.jsx` — department-grouped event participant directory and shared-profile panel.

### Existing units retained

- `SaveParticipant`, `SaveEntry`, `SaveRosterMembership`, `SetEligibility`, and `TransitionEntryStatus` remain the domain authorities.
- Existing single-record mutation route names remain compatible.
- `Admin/Sports/Workspace.jsx` remains the sport shell.
- `Admin/Registrations/Index.jsx` remains the Inertia page entry point but becomes a thin participant-directory composer.

## Task 1: Lock the roster read-model contract with feature tests

**Files:**
- Create: `app/Services/RosterReadModel.php`
- Modify: `app/Http/Controllers/Admin/SportController.php`
- Modify: `tests/Feature/Admin/SportWorkspaceTest.php`

**Interfaces:**
- Produces: `RosterReadModel::forDivision(Event $event, Competition $sport, Division $division, ?EventDelegation $department): array`.
- Produces: Inertia props `selected_department`, `roster_workspace`, and `roster_options` only when `active_tab === 'rosters'` and a division is selected.
- `roster_workspace` shape:

```php
[
    'departments' => [[
        'id' => '7',
        'name' => 'College of Computer Studies',
        'abbreviation' => 'CCS',
        'color' => '#2355A4',
        'entry_id' => '108', // null when not started
        'state' => 'ready', // not_started|review|ready|blocked|locked
        'summary' => '12 of 15 players',
        'attention' => null, // e.g. '2 eligibility decisions'
    ]],
    'selected' => [
        'department_id' => '7',
        'entry' => null, // full payload below when it exists
        'participants' => [], // same-department active participants, assigned and unassigned
        'readiness' => [
            'ready' => false,
            'blockers' => ['Every active athlete must be marked eligible before lock.'],
            'notices' => ['3 optional athlete places remain.'],
        ],
    ],
]
```

- `roster_options` contains `roster_roles` and `eligibility_statuses` using the existing enum label convention.

- [ ] **Step 1: Write failing workspace payload tests**

In `SportWorkspaceTest`, add tests that:

```php
$url = route('admin.sports.show', [$event, $sport])
    .'?tab=rosters&division='.$division->getKey()
    .'&department='.$delegation->getKey();

$this->actingAs($admin)->get($url)->assertInertia(fn (Assert $page) => $page
    ->where('selected_department', (string) $delegation->getKey())
    ->where('roster_workspace.departments.0.name', fn ($name) => is_string($name))
    ->has('roster_workspace.selected.participants')
    ->has('roster_workspace.selected.readiness.blockers')
    ->where('roster_options.roster_roles.0.value', 'student_athlete'));
```

Also assert all active departments appear alphabetically, a department without an Entry has `state = not_started`, assigned and unassigned same-department participants appear, another department's participant does not appear, a foreign/inactive department query returns 404, and the response is private/no-store.

- [ ] **Step 2: Run the focused test and verify failure**

Run:

```powershell
php artisan test tests/Feature/Admin/SportWorkspaceTest.php
```

Expected: FAIL because `department`, `roster_workspace`, and `roster_options` are not implemented.

- [ ] **Step 3: Implement the focused read model**

Create `RosterReadModel` with one public `forDivision()` method and small private serializers. Load:

```php
$event->loadMissing(['delegations' => fn ($query) => $query->where('is_active', true)->orderBy('name')]);
$division->loadMissing([
    'governingRuleVersion',
    'tournaments',
    'entries.delegation',
    'entries.rosterMembers.participant',
    'entries.eligibilityRecords',
]);
```

Query selected-department participants from `$event->participants()` ordered by display name and do not filter them by existing membership. Compute member and eligibility state only against the selected Entry. Derive readiness with the same athlete-role/minimum/maximum/eligibility rules used by `TransitionEntryStatus`; report notices separately from blockers. A roster is `ready` only when the server would allow lock, `locked` when Entry is locked, `blocked` for concrete immutable/published/rule problems, and `review` for ordinary incomplete work.

Use this deterministic state order:

```php
$state = match (true) {
    $entry === null => 'not_started',
    $entry->entryStatus() === EntryStatus::Locked => 'locked',
    in_array($entry->entryStatus(), [EntryStatus::Withdrawn, EntryStatus::Disqualified], true) => 'blocked',
    $readiness['ready'] => 'ready',
    $hasMissingRule || $hasAdverseEligibility || $hasPublishedTournament => 'blocked',
    default => 'review',
};
```

Pending/missing eligibility and a below-minimum draft are Review work; ineligible/withdrawn/disqualified members, missing governing rules, and published-state conflicts are Blocked.

Update `SportController::show()` to validate `department`, call the read model only for the roster tab with a selected division, and expose null/empty roster props on all other tabs.

- [ ] **Step 4: Run the focused test and verify pass**

Run `php artisan test tests/Feature/Admin/SportWorkspaceTest.php`.

Expected: PASS, including cross-sport division and cross-event department containment.

- [ ] **Step 5: Commit the read-model slice**

```powershell
git add app/Services/RosterReadModel.php app/Http/Controllers/Admin/SportController.php tests/Feature/Admin/SportWorkspaceTest.php
git commit -m "feat: add department roster workspace data"
```

## Task 2: Add quick department-roster creation

**Files:**
- Create: `app/Actions/Registrations/CreateDepartmentRoster.php`
- Modify: `app/Http/Controllers/Admin/RegistrationController.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/Admin/RegistrationDeskTest.php`

**Interfaces:**
- Produces: `CreateDepartmentRoster::handle(User $actor, Event $event, Division $division, EventDelegation $department): Entry`.
- Produces route: `POST /admin/events/{event}/divisions/{division}/departments/{department}/roster`, named `admin.department-rosters.store`.
- Reuses: `SaveEntry::handle()` with rule-derived `entry_mode`, `code = department abbreviation`, and `name = "{abbreviation} {sport name} {division name}"`.

- [ ] **Step 1: Write failing creation and containment tests**

Add tests that post to `admin.department-rosters.store` and assert:

```php
$this->assertDatabaseHas('entries', [
    'competition_division_id' => $division->getKey(),
    'event_delegation_id' => $delegation->getKey(),
    'status' => 'draft',
    'entry_mode' => $division->governingRuleVersion->participantMode()->value,
]);
```

Assert repeat submission returns the same current Entry instead of creating a duplicate; missing governing rule returns an `entry` validation error; foreign event/division/department is denied; archived events are forbidden; and success redirects back to `?tab=rosters&division=...&department=...` with `Roster created.`.

- [ ] **Step 2: Run the failing creation tests**

Run:

```powershell
php artisan test tests/Feature/Admin/RegistrationDeskTest.php --filter=department_roster
```

Expected: FAIL because the action and route do not exist.

- [ ] **Step 3: Implement idempotent quick creation**

Inside `CreateDepartmentRoster`, authorize admin/non-archived access, validate event containment, lock the Event, Division, and EventDelegation, then query the current Entry for the pair. Return it if present. Otherwise require the governing rule and its participant mode, and call `SaveEntry` with generated values. Convert a database unique race into the newly found current Entry.

Add `RegistrationController::storeDepartmentRoster()` and the named route. Redirect to the exact sport roster URL, not `back()`, so the newly created department remains selected.

- [ ] **Step 4: Run creation and legacy Entry tests**

Run:

```powershell
php artisan test tests/Feature/Admin/RegistrationDeskTest.php --filter="department_roster|registration"
```

Expected: PASS; existing Entry mutation behavior remains unchanged.

- [ ] **Step 5: Commit quick roster creation**

```powershell
git add app/Actions/Registrations/CreateDepartmentRoster.php app/Http/Controllers/Admin/RegistrationController.php routes/web.php tests/Feature/Admin/RegistrationDeskTest.php
git commit -m "feat: create department rosters from sport context"
```

## Task 3: Make multi-player membership updates transactional

**Files:**
- Create: `app/Actions/Registrations/SaveRosterMembershipBatch.php`
- Modify: `app/Http/Controllers/Admin/RegistrationController.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/Admin/RegistrationDeskTest.php`

**Interfaces:**
- Produces: `SaveRosterMembershipBatch::handle(User $actor, Event $event, Entry $entry, array $members): Collection`.
- `members` shape: `[['participant_id' => 12, 'role' => RosterMemberRole::StudentAthlete], ...]`.
- Produces route: `PUT /admin/events/{event}/entries/{entry}/members`, named `admin.entry-members.batch`.
- Request body:

```php
[
    'members' => [
        ['participant_id' => 12, 'role' => 'student_athlete'],
        ['participant_id' => 18, 'role' => 'faculty_coach'],
    ],
]
```

- [ ] **Step 1: Write failing batch tests**

Test two valid same-department people are added and receive pending EligibilityRecords. Test duplicate participant IDs fail validation. Test a batch whose final person exceeds a roster/role/competition limit creates no membership or eligibility record for any person. Test foreign event, other department, inactive participant, locked roster, published tournament, and archived event rejection.

- [ ] **Step 2: Run the batch tests and verify failure**

Run `php artisan test tests/Feature/Admin/RegistrationDeskTest.php --filter=batch_membership`.

Expected: FAIL because no batch route/action exists.

- [ ] **Step 3: Implement the batch action and endpoint**

Validate `members` as a nonempty array with at most 100 unique participant IDs and enum roles. In one outer `DB::transaction`, lock the Entry, fetch all Participants with `lockForUpdate()` in request order, reject missing IDs, then call `SaveRosterMembership::handle(..., active: true)` per item. Nested Laravel transactions share the same outer transaction, so a later validation exception rolls back earlier membership, eligibility, and audit writes.

Redirect to the exact roster URL with `Players added to roster.`.

- [ ] **Step 4: Run batch and existing roster-limit tests**

Run:

```powershell
php artisan test tests/Feature/Admin/RegistrationDeskTest.php --filter="batch_membership|basketball_roster|individual_pair"
```

Expected: PASS.

- [ ] **Step 5: Commit batch membership support**

```powershell
git add app/Actions/Registrations/SaveRosterMembershipBatch.php app/Http/Controllers/Admin/RegistrationController.php routes/web.php tests/Feature/Admin/RegistrationDeskTest.php
git commit -m "feat: add players to rosters in one transaction"
```

## Task 4: Add confirmed bulk eligibility decisions

**Files:**
- Create: `app/Actions/Registrations/SetEligibilityBatch.php`
- Modify: `app/Http/Controllers/Admin/RegistrationController.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/Admin/RegistrationDeskTest.php`

**Interfaces:**
- Produces: `SetEligibilityBatch::handle(User $actor, Event $event, Entry $entry, array $participantIds, EligibilityStatus $status, ?string $reason): Collection`.
- Produces route: `PUT /admin/events/{event}/entries/{entry}/eligibility`, named `admin.eligibility.batch`.
- Request body: `participant_ids: int[]`, `status: EligibilityStatus`, `reason: ?string`, `confirmed: true`.

- [ ] **Step 1: Write failing eligibility-batch tests**

Add tests for multiple pending members becoming eligible with one reason, missing `confirmed`, duplicate IDs, person not on the selected roster, and complete rollback when one selected record is stale/forbidden. Retain individual adverse-decision coverage for withdrawal and disqualification.

- [ ] **Step 2: Run the eligibility-batch tests and verify failure**

Run `php artisan test tests/Feature/Admin/RegistrationDeskTest.php --filter=batch_eligibility`.

Expected: FAIL because the batch endpoint is missing.

- [ ] **Step 3: Implement the batch action and endpoint**

Validate 1–100 unique participant IDs, an eligibility enum value, optional reason up to 2,000 characters, and `accepted` confirmation. Use one outer transaction, lock the Entry and selected Participants, then call `SetEligibility::handle()` for each. Preserve the existing adverse-status reason and membership-deactivation semantics; do not add document checks.

- [ ] **Step 4: Run eligibility and lock-state tests**

Run:

```powershell
php artisan test tests/Feature/Admin/RegistrationDeskTest.php --filter="batch_eligibility|eligibility_lock_unlock"
```

Expected: PASS.

- [ ] **Step 5: Commit bulk eligibility**

```powershell
git add app/Actions/Registrations/SetEligibilityBatch.php app/Http/Controllers/Admin/RegistrationController.php routes/web.php tests/Feature/Admin/RegistrationDeskTest.php
git commit -m "feat: record roster eligibility in batches"
```

## Task 5: Separate lock preflight from status transition

**Files:**
- Create: `app/Services/RosterReadiness.php`
- Modify: `app/Actions/Registrations/TransitionEntryStatus.php`
- Modify: `app/Services/RosterReadModel.php`
- Modify: `tests/Feature/Admin/RegistrationDeskTest.php`
- Modify: `tests/Feature/Admin/SportWorkspaceTest.php`

**Interfaces:**
- Produces: `RosterReadiness::forEntry(Entry $entry): array{ready: bool, blockers: array<int,string>, notices: array<int,string>}`.
- Consumed by: `TransitionEntryStatus` before `EntryStatus::Locked` and `RosterReadModel` when presenting state/action copy.

- [ ] **Step 1: Write failing shared-readiness tests**

Build an Entry below minimum, with pending eligibility, above maximum, and with an inactive participant. Assert `RosterReadiness` returns stable plain-language blockers and the sport workspace exposes the same messages. Assert a complete roster returns `ready = true`; remaining space is a notice, not a blocker. Assert role maximums are reported only when exceeded and no “missing coach” blocker is fabricated.

- [ ] **Step 2: Run readiness tests and verify failure**

Run:

```powershell
php artisan test tests/Feature/Admin/RegistrationDeskTest.php tests/Feature/Admin/SportWorkspaceTest.php --filter=readiness
```

Expected: FAIL because readiness exists only as a throwing private method.

- [ ] **Step 3: Extract and reuse readiness**

Move the current lock validation into `RosterReadiness`, adding deterministic blocker collection instead of early throws. Make `TransitionEntryStatus` convert the blocker list into one `entry` validation message when locking. Make `RosterReadModel` use the exact same service for state, blockers, and notices.

- [ ] **Step 4: Run readiness and transition tests**

Run the two focused test files. Expected: PASS, with transition and displayed preflight remaining consistent.

- [ ] **Step 5: Commit readiness extraction**

```powershell
git add app/Services/RosterReadiness.php app/Actions/Registrations/TransitionEntryStatus.php app/Services/RosterReadModel.php tests/Feature/Admin/RegistrationDeskTest.php tests/Feature/Admin/SportWorkspaceTest.php
git commit -m "refactor: share roster lock readiness"
```

## Task 6: Build the event participant directory read model

**Files:**
- Create: `app/Services/ParticipantDirectoryReadModel.php`
- Modify: `app/Http/Controllers/Admin/RegistrationController.php`
- Modify: `tests/Feature/Admin/RegistrationDeskTest.php`

**Interfaces:**
- Produces: `ParticipantDirectoryReadModel::forEvent(Event $event, ?string $query): array`.
- Produces Inertia props `department_sections`, `directory_summary`, `filters.q`, and `options` for `Admin/Registrations/Index`.
- `department_sections` shape:

```php
[[
    'id' => '7',
    'name' => 'College of Computer Studies',
    'abbreviation' => 'CCS',
    'color' => '#2355A4',
    'participant_count' => 24,
    'active_membership_count' => 39,
    'participants' => [[
        'id' => '12',
        'display_name' => 'Alex Santos',
        'student_number' => '2024-00128',
        'email' => null,
        'phone' => null,
        'private_notes' => null,
        'is_active' => true,
        'assignments' => [['sport' => 'Basketball', 'division' => 'Men', 'role' => 'student_athlete']],
    ]],
]]
```

- [ ] **Step 1: Replace legacy directory assertions with failing grouped assertions**

Update `RegistrationDeskTest` so the event-level GET asserts alphabetical department sections, alphabetical people inside them, assignment summaries, section counts, global search excluding nonmatching departments, admin-only access, archived readability, and private/no-store headers. Remove assertions tied only to the legacy flat filters, while retaining Participant mutation and no-delete invariants.

- [ ] **Step 2: Run directory tests and verify failure**

Run `php artisan test tests/Feature/Admin/RegistrationDeskTest.php --filter="directory|private_registration"`.

Expected: FAIL because `department_sections` does not exist.

- [ ] **Step 3: Implement the event directory read model**

Simplify `RegistrationController::index()` to validate only `q` and optional `participant`, call the read model, and return grouped props. Do not return event-wide Entries, eligibility forms, Entry modes, or roster-state controls. Keep participant private fields explicit in the private DTO and include only readable assignment summaries.

The old scoped `competition`/`division` query remains accepted temporarily but redirects to `admin.sports.show?tab=rosters&division=...`; if only competition is supplied, redirect to that sport's roster tab with no silent division default.

- [ ] **Step 4: Run directory and compatibility tests**

Run `php artisan test tests/Feature/Admin/RegistrationDeskTest.php`.

Expected: PASS, including legacy scoped-link redirection and shared-profile mutations.

- [ ] **Step 5: Commit the participant directory model**

```powershell
git add app/Services/ParticipantDirectoryReadModel.php app/Http/Controllers/Admin/RegistrationController.php tests/Feature/Admin/RegistrationDeskTest.php
git commit -m "feat: group event participants by department"
```

## Task 7: Add native CSV parsing, header suggestions, and preview

**Files:**
- Create: `app/Services/ParticipantCsvImporter.php`
- Create: `tests/Feature/Admin/ParticipantCsvImportTest.php`
- Modify: `app/Http/Controllers/Admin/RegistrationController.php`
- Modify: `routes/web.php`

**Interfaces:**
- Produces: `ParticipantCsvImporter::inspect(UploadedFile $file): array` with `headers`, `suggestions`, `source_rows`, `sample_rows`, and `row_count`.
- Produces: `ParticipantCsvImporter::preview(User $actor, Event $event, Entry $entry, array $headers, array $mapping, array $sourceRows): array`.
- Canonical fields: `student_number`, `display_name`, `given_name`, `family_name`, `email`, `phone`, `private_notes`, `active`.
- Produces route `POST /admin/events/{event}/entries/{entry}/participant-import/inspect`, named `admin.participant-import.inspect`.
- Produces route `POST /admin/events/{event}/entries/{entry}/participant-import/preview`, named `admin.participant-import.preview`.

- [ ] **Step 1: Write failing CSV inspection tests**

In `ParticipantCsvImportTest`, use `UploadedFile::fake()->createWithContent()` to cover:

```csv
Student ID,Full Name,Email Address
2024-00128,Alex Santos,alex@example.test
```

Assert suggestions map `Student ID -> student_number`, `Full Name -> display_name`, and `Email Address -> email`. Assert UTF-8 BOM and CRLF support, exact 2 MB application limit, 1,000 nonblank row limit, comma-delimited shape, invalid UTF-8 rejection, NUL/control-character rejection, duplicate/blank headers, wrong column counts, and that no file appears on any Storage disk.

- [ ] **Step 2: Run inspection tests and verify failure**

Run `php artisan test tests/Feature/Admin/ParticipantCsvImportTest.php --filter=inspect`.

Expected: FAIL because importer/routes are absent.

- [ ] **Step 3: Implement bounded native parsing**

Use `SplFileObject::READ_CSV`, strip a BOM from the first header, reject non-UTF-8 input with `mb_check_encoding`, skip fully blank rows, and stop with a validation error after 1,000 data rows. Validate uploads with `file|mimes:csv,txt|max:2048`, but also enforce extension, header shape, and content checks because CSV MIME detection varies.

Header suggestions are deterministic case-insensitive aliases:

```php
[
    'student id' => 'student_number',
    'id number' => 'student_number',
    'student number' => 'student_number',
    'full name' => 'display_name',
    'display name' => 'display_name',
    'email address' => 'email',
    'mobile number' => 'phone',
]
```

Do not persist the file or parsed rows in the server session/cache. Return parsed rows only in the authenticated JSON response with private/no-store headers; the browser keeps them in the open Add players panel. The preview and confirmation requests send those rows back, and the server treats every value as untrusted input and revalidates it.

- [ ] **Step 4: Write and run failing mapping/preview tests**

Test unique source/target mapping, required `student_number` and `display_name`, unmapped columns defaulting to ignore, sample preview update, field limits matching manual creation, active values `true/false/1/0/yes/no`, in-file normalized duplicates, same-department existing reuse, and cross-department conflict.

Run `php artisan test tests/Feature/Admin/ParticipantCsvImportTest.php --filter=preview`.

Expected: FAIL before preview implementation.

- [ ] **Step 5: Implement mapping and preview classification**

Apply the mapping server-side to the submitted source rows, normalize student numbers, and return bounded `new`, `existing`, and `errors` arrays with row numbers plus `normalized_rows` for confirmation. Never return private-note or contact values inside error messages. Any error sets `can_confirm = false`. The inspect and preview controller methods return JSON and explicitly set `Cache-Control: private, no-store`.

- [ ] **Step 6: Run CSV inspect/preview tests**

Run `php artisan test tests/Feature/Admin/ParticipantCsvImportTest.php`.

Expected: PASS for every inspection and preview test created in this task.

- [ ] **Step 7: Commit CSV inspection and preview**

```powershell
git add app/Services/ParticipantCsvImporter.php app/Http/Controllers/Admin/RegistrationController.php routes/web.php tests/Feature/Admin/ParticipantCsvImportTest.php
git commit -m "feat: preview mapped participant CSV files"
```

## Task 8: Confirm participant imports transactionally

**Files:**
- Modify: `app/Services/ParticipantCsvImporter.php`
- Modify: `app/Http/Controllers/Admin/RegistrationController.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/Admin/ParticipantCsvImportTest.php`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`

**Interfaces:**
- Produces: `ParticipantCsvImporter::confirm(User $actor, Event $event, Entry $entry, array $normalizedRows): array{participant_ids: array<int,string>, created: int, existing: int}`.
- Produces route: `POST /admin/events/{event}/entries/{entry}/participant-import/confirm`, named `admin.participant-import.confirm`.
- Produces flash prop `flash.selected_participant_ids` containing only string IDs for the Add players panel.

- [ ] **Step 1: Write failing confirmation tests**

Assert a clean preview creates all new profiles and ParticipantCreated audit records, reuses same-department existing people, creates no User accounts and no RosterMembers, and flashes all selected IDs. Assert tampered normalized rows and changed database state between preview and confirmation are revalidated. Assert any validation/domain/database error leaves zero new Participants and audit rows. Assert archived events and foreign Entries are rejected.

- [ ] **Step 2: Run confirmation tests and verify failure**

Run `php artisan test tests/Feature/Admin/ParticipantCsvImportTest.php --filter=confirm`.

Expected: FAIL because confirmation is absent.

- [ ] **Step 3: Implement transactional confirmation**

Rerun every field, normalization, department, and duplicate validation against the submitted canonical rows, lock the Event and all matching normalized participant keys, then call `SaveParticipant::handle()` for each New row inside one outer transaction. Reuse same-department existing IDs. On success, flash `selected_participant_ids`; do not create memberships.

Extend `HandleInertiaRequests` with:

```php
'selected_participant_ids' => $request->session()->get('selected_participant_ids', []),
```

only for authenticated private responses.

- [ ] **Step 4: Run all CSV tests**

Run `php artisan test tests/Feature/Admin/ParticipantCsvImportTest.php`.

Expected: PASS, including source-file non-retention and no accidental roster membership.

- [ ] **Step 5: Commit confirmation support**

```powershell
git add app/Services/ParticipantCsvImporter.php app/Http/Controllers/Admin/RegistrationController.php routes/web.php app/Http/Middleware/HandleInertiaRequests.php tests/Feature/Admin/ParticipantCsvImportTest.php
git commit -m "feat: import participant profiles from roster CSV"
```

## Task 9: Build the accessible SlideOver and team-sheet roster UI

**Files:**
- Create: `resources/js/Components/SlideOver.jsx`
- Create: `resources/js/Pages/Admin/Sports/Rosters.jsx`
- Modify: `resources/js/Pages/Admin/Sports/Workspace.jsx`
- Modify: `resources/js/Components/AppIcon.jsx`
- Modify: `tests/Feature/Admin/SportWorkspaceTest.php`

**Interfaces:**
- `SlideOver({ show, title, onClose, children, initialFocus })` traps focus, closes on Escape, restores trigger focus, covers the viewport on mobile, and occupies the right side on desktop.
- `Rosters({ event, sport, division, selectedDepartment, workspace, options })` renders the inline roster tab and calls the named quick-create, batch, eligibility, and Entry-status routes.
- Extend `workspaceUrl(eventId, sportId, tab, divisionId, departmentId)` so roster links preserve `department` only on the roster tab.

- [ ] **Step 1: Add failing render-contract assertions**

Extend `SportWorkspaceTest` to assert the roster page receives every prop needed by `Rosters`, including archived state, selected department, plain operational states, participant current-Entry membership, readiness, and enum options. These are server-render contract tests; browser behavior is verified later.

- [ ] **Step 2: Run the workspace test before UI changes**

Run `php artisan test tests/Feature/Admin/SportWorkspaceTest.php`.

Expected: PASS for backend payload; record this as the UI baseline.

- [ ] **Step 3: Implement SlideOver and the department index**

Build `SlideOver` with Headless UI `Dialog`, `DialogPanel`, and `Transition`. In `Rosters`, render:

- Explicit division prompt when no division is selected.
- Stable alphabetical department index with Needs attention checkbox.
- Department abbreviation/name, count/empty message, state text, and one concern.
- Desktop two-column index/detail layout.
- Mobile department list and URL-backed drill-in with Back to departments.
- Department color only as a narrow strip; selected row uses navy with gold edge.

Do not use status dots or pill badges.

- [ ] **Step 4: Implement selected-roster detail and state actions**

Render one flat team-sheet surface with counts, readiness copy, player rows, and the state-derived primary action. Wire:

- Create roster confirmation to `admin.department-rosters.store`.
- Review and lock to `admin.entries.status` with `status = locked`.
- Reopen to a reason-required SlideOver form with `status = active`.
- Manage profile to the shared-profile SlideOver shell.

Set `aria-current="page"` on the active workspace tab and remove the no-op Sport settings link.

- [ ] **Step 5: Build frontend assets**

Run:

```powershell
npm.cmd run build
```

Expected: PASS with no JSX, import, or Tailwind compilation errors.

- [ ] **Step 6: Commit the roster workspace UI**

```powershell
git add resources/js/Components/SlideOver.jsx resources/js/Pages/Admin/Sports/Rosters.jsx resources/js/Pages/Admin/Sports/Workspace.jsx resources/js/Components/AppIcon.jsx tests/Feature/Admin/SportWorkspaceTest.php
git commit -m "feat: render department roster team sheets"
```

## Task 10: Implement Add players, quick-create, and CSV panels

**Files:**
- Create: `resources/js/Pages/Admin/Sports/RosterAddPlayers.jsx`
- Modify: `resources/js/Pages/Admin/Sports/Rosters.jsx`
- Modify: `resources/js/Pages/Admin/Sports/Workspace.jsx`
- Modify: `app/Http/Controllers/Admin/RegistrationController.php`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Modify: `tests/Feature/Admin/RegistrationDeskTest.php`

**Interfaces:**
- `RosterAddPlayers({ event, entry, participants, roles, archived, importedIds, onClose })` owns existing-person selection, per-person role state, quick-create form, and CSV subflow.
- Quick-create continues to use `admin.participants.store` with the Entry's `event_delegation_id` fixed server-side by a new optional validated `entry_id` context.
- Successful quick-create flashes `selected_participant_ids = [$participant->id]` and redirects back to the same roster URL.

- [ ] **Step 1: Write failing quick-create context tests**

Add tests showing `storeParticipant` with a valid `entry_id` ignores/rejects another submitted department, creates the Participant in the Entry department, and returns to the exact roster with the new ID selected. Assert foreign/locked/published/archived context is rejected appropriately while plain event-directory creation remains compatible.

- [ ] **Step 2: Run quick-create tests and verify failure**

Run `php artisan test tests/Feature/Admin/RegistrationDeskTest.php --filter=quick_create`.

Expected: FAIL because Entry-scoped creation/selection is not implemented.

- [ ] **Step 3: Implement safe quick-create context**

Extend participant validation with optional `entry_id`, validate the Entry belongs to the Event, derive `event_delegation_id` from it, call `SaveParticipant`, then redirect to its sport workspace and flash the new ID. Never accept a department override in roster context.

- [ ] **Step 4: Build the Add players panel**

Implement:

- Search across only the supplied same-department participant list.
- On roster / Not on roster text based on the selected Entry.
- Multi-select checkboxes with a Student athlete default.
- Per-selected-person role dropdowns.
- Exact-name confirmation summary.
- Submission to `admin.entry-members.batch`.
- Compact quick-create with identity fields visible and private notes in a collapsed disclosure.
- Preserve panel context on server validation errors.

- [ ] **Step 5: Build guided CSV mapping in the same panel**

Implement Download template client-side from the canonical header line; upload to inspect; render suggested mapping dropdowns with one-to-one target enforcement; update a five-row sample preview; request server preview; render New, Already exists, and row-numbered Errors; disable confirmation while errors exist; confirm profile import; merge `flash.selected_participant_ids` into the multi-select state; require the final Add to roster action.

Do not expose a CSV control in the event directory.

- [ ] **Step 6: Run backend tests and frontend build**

Run:

```powershell
php artisan test tests/Feature/Admin/RegistrationDeskTest.php tests/Feature/Admin/ParticipantCsvImportTest.php
npm.cmd run build
```

Expected: PASS.

- [ ] **Step 7: Commit Add players and CSV UI**

```powershell
git add resources/js/Pages/Admin/Sports/RosterAddPlayers.jsx resources/js/Pages/Admin/Sports/Rosters.jsx resources/js/Pages/Admin/Sports/Workspace.jsx app/Http/Controllers/Admin/RegistrationController.php app/Http/Middleware/HandleInertiaRequests.php tests/Feature/Admin/RegistrationDeskTest.php
git commit -m "feat: streamline adding roster players"
```

## Task 11: Add bulk eligibility and shared-profile panels

**Files:**
- Modify: `resources/js/Pages/Admin/Sports/Rosters.jsx`
- Create: `resources/js/Pages/Admin/Registrations/ParticipantProfileForm.jsx`
- Modify: `resources/js/Pages/Admin/Sports/RosterAddPlayers.jsx`
- Modify: `resources/js/Pages/Admin/Registrations/Index.jsx`
- Modify: `tests/Feature/Admin/RegistrationDeskTest.php`

**Interfaces:**
- `ParticipantProfileForm({ participant, eventId, departments, fixedDepartmentId, archived, onSaved })` is reused by the roster panel and event directory.
- Roster bulk eligibility submits to `admin.eligibility.batch` only after a confirmation screen lists exact names, target status, and shared reason.

- [ ] **Step 1: Extract the shared profile form**

Move validation labels and Inertia form logic from the legacy `ParticipantForm` into `ParticipantProfileForm`. When `fixedDepartmentId` is supplied, render department as read-only text and submit that ID. Show: `Changes to this profile affect every sport in this event.`

- [ ] **Step 2: Add bulk eligibility selection and confirmation**

On the roster, allow selection only for current active members. Show a bulk action only when one or more rows are selected. The confirmation panel lists names, chosen status, and reason. Require a nonblank reason in the UI for ineligible, withdrawn, and disqualified, while leaving the server authoritative.

- [ ] **Step 3: Add individual Manage behavior**

Open the shared profile in SlideOver on desktop/full screen on mobile. Keep the team sheet visible behind the dialog. After save, preserve sport/division/department and return focus to the invoking Manage button.

- [ ] **Step 4: Run registration tests and build**

Run:

```powershell
php artisan test tests/Feature/Admin/RegistrationDeskTest.php
npm.cmd run build
```

Expected: PASS.

- [ ] **Step 5: Commit roster eligibility and profile panels**

```powershell
git add resources/js/Pages/Admin/Sports/Rosters.jsx resources/js/Pages/Admin/Registrations/ParticipantProfileForm.jsx resources/js/Pages/Admin/Sports/RosterAddPlayers.jsx resources/js/Pages/Admin/Registrations/Index.jsx tests/Feature/Admin/RegistrationDeskTest.php
git commit -m "feat: manage roster eligibility and shared profiles"
```

## Task 12: Replace the legacy registration page with the department directory UI

**Files:**
- Create: `resources/js/Pages/Admin/Registrations/ParticipantDirectory.jsx`
- Modify: `resources/js/Pages/Admin/Registrations/Index.jsx`
- Delete after migration: legacy-only local components inside `resources/js/Pages/Admin/Registrations/Index.jsx`
- Modify: `tests/Feature/Admin/RegistrationDeskTest.php`

**Interfaces:**
- `ParticipantDirectory({ event, sections, summary, query, departments, selectedParticipantId })` renders grouped sections and the shared profile SlideOver.

- [ ] **Step 1: Implement department sections and search**

Render one flat directory surface with alphabetical section headings, participant count, active membership count, and collapsible people. Global search updates the `q` URL through Inertia and automatically expands matching sections. Empty search explains how to clear the query.

- [ ] **Step 2: Add participant creation and editing**

Reuse `ParticipantProfileForm`. New participant creation requires an active department. Existing people show sport/division/role assignments as secondary text. Do not render Entry selection, eligibility, lock controls, or CSV import.

- [ ] **Step 3: Remove legacy participant-first components**

After the new directory renders, remove `SummaryStrip`, `Filters`, `ParticipantList`, `NewEntryForm`, `EntryEditor`, `RosterControls`, and `RegistrationWorkspace` from `Index.jsx`. Keep only page composition and flash/error handling.

- [ ] **Step 4: Run directory tests and build**

Run:

```powershell
php artisan test tests/Feature/Admin/RegistrationDeskTest.php --filter="directory|participant"
npm.cmd run build
```

Expected: PASS.

- [ ] **Step 5: Commit the participant directory UI**

```powershell
git add resources/js/Pages/Admin/Registrations/ParticipantDirectory.jsx resources/js/Pages/Admin/Registrations/Index.jsx tests/Feature/Admin/RegistrationDeskTest.php
git commit -m "feat: replace registration desk with participant directory"
```

## Task 13: Add the Sports & Events sidebar submenu

**Files:**
- Modify: `resources/js/Layouts/AuthenticatedLayout.jsx`
- Modify: `resources/js/Components/AppIcon.jsx`
- Modify: `tests/Feature/Admin/GlobalDashboardTest.php`
- Modify: `tests/Feature/Admin/PublicProgrammeTest.php`
- Modify: `tests/Feature/Admin/SportWorkspaceTest.php`

**Interfaces:**
- Add `NavGroup({ href, icon, label, active, badge, children, onNavigate })`.
- Children:
  - Sports Directory -> `admin.sports.index`
  - Players & Rosters -> `admin.registrations.index`
  - Schedules & Publishing -> `admin.sports.schedules`

- [ ] **Step 1: Add navigation route-contract tests**

Where practical in feature tests, assert every child destination resolves for the active Event and stays private. Preserve the route names used by current workspace and programme tests. Browser checks cover the client-only expanded state.

- [ ] **Step 2: Implement accessible grouped navigation**

Split the Sports parent into a normal Link and a separate 44px caret button. Give the submenu a stable ID, `aria-expanded`, and `aria-controls`. Initialize expanded when any Sports route is active, preserve manual state while mounted, and close the mobile drawer on child navigation.

Active rules:

```text
Sports Directory: exact admin.sports.index
Players & Rosters: admin.registrations.*, admin.participants.*, admin.entries.*,
                   admin.entry-members.*, admin.eligibility.*, admin.participant-import.*
Schedules: admin.sports.schedules or admin.public-programme.index
Parent only: admin.sports.show, admin.sports.tournament,
             admin.sports.discipline-tournament, admin.discipline-entries.*
```

Put the numeric eligibility badge on Players & Rosters. Give active Links `aria-current="page"`. Do not add Matches & Brackets or Results as submenu children.

- [ ] **Step 3: Build assets and run navigation-adjacent tests**

Run:

```powershell
npm.cmd run build
php artisan test tests/Feature/Admin/GlobalDashboardTest.php tests/Feature/Admin/PublicProgrammeTest.php tests/Feature/Admin/SportWorkspaceTest.php
```

Expected: PASS.

- [ ] **Step 4: Commit the navigation hierarchy**

```powershell
git add resources/js/Layouts/AuthenticatedLayout.jsx resources/js/Components/AppIcon.jsx tests/Feature/Admin/GlobalDashboardTest.php tests/Feature/Admin/PublicProgrammeTest.php tests/Feature/Admin/SportWorkspaceTest.php
git commit -m "feat: add sports operations submenu"
```

## Task 14: Reconcile dashboard copy and actionable roster links

**Files:**
- Modify: `app/Http/Controllers/Admin/DashboardController.php`
- Modify: `resources/js/Pages/Dashboard.jsx`
- Modify: `tests/Feature/Admin/GlobalDashboardTest.php`

**Interfaces:**
- Add optional summary field `sports_attention_url` pointing to the first actionable roster or sport setup target.
- Dashboard presents only three area-level attention concepts: Sports & Events, Event Staff, Results.

- [ ] **Step 1: Write failing dashboard attention tests**

Create a pending eligibility record and assert `summary.sports_attention_url` equals the exact sport roster URL with division and department. Assert zero issues returns the Sports Directory URL. Assert dashboard copy/payload no longer models schedule and registration as separate displayed areas.

- [ ] **Step 2: Run dashboard tests and verify failure**

Run `php artisan test tests/Feature/Admin/GlobalDashboardTest.php`.

Expected: FAIL because actionable URL is absent and the masthead still says five areas.

- [ ] **Step 3: Implement the three-area summary**

In `DashboardController`, derive the first pending eligibility Entry and build `?tab=rosters&division=...&department=...`; otherwise point to the Sports Directory. In `Dashboard.jsx`, count three area statuses, change `All five areas are up to date.` to `Sports, staff, and results are up to date.`, and use `sports_attention_url` for the Sports card.

- [ ] **Step 4: Run dashboard tests and build**

Run:

```powershell
php artisan test tests/Feature/Admin/GlobalDashboardTest.php
npm.cmd run build
```

Expected: PASS.

- [ ] **Step 5: Commit dashboard reconciliation**

```powershell
git add app/Http/Controllers/Admin/DashboardController.php resources/js/Pages/Dashboard.jsx tests/Feature/Admin/GlobalDashboardTest.php
git commit -m "fix: align dashboard with admin navigation"
```

## Task 15: Run regression, privacy, and visual verification

**Files:**
- Modify as defects require: files from Tasks 1–14 only
- Test: `tests/Feature/Admin/SportWorkspaceTest.php`
- Test: `tests/Feature/Admin/RegistrationDeskTest.php`
- Test: `tests/Feature/Admin/ParticipantCsvImportTest.php`
- Test: `tests/Feature/Admin/TournamentWorkspaceTest.php`
- Test: `tests/Feature/Admin/PublicProgrammeTest.php`
- Test: `tests/Feature/Admin/GlobalDashboardTest.php`
- Test: `tests/Feature/Security/ResponseCacheTest.php`

**Interfaces:**
- Produces: a verified admin roster flow with no public-data leakage and preserved bracket/schedule behavior.

- [ ] **Step 1: Run formatter on changed PHP files**

Run:

```powershell
vendor\bin\pint --dirty
```

Expected: changed PHP files conform to the project formatter.

- [ ] **Step 2: Run focused feature regression**

Run:

```powershell
php artisan test tests/Feature/Admin/SportWorkspaceTest.php tests/Feature/Admin/RegistrationDeskTest.php tests/Feature/Admin/ParticipantCsvImportTest.php tests/Feature/Admin/TournamentWorkspaceTest.php tests/Feature/Admin/PublicProgrammeTest.php tests/Feature/Admin/GlobalDashboardTest.php tests/Feature/Security/ResponseCacheTest.php
```

Expected: PASS.

- [ ] **Step 3: Run the complete test suite**

Run:

```powershell
php artisan test
```

Expected: PASS with no regression in bracket, scoring, staff, public, or identity workflows.

- [ ] **Step 4: Build production assets**

Run:

```powershell
npm.cmd run build
```

Expected: PASS.

- [ ] **Step 5: Perform desktop browser walkthrough**

At approximately 1440px wide, verify:

1. Sports & Events expands and its parent opens Sports Directory.
2. Basketball -> Players & Rosters requires a division and preserves it in the URL.
3. Departments are alphabetical and Needs attention does not reorder them.
4. Not started creates and selects a roster without setup fields.
5. Add players searches the selected department, multi-selects, assigns roles, and creates pending eligibility.
6. Quick-create stays in context and warns that the profile is shared.
7. CSV mapping suggests common headings, reports errors by row, creates profiles, and returns them preselected without adding memberships.
8. Bulk eligibility lists exact people before confirmation.
9. Lock shows the same readiness blockers as the team sheet; reopen requires a reason.
10. Event Players & Rosters is a grouped directory with no Entry controls or CSV.

- [ ] **Step 6: Perform mobile and keyboard walkthrough**

At approximately 390px wide, verify the department list drills into one roster and Back to departments returns without losing the division. Using keyboard only, verify sidebar caret, submenu, tabs, participant selection, dialogs, Escape close, focus trap, focus restoration, and visible gold focus. With reduced motion enabled, verify panels do not depend on animated transitions.

- [ ] **Step 7: Inspect public and archived behavior**

Verify public landing/scoreboard/bracket responses contain no participant directory, CSV, private contact, or roster-management metadata. Archive an Event and confirm all new panels render read-only and every new mutation endpoint rejects the write.

- [ ] **Step 8: Review diff and commit verification fixes**

Run:

```powershell
git diff --check
git status --short
```

Confirm only intended files are staged, then:

```powershell
git add app resources/js routes tests
git commit -m "test: verify admin roster workflow"
```

## Deferred follow-up

Create a separate design/spec cycle for participant requirements such as medical permits. That work must decide event-level requirement configuration, private event-participant document ownership, verification and expiry, immutable versions, retention/deletion, download auditing, and malware controls before any file upload code is written.
