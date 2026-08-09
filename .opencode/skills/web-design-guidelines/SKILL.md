---
name: web-design-guidelines
description: Use when reviewing frontend UI for accessibility, responsive behavior, forms, realtime states, PWA safety, UX, and performance issues.
---

# Web Design Guidelines Review

Review UI code after implementation or when the user asks for a UI, UX,
accessibility, or responsive audit. This skill reports findings first and does
not silently modify files.

## Review Inputs

1. Identify the page, component, or file pattern being reviewed.
2. Read the relevant React, CSS, Blade, route, and test files.
3. Fetch the latest Vercel Web Interface Guidelines when network access is
   available:

   ```text
   https://raw.githubusercontent.com/vercel-labs/web-interface-guidelines/main/command.md
   ```

4. Apply the checklist below in addition to the fetched guidance.

## Review Checklist

- Numeric data that changes in place uses stable tabular numerals where useful.
- Color is not the only signal for status or meaning.
- Controls have clear labels, suitable touch targets, and confirmation for
  destructive actions.
- Keyboard focus is visible and the main workflows work without a pointer.
- Forms provide autocomplete, validation, server errors, and recovery paths.
- Loading, empty, error, stale, and disconnected states are explicit.
- Live updates do not unexpectedly steal focus or move the user away from a
  form.
- Public pages do not reveal private data through markup or cached responses.
- PWA theme metadata, viewport behavior, and install experience are coherent.
- Layouts work at the product's required mobile, tablet, desktop, and display
  sizes.
- Motion respects `prefers-reduced-motion` and does not obscure live updates.
- Images have dimensions and useful alternative text.
- Date, time, and numeric formatting are explicit and locale-aware.

## Findings Format

Report findings before summaries:

```text
P1 - resources/js/Pages/Example/Edit.jsx:84
The destructive action has no confirmation or recovery path, so a mistaken
tap can permanently alter data.

Recommendation: require an explicit confirmation and provide an appropriate
recovery flow to authorized users.
```

Use severity levels consistently:

- `P1`: blocks access, correctness, security, or an important workflow
- `P2`: significant usability, responsive, or accessibility issue
- `P3`: polish, consistency, or performance improvement

If there are no findings, say so explicitly and list residual testing gaps.

## Project Context

For CSPC SIKLAB screens, pay particular attention to live-score legibility,
one-handed scorer controls, connection state, public player privacy, and large
public displays.
