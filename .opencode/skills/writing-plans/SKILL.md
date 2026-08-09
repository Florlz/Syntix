---
name: writing-plans
description: Use after a design or PRD is approved and before implementing a multi-step feature; creates a file-specific, testable implementation plan.
---

# Implementation Plans

Write an implementation plan that another engineer can execute with minimal
project context.

## Scope Check

If the source design covers independent subsystems, split it into separate
plans. Each plan should produce one useful, testable vertical slice.

## Plan Location

Write plans to:

```text
docs/plans/YYYY-MM-DD-<feature-name>.md
```

Respect a user-specified path. Do not create commits automatically.

## Required Header

Every plan starts with:

```markdown
# <Feature> Implementation Plan

**Goal:** <one sentence>

**Architecture:** <two or three sentences>

**Tech Stack:** <relevant technologies and versions>

## Global Constraints

- <requirements that apply to every task>
- Do not commit unless explicitly requested.
```

## Task Format

Each task must include:

- A single clear deliverable
- Exact files to create, modify, and test
- Interfaces consumed and produced
- Database or API changes
- Authorization impact
- Verification commands
- Expected test results

Use checkbox steps and keep each step small enough to review independently.

```markdown
### Task 1: <name>

**Files:**
- Create: `path/to/file`
- Modify: `path/to/file`
- Test: `tests/Feature/path`

**Interfaces:**
- Consumes: <existing behavior>
- Produces: <behavior used by later tasks>

- [ ] Write the failing test.
- [ ] Run `php artisan test tests/Feature/path` and verify it fails for the expected reason.
- [ ] Implement the smallest change.
- [ ] Run the focused test and verify it passes.
- [ ] Run the relevant regression tests.
```

## Implementation Guidance

- Follow existing project patterns before adding dependencies or abstractions.
- Name files, interfaces, and domain operations explicitly.
- Define transaction and authorization boundaries where relevant.
- Include focused tests whenever behavior changes.
- Prefer vertical slices that produce independently useful behavior.

## Plan Self-Review

Before presenting the plan:

1. Map every PRD requirement to at least one task.
2. Search for placeholders such as `TODO`, `TBD`, or "handle later".
3. Check names and method signatures for consistency across tasks.
4. Check that the plan includes validation, authorization, tests, and failure
   states.
5. Check that the plan does not include unrelated refactoring.

## Project Context

This repository uses Laravel 13, PHP 8.3, Inertia React, Tailwind, Vite, and
PHPUnit. Read `docs/cspc-siklab-plan.md` for product-wide constraints rather
than repeating all of them in this skill.
