# MOD-005 | Cuti Tahunan: Technical Design & Implementation Plan

**Status:** DRAFT FOR REVIEW / PLANNING ONLY · 24 November 2026  
**Repository:** `harishaikal940322/kutmb-hr`, remote `main` reviewed  
**Observed latest commit:** `768277bb1aefc9477bc84e8ea8d4031d8cc53395` (SSO logout)

This is a proposed extension to the existing DOC-023 and implementation handoff, not a replacement SRS or approval. Reconcile it against the latest local working tree and authoritative DOCX sources before incorporating it into the repository. No code, schema, data, permissions or deployment changes are authorized by this draft.

## 1. Sources and gates

- `AGENTS.md`, `docs/implementation/CURRENT_HANDOFF.md`, `docs/decisions/SCOPE-001_system-core-vs-hr-core.md`.
- `docs/requirements/modules/CUTI_TAHUNAN_V1.docx` v0.5 and `docs/issue/KUTMB_HR_Cuti_Tahunan_56_Isu_Keputusan_dan_Pending.docx` (current HR answer fields, not the historical owner selections).
- `docs/requirements/RTM.md` v0.5; CR-HR-2026-001/002; `docs/design/DOC-023_MOD-005_Annual_Leave_UI_UX_Design_v0.4_DRAFT.md`; `resources/js/pages/AnnualLeavePreview.vue`.
- 56 CT-DEC records are accepted per requestor for UX; 56 planned tests remain NOT TESTED. Named formal HR signatory absent. Do not mark tests or requirements passed. The source register has current answers and residual details; do not use historical ADR choices to override current answers. This draft does not certify a complete independent SRS/DOCX review.
- G1: requestor accepts UX (all role screens, light/dark and responsive; resolve permanent menu).
- G2: formal HR sign-off and explicit project implementation authorization, including bounded MOD-004 dependency. Existing scope hold remains until recorded.
- G3: confirm policy values, scope and exact workflow/status semantics for each slice using current HR answers; unresolved cases stay blocked, not hardcoded. Record current source and AC/test mapping.
- G4: local preflight and environment verification. Use existing local database, synthetic data; no destructive migrations, new databases/containers or server deployment without approval. SSO production review is separate; existing authorized local identity path can be used for development.

## 2. Architecture and boundaries

Keep existing Laravel + Vue 3 + Inertia + MySQL conventions and Spatie RBAC. One application and existing session boundary; do not introduce separate frontend API stack, new framework or services. Inertia page props and Laravel form actions follow existing routes/controllers; only create JSON endpoints if an actual interaction requires one. Server calculates balances and route; Vue displays estimates and actionable validation. Existing account authentication, active-account checks, backend authorization, audit and CSRF remain authoritative. Keycloak subject mapping is an identity concern; never derive employee identity or HR role from username/email claims or automatically provision HR accounts.

MOD-004 minimum proposed dependency: employee identifier, explicit optional link to user, employment start/confirmation/end dates, employment category and applicable entitlement override, effective-dated unit assignment and authorized unit manager/GM/Secretary ALK assignments. Validate link uniqueness and temporal scopes; support authorized non-employee approver accounts without inventing employee entitlements. Do not expand to payroll, attendance or general HR master data. Missing authoritative employee/assignment data blocks the affected operation. No implicit bootstrap of HR roles by IT role.

## 3. Proposed logical data model (not migration-ready)

| Entity/group | Purpose and key constraints |
|---|---|
| employees, organization_units, employee_assignments | Separate from users; unique employee reference; optional unique user linkage subject to approval; effective dates and history of unit/category; explicit approver mapping. Confirm exact physical fields, nullability and lifecycle against MOD-004 authorization. |
| leave_policy_versions, leave_entitlement_rules | Effective-dated approved policy values, category/rate, thresholds, half-day boundaries, EL deadline and correction/reminder settings; preserve historical policy version; distinguish unset from zero or unlimited. |
| leave_calendar_days / calendar versions | Johor public holidays, HR replacement dates and approved workday/session settings; date uniqueness within version, collision validation and audit; historical calculation snapshot. |
| leave_accounts | Employee/year unique; separately track AL and Tabung Cuti via ledger/source allocation; Tabung never negative; balance year follows approved cross-year rule. |
| leave_requests, leave_request_revisions, leave_request_days | Applicant employee, submitting account, AL/EL, dates, status, revision, policy/calendar snapshot, calculated units and balance year. Request days expand full day to AM+PM slots; historical revisions immutable. |
| leave_active_slots | Unique employee/date/session for active reservations or committed leave; release only on authorized state transition; prevents concurrent overlap. |
| leave_workflow_steps, leave_actions | Ordered support/decision stages, assigned actor/scope snapshot, action, reason, time, revision; prior versions audit-only; no self-approval or automatic delegation. |
| leave_reservations, leave_ledger_entries, leave_allocations | Pending holds separate from posted debits/credits; allocation links each debit to original bucket/year; append-only compensations with unique operation key; integer half-day units for posted balances, explicit rounding at calculation boundary. |
| leave_attachments | Private metadata/storage only; permission checked on each read; file size/type/content validation, no public URLs. Retention rules need approval. |
| leave_year_operations / review records | Idempotent credit, carry-forward, cap, forfeiture, adjustment, prorata and calendar impact actions; dry-run/review as required; link to ledger and existing audit. |

Naming is illustrative. Prefer the smallest normalized physical schema that matches existing conventions after reading current code, not a table for every concept. Use InnoDB transactions, foreign keys, unique constraints and indexed employee/year, actor/status, and date/session queries. Lock account rows and request revision in a consistent order for concurrent submission/approval/cancellation. Never use floating point for posted day balances; use integer half-day units and exact arithmetic for prorata before approved rounding. Server-side transaction revalidates policy, ownership, assigned actor, slots, request state and revision. Notifications only after commit; deduplicate retries. No hard deletion of posted history.

## 4. Workflow design

| Applicant | Days | Proposed route from current baseline |
|---|---|---|
| Staf biasa | <= 5 | Pengurus Unit (support/comment) → Pengurus Besar (final) |
| Staf biasa | > 5 | Pengurus Unit → Pengurus Besar (support/recommendation) → Setiausaha ALK (final) |
| Pengurus Unit | any | Confirm precise CT-DEC-050 current HR answer; no self-support or self-approval. Route to GM / Secretary according to approved rule. |
| Pengurus Besar | any | Direct to Setiausaha ALK final, no intermediate self-support, CR-HR-2026-002. |

CT-DEC-008: negative unit support requires reason; forwards for consideration; does not reject automatically. For >5 days, preserve GM recommendation and Secretary final decision. Pengerusi ALK distinct but no GM approval under superseding decision; no access by assumption. No automatic approval, escalation or delegate. Unavailable assignee stays pending with approved reminders only. Verify exact existing SRS status labels before final transition table; do not add business statuses silently.

Proposed commands: draft/update, submit, support/not-support, final approve/reject, return-for-correction, resubmit, withdraw, request/confirm cancellation, HR record and staff confirmation, authorized balance adjustment. All actions use policy and target-level authorization, revision check, transaction and audit; action visibility in Vue is not security. Return-for-correction releases reservation per CT-DEC-024 subject to current HR answer verification; resubmit revalidates and restarts workflow; old decisions remain history. Cancellation restores original allocation only after authorized confirmation; calendar changes do not silently alter approved status. HR sees authorized oversight and adjustments but does not become extra final approver. No payroll or attendance writes.

## 5. Calculation contract

- AL and EL share the same entitlement. Separate request types, not balances. Use approved Johor calendar and working schedule; two nonoverlapping half-day sessions can total one day; reject overlap with active full-day/session. AL normal reason optional; short-notice exception requires emergency reason; EL reason required, attachment optional, retrospective window controlled by approved HR configuration. Early EL notification outside system per CT-DEC-028.
- Use approved leave-year basis, separate annual and Tabung balances, pending reservations deducted from available projection. Use AL then Tabung; authorized deficit resides in AL, not Tabung. Warn about negative projection. Current CT-DEC-002 answer explicitly allows insufficient balance; do not implement historical hard cutoff based on old ADR. Reconcile CT-DEC-014/020 current answers and approved negative-cap behavior before coding any rejection threshold. No invented default cap.
- Final approval converts hold into posted use exactly once; rejection/valid withdrawal releases it once. Preserve original allocation and year on reversal; reconcile post-year-close changes according to CT-DEC-039 rather than creating a second entitlement. No floating-point arithmetic or duplicate debits.
- Cap applies to Tabung only, not combined balance; cap follows annual entitlement per CT-DEC-004. Year-end transfer/cap/forfeiture, negative rollover and future-year reservation must each follow current HR answer; run idempotently with preview and audit. No automatic negative offset or payroll deduction unless specifically approved (payroll remains out of scope).
- Prorata termination formula: annual entitlement / 12 × eligible months; round to nearest 0.5 per SRS, exact tie/partial-month method requires HR confirmation. Do not infer service-band boundaries or promotion adjustment; handle appointment-letter exception only with confirmed HR rule. HR review of legal minimum and final settlement is mandatory; no financial transaction generated.

## 6. Proposed source organization and authorization

Follow actual namespaces/folder conventions in the local repository, not an invented restructure. Keep business calculations and transitions in focused Laravel services/actions, Form Requests for input validation, policies for actor/target permissions, models for persistence; use existing audit integration. Proposed feature areas: Employee/Organization foundation (bounded), Leave Calendar & Policy, Leave Balance, Leave Request, Leave Workflow, Leave Operations, Leave Reports. Vue pages under feature-specific page folders, shared form/date/status/table components reused where appropriate. Replace or retire preview entry only when owner accepts final navigation and functional page is ready. Never ship role-switcher as live authorization.

| Actor | Proposed minimum scope |
|---|---|
| Staf | Own profile, own balance and requests; approved same-unit calendar only; own permitted mutations. |
| Pengurus Unit | Own staff functions when applicable; assigned unit support queue and only approved scoped balance/history/detail; no staff final approval by this role. |
| Pengurus Besar | Own employee functions plus assigned review/final actions according to route; organization statistics as approved. |
| Setiausaha ALK | Assigned final queue and necessary supporting context only; no own leave entitlement in this capacity. |
| HR | Authorized employee/profile, policy, calendar, entitlement and balance maintenance, monitoring, adjustment and recording per SRS; no automatic approval grant. |
| Pentadbir IT | Identity and role assignment within existing authorized process, no leave data or approval permission merely because IT admin. |
| Pengerusi ALK | Separate role without GM approval; no leave data access unless independently approved. |

Implement granular abilities using existing Spatie catalog and Laravel policies. Test direct route access, IDOR, scope after transfers, deactivation/role changes, attachment access and cross-role combinations; permissions union must not bypass actor/target rules. No sensitive reasons/attachments in team calendars or broad logs.

## 7. Dependency-ordered development backlog (all HOLD until gates)

IDs below are proposed task IDs, not GitHub issue numbers. One coherent feature slice at a time. CT-DEC numbers below denote trace links, not newly approved policy. All 56 decisions assigned at least one primary work package; preserve existing AC-CT-DEC-xxx/T-CT-DEC-xxx IDs. Before coding a package, bind exact FR/BR/NFR/RR IDs from full SRS and source answers.

| Task | Scope / dependencies | Primary CT-DEC IDs | Acceptance focus |
|---|---|---|---|
| CT-P00 | Gates, authoritative source reconciliation and UX acceptance; no dependencies | cross-cutting | Sign-off, role screens, exact status matrix, unresolved values recorded; no hold removed prematurely. |
| CT-P01 | Minimum employee/org/actor foundation; P00 + MOD-004 authorization | 006,032,048 | Unique account link, effective unit scope, non-employee approver, unauthorized access denied. |
| CT-P02 | Calendar and policy configuration; P01 | 005,010,012,014,025,027,043,044,047,053,054 | Effective dates, missing values handled explicitly, collision checks, config audit, no hardcoded policy. |
| CT-P03 | Entitlement and ledger foundation; P01–02 | 002,004,018,020,034,035,036,037,040 | Separate buckets, approved year allocation, pending availability, negative behavior, exact arithmetic, concurrency and idempotency. |
| CT-P04 | Staff AL/EL submission and own history; P03 | 009,011,026,028,041,042,055 | Notice and reason rules, session overlap, calendar estimate, private optional attachment, atomic submission. |
| CT-P05 | Support/approval workflow; P04 | 001,007,008,013,019,050,056 | Correct 5-day threshold and GM exception, negative support, no self-approval/delegation, once-only final posting. |
| CT-P06 | Correction, withdrawal and cancellation; P05 | 016,017,021,022,023,024,045,046 | Original-source refund, new revision and route, release once, late/cancel semantics from HR answer. |
| CT-P07 | HR recording, reporting, notifications and calendar visibility; P05–06 | 029,049,051,052 | Authorized on-behalf action and confirmation, approved-only calendar, private detail access, no payroll/attendance mutation. |
| CT-P08 | Year closure, transfer and prorata/exit; P03, P06 | 003,015,030,031,033,038,039 | HR-confirmed partial months, historical year adjustments, Tabung cap, year-job retry, no automatic salary deduction. |
| CT-P09 | Integrated testing, security review and UAT; P01–08 | 001–056 | Trace every planned test to evidence, role and calendar edge cases, DB rollback/recovery, responsive dark/light and signed UAT. |

Cross-cutting CT-DEC-010,025,032,048,053,054 must also be retested with requests/workflow. CT-P02 proposes configuration schema only for uncertain values; activate rules when approved. CT-P07 report types follow RR-01–08, not new reporting requirements. Review dependency graph locally; if an earlier task needs a blocked policy, split the slice or stop that operation, not all unrelated planning.

## 8. Outstanding decision details (not a second 56-issue review)

Extract current HR answer fields and residual text before coding; at minimum verify the following from the sources:

- CT-DEC-005/003/033/036: precise service bands, probation/confirmation boundaries, job-level mapping, partial-month and tie rounding, promotion/appointment override and effective date. Source explicitly says do not assume missing boundaries.
- CT-DEC-012: actual AM/PM start/end, short workday and effective-dated handling. CT-DEC-014/020: approved negative threshold behavior and value if any; reconcile with CT-DEC-002 warning-not-block rule. CT-DEC-025/027/054: approved correction deadline, EL backdate counting basis/value, reminder cadence, closure semantics.
- CT-DEC-017/032/046/050/051: exact current authorization and routing decisions for cancellation, transfer, late approval, unit-manager own request and HR on-behalf recording; do not reopen an already answered item but extract its current answer before final contracts. Confirm authorized assignments.
- HR/IT: audit and attachment retention, access lifecycle, account/employee association, actual initial dataset, production auth and recovery policy. Formal HR approval for normative values and project authorization are separate from UX acceptance.

Do not manufacture missing rates, configured values, role grants or approval evidence. A policy-dependent operation with missing mandatory configuration must not post balances or advance workflow; use an explicit configuration/review error, except where the confirmed policy specifically permits an unset value (e.g. unlimited). Review exact behavior in G3.

## 9. Verification & implementation handoff

- Local Codex: read AGENTS.md, fetch origin, inspect local main and worktree, preserve unrelated changes. Do not run implementation until authorized. Reconcile this draft with complete DOCX SRS and issue answers; update existing planning/design/handoff only (avoid duplicate reports). Use existing change control for normative scope, synchronize RTM/CSV/XLSX only when approved requirements change. Do not mark existing planned tests passed without evidence.
- For each authorized slice: targeted feature/unit checks, build/type/lint as relevant, real existing local MySQL integration with synthetic records under approved test strategy; verify transactions, unique constraints, concurrency, idempotent retries and failure rollback. Do not run destructive operations or create infrastructure without approval. Consolidate full suite, requirements trace and browser review at phase exit.
- Production remains separately gated: release approvals, real browser SSO callback/logout and MFA integration, existing security advisory remediation/review, TLS, verified backup/restore, HR/IT retention and deployment acceptance. Do not equate local test success with production readiness.

**Current result:** planning draft available; `READY_FOR_FUNCTIONAL_IMPLEMENTATION=NO`. Next: owner UX acceptance, formal HR sign-off and MOD-004/005 authorization; reconcile authoritative requirements and physical design locally. No repository mutation or runtime testing performed by this review.
