# MOD-005 | Cuti Tahunan: Technical Design & Implementation Plan

**Status:** v0.2 TECHNICAL DESIGN PROPOSAL / UX BASELINE FROZEN / PLANNING ONLY · 24 November 2026  
**Repository:** `harishaikal940322/kutmb-hr`, remote `main` reviewed  
**Frozen UX source commit:** `ffdeda34062ae0dd5c9edbd08ec5e7ca44cab6a5` (UX-R01–R05)

This is a proposed extension to the existing DOC-023 and implementation handoff, not a replacement SRS or approval. It is recorded in the repository for technical review; reconcile against the local working tree and complete authoritative DOCX sources before any implementation. No code, schema, data, permissions or deployment changes are authorized by this draft.

## 1. Sources and gates

- `AGENTS.md`, `docs/implementation/CURRENT_HANDOFF.md`, `docs/decisions/SCOPE-001_system-core-vs-hr-core.md`.
- `docs/requirements/modules/CUTI_TAHUNAN_V1.docx` v0.5 and `docs/issue/KUTMB_HR_Cuti_Tahunan_56_Isu_Keputusan_dan_Pending.docx` (current HR answer fields, not the historical owner selections).
- `docs/requirements/RTM.md` v0.5; CR-HR-2026-001/002; `docs/design/DOC-023_MOD-005_Annual_Leave_UI_UX_Design_v0.4_DRAFT.md`; `resources/js/pages/AnnualLeavePreview.vue`.
- 56 CT-DEC records are accepted per requestor for UX; 56 planned tests remain NOT TESTED. Named formal HR signatory absent. Do not mark tests or requirements passed. The source register has current answers and residual details; do not use historical ADR choices to override current answers. This draft does not certify a complete independent SRS/DOCX review.
- G1: REQUESTOR UX ACCEPTED / DESIGN FROZEN on 24 November 2026 (DOC-023 v0.5; UX-R01–R05). Independent browser/device/theme and build checks remain unverified. Permanent menu integration is a technical design task, not a new UX acceptance requirement.
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
| leave_requests, leave_request_revisions, leave_request_days | Applicant employee, submitting account, AL/EL, dates, status, revision, policy/calendar snapshot, calculated units and balance year. Request days expand full day to AM+PM slots; historical revisions immutable. Per-date calendar year and source allocation support CT-DEC-034; request header must not force a single year. |
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
| Pengurus Unit | <= 5 | Direct to Pengurus Besar (final), no self-support (FR-20/CT-DEC-050). |
| Pengurus Unit | > 5 | Direct to Pengurus Besar (support/recommendation), then Setiausaha ALK (final); no self-support (FR-20/CT-DEC-050). |
| Pengurus Besar | any | Direct to Setiausaha ALK final, no intermediate self-support, CR-HR-2026-002. |

CT-DEC-008: negative unit support requires reason; forwards for consideration; does not reject automatically. For >5 days, preserve GM recommendation and Secretary final decision. Pengerusi ALK distinct but no GM approval under superseding decision; no access by assumption. No automatic approval, escalation or delegate. Unavailable assignee stays pending with approved reminders only. Verify exact existing SRS status labels before final transition table; do not add business statuses silently.

Proposed commands: draft/update, submit, support/not-support, final approve/reject, return-for-correction, resubmit, withdraw, request/confirm cancellation, HR record and staff confirmation, authorized balance adjustment. All actions use policy and target-level authorization, revision check, transaction and audit; action visibility in Vue is not security. Return-for-correction releases reservation per CT-DEC-024 subject to current HR answer verification; resubmit revalidates and restarts workflow; old decisions remain history. Cancellation restores original allocation only after authorized confirmation; calendar changes do not silently alter approved status. HR sees authorized oversight and adjustments but does not become extra final approver. No payroll or attendance writes.

## 5. Calculation contract

- AL and EL share the same entitlement. Separate request types, not balances. Use approved Johor calendar and working schedule; two nonoverlapping half-day sessions can total one day; reject overlap with active full-day/session. AL normal reason optional; short-notice exception requires emergency reason; EL reason required, attachment optional, retrospective window controlled by approved HR configuration. Early EL notification outside system per CT-DEC-028.
- Use approved leave-year basis, separate annual and Tabung balances, pending reservations deducted from available projection. Use AL then Tabung; authorized deficit resides in AL, not Tabung. Warn about negative projection. Current CT-DEC-002 and CT-DEC-014 HR answers explicitly allow insufficient/negative balance and set no maximum at present; warn, do not reject solely for insufficient balance. Reconcile CT-DEC-020 exact current response before coding pending logic. A future cap needs a controlled policy change, not a hardcoded default.
- Final approval converts hold into posted use exactly once; rejection/valid withdrawal releases it once. Preserve original allocation and year on reversal; reconcile post-year-close changes according to CT-DEC-039 rather than creating a second entitlement. No floating-point arithmetic or duplicate debits.
- Cap applies to Tabung only, not combined balance; cap follows annual entitlement per CT-DEC-004. Year-end transfer/cap/forfeiture, negative rollover and future-year reservation must each follow current HR answer; run idempotently with preview and audit. No automatic negative offset or payroll deduction unless specifically approved (payroll remains out of scope).
- Prorata termination formula: annual entitlement / 12 × eligible months; round to nearest 0.5 per SRS, exact tie/partial-month method requires HR confirmation. Do not infer service-band boundaries or promotion adjustment; handle appointment-letter exception only with confirmed HR rule. HR review of legal minimum and final settlement is mandatory; no financial transaction generated.

## UI refinement alignment (24 November 2026)

Requestor accepted the AL/EL dropdown, removal of the manual AL emergency checkbox, dynamic reason/attachment display, and four pre-form balance metrics (AL before pending, Tabung, pending, combined available). Show projected AL and Tabung separately; no double subtraction of pending. The preview has no short-notice switch; FR-18/BR-20/CT-DEC-009 remain normative until changed through controlled approval. Functional design must derive notice from dates and preserve the approved exception/reason behavior or obtain a policy change before implementation. Keep `>5` exactly (5 days remains GM final, 5.5 days requires Secretary ALK); GM own leave goes directly to Secretary. No implementation approval.

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

## 6A. First implementation slice: CT-P01 physical design proposal

Repository inspection at the frozen commit found existing `app/Models` (User, Role, Permission, AuditEvent), `app/Http/Controllers`, `app/Http/Requests`, `app/Services/Identity`, `app/Queries`, `resources/js/pages/identity`, `resources/js/pages/users`, shared Vue UI components and `routes/web.php` protected middleware. There is no Employee/Organization migration or model. Do not add HR fields to users. Proposed additive MySQL design below is for review, not a migration specification approved for execution.

| Proposed table | Minimum columns (illustrative MySQL types) | Keys / rules |
|---|---|---|
| employees | id BIGINT UNSIGNED; employee_no VARCHAR(64); display_name VARCHAR(255); user_id BIGINT UNSIGNED NULL; service_start DATE; confirmation_date DATE NULL; service_end DATE NULL; timestamps | PK id; unique employee_no and optional user_id FK users.id; no auto-link by email/username. No assumed mandatory account, no personal identifiers beyond approved scope. |
| organization_units | id BIGINT UNSIGNED; code VARCHAR(64); name VARCHAR(255); active BOOLEAN; timestamps | PK id; unique code; inactive rather than deleting referenced unit. Confirm unit taxonomy Q-006. |
| employment_categories | id BIGINT UNSIGNED; code VARCHAR(64); name VARCHAR(255); active BOOLEAN; timestamps | PK id; unique code; no invented category/rate seed; review job level and probation policy. |
| employee_assignments | id BIGINT UNSIGNED; employee_id, unit_id, category_id BIGINT UNSIGNED; effective_from DATE; effective_to DATE NULL; timestamps | FKs to employee/unit/category; index (employee_id,effective_from), (unit_id,effective_from); non-overlap under transaction lock; preserve assignment history. |
| approval_assignments | id BIGINT UNSIGNED; user_id BIGINT UNSIGNED; office_code VARCHAR(32); unit_id BIGINT UNSIGNED NULL; effective_from DATE; effective_to DATE NULL; timestamps | FK users/unit; authorized named office (PU, PB, Setiausaha ALK); PU requires unit scope; no automatic delegation, enforce non-overlap and authorized account; Secretary need not be employee. |

Relationship outline: User 0..1 ↔ 0..1 Employee; Employee 1 → many EmployeeAssignment; Unit/Category 1 → many EmployeeAssignment; User 1 → many ApprovalAssignment; Unit 0..1 → many ApprovalAssignment. Cardinality of employee-user link and office holder assignment must be confirmed under Q-005/Q-004 before migration. Employment category and assignment dates are inputs to later entitlement, not entitlement approval. Any appointment-letter override belongs in approved leave policy/entitlement scope CT-P02/03, not speculative employee fields. Restrict deletes for referenced historical records; use existing audit service and transaction validation. No real staff data imported.

Proposed source layout for P01: `app/Models/Employee.php`, `OrganizationUnit.php`, `EmploymentCategory.php`, `EmployeeAssignment.php`, `ApprovalAssignment.php`; `app/Http/Controllers/EmployeeController.php` and focused org controller; `app/Http/Requests` validation; `app/Services/Employee` domain operations; `app/Queries` scoped lists; `resources/js/pages/employees/` and `resources/js/pages/organization/` using existing shared components. Names are candidates; local agent must check conventions first. Use existing protected middleware and explicit per-target permissions; do not expose employee data through IT IAM routes. No role-switcher in live pages.

P01 proposed completion: authorized HR can manage approved minimum records and effective assignments, user linkage is explicit and unique, unauthorized role access denied, transfer history retained, non-employee approver supported, no leave ledger or payroll writes. Before coding: HR/IT confirm Q-004/005/006 and minimum employee scope/fields, permission ownership and authorized development; then review physical ERD and migration order. Menu proposal: HR workspace > Profil Staf / Struktur Organisasi; Cuti Tahunan > Mohon Cuti / Permohonan Saya / Jadual Unit / approvals / HR operations according to actual authorization. Reuse existing menu access registry; no permanent routes or permissions created at this stage.

## 7. Dependency-ordered development backlog (all HOLD until gates)

IDs below are proposed task IDs, not GitHub issue numbers. One coherent feature slice at a time. CT-DEC numbers below denote trace links, not newly approved policy. All 56 decisions assigned at least one primary work package; preserve existing AC-CT-DEC-xxx/T-CT-DEC-xxx IDs. Before coding a package, bind exact FR/BR/NFR/RR IDs from full SRS and source answers.

| Task | Scope / dependencies | Primary CT-DEC IDs | Acceptance focus |
|---|---|---|---|
| CT-P00 | IN REVIEW: UX accepted; source reconciliation, technical design and approval gates remain; no dependencies | cross-cutting | Sign-off, exact status matrix, unresolved values recorded; no hold removed prematurely. |
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
- CT-DEC-012: actual AM/PM start/end, short workday and effective-dated handling. CT-DEC-014 current answer: no maximum negative cap now; CT-DEC-020 exact current response still to verify. CT-DEC-027 current answer: no maximum EL retrospective deadline now; configurable if future policy changes. CT-DEC-025: five working days initial correction period; proposed next-working-day counting, 17:00 cutoff and one-working-day reminder require HR confirmation. CT-DEC-054: proposed reminder first after two working days, then every two, max three per stage; confirm initial configuration before activation.
- Current issue register answers reviewed (not historical ADR): CT-DEC-017 GM confirms cancellation; before start refund original sources, during leave unused portion only after HR verifies return date, after leave HR special adjustment with GM confirmation; self-action conflict for GM own cancellation needs authorized resolution, no invented exception. CT-DEC-032 pending unsupported transfer to new PU for leave after effective date; preserve prior valid support, mixed-period cases HR review. CT-DEC-046 retain pending and reservation despite leave dates passing, late decision normal; no automatic payroll or attendance classification. CT-DEC-050 direct GM route as above. CT-DEC-051 HR can record/submit only with staff consent and confirmation before submission, preserve actual applicant and HR actor. Verify exact status and remaining details before final contracts.
- CT-DEC-034 current answer supersedes historical start-year choice: split by each leave date's calendar year; for December shortage next-year entitlement only from 25 December subject to CT-DEC-015. January uses next-year AL then Tabung/negative per rule. CT-DEC-037 permits future-year application only after HR confirms entitlement; otherwise draft only. Pre-credit final approval becomes commitment, not pending; Jan 1 official credit once, no double debit. Reconcile full CT-DEC-015 and other cross-year source clauses before implementation; use per-day allocation year, not a single request-level balance year.
- CT-DEC-009 current answer: derive notice from working days; less than three requires emergency reason, mark for reviewer; AL not automatically converted to EL. All GM final approvals/reminders go to Secretary ALK under CR-HR-2026-002, overriding stale Pengerusi wording in issue answers. CT-DEC-053 staffing overlap warning for PU uses approved same-unit absences per date/session, no cap or auto-block; pending separate, sensitive details excluded.
- HR/IT: audit and attachment retention, access lifecycle, account/employee association, actual initial dataset, production auth and recovery policy. Formal HR approval for normative values and project authorization are separate from UX acceptance.

Do not manufacture missing rates, configured values, role grants or approval evidence. A policy-dependent operation with missing mandatory configuration must not post balances or advance workflow; use an explicit configuration/review error, except where the confirmed policy specifically permits an unset value (e.g. unlimited). Review exact behavior in G3.

## 9. Verification & implementation handoff

- Local Codex: read AGENTS.md, fetch origin, inspect local main and worktree, preserve unrelated changes. Do not run implementation until authorized. Reconcile this draft with complete DOCX SRS and issue answers; update existing planning/design/handoff only (avoid duplicate reports). Use existing change control for normative scope, synchronize RTM/CSV/XLSX only when approved requirements change. Do not mark existing planned tests passed without evidence.
- For each authorized slice: targeted feature/unit checks, build/type/lint as relevant, real existing local MySQL integration with synthetic records under approved test strategy; verify transactions, unique constraints, concurrency, idempotent retries and failure rollback. Do not run destructive operations or create infrastructure without approval. Consolidate full suite, requirements trace and browser review at phase exit.
- Production remains separately gated: release approvals, real browser SSO callback/logout and MFA integration, existing security advisory remediation/review, TLS, verified backup/restore, HR/IT retention and deployment acceptance. Do not equate local test success with production readiness.

**Current result:** `UX_DESIGN_ACCEPTED=YES`, `UX_DESIGN_FROZEN=YES`, `TECHNICAL_DESIGN=IN_REVIEW`, `READY_FOR_FUNCTIONAL_IMPLEMENTATION=NO`. CT-P00 remains in review; CT-P01 is the first proposed development slice, blocked pending MOD-004 minimum scope/fields, formal HR sign-off, explicit implementation authorization and local preflight. Remote documentation only; no runtime testing, migrations, source code or infrastructure changes.
