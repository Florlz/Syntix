---
name: brainstorming
description: Use before creating a feature, component, workflow, or behavior change to clarify the problem, constraints, alternatives, and approved design.
---

# Brainstorming

Turn an initial idea into a design the user can review before code is written.

## Hard Gate

Do not write code, scaffold files, run implementation commands, or make
behavior changes until the design has been presented and approved.

## Process

1. Explore the existing project context using `glob`, `grep`, and `read`.
2. Check whether the request contains multiple independent subsystems. If it
   does, identify the smallest useful first slice.
3. Establish the user, problem, success criteria, constraints, and non-goals.
4. Ask one focused clarifying question at a time. Prefer choices when they
   make the decision easier.
5. Propose two or three approaches with trade-offs and a recommendation.
6. Present the design in reviewable sections: scope, workflow, architecture,
   data, authorization, failure handling, testing, and UI when relevant.
7. Wait for user approval. Revise the design if the user disagrees.
8. Write the approved design to `docs/specs/YYYY-MM-DD-<topic>-design.md`,
   unless the user specifies another path.
9. Self-review the document for placeholders, contradictions, scope gaps, and
   ambiguous terms.
10. Ask the user to review the written document before creating an
    implementation plan.

## Project Context

When working in this repository, read `docs/cspc-siklab-plan.md` and use its
domain terminology and privacy constraints. Preserve existing Laravel and
Inertia patterns unless the approved design requires otherwise.

Do not commit unless the user explicitly asks.
