# Settings Follow-up and Top-Tab Settings Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix Settings email and workspace preference correctness, redesign the page as a responsive top-tab Settings interface, and verify the result with Laravel, React, PostgreSQL, build, and real browser checks.

**Architecture:** Keep `/settings` as one authenticated Inertia/React page and preserve all existing mutation routes and the preference JSON shape. Add query-backed client section state, keep inactive forms mounted but hidden to preserve unsaved values, and make the two backend fixes at the existing request/controller boundaries. Use Docker for PHP/PostgreSQL/frontend verification and Codex browser automation for the two-session lifecycle.

**Tech Stack:** Laravel 13, PHP 8.4+, PHPUnit 12, Eloquent, PostgreSQL/SQLite, Inertia 2, React 18, Tailwind CSS 3, Vite 8, Vitest, React Testing Library, Codex browser automation.

**Spec:** `docs/superpowers/specs/2026-08-16-settings-follow-up-design.md`

## Global Constraints

- The Settings page remains a single authenticated Inertia page at `/settings`.
- Existing endpoints remain authoritative: `settings.profile.update`, `settings.preferences.update`, `settings.password.update`, and `settings.sessions.destroy`.
- No separate backend pages, schema changes, preference-shape changes, runtime dependencies, permission changes, or authentication-policy changes.
- Valid section IDs are `profile`, `accessibility`, `workspace`, and `security`; missing or invalid values resolve to `profile`.
- Partial preference PATCH requests must preserve unrelated values and repair stale workspace defaults against the user’s accessible event IDs.
- Email normalization must happen before Laravel uniqueness validation, while the `User` mutator remains in place.
- Use the approved Syntix palette: paper `#F4F5F2`, navy `#082944`/`#17212B`, teal `#0B536D`, gold `#D5A21F`, and subtle borders `#D8DEDC`/`#E6EAE8`.
- Remove the large Settings hero, preference summary strip, account-hub card buttons, “Now editing” badge, and nested decorative card wrappers.
- Desktop uses grouped top tabs; mobile uses a local horizontally scrollable tab rail or compact section select without page-level horizontal overflow.
- Run PHP, frontend, and PostgreSQL verification in Docker because the host PHP is 8.2.12 while the project requires PHP 8.4.1 or newer.
- Do not claim completion until focused tests, PostgreSQL verification, the production build, and browser checks have produced evidence.

---

## File Map

- Modify `app/Http/Requests/ProfileUpdateRequest.php` to canonicalize email input before validation.
- Modify `app/Http/Controllers/SettingsController.php` to normalize current preferences against accessible event IDs before merging partial updates.
- Modify `resources/js/Pages/Settings/Index.jsx` for query-backed top-tab navigation, the four section views, responsive layout, switch semantics, and form feedback.
- Modify `tests/Feature/SettingsTest.php` for email normalization and stale-preference regressions, while retaining existing session and partial-save coverage.
- Modify `tests/ui/Settings.test.jsx` for query navigation, state preservation, section-specific forms, accessibility controls, verification messaging, and processing states.
- Modify `tests/Feature/Backend/PostgresContractTest.php` for PostgreSQL preference JSON/workspace-ID assertions.
- Modify `phpunit.postgres.xml` so the real PostgreSQL run executes both Settings feature coverage and the existing contract suite.

## Interfaces Between Tasks

- `ProfileUpdateRequest::prepareForValidation(): void` normalizes only the `email` input before `rules()` runs; `rules()` and the `User` email mutator remain unchanged otherwise.
- `SettingsController::updatePreferences()` consumes `$events->modelKeys()` and calls `$user->normalizedPreferences($events->modelKeys())` before constructing the complete persisted preference array.
- `SETTINGS_SECTIONS` in `resources/js/Pages/Settings/Index.jsx` is the single client source for section IDs, group labels, titles, descriptions, icons, and tab URLs.
- `useSettingsSection()` returns the current valid section ID and a selector that updates React state plus the browser query string with `history.pushState`; its `popstate` listener restores browser back/forward behavior.
- Each section form keeps its existing Inertia action and submits only its own fields.

---

### Task 1: Normalize Profile Email Before Uniqueness Validation

**Files:**
- Modify: `app/Http/Requests/ProfileUpdateRequest.php:1-30`
- Modify: `tests/Feature/SettingsTest.php` profile validation tests

**Interfaces:**
- Consumes: the existing `ProfileUpdateRequest::rules()` and `User` email mutator.
- Produces: canonical trimmed lowercase email data before the existing unique rule evaluates it.

- [ ] **Step 1: Write the failing regression tests**

Update the existing self-email test input to include whitespace and mixed case, then add a separate duplicate test. The duplicate test must use a different user’s email with case and whitespace changes so the current code reaches the database unique constraint instead of returning validation errors.

```php
public function test_profile_accepts_and_persists_a_trimmed_lowercase_version_of_the_authenticated_email(): void
{
    $user = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'original@example.com',
    ]);

    $this->actingAs($user)
        ->patch('/settings/profile', [
            'name' => 'Original Name',
            'email' => '  ORIGINAL@EXAMPLE.COM  ',
        ])
        ->assertSessionHasNoErrors();

    $this->assertSame('original@example.com', $user->refresh()->email);
}

public function test_profile_rejects_a_case_insensitive_duplicate_before_database_persistence(): void
{
    $user = User::factory()->create(['email' => 'owner@example.com']);
    User::factory()->create(['email' => 'taken@example.com']);

    $this->actingAs($user)
        ->patch('/settings/profile', [
            'name' => 'Owner',
            'email' => "  TAKEN@EXAMPLE.COM\t",
        ])
        ->assertSessionHasErrors('email');

    $this->assertSame('owner@example.com', $user->refresh()->email);
}
```

- [ ] **Step 2: Run the focused tests and verify the current failure**

Run:

```powershell
docker compose up -d --wait
docker compose exec app php artisan test tests/Feature/SettingsTest.php --filter='profile'
```

Expected: the new duplicate test fails with the current database-constraint path or otherwise does not receive an `email` validation error. The self-email test may already pass because the model mutator handles persistence; keep it as regression coverage and use the duplicate test as the red proof for the request-level change.

- [ ] **Step 3: Implement request-level normalization**

Import `Illuminate\Support\Str` and add this method to `ProfileUpdateRequest` before `rules()`:

```php
protected function prepareForValidation(): void
{
    if ($this->has('email')) {
        $this->merge([
            'email' => Str::lower(trim((string) $this->input('email'))),
        ]);
    }
}
```

Do not remove or weaken the `User` model email mutator. Do not change the unique rule or profile controller route.

- [ ] **Step 4: Run the focused tests and verify they pass**

Run:

```powershell
docker compose exec app php artisan test tests/Feature/SettingsTest.php --filter='profile'
```

Expected: both normalization and duplicate tests pass, including the assertion that the original user email remains unchanged after the rejected duplicate.

- [ ] **Step 5: Run the complete Settings feature file**

Run:

```powershell
docker compose exec app php artisan test tests/Feature/SettingsTest.php
```

Expected: the entire Settings feature file passes with no uncaught database exception.

- [ ] **Step 6: Commit the backend email fix**

```powershell
git add tests/Feature/SettingsTest.php app/Http/Requests/ProfileUpdateRequest.php
git commit -m "fix: normalize profile emails before validation"
```

---

### Task 2: Repair Stale Workspace Preferences During Partial Updates

**Files:**
- Modify: `app/Http/Controllers/SettingsController.php:55-96`
- Modify: `tests/Feature/SettingsTest.php` preference update tests

**Interfaces:**
- Consumes: `SettingsController::availableEvents(User): Collection` and `User::normalizedPreferences(Collection|array|null): array`.
- Produces: complete persisted preferences whose `default_event_id` is valid for the current accessible event collection after every successful partial update.

- [ ] **Step 1: Write the failing stale-preference test**

Add a test that stores an inaccessible default, gives the user access to another event, then changes only an accessibility field. The existing controller call without event IDs will preserve the inaccessible value and fail the assertion.

```php
public function test_partial_preference_update_repairs_a_stale_default_event_before_persisting(): void
{
    $user = User::factory()->create([
        'preferences' => [
            'text_size' => 'default',
            'contrast' => 'default',
            'reduce_motion' => false,
            'default_event_id' => 999999,
            'default_landing' => 'sports',
        ],
    ]);
    $accessible = Event::factory()->create(['name' => 'Accessible event']);

    EventUserRole::query()->create([
        'event_id' => $accessible->getKey(),
        'user_id' => $user->getKey(),
        'role' => EventRole::Admin,
        'granted_at' => now(),
    ]);

    $this->actingAs($user)
        ->patch('/settings/preferences', ['text_size' => 'large'])
        ->assertSessionHasNoErrors();

    $preferences = $user->refresh()->preferences;

    $this->assertSame('large', $preferences['text_size']);
    $this->assertSame($accessible->getKey(), $preferences['default_event_id']);
    $this->assertSame('sports', $preferences['default_landing']);
}
```

- [ ] **Step 2: Run the new test and verify it fails for the stale value**

Run:

```powershell
docker compose exec app php artisan test tests/Feature/SettingsTest.php --filter='repairs_a_stale_default_event'
```

Expected: the request succeeds but the persisted `default_event_id` remains `999999`, proving that the current partial merge does not normalize against accessible event IDs.

- [ ] **Step 3: Pass accessible IDs into preference normalization**

In `SettingsController::updatePreferences`, replace:

```php
$current = $user->normalizedPreferences();
```

with:

```php
$current = $user->normalizedPreferences($events->modelKeys());
```

Leave the existing validated-field merge intact so a PATCH containing only `text_size` still preserves `contrast`, `reduce_motion`, `default_event_id`, and `default_landing` after normalization.

- [ ] **Step 4: Run the stale-preference test and the preference regression group**

Run:

```powershell
docker compose exec app php artisan test tests/Feature/SettingsTest.php --filter='preference|workspace|default_event|legacy_boolean'
```

Expected: stale/deleted/inaccessible fallback, archived accessible defaults, empty-workspace behavior, independent section saves, and legacy boolean normalization all pass.

- [ ] **Step 5: Run the complete Settings feature file**

```powershell
docker compose exec app php artisan test tests/Feature/SettingsTest.php
```

Expected: all Settings feature tests pass.

- [ ] **Step 6: Commit the preference repair**

```powershell
git add tests/Feature/SettingsTest.php app/Http/Controllers/SettingsController.php
git commit -m "fix: normalize stale workspace preferences on update"
```

---

### Task 3: Add Query-Backed Top-Tab Navigation and Section State Preservation

**Files:**
- Modify: `resources/js/Pages/Settings/Index.jsx`
- Modify: `tests/ui/Settings.test.jsx`

**Interfaces:**
- Consumes: existing page props (`events`, `preferences`, `mustVerifyEmail`, `other_session_count`, `status`) and existing form components.
- Produces: `SETTINGS_SECTIONS`, `useSettingsSection()`, `SettingsTabs`, and active/inactive section panels with stable local form state.

- [ ] **Step 1: Extend the UI test harness for URL state and write failing tests**

Reset the jsdom URL in `beforeEach` so tests do not leak query parameters:

```jsx
beforeEach(() => {
    window.history.replaceState({}, '', '/settings');
    forms.length = 0;
    globalThis.route = (name) => name === 'settings.edit' ? '/settings' : `/${name}`;
    document.documentElement.removeAttribute('data-text-size');
    document.documentElement.removeAttribute('data-contrast');
    document.documentElement.removeAttribute('data-reduce-motion');
});
```

Add these tests before changing the page:

```jsx
test('opens the section named by the settings query string', async () => {
    window.history.replaceState({}, '', '/settings?section=security');
    const { default: Settings } = await import('../../resources/js/Pages/Settings/Index');

    render(<Settings events={[]} />);

    expect(screen.getByRole('link', { name: /Password & sessions/i })).toHaveAttribute('aria-current', 'page');
    expect(screen.getByRole('heading', { name: 'Security', level: 2 })).toBeVisible();
});

test('selecting a settings tab updates the query string and preserves entered values', async () => {
    const { default: Settings } = await import('../../resources/js/Pages/Settings/Index');

    render(<Settings events={[{ id: 1, name: 'SIKLAB 2026', state: 'preparation' }]} />);
    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Updated name' } });
    fireEvent.click(screen.getByRole('link', { name: /Workspace/i }));
    fireEvent.click(screen.getByRole('link', { name: /Profile/i }));

    expect(window.location.search).toBe('?section=profile');
    expect(screen.getByLabelText('Name')).toHaveValue('Updated name');
});
```

- [ ] **Step 2: Run the focused UI tests and verify the missing-link/query failure**

Run:

```powershell
docker compose exec vite npm run test:ui -- --run tests/ui/Settings.test.jsx
```

Expected: the new tests fail because the current page exposes tab buttons, does not read `window.location.search`, and does not update the query string.

- [ ] **Step 3: Add the section model and URL-aware hook**

Replace the current `HUB_ITEMS` model with a single `SETTINGS_SECTIONS` array:

```jsx
const SETTINGS_SECTIONS = [
    { id: 'profile', group: 'Account', icon: 'users', title: 'Profile', summary: 'Name and email' },
    { id: 'accessibility', group: 'Preferences', icon: 'overview', title: 'Accessibility', summary: 'Display and motion' },
    { id: 'workspace', group: 'Preferences', icon: 'calendar', title: 'Workspace', summary: 'Opening defaults' },
    { id: 'security', group: 'Security', icon: 'settings', title: 'Password & sessions', summary: 'Sign-in safety' },
];

const SETTINGS_SECTION_IDS = SETTINGS_SECTIONS.map((section) => section.id);

function readSettingsSection() {
    const value = new URLSearchParams(window.location.search).get('section');
    return SETTINGS_SECTION_IDS.includes(value) ? value : 'profile';
}

function useSettingsSection() {
    const [selectedSection, setSelectedSection] = useState(readSettingsSection);

    useEffect(() => {
        const handlePopState = () => setSelectedSection(readSettingsSection());
        window.addEventListener('popstate', handlePopState);
        return () => window.removeEventListener('popstate', handlePopState);
    }, []);

    const selectSection = (section) => {
        if (!SETTINGS_SECTION_IDS.includes(section)) return;
        const url = new URL(window.location.href);
        url.searchParams.set('section', section);
        window.history.pushState({ section }, '', `${url.pathname}${url.search}${url.hash}`);
        setSelectedSection(section);
    };

    return { selectedSection, selectSection };
}
```

- [ ] **Step 4: Replace the hub buttons with semantic top-tab links**

Implement `SettingsTabs` with four native anchors. Each anchor must have an `href` generated from `route('settings.edit')` plus `?section=...`, an `aria-current="page"` attribute only for the selected section, an icon, title, group label, and a `data-settings-section` attribute. Its click handler calls `event.preventDefault()` and `selectSection(section.id)` so local form state remains mounted.

Render desktop group labels above the tabs and a mobile horizontal scroll container using `overflow-x-auto`. Do not use a page-level overflow container.

- [ ] **Step 5: Render all panels mounted with only the selected panel visible**

Replace the local selected-category state with `useSettingsSection()`. Keep the four form components mounted in a `SettingsSection` wrapper with:

```jsx
<section
    id={`${section.id}-panel`}
    aria-labelledby={`${section.id}-heading`}
    hidden={!active}
    data-settings-panel={section.id}
>
    <h2 id={`${section.id}-heading`} tabIndex={active ? -1 : undefined}>{section.title}</h2>
    {children}
</section>
```

Only the active section is visible and exposed to the accessibility tree, while each form hook remains alive across navigation.

- [ ] **Step 6: Run the focused UI tests and verify navigation is green**

```powershell
docker compose exec vite npm run test:ui -- --run tests/ui/Settings.test.jsx
```

Expected: query initialization, active navigation, query updates, and value preservation pass. Existing tests that still expect the old tablist/card structure may fail and will be updated in Task 4 as the visual shell is completed.

- [ ] **Step 7: Commit the navigation state change**

```powershell
git add resources/js/Pages/Settings/Index.jsx tests/ui/Settings.test.jsx
git commit -m "feat: add bookmarkable settings section navigation"
```

---

### Task 4: Build the Top-Tab Sections and Form UX

**Files:**
- Modify: `resources/js/Pages/Settings/Index.jsx`
- Modify: `tests/ui/Settings.test.jsx`

**Interfaces:**
- Consumes: `SETTINGS_SECTIONS`, `useSettingsSection()`, existing Inertia form routes, and the server-provided event `state` field.
- Produces: `SettingsField`, `SettingsSelect`, `SettingsToggle`, `SettingsSaveBar`, and the four visually focused section forms with unchanged request payloads.

- [ ] **Step 1: Write failing UI assertions for the new visual and control contract**

Add tests that prove the dashboard-style chrome is gone, the switch has native semantics, archived events are labeled, empty workspaces are clear, and processing feedback is visible:

```jsx
test('uses a compact settings header instead of the dashboard hero and summary strip', async () => {
    const { default: Settings } = await import('../../resources/js/Pages/Settings/Index');

    render(<Settings events={[]} />);

    expect(screen.getByRole('heading', { name: 'Settings', level: 1 })).toBeInTheDocument();
    expect(screen.getByText('Manage your account, preferences, and security.')).toBeInTheDocument();
    expect(screen.queryByText('Make Syntix work for you.')).not.toBeInTheDocument();
    expect(screen.queryByLabelText('Current preferences')).not.toBeInTheDocument();
});

test('renders Reduce Motion as a switch and updates its checked state', async () => {
    const { default: Settings } = await import('../../resources/js/Pages/Settings/Index');

    render(<Settings events={[]} />);
    fireEvent.click(screen.getByRole('link', { name: /Accessibility/i }));

    const toggle = screen.getByRole('switch', { name: 'Reduce motion' });
    expect(toggle).toHaveAttribute('aria-checked', 'true');
    fireEvent.click(toggle);
    expect(toggle).toHaveAttribute('aria-checked', 'false');
});

test('labels archived and empty workspace states without blocking account settings', async () => {
    const { default: Settings } = await import('../../resources/js/Pages/Settings/Index');

    render(<Settings events={[{ id: 1, name: 'SIKLAB 2025', state: 'archived' }]} />);
    fireEvent.click(screen.getByRole('link', { name: /Workspace/i }));
    expect(screen.getByRole('option', { name: /SIKLAB 2025.*Archived/i })).toBeInTheDocument();
});

test('shows an empty workspace state without blocking account settings', async () => {
    const { default: Settings } = await import('../../resources/js/Pages/Settings/Index');

    render(<Settings events={[]} />);
    fireEvent.click(screen.getByRole('link', { name: /Workspace/i }));
    expect(screen.getByText('No events available.')).toBeInTheDocument();
    expect(screen.getByLabelText('First page')).toBeEnabled();
});
```

Extend the UI test `useForm` mock with a `setProcessing(value)` helper that updates the mock form and triggers its internal rerender, then add:

```jsx
test('shows the saving label and disables the active section action while processing', async () => {
    const { default: Settings } = await import('../../resources/js/Pages/Settings/Index');

    render(<Settings events={[]} />);
    forms[0].setProcessing(true);

    expect(screen.getByRole('button', { name: 'Saving...' })).toBeDisabled();
});
```

- [ ] **Step 2: Run the focused UI suite and confirm the visual/control failures**

```powershell
docker compose exec vite npm run test:ui -- --run tests/ui/Settings.test.jsx
```

Expected: the new assertions fail against the existing hero, card, checkbox, and event-label implementation.

- [ ] **Step 3: Replace the dashboard-style page chrome**

In `Settings/Index.jsx`:

- Remove `panel`, the hero section, the preference summary strip, `SettingsHub`, `SignalCard`, and the “Now editing” label.
- Keep the existing authenticated layout and page header.
- Render a compact `<h1>Settings</h1>` plus the approved description.
- Render `SettingsTabs` directly beneath the header.
- Use a quiet content surface with borders and separators only; do not nest form sections inside decorative cards.
- Use the approved navy/teal/gold/paper tokens and the existing serif heading classes.

- [ ] **Step 4: Add focused reusable local controls**

Implement these local components in the same page file:

```jsx
function SettingsSaveBar({ processing, recentlySuccessful, children = 'Save changes', processingLabel = 'Saving...' }) {
    return (
        <div className="flex flex-wrap items-center justify-end gap-3 border-t border-[#E6EAE8] pt-5">
            <button type="submit" className={primaryButton} disabled={processing}>
                {processing ? processingLabel : children}
            </button>
            <StatusMessage visible={recentlySuccessful}>Saved.</StatusMessage>
        </div>
    );
}

function SettingsToggle({ id, label, detail, checked, onChange }) {
    return (
        <div className="flex items-center justify-between gap-4 border-b border-[#E6EAE8] py-4">
            <div>
                <label htmlFor={id} className="block text-sm font-semibold text-[#17212B]">{label}</label>
                <p className="mt-1 text-xs leading-5 text-[#68767E]">{detail}</p>
            </div>
            <button
                id={id}
                type="button"
                role="switch"
                aria-label={label}
                aria-checked={checked}
                onClick={() => onChange(!checked)}
                className={`relative h-6 w-11 shrink-0 rounded-full transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#D5A21F] focus-visible:ring-offset-2 motion-reduce:transition-none ${checked ? 'bg-[#0B536D]' : 'bg-[#AAB8B4]'}`}
            >
                <span aria-hidden="true" className={`absolute left-1 top-1 size-4 rounded-full bg-white transition-transform motion-reduce:transition-none ${checked ? 'translate-x-5' : ''}`} />
            </button>
        </div>
    );
}
```

Add `aria-invalid={Boolean(error)}` and stable error IDs to text/select fields. Keep field labels associated with controls, and keep `InputError` output immediately after each field.

- [ ] **Step 5: Refactor Profile and Accessibility sections**

Keep the existing `useForm` payloads and route calls. Replace card wrappers with:

- Profile heading/description, `ACCOUNT INFORMATION` divider, Name, Email, verification notice, and `SettingsSaveBar`.
- Accessibility heading/description, `DISPLAY` divider, Text size, Contrast, `MOTION` divider, `SettingsToggle`, compact `PreferencePreview`, and `SettingsSaveBar`.

Keep the existing `useEffect` that updates `document.documentElement.dataset.*` as the user edits accessibility controls.

- [ ] **Step 6: Refactor Workspace and Security sections**

For Workspace:

- Map each event to `{ id, name, state }`.
- Render a native option label `${event.name}${event.state === 'archived' ? ' — Archived' : ''}`.
- Keep `default_event_id` and `default_landing` as the only transformed payload fields.
- Render the exact empty-state copy from the spec while leaving First page enabled.

For Security:

- Keep `PasswordCard` and `SecurityCard` as separate forms inside one section.
- Rename the visible password action to `Update password` and keep its existing `put` route.
- Use a `PASSWORD` divider followed by an `ACTIVE SESSIONS` divider.
- Keep the existing `delete` route, current-password validation, `security.reset()` success behavior, and error focus behavior.
- Use restrained amber warning styling for session information without a large card.

- [ ] **Step 7: Update existing UI assertions to the new semantic structure**

Change tests that currently query `role="tab"`, `role="tablist"`, or card headings to query the new links, section headings, labels, and `data-settings-panel` attributes. Preserve assertions for:

- Profile default state.
- Four navigation entries.
- document preference attributes.
- verification prompt.
- entered values surviving section changes.
- independent payload transforms and unchanged route names.

- [ ] **Step 8: Run the focused UI suite and then the full UI suite**

```powershell
docker compose exec vite npm run test:ui -- --run tests/ui/Settings.test.jsx
docker compose exec vite npm run test:ui -- --run
```

Expected: the focused Settings suite and all existing React tests pass with no console errors.

- [ ] **Step 9: Commit the top-tab Settings UI**

```powershell
git add resources/js/Pages/Settings/Index.jsx tests/ui/Settings.test.jsx
git commit -m "feat: redesign settings with top-tab sections"
```

---

### Task 5: Expand and Run the Real PostgreSQL Settings Contract

**Files:**
- Modify: `tests/Feature/Backend/PostgresContractTest.php`
- Modify: `phpunit.postgres.xml`

**Interfaces:**
- Consumes: the migrated PostgreSQL schema, `User` JSON cast, Settings feature routes, and session table.
- Produces: a PostgreSQL test configuration that runs `SettingsTest` plus the existing contract assertions.

- [ ] **Step 1: Add PostgreSQL workspace-ID round-trip assertions**

Extend `test_user_preferences_round_trip_as_nullable_json` with a non-null workspace ID and explicit null reset:

```php
$user = User::factory()->create([
    'preferences' => [
        'text_size' => 'large',
        'contrast' => 'high',
        'reduce_motion' => true,
        'default_event_id' => 42,
        'default_landing' => 'overview',
    ],
]);

$preferences = $user->fresh()->preferences;

self::assertSame(42, $preferences['default_event_id']);

$user->update(['preferences' => null]);
self::assertNull($user->fresh()->preferences);
```

Retain the existing `normalizedPreferences(collect())` assertion for legacy null users.

- [ ] **Step 2: Include Settings feature tests in the PostgreSQL PHPUnit suite**

Change the contract test suite to include both files:

```xml
<testsuite name="PostgreSQL Settings and contract">
    <file>tests/Feature/SettingsTest.php</file>
    <file>tests/Feature/Backend/PostgresContractTest.php</file>
</testsuite>
```

Keep `DB_CONNECTION=pgsql`, `DB_HOST=postgres-contract`, and the other forced PostgreSQL test environment values unchanged.

- [ ] **Step 3: Run the isolated PostgreSQL suite**

Run:

```powershell
docker compose -f compose.yaml -f compose.contract.yaml --profile contract run --rm contract
```

Expected: fresh migrations complete, the Settings feature suite passes against PostgreSQL, and the contract suite passes without PostgreSQL-only skips. This run must exercise nullable preferences, workspace IDs, the normalized duplicate-email validation path, session deletion queries, and legacy null preferences.

- [ ] **Step 4: Commit PostgreSQL contract coverage**

```powershell
git add tests/Feature/Backend/PostgresContractTest.php phpunit.postgres.xml
git commit -m "test: run settings contracts against postgres"
```

---

### Task 6: Execute Full Verification and Browser Session Walkthrough

**Files:**
- No source files are changed in this task; this is the final verification gate.

**Interfaces:**
- Consumes: all committed backend/UI/PostgreSQL changes from Tasks 1–5.
- Produces: command evidence and browser evidence sufficient to claim completion.

- [ ] **Step 1: Confirm the working tree and inspect the complete diff**

Run:

```powershell
git status --short
git diff --check origin/master..HEAD
git log -5 --oneline --decorate
```

Expected: only the intended Settings implementation, tests, configuration, and design/plan documentation are present; `git diff --check` reports no whitespace errors.

- [ ] **Step 2: Run the focused Laravel Settings suite in the application container**

```powershell
docker compose exec app php artisan test tests/Feature/SettingsTest.php
```

Expected: all Settings feature tests pass.

- [ ] **Step 3: Run the focused and complete UI suites in the Vite container**

```powershell
docker compose exec vite npm run test:ui -- --run tests/ui/Settings.test.jsx
docker compose exec vite npm run test:ui -- --run
```

Expected: the Settings suite and the full React suite pass with no console errors.

- [ ] **Step 4: Run the production build**

```powershell
docker compose exec vite npm run build
```

Expected: Vite produces a successful production build with no compilation errors.

- [ ] **Step 5: Start the browser-verification app state**

```powershell
docker compose up -d --wait
```

Use the seeded development account `admin@syntix.test` with password `password` only in the local development browser session. Do not write credentials to repository files.

- [ ] **Step 6: Verify Settings at all required viewport sizes**

Using Codex browser automation, inspect `/settings` at 375px, 768px, 1280px, and 1920px. Confirm:

- The top tab rail stays usable and the page has no horizontal overflow.
- Profile is selected by default.
- `/settings?section=security` opens Security directly.
- Back/forward navigation restores sections.
- Unsaved Name, Accessibility, and Workspace values survive switching sections.
- Verification messaging, inline errors, saving labels, success status, and Reduce Motion switch semantics are visible and usable.
- Archived events include the Archived label and empty workspaces keep First page/account settings usable.

- [ ] **Step 7: Verify real multi-session revocation**

Create two isolated authenticated browser sessions with the same development account:

```text
Session A: log in
Session B: log in
Session A: open /settings?section=security
Session A: enter the current password and choose Sign out other sessions
Session A: request /settings and remain authenticated
Session B: make the next authenticated request and be redirected to login
```

Repeat the Security action with a wrong password, with zero other sessions, and after it has already succeeded. Confirm wrong passwords leave the other session intact and zero/repeated actions return safely.

- [ ] **Step 8: Record final verification status**

Capture the exact pass/fail output from the Laravel, UI, PostgreSQL, and build commands, plus the browser observations. If an environment limitation remains, report it separately and do not label the corresponding verification as passed.
