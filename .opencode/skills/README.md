# Project Skills

These are project-local OpenCode skills with light repository-specific notes.

OpenCode discovers them automatically from `.opencode/skills/<name>/SKILL.md`.
No global installation or `opencode.json` change is required.

## Skills

- `brainstorming`: Turn a rough feature idea into an approved design before implementation.
- `prd-maker`: Create a local product requirements document for a feature or subsystem.
- `writing-plans`: Turn an approved design or PRD into a file-specific implementation plan.
- `domain-modeling`: Sharpen domain terminology, invariants, relationships, and decisions.
- `frontend-design`: Create distinctive, accessible UI direction before implementation.
- `web-design-guidelines`: Audit React and Inertia UI for accessibility, UX, responsive behavior, and performance.
- `grill-me`: Stress-test a plan or design through focused questions. Use this intentionally, not for every task.

## Local Conventions

The skills retain their general purpose while following a few local
conventions:

- Outputs are local Markdown documents under `docs/`.
- Skills do not publish to an issue tracker.
- Skills do not create commits automatically.
- Relevant skills point to `docs/cspc-siklab-plan.md` instead of embedding the
  full product specification.

## Typical Workflow

1. Use `brainstorming` for a new feature or behavior change.
2. Use `domain-modeling` when a feature changes important concepts or invariants.
3. Use `prd-maker` to capture the approved product requirements locally.
4. Use `writing-plans` before implementing a multi-step feature.
5. Use `frontend-design` before creating or reshaping UI.
6. Use `web-design-guidelines` to review completed UI.

## Sources

- [OpenCode Agent Skills documentation](https://opencode.ai/docs/skills/)
- [obra/superpowers](https://github.com/obra/superpowers)
- [mattpocock/skills](https://github.com/mattpocock/skills)
- [anthropics/skills](https://github.com/anthropics/skills)
- [vercel-labs/agent-skills](https://github.com/vercel-labs/agent-skills)

See `THIRD_PARTY_NOTICES.md` for source and license attribution.
