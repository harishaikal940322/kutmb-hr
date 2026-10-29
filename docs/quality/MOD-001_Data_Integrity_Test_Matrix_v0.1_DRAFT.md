# MOD-001 | Data Integrity and Concurrency Test Matrix

> **v0.1 DRAFT • 29 October 2026 • 0 executed tests • 0 passed**
>
> These are **planning subcases** associated with existing `T-...` identifiers, not additional normative SRS requirements or evidence of passing tests. Source: [DOC-015 logical data model](../data/DOC-015_MOD-001_Logical_Data_Model_v0.1_DRAFT.md), [DOC-005](../srs/DOC-005_Identity_Access_SRS.md), [DOC-013](../decisions/DOC-013_MOD-001_IAM_Technical_Decisions_v0.1_DRAFT.md), [DOC-014](../design/DOC-014_MOD-001_Authentication_Authorization_Contracts_v0.1_DRAFT.md).
>
> `MOD-001 Functional Test Scenarios` are independently proposed on [Draft PR #1](https://github.com/harishaikal940322/kutmb-hr/pull/1). Reconcile scenario overlap before creating an executable suite.

| Data case | Existing test ID(s) | Proposed test with synthetic accounts | Expected behavior / invariant | Gate | Execution |
|---|---|---|---|---|---|
| DI-TC-001 | T-SYS-DR-001 | Create system administrator with no Employee/Organization tables | Account works without HR record, DI-001/010 | Identity scope | PLANNED |
| DI-TC-002 | T-IAM-FR-002 | Username duplicate in one request context and mismatched case/whitespace variants | Accepted normalization + unique index reject conflicting principal, DI-002 | DATA-DEC-003 | PLANNED |
| DI-TC-003 | T-IAM-FR-002 | Parallel attempts to insert the same canonical username into MySQL | Exactly one committed unique principal; safe duplicate response, DI-002 | MySQL integration | PLANNED |
| DI-TC-004 | T-IAM-FR-001 | Attempt to create user with fake `is_admin`, `employee_id`, `created_by` or unapproved role field | Rejected or ignored per explicit allowlist; no privileged state persisted | Permission/fields | PLANNED |
| DI-TC-005 | T-IAM-FR-007 | User with no granting role attempts direct protected URL | Server refuses; no update committed, DI-005 | RBAC approval | PLANNED |
| DI-TC-006 | T-IAM-FR-006 | Delegated admin attempts to assign System Admin privileges | Deny by default; role assignments remain unchanged, DI-005 | Role policy | PLANNED |
| DI-TC-007 | T-IAM-FR-008 | Own profile request sends `role`, `status` or another principal ID | Protected fields/target cannot mutate, DI-004 | Field policy | PLANNED |
| DI-TC-008 | T-IAM-FR-005 | Deactivate/activate user; read past audit event | Deactivated login blocked; existing history retained, DI-008 | Lifecycle policy | PLANNED |
| DI-TC-009 | T-IAM-FR-009 | Disable active user with an open session and request protected operation | Access denied immediately after commit, DI-003/009 | Session policy | PLANNED |
| DI-TC-010 | T-IAM-FR-009 | Remove role without disabling user while old session remains open | Old permission cannot authorize any new protected operation, DI-009 | Revocation contract | PLANNED |
| DI-TC-011 | T-IAM-FR-010 | Attempt to disable last active authorized System Admin | Atomic rejection and audit recorded; admin remains active, DI-006 | Admin bootstrap | PLANNED |
| DI-TC-012 | T-IAM-FR-010 | Two parallel requests each try to remove a different one of two final admins | Cannot commit to zero active authorized admins under MySQL test, DI-006 | InnoDB design | PLANNED |
| DI-TC-013 | T-IAM-AUD-001 | Create/revoke role and deactivate account; then read audit through privileged viewer | Actor/target/action/outcome/time present and redacted, DI-007 | Audit policy | PLANNED |
| DI-TC-014 | T-SYS-SEC-002 | Inspect Inertia page props, logs and failed validation for hash, bearer session, token and recovery secret leakage | No raw secret or full sensitive User model exposed, DI-011 | Privacy/security | PLANNED |
| DI-TC-015 | T-IAM-FR-004 | Simulate invalid/expired/reused approved recovery challenge | Always rejected; replay not possible | **BLOCKED** recovery channel | NOT EXECUTABLE |
| DI-TC-016 | T-SYS-AUD-001 | Deactivate account and simulate actor no longer active; verify audit target and actor remain interpretable | Historical evidence preserved, no recycled IDs, DI-012 | Retention policy | PLANNED |
| DI-TC-017 | T-SYS-SEC-001 | Tamper with Vue component permissions or submit manually forged request | Backend policy denies, regardless of UI, DI-004/005 | Policy design | PLANNED |
| DI-TC-018 | T-IAM-FR-003 | Simulate account login/session lifecycle with supported storage driver | Session rotation, secure expiry and authentication status checks | Session policy | PLANNED |

## Execution policy

- Use **MySQL-backed integration** when verifying database uniqueness, concurrency, row locks, grant updates and last-admin safety; mocks/SQLite are insufficient proof of InnoDB-specific guarantees.
- Capture code commit SHA, framework/database versions, fixture setup, simultaneous request schedule, assertion result, issue references, redacted evidence and test-owner review.
- Verify both **positive and negative** authorization cases. UI visibility is never sufficient evidence.
- If a security invariant cannot be enforced, label `BLOCKED` and do not infer `DEV READY`.
- Never use real KUTMB employee records, credentials or HR exports.
- Retain existing RTM `T-IAM-...` / `T-SYS-...` IDs. If later approval changes the normative requirement, record CR and synchronize Excel, CSV and SRS.

**Current status: 18 planned cases, 1 not executable until recovery policy, 0 executed, 0 passed.** The table's `PLANNED` status is not a pass.
