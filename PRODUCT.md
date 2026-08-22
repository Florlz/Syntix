# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Syntix first serves event organizers and Global Admins who configure an intramural event, manage participants and staff, publish schedules and brackets, supervise scoring, approve results, and protect the official championship record.

Judges and Tabulators are primary event-day users. Judges score assigned entries against approved criteria. Tabulators monitor evidence, record authorized adjustments, resolve the operational result, and submit it for approval.

The public audience is secondary and read-only. Students, staff, participants, and spectators follow published schedules, brackets, live unofficial scores, and approved standings without signing in.

## Product purpose

Syntix runs school intramural events through one accountable system. It connects event configuration, registration, staffing, scheduling, brackets, scoring, result approval, standings, and public updates.

Success means event staff can complete those workflows without maintaining competing spreadsheets or unofficial score records, while the public can follow the event without gaining access to operational controls.

## Positioning

Syntix uses one server-authoritative chain from event setup through approved championship points. Role-scoped Judge and Tabulator work, result approval, immutable scoring evidence, and the official ledger remain connected instead of being passed between unrelated tools.

The product is reusable across schools and intramural editions. CSPC SIKLAB is the current reference implementation, not the permanent institutional boundary.

## Operating context

Organizers prepare the event before competition begins, then operate it from an event desk while matches and judged activities run across several venues. Judges commonly score on phones. Tabulators and Global Admins need denser laptop views for evidence review and operational control.

Published schedules, brackets, scoring rules, participant rosters, Judge scorecards, Tabulator adjustments, result submissions, approvals, and championship standings form the event record. Approved institutional proposals may provide source rules, but the system must preserve explicit decisions when those sources conflict or omit required detail.

Public values may update during competition, but they remain unofficial until the required approval creates an official Division Placement and championship-ledger entry.

## Capabilities and constraints

- Support multiple events and editions without hard-coding dates, delegations, rules, or point schedules.
- Support team, individual, combat, athletics, judged, literary, musical, dance, visual-arts, academic, and esports competitions represented by the configured event programme.
- Keep Laravel and PostgreSQL authoritative for authorization, scoring, approvals, brackets, standings, and ledger totals.
- Keep browser storage, PWA caching, queues, and realtime delivery subordinate to server state.
- Restrict staff access by authenticated role and explicit event scope.
- Keep anonymous public access read-only.
- Treat live public values as unofficial and approved placements as official.
- Preserve scoring revisions, audit evidence, authorization rules, and approval history.
- Do not automate subjective judging.
- Pageants, student self-registration, medical-document uploads, ticketing, budgeting, and video streaming remain outside the current verified scope.
- Full offline synchronization, realtime Reverb and Echo delivery, and PDF report archives are not part of the verified baseline yet.

## Brand commitments

The product name is Syntix. Its current reference deployment is CSPC SIKLAB, with institutional source material and CSPC imagery already present in the repository.

Future school deployments must be able to replace institutional names, seals, event programmes, delegations, and rules without changing Syntix's scoring and approval guarantees. No future interface may imply that every deployment belongs to CSPC.

Product copy is direct and operational. It distinguishes live from official data, names blockers, and tells staff the next valid action. It does not fabricate certainty when a source rule is unresolved.

## Evidence on hand

- `README.md` documents the implemented product boundary, authority model, stack, and known incomplete work.
- `docs/Approved-2025-Intramurals-Proposal.md` and `docs/Approved-2025-Intramurals-Proposal.pdf` contain the approved CSPC SIKLAB 2025 source programme.
- `docs/MANUSCRIPT FINAL 123 PDF COPY.pdf` and `docs/SYNTIX.docx` preserve project and institutional source material.
- `docs/chatdocs/` and `docs/superpowers/` contain implementation decisions, design specifications, stabilization plans, and delivery handoffs.
- The running Laravel application, database models, feature tests, UI tests, and public pages demonstrate the current workflows.
- The repository contains CSPC and Syntix brand assets. It does not contain verified testimonials, adoption figures, performance benchmarks, or claims about use by other institutions. Future work must not invent them.

## Product principles

1. The server decides. Client caches and live delivery may improve speed, but they never become a second scoring authority.
2. Every official result has evidence. Scorecards, adjustments, revisions, approvals, and ledger entries remain traceable.
3. Event-day work stays obvious. Each role sees its current state, blocker, and next valid action without exposing controls it cannot use.
4. Reuse comes from configuration. New schools and editions replace institutional data and rules instead of forking core scoring behavior.
5. Public speed never outranks official accuracy. Live information is useful, clearly labeled, and separated from approved standings.

## Accessibility & inclusion

Syntix must remain usable with keyboard navigation, visible focus, semantic labels, screen-reader status announcements, high-contrast preferences, reduced motion, and larger text settings. Worker screens must support phone-sized touch interaction and laptop-sized dense review without hiding required information.

