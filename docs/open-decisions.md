# Institutional Decision Register

**Status:** Open decisions block only the affected activation, approval, or publication path.
**Last reviewed:** August 9, 2026

This is the canonical register for unresolved institutional rules. Product and technical specifications link here instead of maintaining competing decision lists. A decision should identify the approving authority, effective event edition, rule version, and required migration or test changes before implementation is unblocked.

## Identity And Governance

1. Define the one-time `event_creator` bootstrap mechanism and recovery authority.
2. Decide privileged email-verification requirements and institutional mail reliability expectations.
3. Decide whether the submitter or latest correction actor is prohibited from approving the same result, and define any exceptional override authority.
4. Define which Event Role combinations one person may hold in the same Event.
5. Define session idle and absolute lifetimes, including stricter Admin limits if required.
6. Define who may disable Event Admins, revoke sessions, and use exceptional recovery when last-creator protection is unavailable.
7. Define account and security-audit retention after an Event is archived.
8. Decide whether institutional SSO belongs in a later release.

## Competition Rules And Points

9. Define participation-point eligibility for withdrawals, no-shows, walkovers, forfeits, disqualifications, incomplete entries, and cancelled competitions.
10. Define official tie-break sequences for every Division, aggregate Division, and the overall championship.
11. Define decimal input scale, intermediate precision, final precision, rounding mode, and rounding stage for Judge and aggregate totals.
12. Define how multiple Judge scorecards combine: average, sum, rank aggregation, dropped scores, or another method.
13. Decide whether negative judged totals floor at zero and at which calculation stage.
14. Decide whether protests require a dedicated adjudication workflow or are represented only by authorized corrections.
15. Decide whether official reports include a separate medal count; until confirmed, use “championship point tally,” not “medal tally.”
16. Correct the 2025 Essay Writing criteria, whose displayed values total 95 percent.
17. Correct the 2025 Cheer Dance criteria and confirm its placement-point template.
18. Provide the missing 2025 Dance Sports criterion weights and confirm each category's Division and point template.
19. Resolve the Arnis roster conflict between the general table and detailed rules.
20. Resolve the Mobile Legends roster conflict between five members total and five players plus one reserve.
21. Provide a valid seven-team Esports opponent and playoff schedule.
22. Provide complete Call of Duty rules.
23. Correct source event-year and submission-date text that still states 2024.

## Basketball And Tournament Routing

24. Define Basketball participation-point eligibility and official tie/exception evidence.
25. Clarify whether running time applies through finals and define any sudden-death reset or score-recording convention.
26. Define the placement ruling for fewer than four entries when two semifinal losers do not exist.
27. Define which official may authorize walkovers, forfeits, no-shows, withdrawals, disqualifications, cancellations, and post-approval corrections.
28. Decide whether a later release needs an authoritative integrated game clock and hardware controls.
29. Approve the exact BYE distribution convention if the deterministic baseline is not accepted.
30. Define when an Admin may unpublish a bracket before contests begin and what committee approval is required.
31. Sign off every supported double-elimination size's complete winner, loser, BYE, placement, and reset routing map.
32. Define the 2nd Runner-Up rule for supported double-elimination sizes and for tournaments with fewer than four entries.
33. Define sport-specific walkover, no-show, forfeit, withdrawal, and disqualification rules.
34. Define round-robin win/draw/loss values and tie-break sequences.

## Athletics

35. Confirm Athletics as aggregate placement rather than the proposal's conflicting single-elimination label.
36. Confirm canonical names for the `400m x 100m` and `400m x 400m` relay entries.
37. Confirm Men/Women Divisions for long jump and triple jump.
38. Define heat sizes, qualification counts or standards, lane rules, attempt counts, and heat-to-final procedures.
39. Decide whether Men and Women are separate score-bearing Divisions or feed a combined aggregate Division.
40. Define fixed input, storage, comparison, and display precision for each time and distance discipline.
41. Define Athletics tie-break sequences for track, field, relay, aggregate, and overall placement.
42. Define fourth-place, non-medaling, did-not-finish, disqualified, withdrawn, and no-show Sub-Point and participation-point eligibility.
43. Define who may authorize time-trial or semifinal results for unfinished finals.
44. Confirm whether the proposal's 10 Men and 10 Women are exact roster sizes or maxima and how alternates/relay replacements work.
45. Define the maximum relay changes, comparison baseline, and point at which qualification becomes final.
46. Define the 3000m overlap meaning, removal order, timing/status behavior, and behavior with six or fewer starters.
47. Define counting semantics for athlete and delegation discipline limits, including entry, start, substitution, and qualification boundaries.

## Offline Operations

48. Define which Judge and Tabulator command types may be queued offline.
49. Decide whether logout with pending commands is blocked or allows explicit local deletion after identity confirmation and incident logging.
50. Define retention for applied, rejected, conflicted, and abandoned account-scoped commands and receipts.
51. Define the event-day contingency for IndexedDB failure, quota exhaustion, device loss, or unsynchronized commands.
52. Decide whether managed devices require remote wipe, additional encryption, or other local controls.
53. Define the authorized conflict-resolution choices and required reason fields.

## Public, Reports, And Archive

54. Decide whether participant names may be published after approval, for which Divisions, and who approves the setting.
55. Decide which provisional Division rankings may be shown live in addition to raw performance.
56. Define publication timing for schedules, brackets, corrections, voids, archived Events, and detailed reports.
57. Define freshness thresholds for live and non-live public snapshots.
58. Define whether archived pages retain approved participant names or anonymize them after retention.
59. Identify public resources requiring printed QR codes and the owner of link verification.
60. Define retention/disposition for participant data, private reports, generated artifacts, audit evidence, and official results.
61. Define report signatures, certification blocks, report numbers, paper size, branding, PDF/A, digital signatures, and timestamp requirements.
62. Define which staff may access limited reports and what assignment scope applies.
63. Define the archive correction policy after an Event is Closed or Archived.
64. Decide whether reports for contradictory 2025 rules must be withheld until corrected rule versions are approved.

## Decision Workflow

When a decision is approved:

1. Record the authority, date, Event edition, and effective rule version here.
2. Update the relevant ADR or focused specification.
3. Add or update migrations, validation, tests, and seed data as required.
4. Remove the affected blocker from [`implementation-status.md`](implementation-status.md).
