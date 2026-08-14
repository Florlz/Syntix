# Admin Settings Design

Date: 2026-08-15

## Goal

Give every signed-in Syntix admin one clear Settings page for account and dashboard preferences. The page should be easy for student leaders to use and should not mix personal settings with event operations.

## Scope

The page is available at `/settings` and is linked from the bottom of the admin sidebar.

It contains five small cards:

1. **Profile** — edit name and email. Keep the existing email verification behavior.
2. **Password** — enter the current password, a new password, and confirmation. Reuse the existing password update rules.
3. **Accessibility** — choose text size, high contrast, and reduced motion. These preferences apply to the signed-in admin's dashboard immediately and are saved to that admin's account.
4. **Dashboard preferences** — choose the default event and the first page to open. The available event list is limited to events the admin can access.
5. **Security** — sign out other database-backed sessions while keeping the current session active.

Event details, sports, staff, rosters, brackets, and schedules remain in their current workspaces. No event-wide data is added to Settings.

## Visual direction

Use the existing Syntix visual language: navy sidebar, paper background, teal actions, gold focus/accent, serif section headings, and compact utility labels.

The page opens with a short “Your account” header and a small preference summary strip. Below it, cards use a two-column desktop layout and one column on mobile. Each card has its own Save button, a short plain-language explanation, inline errors, and a visible Saved state. The accessibility card includes a small live preview so the effect of text size and contrast is obvious before saving.

## Data and routes

Add a nullable JSON `preferences` column to `users` with safe defaults:

```json
{
  "text_size": "default",
  "contrast": "default",
  "reduce_motion": false,
  "default_event_id": null,
  "default_landing": "overview"
}
```

Cast the column to an array on `User` and expose normalized preferences in shared Inertia auth props.

Use these authenticated routes:

- `GET /settings` — render the Settings page.
- `PATCH /settings/profile` — update name and email.
- `PUT /settings/password` — keep the existing password action and validation.
- `PATCH /settings/preferences` — validate and save accessibility/dashboard preferences.
- `DELETE /settings/sessions` — remove all other database sessions for the signed-in user.

The old `/profile` route remains as a redirect to `/settings` so existing links continue to work.

## Applying preferences

The root admin layout reads the shared preferences and applies them to the document:

- `text_size` changes the root rem size (`default`, `large`, `x-large`).
- `contrast` enables a stronger contrast mode and a more visible focus outline.
- `reduce_motion` disables non-essential transitions and smooth scrolling.

Dashboard preference values are saved now and used by the dashboard/event selector without changing event permissions. Invalid or inaccessible event IDs fall back to the first available event.

## States and safety

- Archived or disabled accounts cannot save settings.
- Password errors focus the failed field and clear only sensitive fields.
- Each card keeps its values after a failed save.
- Successful saves show a short status message and preserve scroll position.
- Signing out other sessions requires the current password and never deletes the current session.
- No password, session token, or private account data is returned in Inertia props.

## Verification

Add coverage for:

- Settings page access and authentication.
- Profile and password updates through the Settings routes.
- Preference validation, persistence, and shared auth props.
- Applying text size, contrast, and reduced-motion attributes in the layout.
- Default event fallback when a saved event is inaccessible.
- Signing out other sessions without removing the current session.
- Responsive Settings layout and card-level success/error states.

## Out of scope

- Event-wide configuration.
- Managing staff roles or permissions.
- Notification delivery settings.
- New dependencies.
- Replacing the existing password policy or authentication system.
