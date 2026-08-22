# Syntix light system and Judge scorecard redesign

**Date:** August 22, 2026
**Status:** Approved for implementation
**Scope:** Product-wide light-only presentation, admin visual migration, and the Judge scorecard composition

## Purpose

Syntix will use one permanent light visual system across public pages, authentication, Global Admin and organizer workspaces, Judge screens, and Tabulator screens. Theme switching and dark-mode behavior will be removed completely. Text-size, high-contrast, and reduced-motion preferences remain supported.

This delivery also replaces the Judge scorecard composition with the user-approved **Guided Criteria Focus** design. The scorecard is the only route receiving a structural recomposition. Admin routes keep their current information architecture, content order, and workflows, but every visible admin element adopts the new light system rather than receiving a shallow token swap.

## Approved direction

The visual world is **Official Results Bulletin**. Syntix should feel like the definitive event record made clear and responsive for live work. It draws from school athletics result sheets, adjudication forms, meet programs, signed officials' records, and precise ruled ledgers.

The system refuses both category defaults:

- generic white-and-blue SaaS made from floating rounded cards;
- black or neon event-control-room styling.

The selected scorecard comp is `.impeccable/mocks/scorecard-results-bulletin-b.png`.

## Direction contract

**THESIS:** Syntix is the official event record in motion. It uses one coherent ruled workspace instead of stacked dashboard cards or a dark control room.

**OWN-WORLD:** Near-white paper fields, ink navy text, cyan-teal actions and rules, tabular numerals, hairline dividers, explicit status labels, minimal elevation, and small-radius controls.

**STORY:** A Judge identifies the current entry, scores one criterion with full context, sees what remains and what the server has saved, then reviews and submits deliberately.

**FIRST VIEWPORT:** A light event masthead leads into a criterion index, one dominant active scoring lane, compact remaining criteria, a narrow official summary rail, and a bottom action strip.

**FORM:** Guided Criteria Focus, the second approved composition in the Official Results Bulletin world. Direction seed `f404af7d`.

**FINISH:** unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Product-wide light-only system

### Theme removal

Dark mode is removed as a feature rather than hidden as an option.

- Remove the theme selector and Appearance section from Settings.
- Remove `resources/js/lib/theme.js` and every import or effect that applies a light, dark, or system theme.
- Remove the `syntix-theme` local-storage contract and operating-system dark-theme listeners.
- Remove the Blade boot script that adds the `dark` class before React loads.
- Remove the Tailwind `dark` custom variant, dark token block, legacy dark utility overrides, and remaining `dark:` utilities.
- Stop sharing `ui.theme_scope` through Inertia.
- Stop accepting or returning a `theme` preference in the backend preference contract.
- Existing `theme` keys stored in user JSON become inert legacy data. The normalized preference response omits them, and the next preference save rewrites the supported preference set without them.
- Set the document and PWA chrome to the light system regardless of device preference.

Theme removal must not remove the Accessibility section or its text-size, high-contrast, and reduced-motion controls.

### Sampled visual tokens

The approved comp, not the earlier palette card, is the color authority. Its dominant sampled values are:

- page ground: `#FEFEFE`;
- primary ink: `#001A3F`;
- action teal: `#1D86A6`;
- cool rule: `#BEC6DB`;
- quiet field: approximately `#FAFCFC`;
- correction red: approximately `#EB4442`.

Implementation may adjust a token only when contrast testing requires it. Any adjustment keeps the same hue role and is recorded in `DESIGN.md` after the build.

The interface uses Figtree for labels and body copy and Barlow Condensed for scores, progress, times, and other compact operational numerals. No new font dependency is required.

### Component grammar

- Page regions are separated by hairline rules and alignment, not shadows.
- Containers use no radius or a small radius; large soft cards are avoided.
- Filled teal is reserved for the primary action and active task state.
- Navy carries identity, headings, navigation, and official information.
- Red appears only for corrections, blockers, destructive actions, and invalid input.
- Status always includes text; color is never the only signal.
- Focus uses a visible high-contrast ring that works on white and teal surfaces.
- Controls retain at least a 44-pixel touch target.
- Dense data uses tabular numerals and aligned columns.

### Application shell

The authenticated shell changes from a dark full-height sidebar to the approved light bulletin language.

- The sidebar is a near-white sheet with navy text, teal active state, ruled section boundaries, and a compact Syntix identity block.
- Active event context remains visible near the top.
- Role-based navigation, badges, notifications, responsive drawer behavior, event switching, and sign-out behavior remain unchanged.
- The top bar is compact and light, separated from the workspace by a single cool rule.
- Mobile navigation remains a drawer with the same light materials.

Public and authentication pages retain their existing content and route-specific composition, but their document chrome and shared tokens are light-only. No route may reactivate a dark theme from stored or operating-system preference.

## Admin workspace visual migration

The complete authenticated admin workspace adopts the Official Results Bulletin design without changing page structure. This includes the event overview, event creation, departments, registrations, participant records, staff, sports, rosters, tournament operations, public programme management, result approvals, account invitations, and settings.

### What changes

- Replace dark promotional hero panels with light ruled mastheads that preserve the same content and actions.
- Replace large rounded cards and detached shadows with small-radius or square bulletin sections separated by rules, alignment, and quiet fields.
- Replace hard-coded navy, teal, gold, slate, and white utilities with semantic system tokens.
- Use the shared ink-navy, teal, cool-rule, quiet-field, danger, and focus roles consistently.
- Use Figtree for admin headings and body copy. Use Barlow Condensed for metrics, counts, ranks, dates, times, and compact status data.
- Restyle forms, filters, search, tables, tabs, notices, empty states, modals, slide-overs, drawers, status tags, and sticky action areas in the same component grammar.
- Keep real sport cover images and department colors where they convey configured event identity. Their surrounding controls and frames adopt the bulletin system.
- Remove decorative lift, hover translation, oversized corner radii, and shadow changes. Hover and active states use rule, field, text, and focus changes instead.
- Preserve one filled teal primary action per action group. Secondary actions use ruled light controls. Destructive actions remain red and explicit.

### What does not change

- No admin route changes its information architecture, content order, navigation hierarchy, or responsive topology solely for this visual migration.
- Existing forms keep their fields, validation, submission methods, and backend contracts.
- Existing tables, drawers, dialogs, tabs, filters, and disclosures keep their behavior and semantic roles.
- Admin workflows, permissions, event state rules, roster rules, scoring approval rules, and audit behavior remain unchanged.
- The migration does not create a broad abstraction that hides route-specific business logic. Shared visual primitives accept ordinary content and classes; each route keeps ownership of its workflow.

### Shared visual primitives

The implementation may introduce a small admin visual layer for repeated presentation:

- a ruled page masthead;
- a bulletin section with optional header and footer slots;
- a compact toolbar or filter row;
- consistent primary, secondary, and danger action classes;
- consistent field, table, notice, status, empty-state, drawer, and dialog classes.

These primitives own appearance only. They do not fetch data, choose routes, submit forms, interpret event state, or contain role checks.

## Judge scorecard composition

### Desktop layout

The scorecard uses one coherent official sheet below the application masthead.

1. **Entry strip:** competition, contest, division, entry, delegation, venue, schedule, assignment position, state, and previous/next entry navigation remain visible in a compact ruled header.
2. **Criterion index:** a narrow numbered rail lists every configured criterion, its weight or points label, and its state. It must support any configured criterion count rather than assuming three.
3. **Active scoring lane:** one selected criterion owns the largest region. It shows the criterion name, source label when useful, allowed range, required state, weight, a prominent numeric input, range markers derived only from configured minimum and maximum, validation feedback, and optional notes.
4. **Remaining criteria:** non-active criteria appear as compact selectable rows with name, weight, current value, and complete or not-scored status. They are not disabled or hidden from navigation.
5. **Official summary rail:** scoring authority, proposal reference, reliability, contest instructions, server-saved weighted score, official adjustments, and required-criteria progress remain visible without competing with the active score input.
6. **Action strip:** server save state, revision, weighted score, Save draft, and Review and submit remain visible at the bottom. Read-only states replace editing actions with explicit submitted or approved language.

The focal element is the active numeric score input. Entry titles, totals, and status labels stay subordinate to it.

### Active criterion behavior

- Initial selection is the first required incomplete criterion. If all required criteria are complete, select the first criterion for review.
- Selecting an item in the criterion index or a compact criterion row changes only presentation. Form state for every criterion remains intact.
- A criterion with a validation error is marked in the index. After a failed save or submit, the first criterion with a field error becomes active and receives focus.
- Completed state is derived from a nonblank raw score, matching the existing draft payload behavior.
- Optional notes are secondary. They open automatically when existing notes or a notes error is present and remain available through an explicit disclosure otherwise.
- The quick reference scale may show configured minimum, midpoint, and maximum numbers. It must not invent labels such as Poor, Fair, or Excellent because the current rule data does not provide those meanings.

### Score authority

Laravel remains the scoring authority.

- The UI does not invent client-side rounding or present an unsaved draft total as official.
- `scorecard.calculated_total` is labeled as the **saved weighted score** and updates after the existing authoritative save response.
- Official adjustments remain read-only for Judges.
- Existing save-before-submit sequencing, revision conflict handling, sparse draft payloads, navigation protection, permissions, and scorecard states remain unchanged.

### Responsive behavior

At phone widths, the scorecard becomes a focused single-column flow.

- The criterion index becomes a compact horizontal or wrapped step selector above the active criterion.
- The active numeric input remains fully visible and at least 56 pixels tall.
- Compact criterion rows follow the active lane without horizontal scrolling.
- Authority, instructions, and adjustments move into clearly labeled disclosures below scoring.
- The sticky action strip keeps save state, total, and the primary action reachable without covering the final field or validation message.
- Long event, entry, delegation, venue, criterion, and instruction text wraps safely.

Desktop should fit the selected criterion, remaining criteria, official summary, and primary actions within a typical laptop viewport when content length allows. Large-text modes may increase vertical scrolling rather than compressing controls.

## Accessibility

- Preserve semantic `main`, navigation, form, fieldset, legend, label, progress, alert, status, and details elements.
- Criterion selectors use buttons with an accessible current-state indicator.
- Score inputs expose required state, minimum, maximum, described range, and validation errors.
- Keyboard order follows the visual task order: entry context, criterion selector, active score, notes, remaining criteria, references, actions.
- Focus is never hidden by the sticky action strip.
- High-contrast mode remains a separate light palette adjustment.
- Reduced-motion behavior remains supported; criterion changes require no decorative motion.
- The design must pass at 360 pixels, a tablet width, the user's browser width, and a wide desktop width.

## Generated-comp interpretation

The approved comp is the spatial contract, with these intentional corrections:

- Do not add a handwritten Judge signature because Syntix has no verified signature workflow.
- Do not add a school seal or mark an in-progress draft as an official certified record.
- Do not invent Head Judge visibility, qualitative scale labels, or participant metadata not present in the page props.
- Fix the mockup's inconsistent example state: a nonblank score must increment progress.
- Keep real Syntix navigation, permissions, data, and status wording even where the generated comp used illustrative copy.

These are corrections to generated defects and unsupported claims, not permission to change the approved topology.

## Data and behavior boundaries

The redesign does not change:

- score calculations or rounding;
- scoring criteria configuration;
- Judge assignments;
- scorecard revisions and stale-write protection;
- score submission or approval lifecycles;
- Tabulator adjustment authority;
- route names, authorization, or audit behavior.

No new backend field is required for the scorecard redesign. Visual elements that need absent data are omitted rather than fabricated.

## Testing and verification

Implementation follows test-first development.

- Add UI tests for the guided active criterion, criterion switching, error activation, compact completion state, optional-note disclosure, server-saved total wording, responsive landmarks, and read-only behavior.
- Replace theme UI tests with assertions that no theme controls or dark-mode side effects remain.
- Replace backend theme persistence tests with assertions that `theme` is rejected or ignored according to the final request contract and omitted from normalized preferences.
- Verify no source references remain for `.dark`, `dark:`, `syntix-theme`, `prefers-color-scheme: dark`, `theme_scope`, or the removed theme helper.
- Add source-level coverage for the admin visual primitives and migrate existing route tests only where accessible names or element roles change.
- Run focused UI tests first, then the full UI suite, relevant Laravel feature tests, the production build, and the Impeccable detector.
- Inspect the scorecard and representative public, auth, admin, Judge, and Tabulator routes in the in-app browser at desktop and phone widths.
- Compare the desktop scorecard against the approved comp at matching dimensions, then run the Impeccable finish review.

## Acceptance criteria

- Syntix stays light regardless of operating-system preference or legacy local storage.
- No theme selector, dark-mode script, dark class, dark token block, or dark backend preference remains active.
- High contrast, larger text, and reduced motion continue to work.
- The authenticated shell matches the light Official Results Bulletin system across roles.
- Every admin route uses the bulletin palette, typography, rules, controls, status language, and restrained corner treatment without changing its workflow structure.
- Admin pages no longer use dark hero panels, large soft card styling, decorative hover lift, or one-off hard-coded palette values where a semantic token exists.
- The Judge scorecard matches the Guided Criteria Focus topology and visual hierarchy.
- Judges can identify the active criterion, its allowed range, scoring progress, saved weighted score, official adjustments, and next action without scanning stacked cards.
- Judges can switch criteria without losing unsaved values or notes.
- Validation directs the Judge to the criterion that needs correction.
- Score entry works without horizontal scrolling at 360 pixels.
- Existing save, submit, revision, permissions, and read-only behavior remain unchanged.
- Browser QA shows no overlap, clipped content, console errors, or sticky-action obstruction on the checked routes and viewports.
