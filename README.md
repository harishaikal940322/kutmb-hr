# KUTMB HR System

> **Status:** Requirements Engineering / Foundation Planning  
> **Baseline:** HR Foundation v0.1 (DRAFT, 29 October 2026)  
> **Approval:** NOT APPROVED. No implementation or testing has been completed.

This repository is the **single source of truth** for the KUTMB HR System, covering requirements, traceability, decisions, architecture, implementation, and verification.


## Scope clarification (29 October 2026)

**Develop System Core first, NOT HR Core.** Our first development target is **MOD-001 Identity & Access / User Management**. System security/audit and minimum required configuration support that module. **Employee & Organization and other HR Core business modules must wait for explicit HR requirements and approval**. An account is not automatically an employee record. Existing v0.1 `HR Foundation` wording is historical and does not authorize HR Core implementation.

See [SCOPE-001: System Core versus HR Core](docs/decisions/SCOPE-001_system-core-vs-hr-core.md). No requirement has been newly approved by this clarification.

## Current scope

Build an HR *foundation* first, while full HR requirements are still pending:
- User accounts, authentication, roles and permissions.
- System configuration and reference data, including *panggilan* (assumed to mean salutation; confirmation pending).
- Privileged action audit trail and basic security / recovery requirements.

**Out of scope until explicitly specified:** employee master, leave, attendance, payroll, overtime, claims, performance, and HR approval workflows.

## Documentation

- [Documentation governance](docs/README.md)
- [System-wide SRS (DOC-004)](docs/srs/DOC-004_System_Wide_SRS.md)
- [Identity & Access SRS (DOC-005)](docs/srs/DOC-005_Identity_Access_SRS.md)
- [Configuration & Reference Data SRS (DOC-006)](docs/srs/DOC-006_Configuration_Reference_Data_SRS.md)
- [Requirements traceability](docs/requirements/RTM.md)
- [Module lifecycle and Excel tracker](docs/traceability/README.md) ([core workbook](docs/traceability/KUTMB_HR_Traceability_Core_v0.2.xlsx))
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
