# Sport Workspace Spacing Pass

## Intent

Make the sport workspace easier to scan by removing the oversized empty band in the hero and tightening the vertical rhythm between the hero, tabs, and overview content. The existing hierarchy, colors, typography, and responsive behavior remain intact.

## Design

- Shorten the hero from a tall bottom-aligned panel to a compact, vertically centered header.
- Keep the division label and selector together as one control group, stacked on narrow screens and inline from the `sm` breakpoint upward.
- Hide the selector on Overview, where it does not filter the visible content. Keep it on division-specific tabs so administrators can still switch context while working.
- Reduce the gap above the workspace tabs and slightly reduce tab vertical padding.
- Tighten the overview stack without shrinking the cards enough to make their content feel cramped.

## Verification

- Run the Vite production build.
- Run `git diff --check`.
- Revisit a sport workspace at desktop width and confirm the selector, tabs, attention cards, and divisions sit in a compact but readable sequence.
