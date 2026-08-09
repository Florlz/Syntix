---
name: grill-me
description: Use when the user explicitly asks to stress-test a plan, PRD, architecture, business rule, or UI direction through focused questions.
---

# Plan Stress Test

Relentlessly test a plan without taking implementation action.

## Rules

- Ask one question at a time.
- Start with the highest-risk unknown, not minor preferences.
- Prefer concrete scenarios over abstract opinions.
- Use existing files and the project glossary to answer questions yourself when
  the repository already contains the answer.
- Challenge scope, authorization, data correctness, privacy, concurrency,
  failure handling, user context, and correction workflows.
- Keep a decision log as the user answers.
- Stop when every important branch has a clear decision or an explicit
  deferred decision with an owner and a trigger.

## Useful Scenarios

- A user submits the same action twice.
- Two authorized users modify one record concurrently.
- An administrator corrects finalized or published information.
- A viewer opens the product while data is stale or disconnected.
- A parent entity changes after dependent records exist.
- A public viewer tries to access private information.

Finish with:

- Confirmed decisions
- Remaining risks
- Deferred decisions
- Recommended next document: PRD, design, or implementation plan

Do not write code, modify documents, or commit while grilling unless the user
explicitly ends the review and requests the next action.
