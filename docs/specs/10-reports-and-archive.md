# Reports and Archive

**Design status:** Baseline
**Implementation status:** See [implementation status](../implementation-status.md).
**Product:** SYNTIX

## Sources and Authority

This specification refines reporting and archive requirements under the [product and domain contract](../cspc-siklab-plan.md). The product contract controls official results, ledger effects, correction history, privacy, authorization, and historical immutability.

- Confirmed product decisions: reports use frozen rule versions and official result revisions; approved data is not overwritten; historical event editions remain reproducible after later rule changes.
- Required report intent: `docs/MANUSCRIPT FINAL 123 PDF COPY.pdf` calls for Judge breakdowns, sports summaries, championship results, PDF generation, and historical retrieval. The product contract replaces its unsupported `medal tally` wording with `championship point tally` unless CSPC confirms separate medal tracking.
- Proposal source: `docs/Approved-2025-Intramurals-Proposal.pdf` supplies the 2025 competition rules, criteria, sub-points, and placement-point schedules used by report inputs.
- Privacy authority: private reports require authorization; public names, publication, and retention remain institutional decisions. Pageants remain outside initial scope.

## Problem

Officials need printable, defensible records after fast-moving competitions. A report generated from mutable current rules, overwritten results, manually edited totals, or undocumented correction state cannot reproduce what was approved and cannot serve as reliable archive evidence.

## Goals

- Generate the initial official report set from frozen, traceable data.
- Preserve source revisions, approvals, corrections, voids, rules, and point effects used by each report.
- Make generated artifacts tamper-evident and their inputs reproducible.
- Authorize private reports separately from sanitized public publication.
- Archive event editions as immutable historical records while preserving approved correction workflows.
- Use `championship point tally`, not `medal tally`, until independent medal tracking is confirmed.

## Non-Goals

- Financial, procurement, medical, eligibility-document, ticketing, or honorarium reports.
- Spreadsheet-based manual override of official totals.
- A medal tally without an approved medal domain and rules.
- Public release of private Judge, participant, approval, or correction details by default.
- Reconstructing missing official source data from paper within this slice.
- Pageant reports.

## Users and Authorization

- An event Admin may request, inspect, download, and regenerate private official reports for an event they administer.
- Admin report access is still event-scoped and policy-enforced. A global account does not automatically receive every archived event.
- Judges and Tabulators do not receive private report access through role alone. Any future report access must be explicitly defined and restricted to assignment and data scope.
- Anonymous users may access only a separately published sanitized artifact or public DTO under `docs/specs/09-public-scoreboard.md`.
- Queue workers may generate reports as the requesting actor's authorized job, but they do not broaden the actor's data scope.
- Report storage, download URLs, logs, and caches must preserve the same privacy classification as the report.

## Workflow

1. Admin selects an event, report type, official cutoff or revision scope, approved filters, and output format.
2. The server authorizes the request and creates a report job with an immutable input manifest and immutable committed-transaction cutoff. The cutoff never advances for that report record.
3. After the transaction boundary is established, a queue worker reads the referenced frozen rules, official result revisions, ledger entries, approvals, and correction history.
4. The generator validates completeness and internal totals, renders the artifact, computes its content hash, and stores generation metadata.
5. Admin downloads the original artifact through an authorized route and can inspect its input manifest and generation status.
6. Regeneration creates a new generated-report record referencing the same or explicitly newer official input set. It never replaces the original artifact.
7. Closing and archiving an event freezes configuration and default report scope. Later authorized corrections append revisions and may generate a new archive report set while retaining the prior set.
8. Public publication, if approved, creates or exposes a separate sanitized artifact rather than making the private source report anonymous.

## Domain/Data Requirements

### Initial Report Set

| Report | Minimum authoritative content | Default classification |
|---|---|---|
| Detailed Judge score breakdown | Competition family, score-bearing Division, entries, frozen criteria and number meanings, authorized Judge values, deductions and stages, calculations, aggregation, placement, separate outcome and placement approval references | Private |
| Division result summary | Competition family and score-bearing Division, final placements and statuses, frozen point rule, placement approval, official point effects | Private; sanitized public version optional |
| Sports match or bracket summary | Competition family, Division, published bracket version or sports format, contests, approved Official Contest Outcomes, BYEs or forfeits, advancement, separately approved final Division placement, corrections | Private; sanitized public version optional |
| Aggregate discipline and sub-point summary | Competition family, score-bearing Division, disciplines, Men/Women category as configured, measurements or outcomes, placements, individual or relay sub-points, totals, ties or rulings, final Division placement | Private; sanitized public version optional |
| General championship point tally | Delegations, every committed signed ledger amount through the immutable cutoff grouped by Division and point-rule version, awards, reversals, replacements, total, and ranking | Private; sanitized public version optional |
| Result correction and approval history | Submission, approval, rejection, correction, void, actors, timestamps, reasons, source and replacement revisions, ledger reversals and replacements | Private |

- A generated report record must identify report UUID, event, type and schema version, format, privacy class, requested actor and time, generation time, locale and timezone, status, filters, immutable official committed-transaction cutoff, source revision manifest, renderer version, storage reference, byte size, content hash, and supersession relationship if any.
- The source manifest must reference all relevant event, Competition, Division, rule, criteria, bracket, scorecard, Official Contest Outcome, final placement, revision, ruling, approval, and ledger identifiers plus their immutable versions or hashes.
- Reports must label unofficial data if a non-official operational preview is ever supported. Official reports distinguish approved Official Contest Outcomes from separately approved final Division placements.
- At the immutable cutoff, each delegation's championship point tally is `SUM(amount)` over every committed ledger entry for that delegation at or before the cutoff. Amounts are signed and include awards, reversals, and replacements; no ledger-status filter or editable report total applies.
- Historical rules and point mappings used by an official result remain readable after new versions are created.
- Original generated bytes are immutable. A correction or regeneration creates a new artifact and relationship; it does not overwrite a file under the same report record.
- Download responses use authorization, safe filenames, correct content type, no public cache for private reports, and expiring or controller-mediated access.
- Archive indexes must separate event editions and display lifecycle, date range, official status, latest report set, correction state, and publication state without changing historical inputs.

## Invariants

- An official report derives from frozen rules, approved outcome and placement revisions, and committed signed ledger entries at an immutable recorded cutoff.
- Later rule versions cannot change an existing report's source manifest or bytes.
- Approved results, correction history, and ledger entries are never deleted or silently overwritten to simplify a report.
- Original generated artifacts are immutable and content-hashed.
- Regeneration creates a new artifact record even when the selected source manifest is identical.
- The championship tally equals the sum of all committed signed award, reversal, and replacement ledger amounts at or before the immutable cutoff; no status flag or report field can override it.
- Private reports are never exposed through anonymous URLs, public broadcasts, or public PWA caches.
- A sanitized public report is a distinct authorized projection, not the private artifact with hidden UI columns.
- `Medal tally` does not appear as an official report type until CSPC confirms an independent medal model.
- Archived event editions are read-only except through authorized append-only correction and retention workflows.

## Edge and Failure Cases

- Missing required official result, rule version, or source revision: fail the report with a clear validation error; do not fill gaps from current configuration.
- Ledger total does not reconcile: block official championship report generation and identify the affected Division or delegation for Admin review.
- Queue failure or timeout: retain failed status and diagnostic reference, allow safe retry, and do not expose a partial artifact as complete.
- Duplicate generation request: either return the existing completed artifact for an identical idempotency key or create one job exactly once.
- Correction occurs while generating: the report uses its immutable established cutoff and manifest. Entries committed after that cutoff, including reversals and replacements, appear only in a later report request with a new cutoff.
- Report renderer changes: retain renderer version and original bytes; regeneration may be data-equivalent without being byte-identical because metadata can differ.
- Private download link is shared: authorization is checked at download time and expired access is denied.
- Participant name visibility changes: existing private artifacts remain governed by retention policy; future public artifacts use the current approved publication decision and recorded setting.
- Event is voided or reopened after archive: preserve archived versions and create a new lifecycle and report revision trail through authorized action.
- Storage object missing or hash mismatch: mark integrity failure, block download as official, alert Admin, and regenerate only from a verified source manifest.
- Retention period expires: apply approved disposition by data class without deleting official evidence that CSPC must retain.

## Functional Requirements

| ID | Requirement |
|---|---|
| RPT-FR-001 | Admin can request a detailed Judge score-breakdown report from frozen criteria, scorecards, deductions, aggregation, and approvals. |
| RPT-FR-002 | Admin can request a Division result summary from its approved final placement and point effects. |
| RPT-FR-003 | Admin can request a sports match or bracket summary that distinguishes approved contest outcomes from separate final Division placement approval. |
| RPT-FR-004 | Admin can request an aggregate discipline and sub-point report, including Athletics individual and relay effects. |
| RPT-FR-005 | Admin can request a general championship point tally reconciled to all committed signed award, reversal, and replacement ledger amounts through its immutable cutoff. |
| RPT-FR-006 | Admin can request a result correction and approval history report with original and replacement revisions. |
| RPT-FR-007 | Every report request is event-scoped and policy-authorized before job creation and download. |
| RPT-FR-008 | The server creates an immutable source manifest and immutable committed-transaction cutoff for each generated report. |
| RPT-FR-009 | Completed artifacts store content hash, renderer version, generation metadata, privacy class, and immutable storage reference. |
| RPT-FR-010 | Regeneration and post-correction generation create new artifact records without replacing prior artifacts. |
| RPT-FR-011 | Historical reports continue to resolve the exact frozen rule and result revisions used at generation. |
| RPT-FR-012 | Private artifacts are excluded from public routes, broadcasts, and service-worker caches. |
| RPT-FR-013 | Any public report is generated or exposed as a separately authorized sanitized projection. |
| RPT-FR-014 | Archive views separate event editions and retain historical lifecycle, official, correction, and report state. |
| RPT-FR-015 | Integrity or reconciliation failure prevents an artifact from being presented as an official report. |
| RPT-FR-016 | The system labels the official overall report `General Championship Point Tally` and provides no medal tally unless institutionally confirmed. |

## Acceptance Criteria

- Each of the six initial report types can be generated for an authorized event with complete source data.
- A championship point tally total for every delegation equals the sum of all committed signed award, reversal, and replacement ledger amounts at or before the immutable recorded cutoff.
- Changing a later event's rules does not change an earlier event's report, source manifest, or original bytes.
- Correcting an approved result leaves the prior report downloadable to authorized users and creates a new report that shows reversal and replacement history.
- A Judge score report traces every total to frozen criterion labels, raw ranges and values, maximum-or-weight meanings, precision, deduction stages, aggregation, tie rule, verified total, rounding policy, and separate outcome and placement approval revisions.
- An aggregate report distinguishes discipline sub-points from Major championship points and does not add sub-points directly to the championship tally.
- A private report URL denies anonymous and cross-event access and is absent from public caches.
- A public artifact, if enabled, contains only the approved sanitized field set and records the publication setting used.
- Downloaded bytes match the stored content hash; a mismatch prevents official download and records an integrity incident.
- A generation failure or missing source never produces a report labeled complete or official.
- The archive shows separate immutable event editions and still resolves historical rule and result revisions.
- No report or interface uses `medal tally` as an official SYNTIX output before institutional confirmation.
- A report generated at cutoff `C` is byte- and manifest-stable when a later reversal or replacement commits after `C`; a new report at cutoff `C2` includes those signed amounts.

## Testing

- Domain tests for report input selection, rule and result revision resolution, ledger reconciliation, correction history, cutoff behavior, and source-manifest hashing.
- Feature tests for each report type, event-scoped authorization, cross-event denial, queued generation, idempotent request, download authorization, retry, and publication separation.
- PostgreSQL tests for a correction racing report generation, immutable consistent cutoff snapshots, signed ledger reconciliation under concurrent approval, and immutable artifact metadata.
- Golden-data tests using proposal-derived judged, bracket, Athletics sub-point, and Major point examples. Compare semantic report content rather than assuming byte equality across renderer versions.
- Integrity tests for missing storage, modified bytes, hash mismatch, missing source revision, and failed generation.
- Privacy tests for participant names, Judge values, internal reasons, cache headers, expiring downloads, logs, and public sanitized artifacts.
- Archive tests proving later rule changes, renderer upgrades, correction, void, and event lifecycle changes do not overwrite prior records.
- User acceptance review with SSC or official Tabulation Committee for layout, signatures or certification needs, terminology, and archive retrieval.

## Decision Register

Reports and archive blockers are centralized in [`../open-decisions.md`](../open-decisions.md), items 15 and 60-64. This specification remains the technical contract for reproducible official artifacts, privacy, retention, and historical correction behavior.
