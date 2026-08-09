---
name: prd-maker
description: Use when the user needs a product requirements document for a feature, subsystem, workflow, or release slice; writes a structured PRD locally.
---

# Product Requirements Documents

Create a practical PRD from the approved conversation, repository context, and
existing project documents.

## Inputs

Before writing the PRD:

1. Read the relevant project files and existing documents.
2. Read the relevant plans, specifications, ADRs, and glossary files.
3. Use the project's domain vocabulary and distinguish facts from assumptions.
4. Separate confirmed requirements, reasonable assumptions, and open decisions.
5. Do not publish the PRD to GitHub, Linear, or another issue tracker.

## Output

Write the PRD to:

```text
docs/prd/YYYY-MM-DD-<feature-name>.md
```

Use another path only when the user specifies one. Do not commit the document
unless the user explicitly asks for a commit.

## Required Structure

```markdown
# <Feature> Product Requirements Document

**Status:** Draft | Ready for review | Approved
**Product:** <product name>

## Problem Statement
## Users and Roles
## Goals
## Non-Goals
## Context and Assumptions
## User Workflows
## Functional Requirements
## Authorization and Privacy
## Domain and Data Requirements
## Realtime and PWA Requirements
## UX Requirements
## Acceptance Criteria
## Testing and Observability
## Rollout and Migration
## Open Decisions
```

## Requirement Rules

- Give requirements stable identifiers such as `FR-001` and `NFR-001`.
- State who performs an action, what they can do, and what the system must
  guarantee.
- Define authorization, data integrity, privacy, failure, and recovery
  behavior where relevant.
- Define empty, loading, error, disconnected, and duplicate-submission states
  for interactive workflows.
- Keep business rules explicit and server-authoritative when the backend owns
  the domain.

## Acceptance Criteria

Prefer concrete scenarios:

```text
Given a user has permission to perform an action
When the user submits valid input
Then the system persists the expected change and returns the authoritative
result.
```

Each acceptance criterion must be testable through an appropriate seam, such
as a feature test, domain service test, integration test, or browser test.

## Review Gate

After writing the PRD, check it for:

- Missing roles or workflows
- Unresolved domain assumptions
- Requirements that conflict with privacy or authorization constraints
- Requirements that cannot be tested
- Scope that should be split into separate PRDs

Ask the user to approve the PRD before using `writing-plans`.

## Project Context

For this repository, use `docs/cspc-siklab-plan.md` as product context. Keep
event scoping, auditable scoring, and CSPC privacy requirements explicit when
they are relevant to the feature.
