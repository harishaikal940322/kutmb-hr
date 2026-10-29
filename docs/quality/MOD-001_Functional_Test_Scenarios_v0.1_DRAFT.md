# MOD-001 | Functional Acceptance & Negative Test Scenarios

> **Version:** v0.1 DRAFT • **Date:** 29 October 2026 • **Execution:** NOT RUN • **Results:** 0 PASS, 0 FAIL, all PLANNED
>
> This document refines the existing `T-IAM-...` IDs without replacing them. No tests or application exist yet; expected behavior is proposed pending decision approval. Source: [DOC-005](../srs/DOC-005_Identity_Access_SRS.md), [DOC-011](../design/DOC-011_MOD-001_Functional_Design_v0.1_DRAFT.md).

| Scenario ID | Requirement / Test ID | Scenario | Expected outcome (proposed) | Priority | Status |
|---|---|---|---|---|---|
| SCN-UM-001 | IAM-FR-001 / T-IAM-FR-001 | Authorized create with valid minimum fields | Account and audit created exactly once | P0 | PLANNED |
| SCN-UM-002 | IAM-FR-001 / T-IAM-FR-001 | User without `users.create` attempts create via direct request | Server denies and persists nothing | P0 | PLANNED |
| SCN-UM-003 | IAM-FR-002 / T-IAM-FR-002 | Duplicate login identifier including race/concurrent submits | Uniqueness enforced atomically; helpful non-sensitive validation | P0 | PLANNED |
| SCN-UM-004 | IAM-FR-003 / T-IAM-FR-003 | Correct credential, active principal | Authenticated session established under approved method | P0 | PLANNED |
| SCN-UM-005 | IAM-FR-003 / T-IAM-FR-003 | Incorrect credential, inactive principal or unknown identifier | Denied; generic error, no account enumeration | P0 | PLANNED |
| SCN-UM-006 | IAM-FR-003 / T-IAM-FR-003 | Logout and replay prior session | Protected request denied after invalidation | P0 | PLANNED |
| SCN-UM-007 | IAM-FR-004 / T-IAM-FR-004 | Verified recovery with approved channel | Can regain access without disclosing secrets | P0 | BLOCKED: UM-DEC-005 |
| SCN-UM-008 | IAM-FR-004 / T-IAM-FR-004 | Expired/reused/invalid recovery challenge | Rejected with safe response | P0 | BLOCKED: UM-DEC-005 |
| SCN-UM-009 | IAM-FR-005 / T-IAM-FR-005 | Authorized deactivate/reactivate | Active login blocked while inactive; audit and identity retained | P0 | PLANNED |
| SCN-UM-010 | IAM-FR-006 / T-IAM-FR-006 | Admin assigns/revokes authorized role | Effective rights change and audit records actor/target | P0 | PLANNED |
| SCN-UM-011 | IAM-FR-006 / T-IAM-FR-006 | Delegated admin assigns privileged role | Denied unless explicitly authorized by approved grant policy | P0 | BLOCKED: UM-DEC-004 |
| SCN-UM-012 | IAM-FR-007 / T-IAM-FR-007 | Every permission mapped to permitted/denied role | Unauthorized action denied by server, not just hidden UI | P0 | BLOCKED: UM-DEC-004 |
| SCN-UM-013 | IAM-FR-008 / T-IAM-FR-008 | Standard user reads/edits approved own fields | Allowed only for self-editable fields | P0 | PLANNED |
| SCN-UM-014 | IAM-FR-008 / T-IAM-FR-008 | Standard user submits `role`, `status` or another user's ID in own-profile request | Privileged/other-user mutation denied | P0 | PLANNED |
| SCN-UM-015 | IAM-FR-009 / T-IAM-FR-009 | Disable user with existing valid session | Next protected request denied / session revoked | P0 | PLANNED |
| SCN-UM-016 | IAM-FR-010 / T-IAM-FR-010 | Deactivate final active System Admin | Atomic refusal, active admin preserved | P0 | PLANNED |
| SCN-UM-017 | IAM-FR-010 / T-IAM-FR-010 | Concurrent attempts to remove final two admins | Cannot end with zero active authorized admins | P0 | PLANNED |
| SCN-UM-018 | IAM-FR-011 / T-IAM-FR-011 | Unauthenticated visitor attempts registration | No public self-signup route/process without policy approval | P0 | PLANNED |
| SCN-UM-019 | IAM-FR-012 / T-IAM-FR-012 | Authorized list/search/filter by identifier, role and status | Accurate pagination/filter result with test fixtures | P1 | PLANNED |
| SCN-UM-020 | IAM-FR-012 / T-IAM-FR-012 | Unauthorized visitor requests user index or export-like endpoint | Server denies; no user information disclosed | P0 | PLANNED |
| SCN-UM-021 | IAM-AUD-001 / T-IAM-AUD-001 | Create, change roles, deactivate/reactivate | Actor, action, target, outcome and time retrievable per approved policy | P0 | PLANNED |
| SCN-UM-022 | SYS-SEC-002 / T-SYS-SEC-002 | Error, validation and audit pathways | No credential/reset token/session secret in logs or response | P0 | PLANNED |
| SCN-UM-023 | SYS-DR-001 / T-SYS-DR-001 | Bootstrap admin without employee data | System functions without an Employee record/table | P0 | PLANNED |
| SCN-UM-024 | SYS-NFR-001 / T-SYS-NFR-001 | Keyboard-only at 360px and 1280px | Core functions accessible, no clipped critical controls | P1 | PLANNED |
| SCN-UM-025 | SYS-SEC-001 / T-SYS-SEC-001 | Forged direct URL/API edit or record ID swap | Authorization enforced at all protected endpoints | P0 | PLANNED |
| SCN-UM-026 | SYS-AUD-001 / T-SYS-AUD-001 | Admin privileged changes across account flows | Tamper-resistant audit evidence per approved architecture | P0 | PLANNED |

## Execution and evidence rules

For each scenario record run date, environment, code commit SHA, tester, result, sanitized evidence location and related defect. Unexecuted scenarios are **PLANNED** or **BLOCKED**, never PASS. Security-sensitive scenarios must use synthetic users and no actual HR personnel data.

Coverage: 13/13 IAM SRS requirement IDs represented, plus cross-cutting System Core IDs. Before DEV READY, review all P0 blockers, sign off field and permission policies, and update SRS/RTM/Excel if any normative requirement changes.

**Important:** The scenario IDs `SCN-UM-001..026` are *additional planning subcases*, not replacements for the existing one-to-one RTM test IDs. No requirement or module ID has been created/deleted.
