# Syntix light system, admin theme, and Judge scorecard implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove dark mode from Syntix, apply the approved Official Results Bulletin visual system throughout the authenticated admin workspace without changing admin page structure, and rebuild the Judge scorecard in the approved Guided Criteria Focus composition.

**Architecture:** Establish one semantic light token set and a presentation-only admin component layer. The authenticated shell and admin routes consume that layer while retaining their current data flow, DOM order, routes, and business rules. The Judge scorecard remains route-owned and adds local active-criterion presentation state without changing its server-authoritative draft and submit lifecycle.

**Tech Stack:** Laravel 13.8, PHP 8.4, Inertia 2, React 18, Tailwind CSS 4.3, Headless UI 2, Vitest 3, Testing Library 16, PostgreSQL, Vite 8.

**Spec:** `docs/superpowers/specs/2026-08-22-syntix-light-system-scorecard-redesign.md`

## Global constraints

- Work directly on `master`. Preserve unrelated logo and icon changes already present in the worktree.
- Syntix is permanently light-only. Remove theme switching, stored-theme boot logic, dark variants, and backend theme preferences.
- Keep text-size, high-contrast, and reduced-motion preferences.
- Admin pages retain their current information architecture, content order, responsive topology, routes, permissions, and workflows.
- The Judge scorecard is the only page receiving structural recomposition.
- Laravel remains authoritative for score calculations, rounding, revisions, permissions, and submission state.
- Use the approved comp at `.impeccable/mocks/scorecard-results-bulletin-b.png` as the Judge scorecard composition reference.
- Use sampled visual roles: `#FEFEFE` ground, `#001A3F` ink, `#1D86A6` teal, `#BEC6DB` rule, `#FAFCFC` quiet field, and a contrast-adjusted correction red near `#EB4442`.
- Use Figtree for interface copy and Barlow Condensed for operational numerals. Add no font dependency.
- Use semantic HTML, 44-pixel touch targets, visible focus, status text alongside color, and no horizontal scorecard scroll at 360 pixels.
- Do not add a signature, official seal, invented scale labels, Head Judge visibility claims, or client-authoritative totals.
- Follow TDD for behavior and component contracts. Pure presentation migrations establish a passing behavior baseline, use the current render as the visual failure, and end with the same behavior tests plus browser comparison against the approved design.

## Execution skill routing

1. Invoke `executing-plans` for inline task execution on `master`, honoring the user's earlier execution choice.
2. Invoke `test-driven-development` before Task 1 and keep its red, green, refactor discipline through every behavior or UI change.
3. Before Task 2, invoke `frontend-design`, `tailwindcss-development`, `tailwind-design-system`, and `impeccable`. Load Impeccable's craft floor before the first UI edit.
4. Before editing Tailwind, resolve the registry's stable `latest` tag and consult the matching official Tailwind CSS documentation. If the installed `4.3.3` line is no longer current, upgrade Tailwind and its Vite integration in Task 2 before applying utilities.
5. Use `systematic-debugging` for any unexpected test, build, or browser failure.
6. Use `verification-before-completion` before Task 9 reports completion, commits the final correction set, or pushes.

---

### Task 1: Remove the backend and boot-time theme contract

**Files:**

- Modify: `app/Models/User.php`
- Modify: `app/Http/Controllers/SettingsController.php`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Modify: `resources/views/app.blade.php`
- Delete: `resources/js/lib/theme.js`
- Modify: `tests/Feature/SettingsTest.php`
- Modify: `tests/Feature/Backend/PostgresContractTest.php`

**Interfaces:**

- Consumes: existing user preference JSON and `User::normalizedPreferences()` callers.
- Produces: normalized preferences with `text_size`, `contrast`, `reduce_motion`, `default_event_id`, `default_landing`, and `notifications`; no `theme` key and no `ui.theme_scope` prop.

- [ ] **Step 1: Replace the theme feature tests with a light-only contract**

Add assertions equivalent to:

```php
public function test_theme_is_not_part_of_the_supported_preference_contract(): void
{
    $user = User::factory()->create([
        'preferences' => ['theme' => 'dark', 'text_size' => 'large'],
    ]);

    $this->assertArrayNotHasKey('theme', $user->normalizedPreferences());
    $this->assertSame('large', $user->normalizedPreferences()['text_size']);

    $this->actingAs($user)
        ->patch('/settings/preferences', ['theme' => 'dark'])
        ->assertSessionHasErrors('theme');
}
```

Update Settings Inertia assertions so `preference_options.themes`, `preferences.theme`, `auth.user.preferences.theme`, and `ui.theme_scope` are absent. Update the PostgreSQL contract fixture to prove a legacy stored `theme` key is ignored.

- [ ] **Step 2: Run the focused backend tests and confirm the old contract fails**

Run:

```powershell
docker compose exec -T app php artisan test tests/Feature/SettingsTest.php tests/Feature/Backend/PostgresContractTest.php
```

Expected: failures show that normalized preferences still return `theme`, the controller still accepts it, and Inertia still shares theme scope.

- [ ] **Step 3: Remove theme from the model, controller, middleware, and boot template**

In `User.php`, remove `theme` from `DEFAULT_PREFERENCES`, validation in `normalizedPreferences()`, and the returned array. In `SettingsController.php`, remove theme options and store no theme. Explicitly reject stale clients:

```php
$validated = $request->validate([
    'theme' => ['prohibited'],
    'text_size' => ['sometimes', 'required', Rule::in(['default', 'large', 'x-large'])],
    'contrast' => ['sometimes', 'required', Rule::in(['default', 'high'])],
    // existing supported fields stay unchanged
]);
```

When rebuilding `$user->preferences`, omit `theme`. This naturally strips a legacy key on the next supported preference save.

In `HandleInertiaRequests.php`, remove `$themeScope`, the `ui` prop, and `usesAdminTheme()`. In `app.blade.php`, remove the pre-hydration theme script, set `<meta name="theme-color" content="#FEFEFE">`, and use a light Apple status-bar style. Delete `resources/js/lib/theme.js`.

- [ ] **Step 4: Run the focused backend tests**

Run the command from Step 2.

Expected: both test files pass.

- [ ] **Step 5: Commit the backend light-only contract**

```powershell
git add app/Models/User.php app/Http/Controllers/SettingsController.php app/Http/Middleware/HandleInertiaRequests.php resources/views/app.blade.php resources/js/lib/theme.js tests/Feature/SettingsTest.php tests/Feature/Backend/PostgresContractTest.php
git commit -m "feat: remove theme preference contract"
```

---

### Task 2: Establish light tokens and rebuild the authenticated shell

**Files:**

- Modify: `resources/css/app.css`
- Modify: `resources/js/Layouts/AuthenticatedLayout.jsx`
- Modify: `resources/js/Layouts/GuestLayout.jsx`
- Modify: `resources/js/Components/PrimaryButton.jsx`
- Modify: `resources/js/Components/SecondaryButton.jsx`
- Modify: `resources/js/Components/DangerButton.jsx`
- Modify: `resources/js/Components/TextInput.jsx`
- Modify: `resources/js/Components/Modal.jsx`
- Modify: `resources/js/Components/SlideOver.jsx`
- Modify: `tests/ui/AuthenticatedLayout.test.jsx`
- Modify: `tests/ui/TailwindV4Config.test.jsx`
- Modify: `tests/ui/BrandIdentity.test.jsx`

**Interfaces:**

- Consumes: existing `auth`, `active_event`, role, badge, notification, accessibility-preference, and navigation props.
- Produces: one light semantic token contract and the same responsive shell behavior with bulletin styling.

- [ ] **Step 1: Write failing shell behavior tests**

Replace dark-root tests with assertions that the layout applies only accessibility data attributes and never mutates a dark class or local storage. Render `AuthenticatedLayout` with a legacy `preferences.theme = 'dark'` fixture and a dark operating-system preference. Assert the navigation remains present, `document.documentElement` has no `dark` class, and `localStorage` receives no `syntix-theme` write.

- [ ] **Step 2: Run the focused UI tests and confirm failure**

Run:

```powershell
npm.cmd run test:ui -- --run tests/ui/AuthenticatedLayout.test.jsx tests/ui/TailwindV4Config.test.jsx tests/ui/BrandIdentity.test.jsx
```

Expected: tests fail on the current dark token block, theme effects, and dark sidebar classes.

- [ ] **Step 3: Replace CSS tokens and remove every dark rule**

Set one root token set in `app.css`:

```css
:root {
    color-scheme: light;
    --background: #fefefe;
    --foreground: #001a3f;
    --surface: #ffffff;
    --surface-muted: #fafcfc;
    --muted: #52677f;
    --border: #bec6db;
    --primary: #1d86a6;
    --primary-hover: #166d88;
    --primary-foreground: #ffffff;
    --accent: #e8aa32;
    --accent-foreground: #001a3f;
    --danger: #c83f3f;
    --danger-surface: #fff2f1;
    --sidebar: #fefefe;
    --ring: #1d86a6;
}
```

Keep the existing high-contrast, text-size, reduced-motion, and print behavior. Remove `@custom-variant dark`, `html.dark`, and all legacy dark utility overrides.

- [ ] **Step 4: Rebuild shared controls and the application shell in the bulletin grammar**

In `AuthenticatedLayout.jsx`, remove theme imports and effects. Keep the effect that applies `data-text-size`, `data-contrast`, and `data-reduce-motion`. Convert navigation from white-on-navy to navy-on-white with a teal ruled active state:

```jsx
const classes = `group flex min-h-11 items-center gap-3 border-l-[3px] px-4 py-2.5 text-sm font-semibold focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring ${active
    ? 'border-primary bg-primary/10 text-foreground'
    : 'border-transparent text-muted hover:bg-surface-muted hover:text-foreground'}`;
```

Use a light sidebar, cool rules, compact top bar, and the user's existing `ApplicationLogo` markup. Preserve event switching, role links, badges, notification popover, mobile drawer, and sign-out behavior.

Restyle shared buttons, inputs, modal, slide-over, and guest layout with small radii, hairline rules, no decorative shadows, and semantic tokens. Do not overwrite current logo asset changes.

- [ ] **Step 5: Run focused UI tests and build CSS once**

Run:

```powershell
npm.cmd run test:ui -- --run tests/ui/AuthenticatedLayout.test.jsx tests/ui/TailwindV4Config.test.jsx tests/ui/BrandIdentity.test.jsx
npm.cmd run build
```

Expected: focused tests pass and Vite emits the light token utilities without unknown-class errors.

- [ ] **Step 6: Commit the light foundation and shell**

```powershell
git add resources/css/app.css resources/js/Layouts/AuthenticatedLayout.jsx resources/js/Layouts/GuestLayout.jsx resources/js/Components/PrimaryButton.jsx resources/js/Components/SecondaryButton.jsx resources/js/Components/DangerButton.jsx resources/js/Components/TextInput.jsx resources/js/Components/Modal.jsx resources/js/Components/SlideOver.jsx tests/ui/AuthenticatedLayout.test.jsx tests/ui/TailwindV4Config.test.jsx tests/ui/BrandIdentity.test.jsx
git commit -m "style: establish permanent light application shell"
```

---

### Task 3: Add the presentation-only admin visual layer

**Files:**

- Create: `resources/js/Components/Admin/AdminSurface.jsx`
- Create: `resources/js/Support/adminStyles.js`
- Create: `tests/ui/AdminVisualSystem.test.jsx`

**Interfaces:**

- Produces: `AdminMasthead`, `AdminSection`, `AdminEmptyState`, and `adminStyles` class strings. These components receive ordinary content and never own routes, forms, permissions, or data loading.
- Consumes: semantic Tailwind token utilities from Task 2.

- [ ] **Step 1: Write failing primitive tests**

```jsx
test('admin masthead and section expose bulletin landmarks without business logic', async () => {
    const { AdminMasthead, AdminSection } = await import('@/Components/Admin/AdminSurface');
    render(<><AdminMasthead eyebrow="SIKLAB 2026" title="Departments" actions={<button>New department</button>} /><AdminSection title="Directory">Rows</AdminSection></>);

    expect(screen.getByRole('heading', { name: 'Departments' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'New department' })).toBeInTheDocument();
    expect(screen.getByRole('region', { name: 'Directory' })).toHaveClass('border-border');
});
```

Assert heading hierarchy, region labelling, action rendering, optional description behavior, custom class forwarding, and the empty-state action. Visual class details belong to browser review rather than a source-text change detector.

- [ ] **Step 2: Run the new test and confirm module-not-found failure**

```powershell
npm.cmd run test:ui -- --run tests/ui/AdminVisualSystem.test.jsx
```

- [ ] **Step 3: Implement the visual primitives**

`adminStyles.js` exports frozen strings:

```js
export const adminStyles = Object.freeze({
    page: 'min-h-[calc(100vh-4rem)] bg-background p-4 text-foreground sm:p-7 lg:p-8',
    section: 'border border-border bg-surface',
    quietSection: 'border border-border bg-surface-muted',
    primaryAction: 'inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-primary px-4 text-sm font-bold text-primary-foreground hover:bg-primary-hover focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
    secondaryAction: 'inline-flex min-h-11 items-center justify-center gap-2 rounded-md border border-border bg-surface px-4 text-sm font-bold text-primary hover:border-primary hover:bg-surface-muted focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
    dangerAction: 'inline-flex min-h-11 items-center justify-center gap-2 rounded-md border border-danger bg-danger-surface px-4 text-sm font-bold text-danger focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-danger focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
    field: 'min-h-11 w-full rounded-md border-border bg-surface text-foreground focus:border-primary focus:ring-primary disabled:bg-surface-muted disabled:text-muted',
    toolbar: 'flex flex-col gap-3 border-y border-border bg-surface-muted px-4 py-3 sm:flex-row sm:items-center sm:justify-between',
    tableHead: 'border-y border-border bg-surface-muted text-xs font-bold uppercase tracking-[0.08em] text-muted',
});
```

`AdminMasthead` renders a light ruled section with eyebrow, title, optional description, actions, and children. `AdminSection` accepts `title`, `description`, `actions`, `as`, `className`, and children, derives a stable `useId()` heading id, and adds `aria-labelledby`. `AdminEmptyState` renders a dashed ruled region with optional action.

- [ ] **Step 4: Run the primitive test**

Run the command from Step 2. Expected: pass.

- [ ] **Step 5: Commit the visual layer**

```powershell
git add resources/js/Components/Admin/AdminSurface.jsx resources/js/Support/adminStyles.js tests/ui/AdminVisualSystem.test.jsx
git commit -m "feat: add bulletin admin visual primitives"
```

---

### Task 4: Migrate the admin overview, events, departments, and registration pages

**Files:**

- Modify: `resources/js/Pages/Dashboard.jsx`
- Modify: `resources/js/Pages/Admin/Events/Create.jsx`
- Modify: `resources/js/Pages/Admin/Events/PublicProgramme.jsx`
- Modify: `resources/js/Pages/Admin/Departments/Index.jsx`
- Modify: `resources/js/Pages/Admin/Departments/Show.jsx`
- Modify: `resources/js/Pages/Admin/Registrations/Index.jsx`
- Modify: `resources/js/Pages/Admin/Registrations/ParticipantDirectory.jsx`
- Modify: `resources/js/Pages/Admin/Registrations/ParticipantProfileForm.jsx`
- Test: `tests/ui/Dashboard.test.jsx`
- Test: `tests/ui/PublicProgramme.test.jsx`
- Test: `tests/ui/DepartmentDirectory.test.jsx`
- Test: `tests/ui/DepartmentRosters.test.jsx`
- Test: `tests/ui/ParticipantDirectory.test.jsx`

**Interfaces:**

- Consumes: `AdminMasthead`, `AdminSection`, `AdminEmptyState`, and `adminStyles` from Task 3.
- Produces: visually migrated foundational admin routes with unchanged actions and route behavior.

- [ ] **Step 1: Run the route-group behavior baseline**

```powershell
npm.cmd run test:ui -- --run tests/ui/Dashboard.test.jsx tests/ui/PublicProgramme.test.jsx tests/ui/DepartmentDirectory.test.jsx tests/ui/DepartmentRosters.test.jsx tests/ui/ParticipantDirectory.test.jsx
```

Expected: pass before styling. Capture representative desktop screenshots as the visual baseline that fails the approved bulletin direction.

- [ ] **Step 2: Migrate the overview and event pages without moving content**

Replace dark `EventHero` and sports/programme hero styling with `AdminMasthead`. Convert panels and empty states to `AdminSection` or `AdminEmptyState`. Replace local button and field strings with `adminStyles`. Keep existing component order, event switcher behavior, summary copy, schedule workspace, scoped navigation, and form submissions.

Example conversion:

```jsx
<AdminMasthead
    eyebrow="Event workspace"
    title={event.name}
    description={`${summary.competitions || 0} sports and ${summary.divisions || 0} activities`}
    actions={<EventSwitcher event={event} events={events} />}
/>
```

- [ ] **Step 3: Migrate department and registration presentation**

Keep grids, explorer hierarchy, filters, drawers, profile forms, department color data, roster capacity, and selection URL state. Change only classes and repeated presentation wrappers. Department color remains an inline CSS custom property or style object because it is configured identity, not a hard-coded app palette.

Use `adminStyles.toolbar` for filters, `adminStyles.field` for inputs, small-radius ruled sections for cards and drawers, and text/rule state changes instead of hover lift.

- [ ] **Step 4: Run the route-group tests and compare the migrated routes visually**

Run the command from Step 1. Expected: all listed tests pass. Capture the same routes and confirm the old dark hero, large soft cards, hover lift, and one-off palette are gone without content movement.

- [ ] **Step 5: Commit the foundational admin migration**

```powershell
git add resources/js/Pages/Dashboard.jsx resources/js/Pages/Admin/Events resources/js/Pages/Admin/Departments resources/js/Pages/Admin/Registrations tests/ui/Dashboard.test.jsx tests/ui/PublicProgramme.test.jsx tests/ui/DepartmentDirectory.test.jsx tests/ui/DepartmentRosters.test.jsx tests/ui/ParticipantDirectory.test.jsx
git commit -m "style: apply bulletin system to admin foundations"
```

---

### Task 5: Migrate staff, invitations, and settings while removing theme UI

**Files:**

- Modify: `resources/js/Pages/Admin/Accounts/Create.jsx`
- Modify: `resources/js/Pages/Admin/Staff/Index.jsx`
- Modify: `resources/js/Pages/Admin/Staff/PeopleSection.jsx`
- Modify: `resources/js/Pages/Admin/Staff/AssignmentsSection.jsx`
- Modify: `resources/js/Pages/Admin/Staff/ReadinessSection.jsx`
- Modify: `resources/js/Pages/Admin/Staff/StaffDrawer.jsx`
- Modify: `resources/js/Components/StaffSetupHandoffCard.jsx`
- Modify: `resources/js/Pages/Settings/Index.jsx`
- Modify: `tests/ui/Settings.test.jsx`
- Test: `tests/ui/StaffAccessFlow.test.jsx`
- Test: `tests/ui/Task3Operations.test.jsx`

**Interfaces:**

- Consumes: Task 1 preference contract and Task 3 admin visual layer.
- Produces: no Appearance tab or theme form; staff and settings behavior unchanged otherwise.

- [ ] **Step 1: Write failing settings and route-group visual tests**

Replace the old theme-choice test with:

```jsx
test('does not expose theme controls or an Appearance section', async () => {
    const Settings = (await import('../../resources/js/Pages/Settings/Index')).default;
    render(<Settings preferences={preferences} />);

    expect(screen.queryByRole('link', { name: /Appearance/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('radio', { name: /Dark|Light|System/i })).not.toBeInTheDocument();
});
```

- [ ] **Step 2: Run focused tests and confirm failure**

```powershell
npm.cmd run test:ui -- --run tests/ui/Settings.test.jsx tests/ui/StaffAccessFlow.test.jsx tests/ui/Task3Operations.test.jsx
```

- [ ] **Step 3: Remove theme UI and preserve accessibility settings**

Delete the theme import, `theme` default, Appearance section metadata, `THEME_OPTIONS`, `AppearanceCard`, and appearance panel mapping from `Settings/Index.jsx`. Update settings summary copy to name account and preferences rather than appearance. Keep Profile, Accessibility, Workspace, Notifications where authorized, and Security.

Accessibility form payload remains exactly:

```js
{
    text_size: data.text_size,
    contrast: data.contrast,
    reduce_motion: data.reduce_motion,
}
```

- [ ] **Step 4: Migrate staff, invitation, setup-card, drawer, and settings classes**

Keep tab selection, assignment management, invitation form data, QR creation, print portal, session security, and save bars unchanged. Replace old cards, shadows, hard-coded colors, and large radii with the shared classes. The printable setup card may keep its dedicated print layout and privacy warning, but its preview frame adopts the bulletin palette.

- [ ] **Step 5: Run focused tests**

Run the command from Step 2. Expected: pass.

- [ ] **Step 6: Commit the staff and settings migration**

```powershell
git add resources/js/Pages/Admin/Accounts/Create.jsx resources/js/Pages/Admin/Staff resources/js/Components/StaffSetupHandoffCard.jsx resources/js/Pages/Settings/Index.jsx tests/ui/Settings.test.jsx tests/ui/StaffAccessFlow.test.jsx tests/ui/Task3Operations.test.jsx
git commit -m "style: migrate staff and settings to light bulletin system"
```

---

### Task 6: Migrate the complete sports and tournament admin workspace

**Files:**

- Modify: `resources/js/Components/Sports/DivisionStatus.jsx`
- Modify: `resources/js/Components/Sports/DivisionSwitcher.jsx`
- Modify: `resources/js/Components/Sports/SportBreadcrumb.jsx`
- Modify: `resources/js/Components/Sports/SportIdentity.jsx`
- Modify: `resources/js/Components/Sports/SportWorkflowNav.jsx`
- Modify: `resources/js/Components/Sports/SportWorkspaceShell.jsx`
- Modify: `resources/js/Components/Sports/WorkflowNotice.jsx`
- Modify: `resources/js/Pages/Admin/Sports/Index.jsx`
- Modify: `resources/js/Pages/Admin/Sports/Workspace.jsx`
- Modify: `resources/js/Pages/Admin/Sports/Rosters.jsx`
- Modify: `resources/js/Pages/Admin/Sports/RosterAddPlayers.jsx`
- Modify: `resources/js/Pages/Admin/Sports/RosterPlayerList.jsx`
- Modify: `resources/js/Pages/Admin/Sports/Tournament.jsx`
- Test: `tests/ui/SportWorkspaceShell.test.jsx`
- Test: `tests/ui/SportWorkspace.test.jsx`
- Test: `tests/ui/Rosters.test.jsx`
- Test: `tests/ui/RosterPlayerList.test.jsx`
- Test: `tests/ui/Tournament.test.jsx`

**Interfaces:**

- Consumes: Task 2 semantic tokens and Task 3 visual classes.
- Produces: the same sports workflow shell, roster editor, tournament controls, cover-image behavior, and department identity in bulletin styling.

- [ ] **Step 1: Run the focused sports behavior baseline**

```powershell
npm.cmd run test:ui -- --run tests/ui/SportWorkspaceShell.test.jsx tests/ui/SportWorkspace.test.jsx tests/ui/Rosters.test.jsx tests/ui/RosterPlayerList.test.jsx tests/ui/Tournament.test.jsx
```

Expected: pass before styling. Capture the sports directory and one focused roster or tournament route as the visual baseline.

- [ ] **Step 2: Migrate the shared sports shell**

Convert breadcrumbs, identity, division switcher, workflow navigation, statuses, and notices to light ruled regions. Preserve every route generated by `sportWorkspaceRoutes.js`, active-section semantics, all-divisions behavior, and accessible labels. Replace pill-heavy navigation with small-radius ruled controls while keeping the same links and breakpoints.

- [ ] **Step 3: Migrate sports directory, drawers, rosters, and tournament controls**

Keep sport cover images. Replace the dark promotional directory hero with `AdminMasthead`; keep each cover as content inside a ruled sport record. Convert drawers and dialogs to light ruled panels, form fields to `adminStyles.field`, and action groups to shared actions.

Preserve roster readiness, approval, reopen, player-state, discipline, draw, publish, archive, and blocker behavior. Department color coding stays intact through existing `departmentColors` data.

- [ ] **Step 4: Run the focused sports suite and compare the migrated routes visually**

Run the command from Step 1. Expected: pass. Capture the same routes and confirm the approved palette, ruled fields, small radii, and non-lifting interactions without workflow movement.

- [ ] **Step 5: Commit the sports migration**

```powershell
git add resources/js/Components/Sports resources/js/Pages/Admin/Sports tests/ui/SportWorkspaceShell.test.jsx tests/ui/SportWorkspace.test.jsx tests/ui/Rosters.test.jsx tests/ui/RosterPlayerList.test.jsx tests/ui/Tournament.test.jsx
git commit -m "style: migrate sports admin workspace to bulletin system"
```

---

### Task 7: Migrate result approvals and close the admin visual contract

**Files:**

- Modify: `resources/js/Pages/Admin/Approvals/Index.jsx`
- Modify: `resources/js/Components/Sports/DivisionStatus.jsx` if approval states expose a remaining mismatch
- Test: `tests/ui/ResultsWorkspace.test.jsx`

**Interfaces:**

- Consumes: existing result submission and division placement props, selection state, review notes, and approve/reject routes.
- Produces: unchanged evidence and approval behavior in the bulletin table and action grammar.

- [ ] **Step 1: Run the focused result behavior baseline**

```powershell
npm.cmd run test:ui -- --run tests/ui/ResultsWorkspace.test.jsx
```

Expected: pass before styling. Capture scoped and unscoped result views as the visual baseline.

- [ ] **Step 2: Restyle evidence, submission, and placement regions without changing review flow**

Replace the dark Results hero with `AdminMasthead`. Convert evidence tables, submission panels, placement panels, note inputs, and action groups to ruled bulletin sections. Preserve selected-scorecard behavior, approve and reject methods, validation, state copy, and read-only outcomes.

- [ ] **Step 3: Run the focused result tests and compare the route visually**

```powershell
npm.cmd run test:ui -- --run tests/ui/ResultsWorkspace.test.jsx
```

Expected: behavior passes and the same result views use the bulletin system without changing review order.

- [ ] **Step 4: Commit result approval styling**

```powershell
git add resources/js/Pages/Admin/Approvals/Index.jsx resources/js/Components/Sports/DivisionStatus.jsx tests/ui/ResultsWorkspace.test.jsx
git commit -m "style: migrate result approvals to bulletin system"
```

---

### Task 8: Rebuild the Judge scorecard as Guided Criteria Focus

**Files:**

- Modify: `resources/js/Pages/Judge/Scorecard.jsx`
- Modify: `tests/ui/JudgeScorecard.test.jsx`
- Modify: `.impeccable/surfaces/resources-js-pages-judge-scorecard-jsx.md` only if implementation inventory changes

**Interfaces:**

- Consumes: the existing `scorecard` prop, `draftData()`, `draftPayload()`, `useForm`, save and submit routes, revision synchronization, navigation protection, and semantic tokens.
- Produces: local `activeCriterionId`, guided criterion selectors, one active criterion fieldset, compact remaining criterion buttons, server-saved score summary, and unchanged payloads.

- [ ] **Step 1: Add failing guided-focus behavior tests**

Add tests equivalent to:

```jsx
test('opens the first required incomplete criterion and preserves values while switching', async () => {
    const user = userEvent.setup();
    render(<Scorecard scorecard={fixture({ values: { 1: { raw_value: '90', notes: '' }, 2: { raw_value: '', notes: '' } } })} />);

    expect(screen.getByRole('group', { name: 'Musicianship scoring' })).toBeVisible();
    await user.click(screen.getByRole('button', { name: /Tone Quality, scored/i }));
    expect(screen.getByRole('spinbutton', { name: 'Tone Quality Score' })).toHaveValue(90);
});

test('activates the first criterion with a field error', async () => {
    draftErrors['values.1.raw_value'] = 'Enter a valid Musicianship score.';
    render(<Scorecard scorecard={fixture()} />);
    expect(screen.getByRole('group', { name: 'Musicianship scoring' })).toBeVisible();
});

test('labels the authoritative total as saved', async () => {
    render(<Scorecard scorecard={fixture({ calculated_total: '88.5000' })} />);
    expect(screen.getByText('Saved weighted score')).toBeInTheDocument();
    expect(screen.getByText('88.50')).toBeInTheDocument();
});
```

Add a note-disclosure test, compact criterion completion labels, arbitrary criterion count, read-only states, and existing payload regression coverage.

- [ ] **Step 2: Run the Judge scorecard test and confirm failure**

```powershell
npm.cmd run test:ui -- --run tests/ui/JudgeScorecard.test.jsx
```

- [ ] **Step 3: Add active-criterion presentation state without changing form state**

Add helpers:

```js
function criterionComplete(data, criterionId) {
    const value = data.values.find((item) => String(item.criterion_id) === String(criterionId));
    return String(value?.raw_value ?? '').trim() !== '';
}

function initialCriterionId(scorecard, data) {
    return scorecard.criteria.find((criterion) => criterion.required && !criterionComplete(data, criterion.id))?.id
        ?? scorecard.criteria[0]?.id
        ?? null;
}
```

Use `useState` for `activeCriterionId`, keep a score-input ref, and activate the first errored criterion when `draftForm.errors` changes. Criterion buttons set presentation state only. `draftForm.data.values` remains the single form state.

- [ ] **Step 4: Implement the approved desktop and mobile composition**

Build the fixed topology from the comp:

```jsx
<div className="grid border border-border bg-surface xl:grid-cols-[12rem_minmax(0,1fr)_18rem]">
    <CriterionIndex ... />
    <section aria-label="Active criterion">...</section>
    <OfficialSummary ... />
</div>
```

At widths below `xl`, put the criterion selector above the active field and move official references below scoring. Use a prominent Barlow Condensed numeric input, numeric min/mid/max markers only, details-based optional notes, compact remaining-criterion buttons, and a ruled sticky action bar. Preserve previous/next entry navigation and all error, flash, rejection, read-only, save, submit, and unsaved-navigation behavior.

Do not render a signature, seal, qualitative scale labels, or client-calculated official total.

- [ ] **Step 5: Run focused scorecard and scoring feature tests**

```powershell
npm.cmd run test:ui -- --run tests/ui/JudgeScorecard.test.jsx
docker compose exec -T app php artisan test tests/Feature/Scoring
```

Expected: UI and backend scoring suites pass.

- [ ] **Step 6: Commit the scorecard redesign**

```powershell
git add resources/js/Pages/Judge/Scorecard.jsx tests/ui/JudgeScorecard.test.jsx .impeccable/surfaces/resources-js-pages-judge-scorecard-jsx.md
git commit -m "feat: rebuild Judge scorecard as guided focus"
```

---

### Task 9: Verify the whole system, inspect it visually, and document the built world

**Files:**

- Create: `.impeccable/review/desktop.png`
- Create: `.impeccable/review/mobile.png`
- Create: `.impeccable/review/user-<width>.png`
- Create: `.impeccable/review/hero-repro.png`
- Create or modify: `DESIGN.md`
- Modify: `docs/superpowers/specs/2026-08-22-syntix-light-system-scorecard-redesign.md` only if the built system requires a recorded accessibility adjustment

**Interfaces:**

- Consumes: all implementation tasks, approved comp, direction contract, PRODUCT.md, and existing browser session.
- Produces: passing suites, valid browser evidence, Impeccable verdict, and durable DESIGN.md.

- [ ] **Step 1: Run the source scan for removed theme behavior**

```powershell
rg -n -S "dark:|html\.dark|syntix-theme|prefers-color-scheme: dark|theme_scope|lib/theme" resources app tests
```

Expected: no active source references. Fixtures that intentionally submit prohibited legacy `theme` input may remain in tests.

- [ ] **Step 2: Run focused and full verification**

```powershell
npm.cmd run test:ui -- --run
docker compose exec -T app php artisan test tests/Feature/SettingsTest.php tests/Feature/Scoring
npm.cmd run build
```

Expected: all commands exit 0. Record test counts and build output.

- [ ] **Step 3: Run the Impeccable detector once**

```powershell
node C:\Users\monte\.codex\skills\impeccable\scripts\detect.mjs --json
```

Fix mechanical findings in one batch. Do not rerun the detector.

- [ ] **Step 4: Inspect representative routes in one browser batch**

Capture the Judge scorecard plus representative event overview, departments, participant directory, staff, sports, results approvals, settings, login, and public landing states. Check desktop, 360-pixel phone, the user's actual browser width, high contrast, large text, keyboard focus, overflow, sticky-action clearance, and console errors.

Save valid full-page evidence under `.impeccable/review/`. Reproduce the scorecard at the approved comp dimensions in `hero-repro.png`. Open every capture once and reject blank, half-loaded, wrong-route, or motion-obscured evidence.

- [ ] **Step 5: Apply one visual correction batch and confirm once**

Compare the scorecard side by side with `.impeccable/mocks/scorecard-results-bulletin-b.png`. Fix topology, type, rule weight, token drift, focus, overflow, and admin-system mismatches in one batch. Rebuild and recapture the same viewports once.

- [ ] **Step 6: Run the Impeccable finish review and act on its disposition**

Provide the reviewer with the original request, approved answers, artifact paths, all screenshots, direction contract, detector findings, PRODUCT.md, craft-floor path, chosen comp, and surface brief. If the disposition is `fix`, apply the ordered fixes in one batch and submit one verdict pass. If it requests a second unresolved round, show the open table to the user.

- [ ] **Step 7: Generate DESIGN.md from the built system**

After the last correction, run the Impeccable documenter with the project root, built targets, direction contract, PRODUCT.md, and document reference. Confirm DESIGN.md records actual shipped tokens, typography, rules, controls, responsive behavior, and the light-only theme decision.

- [ ] **Step 8: Run final regression commands**

```powershell
npm.cmd run test:ui -- --run
docker compose exec -T app php artisan test tests/Feature/SettingsTest.php tests/Feature/Scoring
npm.cmd run build
git diff --check
```

Expected: all commands exit 0 and `git diff --check` prints nothing.

- [ ] **Step 9: Commit documentation and final corrections**

```powershell
git add DESIGN.md .impeccable/review docs/superpowers/specs/2026-08-22-syntix-light-system-scorecard-redesign.md resources app tests
git commit -m "docs: record Syntix light visual system"
```

- [ ] **Step 10: Push master after confirming worktree scope**

Check `git status --short`, verify unrelated pre-existing logo work was preserved and only intended files are committed, then run:

```powershell
git push origin master
```

Report the final commit, test counts, build result, Impeccable disposition, reviewed routes, and any remaining uncommitted user files.
