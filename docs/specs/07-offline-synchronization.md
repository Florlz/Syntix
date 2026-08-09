# Offline Synchronization

**Design status:** Baseline
**Implementation status:** See [implementation status](../implementation-status.md).
**Product:** SYNTIX

## Sources and Authority

This specification refines the required offline command model under the [product and domain contract](../cspc-siklab-plan.md). The online command contract, Laravel policies, PostgreSQL revisions, and server receipts are authoritative.

- Confirmed product decisions: offline score entry is required only after server-authoritative online scoring, idempotency, and revision checks are stable; IndexedDB stores an outbox, not authoritative results or standings; conflicts are not automatically merged.
- Proposal boundary: `docs/Approved-2025-Intramurals-Proposal.pdf` supplies the competition rules whose frozen versions must be revalidated during synchronization. It does not define offline conflict or replay behavior.
- Supporting intent: `docs/MANUSCRIPT FINAL 123 PDF COPY.pdf` and `docs/SYNTIX.docx` call for local persistence and synchronization during intermittent connectivity. Their implication of seamless automatic synchronization is constrained by browser support and server revalidation requirements.
- Architecture authority: Laravel and PostgreSQL decide authorization, validation, ordering, revisions, receipts, official state, and duplicate handling. Client timestamps never determine winners in a conflict.
- This specification does not alter competition rules or initial pageant exclusion.

## Problem

Scoring venues may lose connectivity while Judges and Tabulators are working. Browser storage can prevent immediate data loss, but naive replay can duplicate scores, overwrite newer server state, apply commands after assignment revocation, expose private data, or falsely imply that unsynchronized work is official.

## Goals

- Establish one validated command shape for online mutations before adding offline transport.
- Persist eligible disconnected mutations in an IndexedDB outbox with visible state.
- Synchronize commands idempotently through explicit sequential dependencies, carrying each predecessor's resulting revision into its successor before delivery.
- Revalidate authentication, assignment, rules, lifecycle, and expected revision on every replay.
- Make rejection and conflict explicit, reviewable, and recoverable without automatic merge.
- Require an online close handshake before a result can be completed or submitted.
- Limit private cached data and provide realistic recovery for account changes and device loss.

## Non-Goals

- Making the browser authoritative for results, placements, standings, approvals, or ledgers.
- Offline initial login, Admin approval, rule changes, result correction, or report generation.
- Guaranteed unattended Background Sync.
- Cross-device replication of unsynchronized commands.
- Automatic field-level or last-write-wins conflict merging.
- Broad caching of authenticated Inertia pages or mutation responses.

## Users and Authorization

- Judges and Tabulators may queue only command types enabled for their scoring workflow and last known assignment scope.
- Local eligibility is advisory. The server must revalidate the current authenticated account, active event role, active scoring assignment, rule version, lifecycle, payload, and expected revision when receiving every command.
- Admins may inspect server receipts and audit evidence needed for incident resolution but cannot force an unauthorized stale command to apply without using the normal audited correction workflow.
- Account expiry, logout, role removal, assignment revocation, or event closure may cause queued commands to be rejected even if they were valid when created.
- Public users have no mutation outbox.

## Workflow

1. Each scoring mutation first uses the same online command envelope and server endpoint planned for later offline replay.
2. While online, the client sends the command and stores the authoritative server receipt. Network ambiguity is resolved by retrying the same command UUID.
3. When offline or when delivery cannot be confirmed, the client writes the command to IndexedDB as `pending` and immediately shows that it is not synchronized.
4. On a credible connection signal, foreground app resume, explicit user action, or supported Background Sync event, the client sends the head command of each target dependency chain. Independent target chains may synchronize separately.
5. The client marks a command `syncing` only while a request is in flight. The server validates authentication and actor scope, then checks duplicate UUID and canonical envelope hash before assignment, payload, frozen rules, lifecycle, and base revision validation.
6. The server returns a durable receipt. The client marks the command `applied`, `rejected`, or `conflicted` and displays the authoritative server revision and recovery action.
7. When a command is applied, the client writes its receipt's `resulting_revision` into the direct successor's `base_revision` before finalizing that successor's canonical envelope hash and sending it. A rejection or conflict pauses every transitive successor until the scorer reviews current server state. No automatic merge occurs.
8. Before completing or submitting a result, the client performs an online close handshake. The server confirms authentication, assignment, current revision, no unresolved related outbox commands, and valid completeness before changing lifecycle state.
9. Applied commands may be pruned after their receipts and required audit evidence are safely retained. Rejected and conflicted commands remain visible until acknowledged or resolved under retention policy.

## Domain/Data Requirements

The online and offline command envelope must contain:

| Field | Requirement |
|---|---|
| `command_uuid` | Client-generated UUID, globally unique and immutable. |
| `command_type` | Versioned allow-listed mutation name. |
| `event_id` | Target event edition. |
| `competition_id` | Target Competition family. |
| `division_id` | Target score-bearing Division when applicable. |
| `target_id` | Contest, scorecard, entry, or other command target. |
| `assignment_context` | Last known assignment identifier for diagnostics; never trusted as authorization. |
| `base_revision` | Expected authoritative target revision. |
| `depends_on_command_uuid` | UUID of the immediate predecessor in the same sequential local target chain, or `null` for the chain head. |
| `payload_schema_version` | Version of the command-specific payload contract. |
| `payload` | Command-specific, versioned, minimally necessary data. |
| `client_created_at` | Informational client timestamp, not conflict authority. |
| `queued_at` | Local outbox insertion timestamp. |
| `last_attempted_at` | Local timestamp of the latest delivery attempt. |
| `status` | `pending`, `syncing`, `applied`, `rejected`, or `conflicted`. |

- The local record must also retain attempt count, last transport error, receipt reference, account scope, canonical envelope hash once finalized for delivery, and target-chain ordering information.
- A server receipt must identify command UUID, outcome, processed timestamp, authenticated actor, target, base and resulting revisions, authoritative response or snapshot reference, machine-readable reason code, and safe user message.
- PostgreSQL must enforce global uniqueness of command UUID. Duplicate delivery returns the original durable receipt only when the authenticated actor scope and canonical envelope hash both equal those persisted with the original command, regardless of whether its outcome was applied, rejected, or conflicted.
- Actor scope is the authenticated user identity plus the command's account and event partition. A duplicate UUID from another actor scope or with any canonical envelope difference returns `idempotency_key_reused` and discloses no original actor, target, payload, outcome, revision, timestamp, or receipt reference.
- The canonical envelope hash uses a deterministic, versioned serialization of every immutable server-relevant envelope field, including command UUID, type, event, Competition, Division where applicable, target, assignment context, finalized base revision, dependency UUID, payload schema version, payload, and client creation time. It excludes local transport fields such as queue time, queue status, attempt count, and attempted timestamps.
- Command payloads must use stable identifiers and domain actions, not whole cached records or client-calculated standings.
- Commands targeting the same aggregate must form an acyclic, single-predecessor chain and be delivered serially in local creation order. A successor is ineligible to send until its predecessor has an applied receipt and the successor has received that receipt's resulting revision. Independent target streams may synchronize separately.
- A close-handshake request is online-only and references the target revision plus the set or watermark of related command UUIDs known by the client.
- IndexedDB stores only the minimum command and recovery data needed. It must not store authoritative standings, broad rosters, student numbers, contact data, eligibility notes, peer Judge scores, session secrets, or private report files.
- Outbox data must be partitioned by authenticated account and event. Switching accounts must never expose or replay another account's commands.

## Invariants

- The same command shape and server action serve online delivery and offline replay.
- Every mutation has a command UUID and expected base revision.
- A command UUID is applied at most once; only an exact same-actor-scope, same-envelope retry receives the original outcome.
- A dependent command is never sent with a guessed revision; its predecessor's applied receipt supplies its authoritative base revision.
- The server reauthorizes every command at processing time.
- Client timestamps and local status do not override server revision or authorization.
- Revision conflicts are never automatically merged or overwritten.
- A result cannot complete, submit, or become official while related commands are pending, syncing, rejected without resolution, or conflicted.
- Completion and submission require an online close handshake.
- Public viewers see only the last successfully synchronized server state.
- Authenticated mutation responses are not placed in the service-worker runtime cache.
- Logout or account switch cannot silently discard, expose, or replay another user's pending commands.

## Edge and Failure Cases

- Response lost after server apply: retry the same UUID; server returns the original applied receipt.
- Device reports online but request fails: return `syncing` to `pending`, preserve the command, and show manual retry.
- Browser closes during sync: on next launch, recover stale `syncing` records to `pending` unless a receipt confirms the outcome.
- Base revision is stale: mark `conflicted`, fetch current server state, and require explicit scorer review.
- Assignment revoked: server returns `rejected` with a revocation reason; do not reassign or replay under another user.
- Session or account expired: pause synchronization, require login, then revalidate every command under the same account. A different account cannot adopt the queue.
- Rule version or result lifecycle changed: reject or conflict as appropriate; never reinterpret the old payload under new rules.
- Commands arrive out of order: reject a stale dependent command or hold it behind its unresolved predecessor.
- Dependency is missing, belongs to another actor or target chain, is cyclic, or did not apply: do not send the successor; mark the chain blocked with a safe recovery action.
- Duplicate UUID has a different actor scope or canonical envelope hash: return `idempotency_key_reused` without confirming or exposing the original command.
- Quota exceeded or IndexedDB unavailable: warn before accepting more offline work and direct the scorer to restore connectivity or use an authorized contingency; do not claim the command was saved.
- User clears site data, browser evicts storage, or device is lost: unsynchronized commands may be unrecoverable. The server can recover only commands for which it issued a receipt.
- Device stolen: account revocation blocks future replay, but locally stored data remains subject to device security and institutional incident response.
- Logout with pending commands: block silent cleanup, show command count and consequences, and require an explicit authorized choice under the approved retention policy.
- Background Sync unsupported: foreground reconnect checks and manual retry remain fully functional.

## Functional Requirements

| ID | Requirement |
|---|---|
| OFF-FR-001 | All eligible online scoring mutations use a versioned command envelope with UUID and base revision. |
| OFF-FR-002 | The server persists actor scope and canonical envelope hash with a durable outcome receipt and enforces command UUID uniqueness. |
| OFF-FR-003 | Duplicate delivery returns the original receipt without reapplication only for the same actor scope and canonical envelope hash; otherwise it returns `idempotency_key_reused` without original-command disclosure. |
| OFF-FR-004 | The client stores undelivered eligible commands in an account-scoped IndexedDB outbox. |
| OFF-FR-005 | The outbox exposes `pending`, `syncing`, `applied`, `rejected`, and `conflicted` states. |
| OFF-FR-006 | The server revalidates authentication, event role, assignment, rule version, lifecycle, payload, and revision for every replay. |
| OFF-FR-007 | The client synchronizes each `depends_on_command_uuid` chain sequentially and assigns each applied receipt's resulting revision to the direct successor before sending it. |
| OFF-FR-008 | Revision conflicts stop dependent replay and open a comparison UI without automatic merge. |
| OFF-FR-009 | Rejected commands show a safe reason and preserve data needed for authorized recovery. |
| OFF-FR-010 | The client retries opportunistically and always provides a manual retry action. |
| OFF-FR-011 | Core synchronization does not depend on Background Sync availability. |
| OFF-FR-012 | Completing or submitting a result requires an online close handshake and no unresolved related commands. |
| OFF-FR-013 | Public state changes only after a command is successfully applied by the server. |
| OFF-FR-014 | Service-worker caching excludes authenticated Inertia responses, mutations, private roster data, and outbox contents. |
| OFF-FR-015 | Account expiry, revocation, logout, account switch, quota failure, and device loss have explicit user and operational states. |
| OFF-FR-016 | The UI shows connection state, queue count, last synchronization time, and whether displayed scoring data is authoritative or local-only. |

## Acceptance Criteria

- An online command and its later offline replay use the same server validation path and response shape.
- Replaying one UUID after an ambiguous timeout changes server state exactly once and returns the original receipt.
- Reusing that UUID under another actor scope or with a changed envelope returns `idempotency_key_reused`, changes no state, and reveals no original command or receipt data.
- A stale base revision produces a visible conflict and does not overwrite the current server value.
- Revoking a scorer's assignment before replay causes rejection even when the command was queued while assigned.
- A scorer can inspect pending, syncing, applied, rejected, and conflicted commands and manually retry eligible pending commands.
- Later commands for one target do not pass an unresolved conflicting predecessor.
- Three changes created offline for one target form `A -> B -> C`: `A` sends with the last known server revision, its applied resulting revision becomes `B.base_revision`, and `B`'s applied resulting revision becomes `C.base_revision`; all three apply in order without a false revision conflict and the final server revision has advanced three times.
- A result cannot be completed or submitted while related outbox work remains unresolved or while offline.
- Account switching does not reveal or replay the previous account's queue.
- Public endpoints continue to show the last synchronized state while a scorer has local pending work.
- The workflow functions in a browser without Background Sync support.
- Reloading or closing during an in-flight request does not duplicate an applied command.
- Private authenticated pages and mutation responses are absent from service-worker runtime caches.

## Testing

- Domain and contract tests for command schema versions, canonical envelope hashing, actor-scoped UUID reuse, receipt replay, dependency acyclicity, resulting-revision propagation, ordering, close handshake, and state transitions.
- Feature tests for current authentication, role and assignment revalidation, revocation, event closure, rule changes, duplicate delivery, conflict, rejection, and submission blocking.
- PostgreSQL concurrency tests for exact-retry and mismatched-scope/hash duplicate UUID races, competing base revisions, receipt durability, and transactional command application.
- Browser tests for offline queue creation, reload persistence, reconnect, manual retry, missing Background Sync, account switch, expired session, quota failure, and conflict comparison.
- PWA tests proving authenticated Inertia responses, mutations, private data, and outbox data are not runtime cached by the service worker.
- Operational tests for lost device, cleared site data, stolen device and account revocation, and scorer handoff with unsynchronized work.
- User acceptance simulation at a venue with deliberate network interruption and ambiguous response loss.

## Decision Register

Offline-operation blockers are centralized in [`../open-decisions.md`](../open-decisions.md), items 48-53. This specification remains the technical contract for command envelopes, receipts, dependency ordering, revision conflicts, and close handshakes.
