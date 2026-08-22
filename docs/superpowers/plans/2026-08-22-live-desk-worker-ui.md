# Live Desk worker UI implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the Judge and Tabulator worker screens with the approved Live Desk interface while preserving every scoring, permission, revision, adjustment, and finalization rule.

**Architecture:** Keep the existing Laravel controllers, Inertia props, routes, and form behavior unchanged. Build three small presentational components for shared operational status, schedule time, and accessible progress, then reshape each role page around those components. Judge and Tabulator detail pages remain separate because their actions and evidence models differ.

**Tech stack:** Laravel 13, PHP 8.4, PostgreSQL, Inertia 2, React 18, Tailwind CSS 4.3.3, Vite 8, Vitest 3, Testing Library

**Spec:** `docs/superpowers/specs/2026-08-22-live-desk-worker-ui-design.md`

## Global constraints

- Work directly on `master`; do not create a branch or worktree.
- Preserve `AuthenticatedLayout`, routes, Inertia prop shapes, score calculations, scorecard revisions, permissions, assignment rules, adjustments, and result lifecycles.
- Use Figtree for worker UI and Barlow Condensed only for scores, ranks, times, and progress.
- Use the existing semantic Tailwind tokens. Gold marks the active live task; red is reserved for blockers and corrections.
- Keep one filled primary action in each action group and touch targets at least 44 pixels high.
- Support 360-pixel phone, intermediate tablet, and wide desktop layouts.
- Preserve light, dark, high-contrast, large-text, extra-large-text, reduced-motion, keyboard, and screen-reader behavior.
- Do not add dependencies or backend data solely for presentation.
- Write each behavior test first, run it, and confirm the expected failure before changing production code.

---

### Task 1: Record Impeccable product context and implementation authority

**Files:**
- Create: `PRODUCT.md`
- Create: `.impeccable/config.json`
- Modify: `docs/superpowers/specs/2026-08-22-live-desk-worker-ui-design.md`
- Create: `docs/superpowers/plans/2026-08-22-live-desk-worker-ui.md`

**Interfaces:**
- Consumes: repository product facts and the approved Live Desk design.
- Produces: durable product context with `web` platform and `{ "buildPath": "comp" }` workflow default.

- [ ] **Step 1: Validate the product record and config**

Run:

```powershell
node -e "const fs=require('fs'); JSON.parse(fs.readFileSync('.impeccable/config.json','utf8')); console.log('config valid')"
rg -n "impeccable:product-schema|^## Platform$|^web$|^## Users$|^## Product purpose$|^## Positioning$|^## Product principles$" PRODUCT.md
git diff --check -- PRODUCT.md .impeccable/config.json docs/superpowers/specs/2026-08-22-live-desk-worker-ui-design.md docs/superpowers/plans/2026-08-22-live-desk-worker-ui.md
```

Expected: JSON parses, required product headings are present, and `git diff --check` prints nothing.

- [ ] **Step 2: Mark the written design approved**

Change the design spec status to:

```markdown
**Status:** Approved for implementation
```

- [ ] **Step 3: Commit the product and planning authority**

```powershell
git add -- PRODUCT.md .impeccable/config.json docs/superpowers/specs/2026-08-22-live-desk-worker-ui-design.md docs/superpowers/plans/2026-08-22-live-desk-worker-ui.md
git commit -m "docs: initialize impeccable product context"
```

### Task 2: Add shared Live Desk operational components

**Files:**
- Create: `resources/js/Components/LiveDesk/OperationalStatus.jsx`
- Create: `resources/js/Components/LiveDesk/ScheduleTime.jsx`
- Create: `resources/js/Components/LiveDesk/LiveProgress.jsx`
- Create: `tests/ui/LiveDeskComponents.test.jsx`

**Interfaces:**
- Consumes: semantic Tailwind tokens from `resources/css/app.css`.
- Produces: `OperationalStatus({ label, detail, tone })`, `ScheduleTime({ startsAt, align })`, and `LiveProgress({ label, value, max, detail })`.

- [ ] **Step 1: Write failing component behavior tests**

Create `tests/ui/LiveDeskComponents.test.jsx`:

```jsx
import React from 'react';
import { render, screen } from '@testing-library/react';
import { expect, test } from 'vitest';
import OperationalStatus from '../../resources/js/Components/LiveDesk/OperationalStatus';
import ScheduleTime from '../../resources/js/Components/LiveDesk/ScheduleTime';
import LiveProgress from '../../resources/js/Components/LiveDesk/LiveProgress';

test('operational status exposes its label and explanation', () => {
    render(<OperationalStatus label="Waiting" detail="Waiting for 3 Judge scorecards." tone="danger" />);
    expect(screen.getByText('Waiting')).toBeInTheDocument();
    expect(screen.getByText('Waiting for 3 Judge scorecards.')).toBeInTheDocument();
});

test('schedule time presents scheduled and pending values', () => {
    const { rerender } = render(<ScheduleTime startsAt="2026-11-13T09:00:00+08:00" />);
    expect(screen.getByText(/9:00/)).toBeInTheDocument();
    rerender(<ScheduleTime startsAt={null} />);
    expect(screen.getByText('Unscheduled')).toBeInTheDocument();
    expect(screen.getByText('Date pending')).toBeInTheDocument();
});

test('live progress exposes completed work to assistive technology', () => {
    render(<LiveProgress label="Required criteria" value={2} max={3} detail="2 of 3 scored" />);
    expect(screen.getByRole('progressbar', { name: 'Required criteria' })).toHaveAttribute('aria-valuenow', '2');
    expect(screen.getByText('2 of 3 scored')).toBeInTheDocument();
});
```

- [ ] **Step 2: Run the test and confirm the missing-module failure**

Run:

```powershell
npm.cmd run test:ui -- --run tests/ui/LiveDeskComponents.test.jsx
```

Expected: FAIL because `Components/LiveDesk/OperationalStatus`, `ScheduleTime`, and `LiveProgress` do not exist.

- [ ] **Step 3: Implement the three focused components**

`OperationalStatus.jsx` maps `neutral`, `live`, `ready`, and `danger` to existing semantic token classes. It renders a compact status label and optional explanation without deciding business state.

`ScheduleTime.jsx` owns the duplicated `Intl.DateTimeFormat` calls from the two dashboards. It renders `Unscheduled` and `Date pending` for missing values and applies `font-condensed tabular-nums` to the time.

`LiveProgress.jsx` clamps `value` between zero and `max`, calculates a safe percentage when `max` is zero, and renders:

```jsx
<div
    role="progressbar"
    aria-label={label}
    aria-valuemin="0"
    aria-valuemax={max}
    aria-valuenow={safeValue}
>
    <div className="h-1.5 overflow-hidden rounded-full bg-border">
        <div className="h-full bg-primary" style={{ width: `${percentage}%` }} />
    </div>
    <p>{detail ?? `${safeValue} of ${max}`}</p>
</div>
```

- [ ] **Step 4: Run the focused test**

Run:

```powershell
npm.cmd run test:ui -- --run tests/ui/LiveDeskComponents.test.jsx
```

Expected: PASS.

- [ ] **Step 5: Commit the shared components**

```powershell
git add -- resources/js/Components/LiveDesk tests/ui/LiveDeskComponents.test.jsx
git commit -m "feat: add live desk operational components"
```

### Task 3: Reshape the Judge work queue

**Files:**
- Modify: `resources/js/Pages/Judge/Index.jsx`
- Modify: `tests/ui/StaffAccessFlow.test.jsx`

**Interfaces:**
- Consumes: `ScheduleTime`, `OperationalStatus`, and `LiveProgress`; existing `event`, `summary`, and `contests` props.
- Produces: correction-first assignment queue and accessible `Judging progress` summary.

- [ ] **Step 1: Add failing Judge queue assertions**

Extend the existing Judge queue test in `tests/ui/StaffAccessFlow.test.jsx`:

```jsx
const progress = screen.getByRole('progressbar', { name: 'Judging progress' });
expect(progress).toHaveAttribute('aria-valuenow', '0');
expect(progress).toHaveAttribute('aria-valuemax', '3');
expect(screen.getByText('0 of 3 complete')).toBeInTheDocument();
expect(screen.queryByRole('region', { name: 'Judging summary' })).not.toBeInTheDocument();
```

Add one assertion that `Correction event` appears inside the `Needs attention` region and not inside `Today's judging schedule`.

- [ ] **Step 2: Run the Judge queue test and confirm failure**

Run:

```powershell
npm.cmd run test:ui -- --run tests/ui/StaffAccessFlow.test.jsx -t "Judge queue"
```

Expected: FAIL because `Judging progress` does not exist and the page still renders the old summary region.

- [ ] **Step 3: Implement the Judge Live Desk queue**

- Delete the three summary cards.
- Add one compact top strip with `LiveProgress`, assigned count, completed count, and attention count.
- Count completed work as `submitted + approved`; use `summary.assigned ?? items.length` as the maximum.
- Replace local date functions with `ScheduleTime`.
- Use `OperationalStatus` for the assignment state and blocker.
- Keep corrections and blocked cards only in `Needs attention`; keep ordinary work only in the schedule.
- Remove serif classes from worker headings and apply `font-condensed` only to time and numeric progress.
- Give active `in_progress` assignments a narrow gold left edge. Give correction and blocked assignments a red left edge.
- Keep the current route and `actionLabel` behavior.

- [ ] **Step 4: Run the Judge queue tests**

Run:

```powershell
npm.cmd run test:ui -- --run tests/ui/StaffAccessFlow.test.jsx tests/ui/Task3Operations.test.jsx
```

Expected: PASS.

- [ ] **Step 5: Commit the Judge dashboard**

```powershell
git add -- resources/js/Pages/Judge/Index.jsx tests/ui/StaffAccessFlow.test.jsx
git commit -m "feat: redesign judge work queue"
```

### Task 4: Reshape the Judge scorecard for phone scoring

**Files:**
- Modify: `resources/js/Pages/Judge/Scorecard.jsx`
- Modify: `tests/ui/JudgeScorecard.test.jsx`

**Interfaces:**
- Consumes: existing `scorecard` DTO and `LiveProgress`.
- Produces: required-criterion progress, larger scoring rows, sticky save state, and `Review and submit` action.

- [ ] **Step 1: Write failing scorecard progress and action tests**

Add to `tests/ui/JudgeScorecard.test.jsx`:

```jsx
test('shows required scoring progress and a review submission action', async () => {
    const { default: Scorecard } = await import('../../resources/js/Pages/Judge/Scorecard');
    render(<Scorecard scorecard={fixture({
        values: { 1: { raw_value: '90', deduction: '0', notes: '' }, 2: { raw_value: '', deduction: '0', notes: '' } },
    })} />);

    expect(screen.getByRole('progressbar', { name: 'Required criteria' })).toHaveAttribute('aria-valuenow', '1');
    expect(screen.getByText('1 of 2 required criteria scored')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Review and submit' })).toBeInTheDocument();
});
```

Update the existing submission test to click `Review and submit`. Keep the route and payload assertions unchanged.

- [ ] **Step 2: Run the scorecard test and confirm failure**

Run:

```powershell
npm.cmd run test:ui -- --run tests/ui/JudgeScorecard.test.jsx
```

Expected: FAIL because the required-criteria progressbar and `Review and submit` action do not exist.

- [ ] **Step 3: Implement the Judge Live Desk scorecard**

- Derive `requiredCount` from `criterion.required` and `completedRequired` from nonblank matching `raw_value` values.
- Add `LiveProgress` below the compact entry identity block.
- Keep venue, call time, and assignment position visible without three card-like blocks.
- Render each criterion as a quiet row with the large score input aligned right at `sm` widths and stacked safely below `sm`.
- Change notes to `<textarea rows="2">` while preserving the same form value and payload.
- Keep field errors next to their criterion and page errors above the rubric.
- Keep proposal authority and official adjustments in the existing secondary column.
- Keep the bottom action area visible and add enough page bottom padding to prevent overlap.
- Show `Draft saved` when the form is clean and `Unsaved changes` when `draftForm.isDirty`.
- Rename the draft submission action to `Review and submit`; keep `Resubmit scorecard` for rejected cards.
- Preserve save-before-submit, revision synchronization, navigation warning, and read-only behavior.

- [ ] **Step 4: Run scorecard tests**

Run:

```powershell
npm.cmd run test:ui -- --run tests/ui/JudgeScorecard.test.jsx
```

Expected: PASS with the existing payload, revision, error, correction, and read-only tests unchanged apart from the approved button label.

- [ ] **Step 5: Commit the Judge scorecard**

```powershell
git add -- resources/js/Pages/Judge/Scorecard.jsx tests/ui/JudgeScorecard.test.jsx
git commit -m "feat: redesign judge scorecard"
```

### Task 5: Reshape the Tabulator work queue

**Files:**
- Modify: `resources/js/Pages/Tabulator/Index.jsx`
- Modify: `tests/ui/StaffAccessFlow.test.jsx`
- Modify: `tests/ui/Task3Operations.test.jsx`

**Interfaces:**
- Consumes: `ScheduleTime` and `OperationalStatus`; existing `event`, `summary`, `judged`, and `objective` props.
- Produces: one compact queue with ready and blocked work ahead of scheduled work.

- [ ] **Step 1: Add failing Tabulator queue assertions**

Extend the existing Tabulator queue test:

```jsx
expect(screen.getByText('2 assignments')).toBeInTheDocument();
expect(screen.getByText('1 judged · 1 objective')).toBeInTheDocument();
expect(screen.queryByRole('region', { name: 'Tabulation summary' })).not.toBeInTheDocument();
```

Add a fixture with one ready judged contest and assert it appears in `Needs attention` but does not appear in `Today's tabulation schedule`.

- [ ] **Step 2: Run the Tabulator queue test and confirm failure**

Run:

```powershell
npm.cmd run test:ui -- --run tests/ui/StaffAccessFlow.test.jsx -t "Tabulator queue"
```

Expected: FAIL because the compact assignment summary does not exist and the old summary region remains.

- [ ] **Step 3: Implement the Tabulator Live Desk queue**

- Delete the three summary cards.
- Add a compact assignment line with total, judged, and objective counts.
- Replace local date functions with `ScheduleTime`.
- Use `OperationalStatus` for readiness, blockers, and objective contest state.
- Put ready-to-finalize, blocked, live, and completed work in `Needs attention` only.
- Keep ordinary scheduled work in the timeline only.
- Use the gold live edge for live or ready work and red for blockers.
- Remove serif classes and preserve the existing `Open tabulation` route.

- [ ] **Step 4: Run Tabulator dashboard tests**

Run:

```powershell
npm.cmd run test:ui -- --run tests/ui/StaffAccessFlow.test.jsx tests/ui/Task3Operations.test.jsx
```

Expected: PASS.

- [ ] **Step 5: Commit the Tabulator dashboard**

```powershell
git add -- resources/js/Pages/Tabulator/Index.jsx tests/ui/StaffAccessFlow.test.jsx tests/ui/Task3Operations.test.jsx
git commit -m "feat: redesign tabulator work queue"
```

### Task 6: Turn judged tabulation into a Live Desk

**Files:**
- Modify: `resources/js/Pages/Tabulator/JudgedContest.jsx`
- Modify: `tests/ui/JudgedTabulation.test.jsx`

**Interfaces:**
- Consumes: existing `contest`, `tabulation`, and `adjustment_configuration` props plus `LiveProgress` and `OperationalStatus`.
- Produces: evidence progress, explicit missing values, mobile entry disclosures, and explained finalization state.

- [ ] **Step 1: Write failing evidence and blocker tests**

Extend `tests/ui/JudgedTabulation.test.jsx`:

```jsx
const progress = screen.getByRole('progressbar', { name: 'Judge submissions' });
expect(progress).toHaveAttribute('aria-valuenow', '1');
expect(progress).toHaveAttribute('aria-valuemax', '2');
expect(screen.getAllByText('Missing').length).toBeGreaterThan(0);

const finalize = screen.getByRole('button', { name: 'Finalize and submit result' });
expect(finalize).toBeDisabled();
expect(finalize).toHaveAttribute('aria-describedby', 'finalization-status');
expect(screen.getByText('Waiting for all Judges to submit their scorecards.')).toHaveAttribute('id', 'finalization-status');
```

Add a ready-state test that expects the same button to be enabled and the status text to read `All evidence received. Finalize when the ranking is verified.`

- [ ] **Step 2: Run the judged tabulation test and confirm failure**

Run:

```powershell
npm.cmd run test:ui -- --run tests/ui/JudgedTabulation.test.jsx
```

Expected: FAIL because submission progress, explicit `Missing` values, and the described finalization action do not exist.

- [ ] **Step 3: Implement the judged Live Desk**

- Count every scorecard cell across entries; count submitted evidence when `raw_total` is neither `null` nor `undefined`.
- Add `LiveProgress` for `Judge submissions` to the compact readiness header.
- Use `OperationalStatus` for the current state and blockers.
- Keep the desktop matrix primary, align numeric values right, and render `Missing` for absent evidence.
- Keep the existing mobile `<details>` representation, but show delegation and final or waiting state in the summary.
- Preserve criterion disclosures and every adjustment control and history item.
- Replace the floating lone button with a sticky action area containing `id="finalization-status"` explanatory text and the `Finalize and submit result` button.
- Apply `aria-describedby="finalization-status"` to the button.
- Keep the button disabled unless `operationalState === 'ready'` and the contest is not read-only.
- Preserve the same finalize route and request payload.

- [ ] **Step 4: Run judged tabulation tests**

Run:

```powershell
npm.cmd run test:ui -- --run tests/ui/JudgedTabulation.test.jsx
```

Expected: PASS, including adjustment recording and void-history tests.

- [ ] **Step 5: Commit judged tabulation**

```powershell
git add -- resources/js/Pages/Tabulator/JudgedContest.jsx tests/ui/JudgedTabulation.test.jsx
git commit -m "feat: redesign judged tabulation desk"
```

### Task 7: Verify Live Desk across states and viewports

**Files:**
- Modify only files with confirmed defects from this verification pass.

**Interfaces:**
- Consumes: completed Judge and Tabulator Live Desk pages.
- Produces: tested production build, one Impeccable detector report, and verified phone and desktop UI.

- [ ] **Step 1: Run focused and full UI tests**

```powershell
npm.cmd run test:ui -- --run tests/ui/LiveDeskComponents.test.jsx tests/ui/StaffAccessFlow.test.jsx tests/ui/JudgeScorecard.test.jsx tests/ui/JudgedTabulation.test.jsx tests/ui/Task3Operations.test.jsx
npm.cmd run test:ui -- --run
```

Expected: all tests pass with no unhandled errors.

- [ ] **Step 2: Run relevant Laravel feature tests**

```powershell
docker compose exec -T app php artisan test tests/Feature/Judge tests/Feature/Tabulator tests/Feature/Admin/GlobalDashboardTest.php
```

Expected: all selected tests pass. If the repository groups the files differently, use `rg --files tests/Feature | rg "Judge|Tabulator|GlobalDashboard"` and pass the resulting explicit paths to Artisan.

- [ ] **Step 3: Build production assets**

```powershell
npm.cmd run build
```

Expected: Vite exits zero with a production manifest and no compilation errors.

- [ ] **Step 4: Run the Impeccable detector once**

```powershell
node C:/Users/monte/.codex/skills/impeccable/scripts/detect.mjs --json resources/js/Components/LiveDesk resources/js/Pages/Judge/Index.jsx resources/js/Pages/Judge/Scorecard.jsx resources/js/Pages/Tabulator/Index.jsx resources/js/Pages/Tabulator/JudgedContest.jsx
```

Expected: valid JSON. Fix actionable findings in one batch; do not rerun the detector.

- [ ] **Step 5: Inspect rendered pages in one bounded browser pass**

Use the local app at `http://localhost:8000` with the synthetic Judge and Tabulator accounts already created for development.

Inspect these states at 360-pixel phone and desktop widths:

- Judge queue with correction and scheduled work;
- editable Judge scorecard with partial criteria;
- read-only submitted scorecard;
- Tabulator queue with ready and blocked work;
- judged tabulation with missing evidence;
- judged tabulation ready to finalize;
- dark mode and large text on one Judge and one Tabulator page.

Check overflow, sticky-action clearance, long names, focus visibility, disabled explanations, console errors, and touch target size. Fix all confirmed defects in one batch, then perform one confirmation pass at phone and desktop widths.

- [ ] **Step 6: Review the source diff**

```powershell
git diff --check
git status --short
git diff --stat
```

Expected: no whitespace errors, no temporary browser artifacts, and no unrelated user changes staged.

- [ ] **Step 7: Commit verified UI fixes**

```powershell
git add -- resources/js/Components/LiveDesk resources/js/Pages/Judge resources/js/Pages/Tabulator tests/ui/LiveDeskComponents.test.jsx tests/ui/StaffAccessFlow.test.jsx tests/ui/JudgeScorecard.test.jsx tests/ui/JudgedTabulation.test.jsx tests/ui/Task3Operations.test.jsx
git commit -m "fix: polish live desk worker UI"
```

- [ ] **Step 8: Push master after final verification**

```powershell
git push origin master
```

Expected: `origin/master` advances to the verified local `master` commit.
