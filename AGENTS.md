# Coding-agent instructions: KUTMB HR

1. Treat this Git repository as the source of truth for system requirements and decisions.
2. Current baseline is **v0.1 DRAFT**. No full HR domain or business policy has been approved.
3. Do not implement or fabricate employee, payroll, attendance, leave, OT, claims, sensitive-personal-data workflows or policy rates without an approved requirements update.
4. For module scope changes, open a CR with unique ID; update `docs/traceability/` Excel operational master and CSV mirrors alongside SRS/RTM, do not delete/reuse retired module IDs.
5. Consult `docs/requirements/OPEN_QUESTIONS.md`, `docs/decisions/ADR_REGISTER.md`, `docs/requirements/RTM.md`, and module SRS before architecture/coding.
6. Laravel, Vue and MySQL are selected stack families; exact versions/integration, physical file structure and hosting are still proposed or deferred in DOC-008 / ADR-006.
7. Respect user/account vs employee/person separation as **proposed**, not settled; flag decisions requiring HR approval.
8. Keep IDs stable and maintain Feature → Requirement → Acceptance Criterion → Planned Test → Evidence traceability. Do not claim test passes without execution evidence.
9. No secrets, real personal HR data, database exports, tokens or confidential configuration in Git history, PRs, fixtures or logs. This repo is **public**.
10. Scope changes require documentation review via PR. A merged proposal is not equivalent to business sign-off.

## Mandatory scope boundary (SCOPE-001)

- Read [SCOPE-001](docs/decisions/SCOPE-001_system-core-vs-hr-core.md) before planning implementation.
- The **current development target is System Core**, beginning with `MOD-001` User Management / Identity & Access. `MOD-002` configuration and `MOD-003` security/audit are supporting work, not permission to implement HR domain rules.
- **HR Core is ON HOLD until HR provides and approves requirements**. Do not build employee master or HR workflow modules or derive HR policies from example fields, speculative assumptions or legacy `HR Foundation` naming.
- Changing presentation terminology is not an approved business requirement or permission to skip user-module security/architecture reviews.

## Confirmed stack family and proposed technical baseline

- The developer selected **Laravel + Vue + MySQL**. The **preferred, not yet approved architecture** in `DOC-008` is Laravel 13 + Vue 3 + Inertia 3 + TypeScript + MySQL 8.4 LTS. Review `ADR-006` before adopting.
- Read `docs/security/SEC-001_System_Core_Security_Baseline_v0.1_DRAFT.md` for proposed security controls and `DOC-012` for the staged Vue learning plan. Never assume Vue client authorization is enough.
- **Do not invent or freeze file/directory structure**, API topology, physical schema, hosting or Docker layout. User explicitly deferred structure planning.
- Do not start user module implementation solely from these planning docs; close relevant UM-DEC security decisions and review gates first. HR Core remains on hold.

## MOD-001 technical decision checkpoint

- Consult `docs/decisions/DOC-013_MOD-001_IAM_Technical_Decisions_v0.1_DRAFT.md` and `docs/design/DOC-014_MOD-001_Authentication_Authorization_Contracts_v0.1_DRAFT.md` when planning account/auth work. These contain **design defaults**, not implementation authorization or approval.
- Username is the preferred login principal. System Admin and Standard User are initial proposed roles; delegated User Admin must **not** be enabled by default. Public self-registration is disabled by default.
- Do not implement recover/invite flow before an approved delivery and identity-verification channel; do not ship privileged production login without reviewed MFA/recovery. Never use default passwords or secret logging.
- Backend must enforce active status, per-target permissions, last-admin safety and revoked access after privilege changes. Vue permission hints are never authorization.
- The user deferred physical folder structure, migrations/ERD and deployment topology to a later workshop. No app code is requested or authorized by these planning documents.

## P0.6 data-model boundary

- Read `docs/data/DOC-015_MOD-001_Logical_Data_Model_v0.1_DRAFT.md` and `docs/quality/MOD-001_Data_Integrity_Test_Matrix_v0.1_DRAFT.md` as **logical review material**, not executable implementation instructions.
- Never generate Employee tables/FKs from System Core, and never commit real employee or user credentials. No physical DB migration, Eloquent mapping, folder or Docker layout has been accepted.
- Backend must enforce canonical username uniqueness at the database boundary, last-admin concurrency-safe invariants, per-target role grants and fresh session authorization, **once approved and implemented**.
- Mock/SQLite test successes do not prove MySQL/InnoDB locking and concurrent write behavior. Capture integration evidence when code exists.
- Do not create new SRS requirement IDs or alter Excel/CSV from this conceptual-only design; use a formal CR if future decisions change normative scope or behavior.

## P0.7 readiness gate

- Before proposing any System Core code, read `docs/reviews/DOC-016_P0.7_System_Core_Technical_Readiness_v0.1_DRAFT.md`. Its **engineering-selected defaults are not approved HR/IT requirements**.
- Never mark UM-DEC/DATA-DEC closed, accept an ADR, pass a release gate, or claim DEV READY without the relevant reviewer decision/evidence.
- The approved stack *families* are Laravel + Vue + MySQL; specific versions/Inertia remain recommendations. No user module or HR Core source code in this docs-only PR.
- Physical ERD, MySQL migrations, Laravel/Vue folder structure and deployment topology remain for an explicit future workshop, per requestor.

## P0.8 physical design proposals (NOT implementation authorization)

- Read DOC-017 (candidate MySQL schema), DOC-018 (source organization alternatives), DOC-019 (10 pending physical design decisions) before planning first Laravel/Vue files.
- The **preferred** source organization is official Laravel Vue starter conventions, with modest System Core feature grouping. Do **not** create folders, migrations, Eloquent models, seeders, Vue pages or a separate API based on illustrative documents alone.
- The proposed `iam_guard` transaction lock is a reviewable design candidate. Do not claim last-admin concurrency safety without ensuring every relevant mutation shares its lock and tests PASS on MySQL/InnoDB.
- Keep public repository free of production secrets, personal employee data and confidential infra. HR Core MOD-004..011 remains HOLD.
- The 10 physical design review items in DOC-019 are still OPEN; user and IT review must precede schema/code freeze.

## P0.9 proposed implementation work packages (NOT STARTED)

- Read `docs/implementation/DOC-020_MOD-001_Implementation_Blueprint_v0.1_DRAFT.md` for proposed `WP-000..WP-110` and `docs/reviews/DOC-021_MOD-001_PR_Reconciliation_and_Test_Trace_v0.1_DRAFT.md` for branch and test overlap.
- **WP-000 documentation reconciliation comes first.** Draft PR #1 contains DOC-011 and 26 functional scenarios; Draft PR #2 contains architecture and 18 data scenarios. Five common docs require manual reviewed reconciliation. Never silently discard one PR's edits.
- `WP-010` requires actual decisions and evidence; coding, DB migrations, bootstrap, recovery and MFA work packages are explicitly **BLOCKED until applicable security/architecture/IT gates are accepted**. A 'proceed' in planning is not a blanket production authorization.
- Maintain 32 original requirement, AC and base test IDs. Scenario subcases do not create new SRS requirements. Do not claim 44 unique executable tests because scenario plans have overlaps.
- No source files, folder tree, SQL, HR Core records or deployment in this documentation PR. Source repository remains public; never store credentials, secrets, real staff data or private infrastructure configuration.

## MOD-001 functional design now consolidated into review branch

- Read `docs/design/DOC-011_MOD-001_Functional_Design_v0.1_DRAFT.md` and `docs/quality/MOD-001_Functional_Test_Scenarios_v0.1_DRAFT.md` as **DRAFT proposals**. They were copied from Draft PR #1 into the consolidated architecture branch without treating them as approved or tested.
- Preserve the original 13 IAM requirements and all 32 RTM IDs. The 26 functional subcases and 18 data subcases may overlap, and have **zero executed tests**.
- Resolve applicable UM-DEC decisions and accept the physical-model/file-layout and security gates before code. A docs consolidation commit is **not** implementation authorization.
