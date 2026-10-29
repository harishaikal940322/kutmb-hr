# P0.3 | MOD-001 User Management Requirements Review

> **Version:** v0.3 DRAFT · **29 October 2026** · **Status:** REVIEW REQUIRED  
> **Decision:** [SCOPE-001](../decisions/SCOPE-001_system-core-vs-hr-core.md) confirms System Core first, HR Core on hold.  
> **Normative source:** [DOC-005](../srs/DOC-005_Identity_Access_SRS.md) (13 DRAFT requirements), [DOC-004](../srs/DOC-004_System_Wide_SRS.md), [RTM](RTM.md).  
> **Caution:** The proposed options below are **not** approved HR/IT policies.

## Scope of the first development module

**MOD-001 is System Core**, and User Management is the first planned implementation. It must function without an employee master or HR policy engine.

In scope for requirements review: accounts, login/logout, account activation/deactivation, unique identifiers, lists/search, self-profile, roles and permission boundaries, access recovery, session revocation, last-admin protection, disabled public registration, and IAM auditing. Security and auditing are delivered by cross-cutting MOD-003; MOD-002 provides only specifically approved shared settings.

**Not in scope:** employee records, positions, departments as HR records, leave, attendance, overtime, claims, salary, payroll and performance workflows. Do not create a required `employee_id` foreign key for account creation in this phase.

## Decision queue

| Decision ID | Related requirement | Decision required | Safe proposal for discussion | Owner | Status |
|---|---|---|---|---|---|
| UM-DEC-001 | IAM-FR-002 | Login identifier: username, email, staff ID, SSO? | Prefer an identity independent of employee records | IT / Requestor | OPEN |
| UM-DEC-002 | IAM-FR-001 / IAM-FR-008 | Mandatory profile fields and editable profile fields? | Collect minimum operational information | IT / HR | OPEN |
| UM-DEC-003 | IAM-FR-001 / IAM-FR-006 / IAM-FR-010 | Who is allowed to bootstrap and administer the first System Admin? | Explicit privileged setup with last-admin protection | IT | OPEN |
| UM-DEC-004 | IAM-FR-006 / IAM-FR-007 | Role catalogue and permission matrix? | Separate account administrator from future HR permissions | IT / HR | OPEN |
| UM-DEC-005 | IAM-FR-004 | Password reset / invitation channel? | No assumed SMTP, SSO or external service | IT | OPEN |
| UM-DEC-006 | IAM-FR-003 / IAM-FR-009 | Session policy, disabled-account revocation and recovery? | Re-check authorization on protected operations | IT / Security | OPEN |
| UM-DEC-007 | IAM-FR-011 | Is public self-registration permitted? | Disabled until explicitly approved | IT / HR | OPEN |
| UM-DEC-008 | IAM-FR-005 / IAM-AUD-001 | Deactivation vs deletion and retention requirements? | Deactivate rather than erase audit history | IT / Compliance | OPEN |
| UM-DEC-009 | SYS-DR-001 | Must users map to employees, and when? | No mandatory employee record in System Core | HR / IT | OPEN |
| UM-DEC-010 | SYS-NFR-001 | Target language, keyboard and mobile requirements? | Start accessible and responsive; confirm detailed targets | Requestor / IT | OPEN |
| UM-DEC-011 | SYS-SEC-001 / SYS-SEC-002 | MFA, brute-force limits, rate limit and security controls? | Design least-privilege security baseline, confirm controls | IT / Security | OPEN |
| UM-DEC-012 | SYS-AUD-001 / IAM-AUD-001 | Audit access and retention period? | Audit privileged changes; retention awaiting policy | IT / Compliance | OPEN |

## Review outcomes (not yet approved)

The following 13 requirement IDs already exist in DOC-005 and the RTM:
`IAM-FR-001` to `IAM-FR-012` and `IAM-AUD-001`. IDs and acceptance/test mappings are unchanged.

**Exit criteria for MOD-001 implementation-readiness assessment:**
1. System Core boundary confirmed, with no mandatory employee record.
2. Login identifier and account fields decided.
3. Initial admin and role-permission model reviewed.
4. Login/reset/session revocation pathways agreed.
5. Privileged audit retention/security controls reviewed.
6. SRS/RTM and test scenarios updated to reflect decisions, with reviewers and date recorded.
7. DOC-008 architecture contains only approved technical decisions.

**Gate status:** NOT PASSED. No development permission, HR Core approval, UAT or production readiness is implied by this review document.

## Change management

Once decisions are received, record requestor, answer, effective date and impacted requirement IDs. If normative behavior changes, create a Change Request and update SRS, RTM, Excel and CSV mirrors together. Do not silently rename existing requirement IDs.

## Functional design review packet

- [DOC-011 MOD-001 Functional Design v0.1 DRAFT](../design/DOC-011_MOD-001_Functional_Design_v0.1_DRAFT.md): proposed account fields, role-permission matrix, account lifecycle, security and audit flows.
- [MOD-001 Functional Test Scenarios v0.1 DRAFT](../quality/MOD-001_Functional_Test_Scenarios_v0.1_DRAFT.md): 26 planned positive/negative scenarios with existing T-IAM/SYS IDs. No test execution.

**Review handling:** All 12 decisions above remain OPEN. A draft design is not an HR/IT approval, implementation authorization or change to the 32 requirements.
