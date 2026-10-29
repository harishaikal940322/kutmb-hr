# KUTMB HR System

> **Status:** System Core development foundation in WP-020 review
> **Baseline:** HR Foundation v0.1 (DRAFT, 29 October 2026)  
> **Approval:** Requirements remain NOT APPROVED. WP-020 verification applies only to the isolated development starter.

This repository is the **single source of truth** for the KUTMB HR System, covering requirements, traceability, decisions, architecture, implementation, and verification.

## Scope clarification (29 October 2026)

**Develop System Core first, NOT HR Core.** Our first development target is **MOD-001 Identity & Access / User Management**. System security/audit and minimum required configuration support that module. **Employee & Organization and other HR Core business modules must wait for explicit HR requirements and approval**. An account is not automatically an employee record. Existing v0.1 `HR Foundation` wording is historical and does not authorize HR Core implementation.

See [SCOPE-001: System Core versus HR Core](docs/decisions/SCOPE-001_system-core-vs-hr-core.md). No requirement has been newly approved by this clarification.

## Current implementation scope

WP-020 establishes the Laravel, Vue, Inertia, TypeScript, Vite and MySQL development foundation only. It does not implement User Management CRUD, RBAC, account recovery, MFA, audit storage, configuration workflows or any HR Core module. `MOD-004` through `MOD-011` remain on hold until HR requirements are approved.

The SRS, RTM, Excel traceability master, CSV mirrors, module IDs and requirement IDs remain unchanged. Starter login is present for framework integration checks; there are no seeded accounts or committed credentials. Login identifier and future user fields remain open requirements.

## Local development

Docker and Docker Compose are the only host dependencies. From the repository root:

```sh
./bin/dev up
```

This creates a private `.env` with random local-only credentials, starts the app on PHP 8.5.12 and MySQL 26.10.0 containers, applies the starter's baseline migrations and launches Vite. The app and Vite ports bind to localhost; MySQL has no host port. No privileged user is seeded. Use `./bin/dev down` to stop the containers. The development database persists in a new named Docker volume, `kutmb-hr_mysql_data_php8512_mysql26100`; the prior MySQL 8.4 volume is retained and must not be mounted by MySQL 26.10.0. Removing the active volume is an explicit destructive action (`docker compose --project-name kutmb-hr down --volumes`).

Useful checks:

```sh
./bin/dev test
./bin/dev mysql-test
./bin/dev build
./bin/dev types
./bin/dev check
./bin/dev routes
./bin/dev audit
```

`./bin/dev test` and `./bin/dev mysql-test` build the Vite manifest, then run baseline migrations and checks against a temporary MySQL 26.10.0 container that is removed automatically; tests never use SQLite or the persistent development database. `./bin/dev check` runs frontend formatting/lint, TypeScript, PHPStan, Pint, PHPUnit, a production build and dependency audits. GitHub Actions uses PHP 8.5.12 and MySQL 26.10.0.

Public registration, password recovery, email verification, MFA and passkey flows are disabled. The starter login uses its default email field only for the isolated scaffold and does not settle the open login-identifier requirement.

## Documentation

- [Documentation governance](docs/README.md)
- [System-wide SRS (DOC-004)](docs/srs/DOC-004_System_Wide_SRS.md)
- [Identity & Access SRS (DOC-005)](docs/srs/DOC-005_Identity_Access_SRS.md)
- [Configuration & Reference Data SRS (DOC-006)](docs/srs/DOC-006_Configuration_Reference_Data_SRS.md)
- [MOD-001 Functional Design (DOC-011, DRAFT)](docs/design/DOC-011_MOD-001_Functional_Design_v0.1_DRAFT.md)
- [MOD-001 Functional Test Scenarios (26 planned, not executed)](docs/quality/MOD-001_Functional_Test_Scenarios_v0.1_DRAFT.md)
- [DOC-022 First Codex Coding Handoff (DRAFT)](docs/implementation/DOC-022_P1.1_First_Codex_Handoff_v0.1_DRAFT.md)
- [DOC-020 MOD-001 Implementation Blueprint / Codex Work Packages (DRAFT)](docs/implementation/DOC-020_MOD-001_Implementation_Blueprint_v0.1_DRAFT.md)
- [DOC-021 Cross-PR Reconciliation & Test Traceability (DRAFT)](docs/reviews/DOC-021_MOD-001_PR_Reconciliation_and_Test_Trace_v0.1_DRAFT.md)
- [DOC-017 MySQL Physical Schema Proposal (DRAFT)](docs/data/DOC-017_MOD-001_Physical_Schema_Proposal_v0.1_DRAFT.md)
- [DOC-018 Laravel Vue Source Structure Options (DRAFT)](docs/architecture/DOC-018_Laravel_Vue_Source_Organization_Options_v0.1_DRAFT.md)
- [DOC-019 P0.8 Physical Design Decision Sheet (DRAFT)](docs/reviews/DOC-019_P0.8_Physical_Design_Decision_Sheet_v0.1_DRAFT.md)
- [P0.7 Technical Readiness and Design Review (DOC-016, DRAFT)](docs/reviews/DOC-016_P0.7_System_Core_Technical_Readiness_v0.1_DRAFT.md)
- [DOC-015 MOD-001 Logical Data Model (DRAFT)](docs/data/DOC-015_MOD-001_Logical_Data_Model_v0.1_DRAFT.md)
- [MOD-001 Data Integrity / MySQL Concurrency Test Matrix (DRAFT)](docs/quality/MOD-001_Data_Integrity_Test_Matrix_v0.1_DRAFT.md)
- [DOC-013 MOD-001 IAM Technical Defaults (DRAFT)](docs/decisions/DOC-013_MOD-001_IAM_Technical_Decisions_v0.1_DRAFT.md)
- [DOC-014 Authentication, Authorization & Inertia Contracts (DRAFT)](docs/design/DOC-014_MOD-001_Authentication_Authorization_Contracts_v0.1_DRAFT.md)
- [DOC-008 System Core Solution Architecture (DRAFT)](docs/architecture/DOC-008_System_Core_Solution_Architecture_v0.1_DRAFT.md)
- [Security Baseline / Threat Review (DRAFT)](docs/security/SEC-001_System_Core_Security_Baseline_v0.1_DRAFT.md)
- [Vue 3 Learning Plan for MOD-001](docs/learning/DOC-012_Vue_Onboarding_MOD-001_v0.1_DRAFT.md)
- [ADR-006 Proposed Inertia-first Stack](docs/decisions/ADR-006_Laravel_Vue_Inertia_MySQL_v0.1_PROPOSED.md)
- [Requirements traceability](docs/requirements/RTM.md)
- [Module lifecycle and Excel tracker](docs/traceability/README.md) ([current Excel v0.3 DRAFT](docs/traceability/KUTMB_HR_System_Core_Traceability_v0.3_DRAFT.xlsx))
- [MOD-001 User Management requirements review](docs/requirements/MOD-001_USER_MANAGEMENT_REVIEW_v0.3.md)
- [Open questions](docs/requirements/OPEN_QUESTIONS.md)
- [Decision register](docs/decisions/ADR_REGISTER.md)
- [Release gates](docs/quality/RELEASE_GATES.md)

## Authority rules

1. All v0.1 items are **DRAFT**, not HR-approved requirements.
2. Requirement IDs, acceptance criteria IDs and test IDs must remain stable once referenced.
3. A proposed change must update the relevant SRS and RTM in the same PR; architecture decisions also need an ADR.
4. No `DEV READY`, `TEST PASSED` or `RELEASE READY` declaration without corresponding evidence and sign-off.
5. No personally identifiable HR records, secrets, environment files or production exports may be committed.
6. Use pull requests for changes; `main` is the approved-history branch, but documents can still be marked DRAFT.

See `docs/README.md` for document authority and controls.
