# Syntix Settings Account Hub handoff

## 2026-08-16 Settings page work

## Current user request

Create a handoff for the current Settings-page work. The intended product direction is recorded in:

- `docs/superpowers/specs/2026-08-16-settings-account-hub-design.md`

The goal is an account hub at `/settings` that groups the existing account and preference controls without changing their permissions, persistence rules, or the established `/profile` compatibility routes.

## Current implementation status

The worktree contains an in-progress Settings implementation. The main page has been built and its supporting backend/settings preference work is present, but this handoff author has **not** run the tests or a browser verification pass. Treat the implementation as ready for review and verification, not as confirmed complete.

### Delivered UI direction

`resources/js/Pages/Settings/Index.jsx` implements the account hub design:

- Profile, Accessibility, Workspace, and Security categories appear as keyboard-operable Signal-card tabs.
- Profile is selected initially; changing categories retains form values because the panels remain mounted and inactive panels use `hidden`.
- Keyboard activation is intended to move focus to the newly revealed panel heading, while pointer activation leaves focus in place.
- The four categories retain the five original functional areas:
  - Profile
  - Accessibility preferences and live preview
  - Workspace/default event/default landing page
  - Password update and sign out other sessions
- The design uses the existing navy, teal, gold, paper, serif-heading visual system and includes responsive wrapping, reduced-motion-aware transitions, visible focus states, and document preference attributes.

### Supporting backend changes in the worktree

- `app/Http/Controllers/SettingsController.php` serves the Settings Inertia payload, validates/persists user preferences, scopes available events to the signed-in user, and supports ending database-backed sessions other than the current one.
- `database/migrations/2026_08_15_000001_add_preferences_to_users_table.php` adds the nullable JSON `users.preferences` column.
- `app/Models/User.php` includes preference normalization and related user-preference support.
- `app/Http/Middleware/HandleInertiaRequests.php` shares safe preference data with authenticated Inertia responses.
- `app/Http/Controllers/ProfileController.php` and `app/Http/Controllers/Auth/PasswordController.php` redirect successful changes back to `/settings` when submitted from the Settings routes.
- `app/Http/Controllers/Admin/DashboardController.php` reads the saved default event/landing preference for an initial dashboard visit.
- `routes/web.php` introduces the Settings routes while retaining the existing `/profile` routes:

```text
GET     /settings              settings.edit
PATCH   /settings/profile      settings.profile.update
PUT     /settings/password     settings.password.update
PATCH   /settings/preferences  settings.preferences.update
DELETE  /settings/sessions     settings.sessions.destroy
```

### Test coverage added

- `tests/Feature/SettingsTest.php` covers authentication, safe Inertia payloads, profile/password compatibility behavior, preference validation and access scoping, fallback from inaccessible saved events, initial dashboard routing, other-session revocation, and disabled-account rejection.
- `tests/ui/Settings.test.jsx` covers hub rendering, category switching with retained values, keyboard focus behavior, document preference attributes, and the five form action routes.

## Important constraints

- Do not change the Settings routes, backend contracts, permission model, or persistence shape without an explicit architecture decision.
- Preserve `/profile` and its existing named routes for compatibility.
- Accessibility and Workspace intentionally submit independently to the same preferences endpoint; keep their unsaved state independent.
- Default events must remain restricted to events accessible through the user’s active role, except global administrators who can access all events.
- Ending other sessions must retain the current database-backed session and require the current password.
- Disabled accounts may view but must not mutate settings.
- Do not add dependencies.
- Preserve unrelated dirty-worktree changes. In particular, `AGENTS.md`, dashboard/auth/profile/layout/CSS work, and the deleted `copyAGENTS.md` are already modified.

## Recommended review and verification

1. Review the new routes and redirect behavior against the existing profile/password flows, especially validation error redirects.
2. Run the focused Laravel test:

```powershell
docker compose exec -T app php artisan test --filter=SettingsTest
```

3. Run the Settings UI test and production build:

```powershell
docker compose exec -T vite npm run test:ui -- --run tests/ui/Settings.test.jsx
docker compose exec -T vite npm run build
```

4. If the local app is available, verify in a browser:

- Desktop and narrow/mobile layout.
- Pointer and keyboard category switching, including focus after keyboard selection.
- Profile, password, accessibility, workspace, and session-revocation validation/success paths.
- Live `data-text-size`, `data-contrast`, and `data-reduce-motion` changes.
- High-contrast and reduced-motion visual states.
- A non-global user cannot select an inaccessible default event.
- Signing out other sessions preserves the active session.

## Known verification status

No validation command or browser session was run while creating this handoff. Check Docker availability before interpreting a failed container command as an application failure.

## Relevant files

- `docs/superpowers/specs/2026-08-16-settings-account-hub-design.md`
- `resources/js/Pages/Settings/Index.jsx`
- `resources/js/Layouts/AuthenticatedLayout.jsx`
- `resources/css/app.css`
- `app/Http/Controllers/SettingsController.php`
- `app/Http/Controllers/ProfileController.php`
- `app/Http/Controllers/Auth/PasswordController.php`
- `app/Http/Controllers/Admin/DashboardController.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `app/Models/User.php`
- `database/migrations/2026_08_15_000001_add_preferences_to_users_table.php`
- `routes/web.php`
- `tests/Feature/SettingsTest.php`
- `tests/ui/Settings.test.jsx`
