# KUTMB HR coding instructions

## Owner directive — Implementation First, Formal Testing Later

- Resume documented MOD-005 and required MOD-004 development with synthetic data. Implement backend and Vue functionality slice by slice, use focused Chromium review where practical, and continue independently when a policy-dependent operation is gated.
- Defer automated tests and formal QA until Owner approval after functional review. Syntax checks, compilation and asset builds are permitted. Do not initiate CI troubleshooting or manually dispatch workflows during this phase. Formal testing remains required before release. This section supersedes conflicting testing and sequencing instructions below.
- Recovery is deferred. Preserve the incident evidence. Use the existing development application and authorized setup flows. Destructive database operations remain prohibited except the Owner's explicitly approved, manually confirmed local synthetic reset below. Verify the existing target and inspect each additive migration before applying it.
- Owner direction (26 November 2026): allow an intentionally requested reset of the existing synthetic `kutmb_hr_dev` database only via the interactive `dev:reset-database` command. The human Owner types the exact confirmation in their own terminal after preserving required backups/evidence; agents, tests and CI must not execute the reset or enter that confirmation on the Owner's behalf. Ordinary destructive commands remain prohibited on development, and production protection is unchanged. This is a narrow exception to all older blanket destructive-operation prohibitions, not authorization to create new infrastructure or change other databases.

## Scope and authority

- Treat the repository's SRS, RTM, decisions and current handoff as the source of truth. Requirements are DRAFT unless explicitly approved; a commit or merge is not HR/IT sign-off.
- Latest requestor direction (24 November 2026): autonomous development of the documented MOD-004 minimum foundation and MOD-005 Cuti Tahunan scope in the existing local development environment, using synthetic data only. This limited development authorization supersedes the earlier blanket HR Core coding hold; formal HR requirements/authority, actual business values, live operation and production release remain unapproved. MOD-006..011 and broader MOD-004 scope remain on hold. Never infer missing HR business rules or policy rates. See SCOPE-001.
- Before work that touches requirements, authentication, authorization or data, consult `docs/requirements/OPEN_QUESTIONS.md`, `docs/decisions/ADR_REGISTER.md`, `docs/requirements/RTM.md`, the applicable SRS, [SCOPE-001](docs/decisions/SCOPE-001_system-core-vs-hr-core.md), and the relevant design/security records. Resolve only decisions needed for the current change; do not reopen settled technical choices without concrete evidence.
- Keep account identity separate from employee/person concepts as a proposed boundary. Do not add employee tables, joins or data to System Core.
- Keep requirement and test IDs stable. Update SRS/RTM and the traceability CSV/XLSX master together only when approved requirements actually change; use the existing change-control process for normative scope changes.
- Laravel, Vue and MySQL are the selected stack families. Follow the existing application architecture and current code conventions. Do not introduce a new folder layout, API topology, hosting model, package or infrastructure without a specific need and accepted decision.

## Security boundaries

- Backend authorization is authoritative; Vue permission hints are presentation only. Preserve active-account checks, per-target authorization, last-admin safety, fresh authorization after privilege changes, validation and relevant audit behavior.
- Username is the current login principal. Public self-registration, delegated User Admin, production bootstrap, account recovery and privileged mutation flows remain disabled or gated unless their requirements and security decisions are accepted.
- MFA is planned, not implemented. Local development uses an 8-character password minimum for single-factor bootstrap and sign-in.
- Never commit secrets, credentials, real personal HR data, database exports or confidential infrastructure details. Use synthetic data only.
- Never run destructive database operations without explicit authorization. Use the existing development environment/database; do not create pretend staging, UAT or production databases in the development server.

## Simplified development method

- Work only in `/home/haris/www/kutmb-hr`. Fetch `origin`, inspect `main` and the working tree before changes. Preserve unrelated local changes; never overwrite, reset or discard them.
- Use `main` for ordinary development. Do not create clones, worktrees or feature branches. Do not modify or disable existing CI or add PR gates without owner approval.
- Plan only the current implementation slice. Reuse the existing SRS, RTM, architecture and implementation records. Do not create duplicate plans, reports, checklists or work packages.
- Implement one coherent, requirements-backed authorized feature at a time, starting with CT-P01, then CT-P02–09 in dependency order. Reuse Laravel/Vue/Inertia patterns and avoid speculative requirements and unnecessary abstractions. Implement only documented behavior whose source is unambiguous; unresolved policy-dependent operations remain disabled/fail closed. HR Core functionality is development-only (local/testing, server-side fail-closed in production until separately authorized); no real HR data or actual notifications.
- Run focused checks relevant to changed code. Do not repeatedly run the full suite for minor edits. Keep security, authorization, validation and integrity checks; run consolidated requirement, error-handling, UI/UX and dependency checks at the relevant phase exit. Do not claim a check passed unless it ran.
- Update `docs/implementation/CURRENT_HANDOFF.md` and existing chronological records with delivered scope, files, checks/results, decisions/blockers, limitations and the next ready scope. Keep records concise and accurate.
- Commit completed, verified work on `main`, push to `origin/main`, then verify the remote SHA and local status. Never bypass failing CI, enforced protection or required reviews. Report a genuine blocker with evidence.
- Do not deploy, create production credentials, merge external PRs or claim release/business approval unless explicitly authorized.

## Autonomous execution

- Requestor delegates ordinary technical decisions, debugging, design alignment, migration design, test strategy and sequencing within authorized scope. Do not ask for repeated approvals or stop after a successful slice. Read current handoff, verify preflight, implement, run focused checks, resolve failures, document and commit/push verified work before selecting the next ready slice.
- Investigate and resolve technical blockers independently, using safe alternatives within constraints. If a blocker cannot be safely resolved, record exact evidence, affected scope, attempted remedies and next action in CURRENT_HANDOFF.md. Continue independent ready work; do not skip prerequisites, fabricate checks or bypass security. Escalate only when no independent authorized work remains, or a decision/credential/manual action truly requires requestor/HR/IT. Group such decisions into one concise interaction.
- Additive migrations for the authorized MOD-004/005 development schema are allowed only on the verified existing development database after checking target and migration plan; no destructive operations, extra databases/containers, real records, privilege grants to existing accounts, or infrastructure changes. Use rollback-isolated synthetic tests; do not run RefreshDatabase or destructive suites on the shared development database. If testing cannot safely proceed, record limitations honestly and do not claim full acceptance. Preserve prior evidence; retest affected behavior only.

## Requirements correction and vertical delivery (25 November 2026)

- For the 56-issue register, use the latest written `Keputusan HR / catatan` answer field, not the historical A/B/C option, historical owner choice, or earlier pending label. The source is the current tracked DOCX (blob f396cdd53480caff34cb0fe0225c44729ad31ab0). Follow superseding explicit CRs. Do not reverse an existing implementation without tracing the exact answer field and recording why.
- Deliver one end-to-end local/testing-only feature at a time: first ordinary same-year AL, with authenticated linked staff, authorized current approvers, explicit synthetic entitlement, working calendar, submit/decision UI, transaction, and focused acceptance. Use existing services and frozen UI, not another layer of internal foundations. Next EL and other independently resolved cases. Keep unresolved cross-year, year-end, promotion/termination and production behavior separately gated. Do not treat incomplete integrations as unanswered HR policy.
- Requestor explicitly authorized the remote corrective Git commits after the previous run. This is a one-time exception to the local checkout rule for the corrective patch, not evidence of local preflight/test completion. Local verification remains required before operational use or further implementation; do not create clones, extra databases, containers or destructive migrations.

## Current handoff

Read [CURRENT_HANDOFF.md](docs/implementation/CURRENT_HANDOFF.md) before implementation. It is the current operational status. [DOC-020](docs/implementation/DOC-020_MOD-001_Implementation_Blueprint_v0.1_DRAFT.md) is historical planning context; its older package, branch and gate instructions are superseded by the simplified method recorded in its current workflow section and this file.
