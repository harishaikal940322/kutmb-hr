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
