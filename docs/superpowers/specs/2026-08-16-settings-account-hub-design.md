# Settings Account Hub Design

Date: 2026-08-16

## Goal

Make the signed-in Syntix administrator Settings page feel like an organised account hub instead of a long collection of equal forms. Improve visual polish and navigability without changing settings behaviour, permissions, routes, or persistence.

## Scope

The existing `/settings` page remains the single destination for personal account and dashboard preferences. The existing authenticated sidebar, backend routes, form actions, validation rules, and saved preference model remain unchanged.

The page is reorganised into four dashboard-hub categories:

1. **Profile** — name, email, and email verification.
2. **Accessibility** — text size, contrast, reduced motion, and the live preference preview.
3. **Workspace** — default event and first dashboard page.
4. **Security** — password update and ending other signed-in sessions.

## User experience

### Account hub

The page starts with a compact account header and status summary, followed by four Signal cards. The cards are the primary in-page navigation; selecting one opens its focused work area immediately beneath the hub.

The initially selected category is **Profile**. Choosing another category changes only the focused work area and does not discard unsaved values already entered in another form. The selected card is visibly active and labels the currently open work area.

Each work area preserves its own submit action and its existing inline validation and success feedback:

- Profile uses the existing profile patch action.
- Accessibility and Workspace use their existing independent preference submissions.
- Security keeps distinct password and other-session actions in the same category.

### Visual direction: Signal cards

The existing Syntix visual language remains the foundation:

- Navy: `#0B2E4F` / `#082944`
- Teal action and icon colour: `#0B536D`
- Gold accent and focus colour: `#D5A21F` / `#E7C865`
- Paper background: `#F4F5F2`
- Serif headings, compact uppercase utility labels, and rounded surfaces

The account banner uses navy with a restrained gold radial accent. Each Signal card has a clear teal icon, concise summary, action verb, and optional status label. Soft category-specific background motifs distinguish the cards while the detailed form area remains quiet and functional.

The hub is a two-by-two grid from small screens upward. On narrow screens it stays readable as a two-column grid and the selected work area follows immediately after the grid. Motion is limited to a short selected-state transition and respects the existing reduced-motion preference.

## Component boundaries

Refactor `resources/js/Pages/Settings/Index.jsx` only as needed to introduce:

- `SettingsHub` — owns the selected category and renders the four navigation cards.
- `SignalCard` — a semantic, keyboard-operable category selector with a label, summary, icon, and state.
- Focused category panels — compose the existing form implementations into Profile, Accessibility, Workspace, and Security areas.

The existing form hooks, route calls, value preservation, errors, status messages, and live preview logic stay local to their existing form components. No shared client store, endpoint change, backend change, or dependency is needed.

## Accessibility and interaction

- Use button/tab semantics for category selection, with `aria-selected`, an accessible relationship to the active panel, and visible focus treatment.
- Preserve normal tab order within the open panel.
- When category selection comes from keyboard input, move focus to the newly revealed panel heading; pointer selection does not unexpectedly move focus.
- Honour document-level text-size, high-contrast, and reduced-motion settings.
- Existing field labels, inline errors, required input behaviour, and password-error focus behaviour remain intact.

## Verification

Update the Settings UI coverage to verify:

- All four hub categories render and switch the focused work area.
- All original five settings areas remain reachable through the new groups.
- Existing form endpoints and success/error behaviour are unchanged.
- Accessibility edits still update document preference attributes immediately.
- Keyboard selection exposes the associated panel and preserves accessible focus behaviour.

Run the relevant UI tests and a production build. Use browser verification when the local app can run: desktop and mobile layouts, category switching, validation, and the reduced-motion/high-contrast states.

## Out of scope

- Changes to routes, backend contracts, data storage, permissions, or event access.
- New settings categories or notification settings.
- Any dependency addition.
- Changes to the global authenticated sidebar or account/session security rules.
