---
name: frontend-design
description: Use when creating or reshaping frontend pages, dashboards, interactive workflows, or PWA screens; defines an intentional visual direction before implementation.
---

# Frontend Design

Design a distinct product experience rather than a generic dashboard. Ground
visual choices in the subject, audience, and job of the screen.

## Design Before Code

Before implementation, write a compact design direction covering:

- The subject, audience, and single job of the screen
- A 4-6 color palette with named roles and hex values
- A deliberate display, body, and data typography pairing
- Layout structure for desktop and mobile
- One memorable signature element tied to the product's subject
- Motion and interaction behavior, including reduced-motion behavior
- Loading, empty, error, disconnected, and correction states

Use realistic product content. Avoid placeholder marketing copy when the
interface can show meaningful data.

## Visual Quality Bar

- Avoid interchangeable cards, excessive rounded containers, and default
  gradient hero layouts.
- Make typography, spacing, and hierarchy carry real meaning.
- Spend boldness on one signature element and keep surrounding UI disciplined.
- Use animation only when it communicates a live update, transition, or state.
- Respect `prefers-reduced-motion`.
- Preserve visible keyboard focus and semantic HTML.
- Support the required mobile, touch, desktop, and large-screen contexts.

## Implementation Constraints

- Follow existing project patterns and avoid adding a state library by default.
- Keep server state and permissions authoritative when the application owns
  them on the backend.
- Avoid `useMemo` and `useCallback` by default unless the existing project or
  measured behavior justifies them.
- Keep components focused and composable.
- Make URL state and filters shareable when that benefits viewers or admins.

Present the design direction for approval before writing a substantial new
screen. After implementation, review the result with `web-design-guidelines`.

## Project Context

This repository uses React through Inertia and targets mobile scorers,
administrators, viewers, and public display screens. Use approved CSPC branding
and make live scores, rankings, connection state, and touch controls legible.
