# Syntix Live Desk worker UI design

**Date:** August 22, 2026  
**Status:** Design approved, pending written-spec review  
**Scope:** Judge and Tabulator worker interfaces

## Purpose

Live Desk gives Judges and Tabulators one event-day interface language. It must help Judges enter accurate scores quickly on phones and help Tabulators verify evidence and finalize results on laptops. The redesign changes presentation and interaction hierarchy without changing scoring rules, routes, permissions, or submission lifecycles.

## Design direction

Live Desk is a restrained operations interface. It uses the existing Syntix navy, teal, gold, and semantic status colors. Gold identifies the active live task. Red identifies blockers and corrections only. The interface avoids decorative dashboard chrome and exposes the next valid action near the data that controls it.

Figtree is the primary interface typeface. Barlow Condensed is reserved for scores, ranks, times, progress, and other compact operational data. Worker screens do not use serif typography for headings, labels, controls, or data.

The visual signature is a narrow gold live-signal line attached to the active task or current state. It must never become a decorative border around every card.

## Shared worker patterns

### Page shell

- Preserve `AuthenticatedLayout`, navigation, theme preferences, text-size preferences, high-contrast mode, reduced-motion behavior, and event context.
- Keep headers compact so work begins near the top of the viewport.
- Replace detached metric cards with inline progress and status summaries tied to the active queue or contest.
- Keep one filled primary action in each action group.
- Use 44-pixel or larger touch targets for interactive controls.

### Status language

- `not_started`: neutral and actionable.
- `in_progress`: gold live signal with the current progress visible.
- `needs_correction` or `rejected`: red correction state with the reason and recovery action.
- `blocked` or `waiting`: explicit blocker text with unavailable actions disabled or absent.
- `submitted`: read-only pending-review state.
- `approved`: read-only official state.
- `ready`: teal readiness state with the next valid finalization action.

Color must always be paired with text. Status wording remains consistent between dashboards and detail pages.

## Judge experience

### Judge dashboard

The Judge dashboard becomes a compact work queue organized around urgency and schedule.

- Corrections and blocked work appear first in a dedicated attention group.
- Scheduled work follows in chronological order, with unscheduled work last.
- Each assignment row shows time, contest, entry, delegation, venue, status, and one action.
- Summary information becomes a compact inline progress line instead of three large cards.
- Empty state explains that assignments appear after the Global Admin adds the Judge to a panel.

### Judge scorecard

The scorecard prioritizes entry identity, scoring inputs, progress, and safe submission.

- The compact header shows competition, division, entry, delegation, venue, call time, assignment position, and state.
- Previous and next navigation remains available but cannot overpower scoring controls.
- A progress summary reports completed required criteria and weighted total.
- Each criterion has a large numeric input, visible allowed range, weight, validation error, and optional notes.
- Notes remain visually secondary and may use a multiline control when space permits.
- Proposal authority, contest instructions, and official adjustments remain available as secondary reference content.
- The bottom action bar stays visible while scoring. It shows save state, weighted total, Save draft, and Review and submit.
- Submission remains a deliberate action. Existing save-before-submit behavior and unsaved-navigation protection remain unchanged.
- Submitted and approved scorecards are clearly read-only.

On phones, each criterion becomes a compact scoring row with the score input aligned to the right. The bottom bar keeps the total and submission action thumb-reachable. On larger screens, the rubric and reference material may use two columns.

## Tabulator experience

### Tabulator dashboard

The Tabulator dashboard becomes a single work queue for judged and objective assignments.

- Assignments remain sorted by scheduled time.
- Every row identifies mode, contest, division, venue, current state, evidence progress, blocker, and one action.
- Ready-to-finalize and blocked assignments receive stronger hierarchy than scheduled work.
- Summary information becomes an inline queue status rather than detached metric cards.
- Empty state explains how assignments are created.

### Judged contest tabulation

The judged contest page becomes a Live Desk for evidence review and finalization.

- A compact contest header shows operational state and current blockers.
- Desktop uses an assignment rail when multiple contests are available from existing page data. If the route does not provide sibling assignments, no empty rail is invented.
- Readiness is shown as panel submissions, entry count, and the next valid action.
- The Judge score matrix stays the primary desktop view. Entry names remain fixed in the first column when practical, numeric columns align right, and missing evidence is written as `Missing` rather than a dash.
- Mobile replaces the wide matrix with expandable entry rows. The collapsed row shows entry, delegation, final value or waiting state, and any blocker.
- Criterion details remain available on demand.
- Official adjustment controls stay close to the affected entry and retain authorization, void, and history rules.
- Final submission stays unavailable until the backend reports `ready`. The action area explains the active blocker instead of relying on disabled opacity alone.
- Completed, submitted, approved, cancelled, and suspended contests are read-only.

## Responsive behavior

- Verify at 360 pixels, an intermediate tablet width, and a wide desktop width.
- Worker dashboards become one-column queues below the desktop breakpoint.
- Judge score inputs remain fully visible without horizontal scrolling.
- The Tabulator matrix may scroll horizontally only at widths where the desktop table remains the correct representation.
- Mobile tabulation uses disclosure rows instead of shrinking the matrix.
- Sticky and fixed action areas must not cover the last content row, validation message, or browser controls.
- Long event, contest, entry, delegation, venue, and Judge names wrap without hiding actions.

## Interaction and accessibility

- Preserve semantic headings, regions, tables, fieldsets, legends, labels, alerts, and live regions.
- Keep visible keyboard focus and logical document order.
- Inputs expose required state, allowed range, and validation errors programmatically.
- Disabled controls include visible explanatory text.
- Motion is limited to short state transitions and respects reduced-motion preferences.
- Light, dark, high-contrast, large-text, and extra-large-text preferences remain supported.

## Data and behavior boundaries

The redesign consumes the existing Inertia props and uses the existing routes. It does not change:

- score calculations;
- scorecard revisions or conflict handling;
- scoring permissions;
- Judge assignment rules;
- Tabulator readiness rules;
- adjustment authorization or audit history;
- result finalization and approval lifecycles.

If a desired visual element needs data that the current page does not receive, the implementation omits that element unless the data can be derived safely from existing props. This prevents presentation work from expanding into backend behavior changes.

## Error and edge states

The implementation covers:

- no assignments;
- unscheduled assignments;
- blocked contests;
- corrections with reasons;
- partial scorecards;
- unsaved changes;
- validation failures;
- revision conflicts returned by the server;
- missing Judge submissions;
- unauthorized adjustment calculations;
- ties requiring Admin resolution;
- submitted and approved read-only states;
- long labels and large text settings.

## Component boundaries

The implementation extracts only these shared patterns when the same markup appears in both role flows:

- `OperationalStatus`, for a labeled state and optional explanation;
- `ScheduleTime`, for the shared time and date presentation;
- `LiveProgress`, for completed and total work with accessible progress semantics.

Sticky action areas remain role-specific because Judge saving and Tabulator finalization have different behavior.

Judge-specific scoring rows and Tabulator-specific evidence matrices remain separate. The implementation must not create a broad worker-dashboard abstraction that hides role-specific behavior.

## Testing and verification

Implementation follows test-first development.

- Extend UI tests for queue ordering, correction priority, compact progress, status wording, scorecard progress, disabled-action explanations, and mobile disclosure semantics.
- Run each new focused test before implementation and confirm it fails for the expected missing behavior.
- Run the focused UI suite, full UI suite, relevant Laravel feature tests, and production build.
- Inspect Judge dashboard, Judge scorecard, Tabulator dashboard, and judged tabulation in the in-app browser at phone and desktop widths.
- Check keyboard focus, long content, dark mode, console errors, overflow, and sticky-action clearance.
- Run the Impeccable detector once after all UI edits, then fix actionable defects in one bounded pass and confirm once.

## Acceptance criteria

- Judges can identify the next assignment and enter scores without horizontal scrolling on a 360-pixel viewport.
- Judges can see scoring progress, weighted total, save state, and submission action while working.
- Corrections and blockers are visible before ordinary scheduled work.
- Tabulators can identify missing evidence and the next valid action without opening every entry.
- Desktop tabulators can compare Judge evidence and final totals in one primary view.
- Disabled finalization always has a visible reason.
- Worker pages share typography, spacing, status language, and action hierarchy.
- Existing scoring behavior and permissions remain unchanged.
- Light mode, dark mode, keyboard use, large text, and high contrast remain usable.
