# KUTMB HR documentation governance

**Version:** v0.1 • **Status:** DRAFT / NOT APPROVED • **Date:** 29 October 2026

This Git repository is the system's source of truth. **Human-readable Markdown in this repo is authoritative for ongoing changes**. DOCX and XLSX v0.1 drafts were the initial inputs; they are not uploaded here and should not be treated as newer than subsequent repository commits.

## Document hierarchy

- **Level 0**: [Document authority register](governance/DOC_REGISTER.md), [glossary](governance/GLOSSARY.md)
- **Level 1**: [Feature Matrix](requirements/FEATURE_MATRIX.md)
- **Level 2**: [System SRS](srs/DOC-004_System_Wide_SRS.md), [RTM](requirements/RTM.md), [Decision Register](decisions/ADR_REGISTER.md), [Release Gates](quality/RELEASE_GATES.md)
- **Level 3**: [Identity & Access SRS](srs/DOC-005_Identity_Access_SRS.md), [Configuration SRS](srs/DOC-006_Configuration_Reference_Data_SRS.md)
- **Change tracking (operational)**: [Excel master + Git mirrors](traceability/README.md), including module lifecycle, CR log, impact analysis and signed decisions.
- **Intake**: [Open Questions](requirements/OPEN_QUESTIONS.md)
- **Reserved**: DOC-007 Employee & Organization SRS; DOC-008 Solution Architecture

## Change control

1. Record the request or issue, identify affected feature IDs and requirement IDs.
2. Record a CR ID for module ADD/MODIFY/REMOVE/DEPRECATE and update the Excel master / diff-friendly CSV mirrors.
3. Check open questions and decisions before selecting an architecture or implementing scope.
4. Update SRS, Feature Matrix and RTM together; preserve referenced IDs (deprecate, never silently reuse).
5. Mark proposed decisions as `PROPOSED`/`ASSUMPTION` until HR/IT actually approve them. Create an ADR for material architecture choices.
6. Review via PR; capture reviewer/date/reason and link evidence. Approval is not implied by merge to `main`.
7. Execute tests only when implementation exists; attach evidence and evaluate gates before claiming DEV READY/RELEASE READY.
8. No secrets or personal employee data in public repository, examples, test fixtures or CI logs.

## Authority and statuses

`DRAFT` → `IN REVIEW` → `APPROVED` (only with recorded authorization) → `SUPERSEDED`/`DEPRECATED`. Open questions use `OPEN`/`BLOCKED` until resolved with linked evidence; release gates use `NOT PASSED` until passed.

**Important:** An unconfirmed requirement is allowed to appear in the baseline for planning, but must never be represented as an approved HR policy.

## Active scope / terminology

**Current operational workbook:** [System Core Traceability Excel v0.3](traceability/KUTMB_HR_System_Core_Traceability_v0.3_DRAFT.xlsx) and [MOD-001 requirements review](requirements/MOD-001_USER_MANAGEMENT_REVIEW_v0.3.md). Existing SRS requirements remain DRAFT, with IDs unchanged.

See [SCOPE-001](decisions/SCOPE-001_system-core-vs-hr-core.md): **System Core first**, prioritizing `MOD-001` User Management. **HR Core is waiting for HR requirements**. This clarification updates development sequencing and the meaning of the historical `HR Foundation` name; it does not modify the existing 32 draft requirements, 11 module IDs, workbook or gates. A subsequent controlled rebaseline should update presentation labels in Excel/CSV/SRS together.

## P0.4 Solution Architecture (technical planning only)

- [DOC-008 System Core Solution Architecture v0.1 DRAFT](architecture/DOC-008_System_Core_Solution_Architecture_v0.1_DRAFT.md)
- [SEC-001 Security Baseline and Threat Review](security/SEC-001_System_Core_Security_Baseline_v0.1_DRAFT.md)
- [ADR-006 Inertia-first architecture proposal](decisions/ADR-006_Laravel_Vue_Inertia_MySQL_v0.1_PROPOSED.md)
- [DOC-012 Vue Onboarding and Delivery Plan](learning/DOC-012_Vue_Onboarding_MOD-001_v0.1_DRAFT.md)
- [DOC-013 MOD-001 IAM technical defaults (DRAFT)](decisions/DOC-013_MOD-001_IAM_Technical_Decisions_v0.1_DRAFT.md)
- [DOC-014 Laravel ↔ Vue authentication/authorization contracts (DRAFT)](design/DOC-014_MOD-001_Authentication_Authorization_Contracts_v0.1_DRAFT.md)

Only the **Laravel + Vue + MySQL stack families** are user-selected. Versions, Inertia integration, role/security policy, auth methods and MySQL LTS target remain proposed. Physical file structure, actual ERD and deployment topology are expressly deferred. None of these design documents permits HR Core development.

## P0.6 System Core Data Design (logical only)

- [DOC-015 MOD-001 Logical Data Model v0.1 (DRAFT)](data/DOC-015_MOD-001_Logical_Data_Model_v0.1_DRAFT.md)
- [18 planned Data Integrity & Concurrency Test Subcases](quality/MOD-001_Data_Integrity_Test_Matrix_v0.1_DRAFT.md)

The physical ERD, MySQL schema/migrations, source file structure and deployment layout are **deliberately not fixed**. Existing SRS/RTM/Excel/CSV remain unchanged. HR Core is on hold pending official requirements.

## P0.7 Technical Readiness Review (planning only)

- [DOC-016 System Core Technical Readiness v0.1 DRAFT](reviews/DOC-016_P0.7_System_Core_Technical_Readiness_v0.1_DRAFT.md)

This cross-references the 22 prior IAM technical defaults and open owner/IT decisions, recommending 16 engineering choices while retaining 12 unresolved review questions. **No review or release gate is passed.** Continue through a separate owner-involved physical ERD and source file structure workshop after the critical account/auth/security choices are accepted. HR Core remains HOLD.

## P0.8 Physical Design Workshop (draft options)

- [DOC-017 MySQL Physical Schema Proposal](data/DOC-017_MOD-001_Physical_Schema_Proposal_v0.1_DRAFT.md)
- [DOC-018 Laravel + Vue File Organization Alternatives](architecture/DOC-018_Laravel_Vue_Source_Organization_Options_v0.1_DRAFT.md)
- [DOC-019 Physical Design Decision Sheet](reviews/DOC-019_P0.8_Physical_Design_Decision_Sheet_v0.1_DRAFT.md)

**Purpose:** Discuss the layout/schema, not freeze it. No migrations, source code or HR Core implementation are authorized. All 10 P0.8 decision items still require review.

## P0.9 Implementation Blueprint (documentation only)

- [DOC-020 MOD-001 Codex Implementation Blueprint v0.1 (DRAFT)](implementation/DOC-020_MOD-001_Implementation_Blueprint_v0.1_DRAFT.md)
- [DOC-021 Cross-PR Reconciliation & Test Traceability (DRAFT)](reviews/DOC-021_MOD-001_PR_Reconciliation_and_Test_Trace_v0.1_DRAFT.md)

**Readiness:** 12 work packages prepared; 0 approved, no app code. PR #1 and PR #2 contain 5 overlapping documents that need coordinated review. 26 functional and 18 data-security scenario IDs map to 18 existing RTM test IDs (15 shared across both plans); no tests executed. Schema and physical file structure are still not approved. HR Core remains HOLD.
