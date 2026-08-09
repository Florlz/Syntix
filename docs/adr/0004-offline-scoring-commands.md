# ADR 0004: Offline Scoring Commands

## Status

Accepted

## Date

2026-08-09

## Context

Offline score entry is required, but reliable synchronization depends on server-authoritative online mutations, duplicate protection, and explicit concurrency behavior. Browser storage cannot safely become an alternate source of truth.

## Decision

Implement online scoring first. From the first scoring mutation, each command envelope carries a client-generated `command_uuid`, a `base_revision`, and an optional `depends_on_command_uuid`. The server validates authentication, active Scoring Assignment, frozen rules, payload, dependency, and revision before applying the command transactionally.

The server persists a Command Receipt for every terminal disposition. It stores an actor scope and a hash of the canonical command envelope. Actor scope is the authenticated actor identifier and Event. The canonical envelope hash covers the schema version, command type, Event and target identifiers, payload, `base_revision`, and `depends_on_command_uuid`; retry and transport metadata are excluded.

Repeating a `command_uuid` returns the original Command Receipt only when both actor scope and canonical envelope hash match the stored values. It never applies the mutation again. If either value differs, the server returns the non-disclosing `idempotency_key_reused` error and does not reveal the original command, disposition, or response.

Sequential commands cannot share one stale base revision. The first command in a chain uses the last acknowledged server revision. A successor names its predecessor in `depends_on_command_uuid` and is sent only after the predecessor's applied receipt supplies its resulting revision; that resulting revision becomes the successor's `base_revision`. The server accepts the dependency only when the predecessor has an applied receipt in the same actor scope and its resulting revision equals the successor's `base_revision`. A rejected, conflicted, or unresolved predecessor blocks its successors for explicit review.

Closing and submitting online use an explicit handshake: the client sends a close or submit command against the expected revision; the server validates completeness, atomically changes state, and returns a receipt with authoritative state and revision. The client treats the action as complete only after that receipt. After a timeout, it retries the same `command_uuid` rather than inventing a new submission.

The later offline phase adds an IndexedDB outbox of pending command intents, not authoritative scores or Standings. It may queue a successor locally, but it must not finalize or send that successor envelope until the predecessor receipt provides the revision as described above. Pending commands must synchronize before related submission can complete. A Revision Conflict is never automatically merged; the scorer reviews current server state and explicitly replaces, reapplies, or abandons the local intent as authorized.

## Consequences

- Online mutation endpoints must expose command and revision semantics before offline UI work begins.
- Assignment revocation, rule changes, dependencies, and stale state are revalidated for every not-yet-received command. An exact duplicate only returns its stored receipt under the actor-scope and envelope-hash rule.
- The UI must distinguish pending, syncing, applied, rejected, conflicted, and unknown-after-timeout states.
- Background Sync may improve retries but cannot be required for correctness.

## Rejected Alternatives

- Build offline storage before online command semantics: rejected because it would encode unstable mutation behavior.
- Store authoritative Standings in IndexedDB: rejected because Laravel and PostgreSQL are authoritative.
- Use last-write-wins or automatic field merges: rejected because scoring conflicts require human review.
- Mark close or submission complete when merely queued or sent: rejected because delivery and acceptance are not guaranteed.
- Treat each retry as a new command: rejected because it can duplicate mutations and official effects.
- Send an offline command chain against one shared base revision: rejected because each applied mutation advances the authoritative revision.

## References

- [SYNTIX product and domain contract](../cspc-siklab-plan.md)
- [SYNTIX Domain Glossary](../domain-glossary.md)
