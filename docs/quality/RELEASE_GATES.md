# DOC-010 | Roadmap & Release Gates

> v0.1, 29 October 2026 • **0 of 8 gates passed**. No DEV READY, UAT PASS, or GO-LIVE authorization.

| Gate | Control | Exit criterion | Status | Proposed reviewer |
|---|---|---|---|---|
| GATE-01 | Feature/scope approval | Master matrix reviewed; scope confirmed | NOT PASSED | HR/Product Owner |
| GATE-02 | SRS module review | DOC-004/005/006 approved with signoff | NOT PASSED | HR/IT |
| GATE-03 | Dependencies & open issues | All critical blocking questions resolved | NOT PASSED | HR/IT |
| GATE-04 | Acceptance criteria | All must-have AC reviewed and testable | NOT PASSED | QA/Requestor |
| GATE-05 | Traceability | Feature → Req → AC → Test coverage reviewed | NOT PASSED | QA/Developer |
| GATE-06 | Architecture/ADR impact | Stack, identity model, backup/deployment recorded | NOT PASSED | Architect/IT |
| GATE-07 | Security/privacy | Access controls, logging, privacy obligations reviewed | NOT PASSED | IT/Compliance |
| GATE-08 | Release readiness | Tests executed, evidence retained, owner UAT passed | NOT PASSED | QA/HR |

## Proposed phases

1. **P0 Planning**: requirements, feature matrix, SRS/RTM, open questions, decision review.
2. **P1-A Identity & Access**: only after scope and permission decisions are approved.
3. **P1-B Configuration & Reference Data**: approved categories and salutations, lifecycle and auditing.
4. **P1-C Verification**: test execution and evidence, security/access checks, backup-restore dry-run and UAT.
5. **P2+ HR domain**: employee records, leave, attendance, payroll and other modules only after separate SRS approval.

## Evidence and sign-off

Record reviewer, approval reference, date, release candidate commit SHA, test execution identifiers, failed/passed counts, deviations and rollback plan. A GitHub green check alone is insufficient to mark any gate passed.
