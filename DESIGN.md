---
name: Syntix Official Results Bulletin
description: A precise, paper-bright interface for accountable event operations.
colors:
  paper: "#fefefe"
  ink-navy: "#001a3f"
  surface: "#ffffff"
  quiet-paper: "#fafcfc"
  slate-copy: "#52677f"
  rule: "#bec6db"
  control-rule: "#76869b"
  action-cyan: "#197f9d"
  action-cyan-hover: "#166d88"
  action-ink: "#ffffff"
  bulletin-gold: "#e8aa32"
  danger-red: "#c83f3f"
  danger-paper: "#fff2f1"
typography:
  display-mark:
    fontFamily: "Barlow Condensed, Figtree, ui-sans-serif, system-ui, sans-serif"
    fontSize: "7rem"
    fontWeight: 700
    lineHeight: 0.8
  headline:
    fontFamily: "Figtree, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 700
    lineHeight: 1.25
  title:
    fontFamily: "Figtree, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 700
    lineHeight: 1.5
  body:
    fontFamily: "Figtree, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: "Figtree, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 700
    lineHeight: 1.25
    letterSpacing: "0.08em"
  micro-label:
    fontFamily: "Figtree, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.625rem"
    fontWeight: 700
    lineHeight: 1.25
    letterSpacing: "0.08em"
  compact-label:
    fontFamily: "Figtree, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.65rem"
    fontWeight: 700
    lineHeight: 1.25
    letterSpacing: "0.08em"
  overline:
    fontFamily: "Figtree, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.68rem"
    fontWeight: 700
    lineHeight: 1.25
    letterSpacing: "0.08em"
  operational-number:
    fontFamily: "Barlow Condensed, Figtree, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.875rem"
    fontWeight: 700
    lineHeight: 1
rounded:
  sm: "2px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "12px"
  lg: "16px"
  xl: "24px"
  2xl: "32px"
components:
  button-primary:
    backgroundColor: "{colors.action-cyan}"
    textColor: "{colors.action-ink}"
    typography: "{typography.body}"
    rounded: "{rounded.sm}"
    padding: "0 16px"
    height: "44px"
  button-primary-hover:
    backgroundColor: "{colors.action-cyan-hover}"
  button-secondary:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.action-cyan}"
    typography: "{typography.body}"
    rounded: "{rounded.sm}"
    padding: "0 16px"
    height: "44px"
  input:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink-navy}"
    typography: "{typography.body}"
    rounded: "{rounded.sm}"
    padding: "10px 12px"
    height: "44px"
  status-tag:
    backgroundColor: "{colors.quiet-paper}"
    textColor: "{colors.ink-navy}"
    typography: "{typography.label}"
    rounded: "{rounded.sm}"
    padding: "4px 8px"
---

# Design System: Syntix Official Results Bulletin

## Overview

**Creative North Star: "Official Results Bulletin"**

Syntix is the official event record in motion. Its interface borrows the authority and legibility of a posted results bulletin: bright paper, dark ink, crisp rules, compact operational labels, and a restrained cyan action color. It should feel trustworthy under event-day pressure, not decorative or theatrical.

The system is information-dense without becoming cramped. Hierarchy comes from typography, alignment, rules, and quiet tonal changes rather than stacks of floating cards. Global Admin and organizer screens use the same visual language as Judge and Tabulator work, while preserving each workflow's established structure.

**Key Characteristics:**

- Paper-bright, permanent light presentation
- Ink-navy hierarchy with cyan actions and restrained gold signals
- Flat surfaces separated by hairline rules
- Compact labels and condensed operational numerals
- Clear next actions and explicit record states

## Colors

The palette reads like official paper and ink, with cyan reserved for action and status clarity.

### Primary

- **Action Cyan** (#197f9d): Primary actions, links, progress, active navigation, and focus rings. White small text meets WCAG AA on this color.
- **Deep Action Cyan** (#166d88): Hover and pressed treatment for primary actions.

### Secondary

- **Bulletin Gold** (#e8aa32): Sparse event markers, emphasis rules, and special attention states that are not errors.

### Neutral

- **Paper** (#fefefe): Application background.
- **Clean Surface** (#ffffff): Forms, tables, and operational sections.
- **Quiet Paper** (#fafcfc): Toolbars, secondary rows, and subdued regions.
- **Ink Navy** (#001a3f): Primary copy, headings, and authoritative values.
- **Slate Copy** (#52677f): Supporting copy and metadata.
- **Hairline Rule** (#bec6db): Borders, dividers, and table structure.
- **Control Rule** (#76869b): Field and actionable control boundaries that must remain distinct from white surfaces.
- **Danger Red** (#c83f3f): Destructive actions, blocking errors, and invalid states.
- **Danger Paper** (#fff2f1): Background for danger controls and error notices.

**The Cyan Is Action Rule.** Action Cyan identifies interaction, progress, or focus. Do not use it as broad decoration.

**The Gold Is Rare Rule.** Bulletin Gold marks exceptional event context. It must not compete with primary actions.

## Typography

**Display Font:** Figtree (with `ui-sans-serif`, `system-ui`, `sans-serif`)
**Body Font:** Figtree (with `ui-sans-serif`, `system-ui`, `sans-serif`)
**Label/Number Font:** Barlow Condensed for operational numerals; Figtree for labels

**Character:** Figtree keeps dense operational copy open and modern. Barlow Condensed gives totals, rankings, scores, and short identifiers the compact presence of a posted result sheet.

### Hierarchy

- **Headline** (700, 1.5rem, 1.25): Page titles and primary workflow headings.
- **Display Mark** (700, 7rem, 0.8): Oversized editorial numerals or initials used as quiet section artwork.
- **Title** (700, 1rem, 1.5): Section and component headings.
- **Body** (400, 0.875rem, 1.5): Instructions and supporting content, generally kept below 75ch.
- **Label** (700, 0.75rem, 0.08em, uppercase when appropriate): Status, table headers, metadata, and operational group labels.
- **Micro Labels** (700, 0.625rem to 0.68rem, 0.08em): Dense overlines, compact status metadata, and diagram annotations.
- **Operational Number** (700, 1.875rem, 1): Scores, rankings, totals, and other values that need quick scanning.

**The Numbers Carry State Rule.** Use condensed numerals for important operational values, not for paragraphs or ordinary control labels.

## Layout

Pages use a disciplined 4px spacing base with common steps at 8, 12, 16, 24, and 32px. Admin pages retain their existing information architecture and use ruled sections, compact toolbars, and stable table alignment. Content padding begins at 16px on narrow screens and grows to 28 or 32px on larger screens.

Judge scorecards use guided criteria focus. On wide screens, the criterion index, active scoring workspace, and official context occupy three columns. On phones, the same content becomes a single flow with the score input kept touch-safe and the final actions visible without horizontal scrolling.

At 640px, actions and toolbars may begin arranging horizontally. Dense three-column scorecard composition is reserved for extra-large screens. Controls that trigger consequential operations remain at least 44px high.

## Elevation & Depth

The system is flat by default. Depth is communicated with borders, section rules, tonal surfaces, and sticky positioning. Shadows are reserved for temporary layers such as dialogs or drawers where separation from the page is structural.

### Shadow Vocabulary

- **Temporary Layer** (`0 20px 50px rgb(0 26 63 / 0.18)`): Dialogs, drawers, and other content that sits above the current workflow.

**The Ruled Paper Rule.** Prefer a hairline border or quiet surface shift before introducing a shadow.

## Shapes

Corners are precise and nearly square. Interactive controls and small status markers use a 2px radius. Sections rely on full outlines and horizontal rules instead of rounded card stacks. Pills are reserved for genuinely categorical states, never as a default container shape.

## Components

### Buttons

- **Shape:** Compact rectangle with a 2px radius and a minimum 44px height.
- **Primary:** Action Cyan background, white bold text, 16px horizontal padding, and a matching border.
- **Hover / Focus:** Deep Action Cyan on hover; a two-pixel Action Cyan focus ring with visible offset.
- **Secondary:** White surface, Control Rule border, and Action Cyan text. Hover shifts to Quiet Paper and an Action Cyan border.
- **Danger:** Danger Paper with Danger Red border and copy. Reserve it for destructive or irreversible choices.

### Chips

- **Style:** Small 2px-radius labels with a rule border, quiet background, bold compact text, and explicit wording.
- **State:** Use color only as reinforcement. State labels must remain understandable without color.

### Cards / Containers

- **Corner Style:** Square or 2px radius only when the container is interactive.
- **Background:** Clean Surface for primary content and Quiet Paper for secondary bands.
- **Shadow Strategy:** None at rest.
- **Border:** One-pixel Hairline Rule, with stronger rules reserved for selected or authoritative regions.
- **Internal Padding:** 16px on compact surfaces, 24px for major desktop sections.

### Inputs / Fields

- **Style:** White surface, one-pixel Control Rule, 2px radius, dark copy, and a 44px minimum height.
- **Focus:** Action Cyan border and ring. Focus must remain obvious in high-contrast mode.
- **Error / Disabled:** Error copy and border use Danger Red. Disabled fields use Quiet Paper and Slate Copy while keeping labels readable.

### Navigation

Navigation is flat, ruled, and text-led. Active destinations use Action Cyan or a restrained gold edge, strong Ink Navy copy, and explicit selected semantics. Mobile navigation preserves labels and touch target size rather than collapsing important destinations into ambiguous icons.

### Ruled Bulletin Section

A Ruled Bulletin Section groups one operational topic with a small uppercase kicker, a strong title, and a full-width divider. It is the preferred replacement for generic floating cards across admin screens.

### Guided Criterion Step

The Judge scorecard shows one criterion as the active task while keeping all criteria visible in a compact index. Completion uses icon, text, and color together. Switching steps must preserve entered scores and notes, and field errors must activate the affected criterion.

## Do's and Don'ts

### Do:

- **Do** use Ink Navy and typographic weight to establish authority before adding color.
- **Do** separate dense content with Hairline Rule borders and Quiet Paper bands.
- **Do** keep score, approval, and result states explicit in text.
- **Do** preserve 44px touch targets and visible keyboard focus.
- **Do** use Barlow Condensed for scores, totals, rankings, and other compact operational numbers.

### Don't:

- **Don't** add dark mode or dark event-control styling. Syntix is permanently light.
- **Don't** build generic white-and-blue SaaS screens from rounded cards and soft shadows.
- **Don't** use cyan or gold as broad decoration.
- **Don't** hide official status behind color, icons, or hover-only content.
- **Don't** change an established admin workflow merely to make it resemble the Judge scorecard.
