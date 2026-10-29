# DOC-002 | Document Authority / Control Register

> v0.1, 29 October 2026 • **DRAFT**. The statuses below are document statuses, NOT approvals of requirements.

| Document ID | Name | Type | Level | Version | Status | Coverage | Authoritative repo path or reservation |
|---|---|---|---|---|---|---|---|
| DOC-001 | Master Feature Matrix | Register | Level 1 | v0.1 | DRAFT | Semua | docs/requirements/FEATURE_MATRIX.md |
| DOC-002 | Document Authority / Control Register | Register | Level 0 | v0.1 | DRAFT | Semua | docs/governance/DOC_REGISTER.md |
| DOC-003 | Glossary | Reference | Level 0 | v0.1 | DRAFT | Semua | docs/governance/GLOSSARY.md |
| DOC-004 | System-Wide SRS | SRS | Level 2 | v0.1 | DRAFT | Cross-module | docs/srs/DOC-004_System_Wide_SRS.md |
| DOC-005 | Identity & Access SRS | Module SRS | Level 3 | v0.1 | DRAFT | F-001/002/003 | docs/srs/DOC-005_Identity_Access_SRS.md |
| DOC-006 | Configuration & Reference Data SRS | Module SRS | Level 3 | v0.1 | DRAFT | F-005/006 | docs/srs/DOC-006_Configuration_Reference_Data_SRS.md |
| DOC-007 | Employee & Organization SRS | Module SRS | Level 3 | TBD | NOT STARTED | F-009 | RESERVED |
| DOC-008 | System Core Solution Architecture | Architecture | Level 3 | v0.1 | DRAFT / REVIEW REQUIRED | MOD-001..003 | docs/architecture/DOC-008_System_Core_Solution_Architecture_v0.1_DRAFT.md |
| DOC-009 | ADR / Decision Register | Register | Level 2 | v0.1 | DRAFT | Semua | docs/decisions/ADR_REGISTER.md |
| DOC-010 | Roadmap & Gates | Plan | Level 2 | v0.1 | DRAFT | Semua | docs/quality/RELEASE_GATES.md |
| DOC-011 | MOD-001 User Management Functional Design | Functional Design | Level 3 | v0.1 | DRAFT / REVIEW REQUIRED | MOD-001 | docs/design/DOC-011_MOD-001_Functional_Design_v0.1_DRAFT.md |
| DOC-012 | Vue Onboarding for MOD-001 | Learning Plan | Level 3 | v0.1 | DRAFT / REVIEW REQUIRED | MOD-001 UI | docs/learning/DOC-012_Vue_Onboarding_MOD-001_v0.1_DRAFT.md |
| DOC-013 | MOD-001 IAM Technical Decision Baseline | Technical Decisions | Level 3 | v0.1 | DRAFT / REVIEW REQUIRED | MOD-001/003 | docs/decisions/DOC-013_MOD-001_IAM_Technical_Decisions_v0.1_DRAFT.md |
| DOC-014 | MOD-001 Auth & Inertia Contracts | Interface / Security Design | Level 3 | v0.1 | DRAFT / REVIEW REQUIRED | MOD-001 | docs/design/DOC-014_MOD-001_Authentication_Authorization_Contracts_v0.1_DRAFT.md |
| DOC-015 | MOD-001 System Core Logical Data Model | Logical Data Design | Level 3 | v0.1 | DRAFT / REVIEW REQUIRED | MOD-001/003 | docs/data/DOC-015_MOD-001_Logical_Data_Model_v0.1_DRAFT.md |
| DOC-016 | P0.7 System Core Technical Readiness & Design Review | Technical Review | Level 3 | v0.1 | DRAFT / REVIEW REQUIRED | MOD-001/003 | docs/reviews/DOC-016_P0.7_System_Core_Technical_Readiness_v0.1_DRAFT.md |
| DOC-017 | MOD-001 Proposed Physical MySQL Schema | Physical Data Design | Level 3 | v0.1 | DRAFT / REVIEW REQUIRED | MOD-001/003 | docs/data/DOC-017_MOD-001_Physical_Schema_Proposal_v0.1_DRAFT.md |
| DOC-018 | Laravel Vue Source Organization Options | Source Architecture | Level 3 | v0.1 | DRAFT / REVIEW REQUIRED | System Core | docs/architecture/DOC-018_Laravel_Vue_Source_Organization_Options_v0.1_DRAFT.md |
| DOC-019 | P0.8 Physical Design Decision Sheet | Technical Review | Level 3 | v0.1 | DRAFT / REVIEW REQUIRED | MOD-001/003 | docs/reviews/DOC-019_P0.8_Physical_Design_Decision_Sheet_v0.1_DRAFT.md |
| DOC-020 | MOD-001 Implementation Blueprint & Codex Work Packages | Implementation Plan | Level 3 | v0.1 | DRAFT / REVIEW REQUIRED | MOD-001/003 | docs/implementation/DOC-020_MOD-001_Implementation_Blueprint_v0.1_DRAFT.md |
| DOC-021 | Cross-PR Reconciliation & MOD-001 Test Coverage | Traceability Review | Level 3 | v0.1 | DRAFT / REVIEW REQUIRED | MOD-001/003 | docs/reviews/DOC-021_MOD-001_PR_Reconciliation_and_Test_Trace_v0.1_DRAFT.md |
| DOC-022 | Documentation Exit & WP-020 Codex Bootstrap Handoff | Implementation Handoff | Level 3 | v0.1 | DRAFT / CODE AUTHORIZATION REQUIRED | MOD-001/003 | docs/implementation/DOC-022_P1.1_First_Codex_Handoff_v0.1_DRAFT.md |

## Control rule

Repository Markdown tracks future approved edits. Prior generated DOCX/XLSX v0.1 files were drafting inputs and are not authoritative over newer commits. Before promotion, capture reviewer identity, review date, change reference, approval record and a tested RTM snapshot.

**Supplementary proposal documents:** `SEC-001` security baseline and `ADR-006` are DRAFT in this review branch. **DOC-011 Functional Design** is under separate Draft PR #1 and must not be marked merged or approved here.

**Data integrity verification:** `docs/quality/MOD-001_Data_Integrity_Test_Matrix_v0.1_DRAFT.md` contains 18 proposed subcases using existing RTM test IDs, with 0 executed. No physical ERD/migration or file structure is approved.

**Functional test scenario coverage:** `docs/quality/MOD-001_Functional_Test_Scenarios_v0.1_DRAFT.md` includes 26 PLANNED subcases; none executed. These are distinct from 18 data-integrity subcases and share some parent RTM test IDs.

**Documentation integration status:** DOC-011 and 26 functional scenarios from PR #1 have been consolidated on the PR #2 review branch; RTM navigation and five shared guidance/register files reconciled. No PR merge, business approval or test execution is implied. See DOC-021 and DOC-022.
