# Syntix Skills Setup Handoff

Date: 2026-08-17  
Project: Syntix  
Purpose: preserve the agreed Codex skill setup and the workflow for future Syntix sessions.

## User preferences established

- Use source-first UI development: inspect and follow the existing source, routes, components, tokens, and interaction patterns before adding new UI.
- Use the latest Tailwind version supported by the project and keep future work Tailwind v4-compatible.
- Use frontend-design guidance for intentional visual hierarchy, typography, layout, and product character.
- Use shadcn conventions when working with shadcn components or component patterns.
- Use browser inspection and automated tests for UI verification.
- Work directly in the current checkout and use inline execution.
- Commit and push product/documentation work to `master` when requested.
- Keep skill-development-only work local unless explicitly requested otherwise.

## Core skills and when to use them

| Skill | Use for Syntix |
| --- | --- |
| `using-superpowers` | Start each task, discover applicable skills, and follow the required skill workflow. |
| `brainstorming` | Explore intent and design options before creative feature or UI work. |
| `source-first-ui-development` | Any UI/UX redesign, responsive layout, component, accessibility, or design-system change. |
| `frontend-design` | Establish distinctive visual direction, typography, hierarchy, and non-template UI choices. |
| `tailwindcss-development` | Any message mentioning Tailwind, Tailwind utility work, responsive layouts, grids, forms, tables, or navigation styling. |
| `tailwind-design-system` | Tailwind v4 tokens, reusable patterns, component libraries, and scalable design-system work. |
| `shadcn` | shadcn/ui components, `components.json`, registries, presets, composition, and debugging. |
| `browser:control-in-app-browser` | Inspect and smoke-test local pages in the in-app browser. |
| `chrome:control-chrome` | Use the user's Chrome session when existing tabs, authentication, or Chrome state matters. |
| `test-driven-development` | Write or update tests before implementing a feature or fix. |
| `systematic-debugging` | Investigate bugs, test failures, database errors, and unexpected behavior before changing code. |
| `verification-before-completion` | Run fresh verification before claiming completion, committing, or pushing. |
| `requesting-code-review` | Review major feature work or completed implementation before integration. |
| `finishing-a-development-branch` | Decide how completed work is committed, merged, and published. |
| `github:yeet` | Use for an intentional GitHub publish workflow; current user preference is to publish to `master` and avoid unrequested PR creation. |
| `writing-plans` / `executing-plans` | Create or execute detailed multi-step plans supplied in `docs/chatdocs/`. |
| `subagent-driven-development` | Split independent implementation or review tasks when useful. |
| `skill-creator` / `writing-skills` | Create, edit, or validate a reusable Codex skill. |
| `find-skills` / `skill-installer` | Discover or install additional skills when the user asks for new capabilities. |

## Current skill locations

The skills are environment-level Codex assets, not application source files:

- Source-first UI: `C:\Users\monte\.codex\skills\source-first-ui-development\SKILL.md`
- Frontend design: `C:\Users\monte\.codex\skills\frontend-design\SKILL.md`
- Tailwind design system: `C:\Users\monte\.codex\skills\tailwind-design-system\SKILL.md`
- Tailwind CSS development: `C:\Users\monte\.agents\skills\tailwindcss-development\SKILL.md`
- shadcn: `C:\Users\monte\.codex\skills\shadcn\SKILL.md`
- Superpowers entry point: `C:\Users\monte\.codex\skills\using-superpowers\SKILL.md`

Always read the selected `SKILL.md` completely before taking the related task actions. If a skill references additional files, follow its routing instructions and only load the relevant references.

## Recommended Syntix UI workflow

1. Read the relevant plan or inspect the current page and source files.
2. Invoke `using-superpowers`, then the specific UI skills required by the request.
3. For new visual work, use `brainstorming` before implementation and record the chosen direction.
4. Use `source-first-ui-development` to preserve existing routes, data contracts, components, and visual language.
5. For Tailwind work, use `tailwindcss-development` and `tailwind-design-system`; verify the project is still on Tailwind v4 before changing configuration.
6. Use shadcn patterns where the project already uses them or where the user specifically requests them.
7. Use TDD for behavior changes and systematic debugging for failures or regressions.
8. Run targeted tests, the relevant full test suites, the production build, and browser smoke checks when UI behavior is involved.
9. Use `verification-before-completion` before making completion claims or publishing.
10. Commit only the intended files and push to `master` when authorized. Do not include local-only skill-development artifacts.

## Tailwind v4 project baseline

At this session's repository snapshot:

- `tailwindcss`: `^4.3.3`
- `@tailwindcss/vite`: `^4.3.3`
- Tailwind is integrated through [`vite.config.js`](../../vite.config.js).
- The unified setup removed the legacy `tailwind.config.js` and `postcss.config.js` path.
- The migration references are [`syntix-tailwind-v4-upgrade-plan.md`](../chatdocs/syntix-tailwind-v4-upgrade-plan.md) and [`2026-08-17-tailwind-v4-migration.md`](../superpowers/plans/2026-08-17-tailwind-v4-migration.md).

Before a future upgrade, verify the current package versions and official project guidance instead of assuming the version has not changed.

## Source-first skill development state

The source-first workflow was documented during this session in [`2026-08-17-source-first-ui-development-skill.md`](../superpowers/plans/2026-08-17-source-first-ui-development-skill.md).

The following commits are intentionally preserved only on the local branch `local/source-first-ui-skill` and must not be pushed unless the user changes that instruction:

- `99b286d` - `docs(skills): define source-first UI workflow`
- `5775c9b` - `docs(skills): plan source-first UI skill`

The product `master` branch contains the application work and handoff documentation, not these skill-development commits.

## Current repository state

- Remote `master` is at `ab7abb3`.
- `ab7abb3` contains the full-session handoff.
- `8654437` contains the sport workspace stabilization implementation.
- The stabilization branch remains available as `agent/sport-workspace-stabilization`.
- The local-only skill commits remain available as `local/source-first-ui-skill`.
- User-provided plan documents under `docs/chatdocs/` and the Tailwind migration plan remain local/uncommitted unless explicitly requested.

## Future handoff guidance

For future sessions, include the active plan, selected skills, source files changed, tests/build/browser verification, commit and branch state, known warnings or skipped tests, and any intentionally local-only work. Keep the handoff in `docs/handoffs/` and do not stage unrelated plan files automatically.
