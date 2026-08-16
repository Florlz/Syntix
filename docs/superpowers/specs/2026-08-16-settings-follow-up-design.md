# Syntix Settings Follow-up and Top-Tab Settings Design

Date: 2026-08-16

## Goal

Finish the remaining Settings stabilization work and reshape `/settings` into a calm, conventional application settings experience. The page will keep the existing Inertia/React single-page architecture, routes, permissions, and preference storage while adding reliable pre-validation email normalization, stale workspace preference repair, bookmarkable section navigation, and focused form UX.

## Scope

This work has three connected deliverables:

1. Backend correctness fixes for profile email validation and partial workspace preference updates.
2. A top-tab Settings interface covering Profile, Accessibility, Workspace, and Security.
3. Automated regression coverage plus Docker-backed PostgreSQL and browser verification.

The Settings page remains a single authenticated Inertia page at `/settings`. Existing endpoints remain the source of truth:

- `settings.profile.update` for Profile.
- `settings.preferences.update` for Accessibility and Workspace.
- `settings.password.update` for password changes.
- `settings.sessions.destroy` for ending other sessions.

No separate backend pages, schema changes, preference-shape changes, runtime dependencies, or permission changes are introduced.

## Confirmed backend behavior

### Email normalization before validation

`ProfileUpdateRequest` will canonicalize an email before Laravel evaluates its validation rules. If the request contains `email`, it will trim surrounding whitespace and lowercase the value using `Str::lower`. The existing `User` model mutator remains as a second safety layer for writes from other code paths.

The validation sequence becomes:

```text
raw request email
    -> trim and lowercase in ProfileUpdateRequest
    -> format and unique validation
    -> User mutator on persistence
```

The feature suite will prove that:

- A user can submit their own address in uppercase and receive no validation error.
- An uppercase or whitespace-variant duplicate belonging to another user returns a normal `email` validation error.
- A successful update persists the canonical lowercase, trimmed value.
- Duplicate handling does not fall through to an uncaught database unique-constraint exception.

### Stale workspace preference repair

`SettingsController::updatePreferences` will load the same accessible event collection used for the Settings page, normalize the current preference state against `$events->modelKeys()`, then merge only the validated fields from the partial request. The normalized complete preference array is persisted on every successful update.

The data flow is:

```text
accessible events
    -> accessible event IDs
    -> normalize stored preferences against those IDs
    -> merge validated partial fields
    -> persist complete normalized preferences
```

This preserves unrelated values during section-specific PATCH requests while repairing a deleted or inaccessible `default_event_id` instead of leaving stale JSON that differs from the UI. Accessible archived events remain valid options and are labeled as archived in the UI.

## Settings information architecture

The page uses four top-level sections:

| Section | Purpose | Submitted fields |
| --- | --- | --- |
| Profile | Personal account information and email verification | `name`, `email` |
| Accessibility | Display and motion preferences | `text_size`, `contrast`, `reduce_motion` |
| Workspace | Sign-in destination preferences | `default_event_id`, `default_landing` |
| Security | Password changes and active-session controls | Password form and `current_password` session form |

Password changes and session revocation share one Security section visually, but remain separate forms and requests so each action submits only its own fields.

## Navigation and state model

The existing `/settings` route remains the only page route. The selected section is represented by the `section` query parameter:

```text
/settings                         -> profile
/settings?section=profile         -> profile
/settings?section=accessibility   -> accessibility
/settings?section=workspace       -> workspace
/settings?section=security        -> security
```

The client will:

- Treat only the four known section IDs as valid.
- Fall back to `profile` for missing or invalid values.
- Update the query string with browser history when a tab is selected.
- Listen for `popstate` so browser back/forward changes the visible section.
- Keep the active section selection refresh-safe without requiring a new controller branch.

All section form components remain mounted and inactive sections use `hidden`. This keeps local `useForm` state, validation errors, and unsaved values intact while only the selected section is exposed visually and semantically.

## Visual design

### Product direction

Syntix Settings is an event-operations utility, not a dashboard landing page. The design uses a paper-and-ink vocabulary:

- Paper background: `#F4F5F2`.
- Navy structure and headings: `#082944` and `#17212B`.
- Teal actions and active text: `#0B536D`.
- Gold focus and active-tab signal: `#D5A21F`.
- Subtle borders: `#D8DEDC` and `#E6EAE8`.
- Serif headings with the existing Syntix treatment.
- System sans-serif for labels, descriptions, controls, and status text.

The signature visual is one restrained gold underline beneath the active top tab. Other hierarchy comes from spacing, typography, group labels, and separators rather than large filled cards, shadows, badges, or decorative gradients.

### Desktop shell

The page structure is:

```text
Settings
Manage your account, preferences, and security.

Account       Preferences                         Security
[ Profile ]   [ Accessibility ] [ Workspace ]    [ Password & sessions ]
────────────────────────────────────────────────────────────────────────

Profile
Update your account information.

ACCOUNT INFORMATION
────────────────────────────────────────────────────────────────────────
Name       [ Florian Monte                                  ]
Email      [ florian@example.com                             ]

                                                   [ Save changes ]
```

The previous large hero, preference summary strip, account-hub label, “Now editing” badge, and nested card wrappers are removed. The selected content area uses a quiet white/paper surface with simple horizontal rules and compact controls.

### Mobile shell

At narrow widths, the tab rail becomes a local horizontal scroll region or compact native select. The page itself does not overflow horizontally. The content stacks vertically, labels sit above controls, and action rows wrap without clipping.

## Section behavior

### Profile

Show a compact `Profile` heading and “Update your account information.” Use an `ACCOUNT INFORMATION` divider above Name and Email. Keep the existing verification warning visible but subtle when required. The profile form keeps non-sensitive input values after validation errors and announces successful saves.

### Accessibility

Show `DISPLAY` and `MOTION` dividers. Keep native select controls for Text size and Contrast. Replace the checkbox-style Reduce Motion control with a semantic switch button using `role="switch"` and `aria-checked`. Keep the live preview secondary: a small bordered preview with restrained copy and no dominant card styling.

The existing document-level `data-text-size`, `data-contrast`, and `data-reduce-motion` updates continue to happen immediately as the user edits the form, before saving.

### Workspace

Show `DEFAULT WORKSPACE` above Default event and First page. Native event options include the event name and an `Archived` suffix when the controller marks the event as archived. If no events are available, show:

```text
No events available.

Your account settings and security options are still available.
```

The First page control remains usable when no event exists.

### Security

Use one Security heading and two separated form regions:

```text
PASSWORD
Current password
New password
Confirm password
                                      [ Update password ]

ACTIVE SESSIONS
2 other sessions are currently signed in.
Current password
                                      [ Sign out other sessions ]
```

Destructive/session controls use restrained warning styling. Password fields clear after successful password or session actions. Wrong passwords focus the relevant field and leave sessions unchanged. Zero other sessions and repeated successful actions remain safe.

## Form UX and accessibility contract

Every save action will:

- Disable its button while processing.
- Show `Saving...` or the action-specific processing label.
- Preserve scroll position through the existing Inertia options.
- Announce success through a `role="status"` message.
- Preserve non-sensitive input values after validation errors.
- Expose inline field errors with live announcements and invalid-field state.
- Preserve visible keyboard focus and gold focus rings.

The top navigation will use real buttons or links with a clear active-state relationship, `aria-current` or equivalent active semantics, and focus-visible styling. The selected content heading receives focus only when section changes originate from keyboard navigation; pointer selection does not cause unexpected focus movement.

## Verification strategy

### Laravel regression coverage

Extend `tests/Feature/SettingsTest.php` with focused tests for:

- Self-owned uppercase email acceptance.
- Other-user uppercase duplicate rejection with session validation errors and no database exception.
- Whitespace trimming and canonical persistence.
- Partial preference saves repairing an inaccessible or deleted stored workspace ID.
- Preservation of unrelated accessibility/workspace preference values.
- Existing session revocation rules, wrong-password behavior, zero-session behavior, and repeated action safety.

### React UI coverage

Update `tests/ui/Settings.test.jsx` to verify:

- Profile is the default section.
- `?section=security` selects Security on first render.
- Tab navigation updates the visible section and browser URL.
- Browser history restores prior sections.
- Entered values survive section changes.
- Each form submits only its own fields and existing routes remain unchanged.
- Accessibility controls update the live preview and document preference attributes.
- Verification messaging renders.
- Processing and success states render with the correct labels.

### PostgreSQL verification

Use the existing `compose.contract.yaml` PostgreSQL service and `phpunit.postgres.xml`. The PostgreSQL run must include the Settings feature suite and the PostgreSQL contract suite. Verify:

- Nullable `users.preferences` JSON round-trip.
- Stored workspace IDs in the JSON payload.
- Legacy users with `preferences = null`.
- Case-insensitive duplicate email behavior through the application validation path.
- Session deletion queries and current-session preservation.
- Existing PostgreSQL model casts and database constraints.

SQLite remains the fast local test configuration, but it is not sufficient evidence for the database portion of this work.

### Browser verification

Use the available Codex browser automation to run a real local-app walkthrough with two isolated authenticated sessions:

```text
Session A: log in -> Settings -> Security -> sign out other sessions
Session A: remains authenticated
Session B: next request becomes unauthenticated
```

Also verify wrong passwords, zero other sessions, repeated action safety, direct query-string navigation, section switching with unsaved values, validation and success feedback, and the 375px, 768px, 1280px, and 1920px layouts. Record any environment limitation separately from application failures.

### Build verification

Run the production Vite build after the UI refactor and run the focused UI suite in the project’s supported container environment. No success claim is made until the relevant command output is available.

## Non-goals

- Adding notification settings or new account categories.
- Creating separate backend pages for each section.
- Changing authentication, authorization, session storage, or password policy.
- Changing the preference JSON schema.
- Introducing a new runtime dependency for browser verification.
- Redesigning the authenticated global sidebar.

## Definition of done

- Mixed-case and whitespace-variant duplicate emails return normal validation errors.
- Email values are canonical before uniqueness validation and remain protected by the model mutator.
- Partial preference saves repair inaccessible or deleted workspace defaults while preserving unrelated fields.
- PostgreSQL Settings and contract tests run against a real PostgreSQL service.
- Real multi-session revocation behavior is verified with two isolated browser sessions.
- Settings uses the selected top-tab layout on desktop and a clean mobile navigation treatment.
- Profile, Accessibility, Workspace, and Security are clear dedicated sections.
- Existing accessibility behavior and keyboard semantics are preserved or improved.
- Relevant Laravel tests, UI tests, and the production build pass in the supported environment.
