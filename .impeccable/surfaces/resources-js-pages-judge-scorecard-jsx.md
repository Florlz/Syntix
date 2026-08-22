---
version: 1
slug: "resources-js-pages-judge-scorecard-jsx"
primary_target: "resources/js/Pages/Judge/Scorecard.jsx"
related_targets: []
---

Scope: Judge scorecard route, Operate mode, inside the product-wide permanent light system.

Audience and job: An event-day Judge on phone or laptop must score the assigned entry accurately, understand required progress and official context, preserve a server-authoritative draft, and submit deliberately.

Content and constraints: Use existing scorecard props, routes, authorization, revision handling, official adjustments, and proposal evidence. Support arbitrary criterion counts, 360px touch use, large text, high contrast, reduced motion, errors, rejected corrections, and submitted or approved read-only states. Do not invent signature, seal, qualitative scale, Head Judge visibility, or client-authoritative totals.

Chosen direction: Official Results Bulletin with Guided Criteria Focus. Approved comp: .impeccable/mocks/scorecard-results-bulletin-b.png.

Memorable moment: A numbered criterion index points to one dominant scoring lane with a large numeric input, while compact remaining rows and the official summary keep the whole scorecard legible.

Component grammar: near-white ground; ink navy type; cyan-teal actions and active rules; cool hairline dividers; small-radius controls; no shadows as structure; Figtree UI copy; Barlow Condensed operational numerals; visible status text and focus.

Implementation inventory: semantic shell and masthead in React/Tailwind; criterion index buttons; active fieldset with native number input and details-based notes; compact criterion buttons; semantic details for authority on mobile; CSS-only ruled sheet and status geometry; existing Syntix logo and AppIcon assets; no new shipping raster. The primary action remains a semantic button in the bottom action strip.

Unresolved decisions: none. Generated-comp defects are corrected according to docs/superpowers/specs/2026-08-22-syntix-light-system-scorecard-redesign.md.
