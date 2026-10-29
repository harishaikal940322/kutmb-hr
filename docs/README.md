# KUTMB HR documentation governance

**Version:** v0.1 • **Status:** DRAFT / NOT APPROVED • **Date:** 29 October 2026

This Git repository is the system's source of truth. **Human-readable Markdown in this repo is authoritative for ongoing changes**. DOCX and XLSX v0.1 drafts were the initial inputs; they are not uploaded here and should not be treated as newer than subsequent repository commits.

## Document hierarchy

- **Level 0**: [Document authority register](governance/DOC_REGISTER.md), [glossary](governance/GLOSSARY.md)
- **Level 1**: [Feature Matrix](requirements/FEATURE_MATRIX.md)
- **Level 2**: [System SRS](srs/DOC-004_System_Wide_SRS.md), [RTM](requirements/RTM.md), [Decision Register](decisions/ADR_REGISTER.md), [Release Gates](quality/RELEASE_GATES.md)
- **Level 3**: [Identity & Access SRS](srs/DOC-005_Identity_Access_SRS.md), [Configuration SRS](srs/DOC-006_Configuration_Reference_Data_SRS.md)
- **Intake**: [Open Questions](requirements/OPEN_QUESTIONS.md)
- **Reserved**: DOC-007 Employee & Organization SRS; DOC-008 Solution Architecture

## Change control

1. Record the request or issue, identify affected feature IDs and requirement IDs.
2. Check open questions and decisions before selecting an architecture or implementing scope.
3. Update SRS, Feature Matrix and RTM together; preserve referenced IDs (deprecate, never silently reuse).
4. Mark proposed decisions as `PROPOSED`/`ASSUMPTION` until HR/IT actually approve them. Create an ADR for material architecture choices.
5. Review via PR; capture reviewer/date/reason and link evidence. Approval is not implied by merge to `main`.
6. Execute tests only when implementation exists; attach evidence and evaluate gates before claiming DEV READY/RELEASE READY.
7. No secrets or personal employee data in public repository, examples, test fixtures or CI logs.

## Authority and statuses

`DRAFT` → `IN REVIEW` → `APPROVED` (only with recorded authorization) → `SUPERSEDED`/`DEPRECATED`. Open questions use `OPEN`/`BLOCKED` until resolved with linked evidence; release gates use `NOT PASSED` until passed.

**Important:** An unconfirmed requirement is allowed to appear in the baseline for planning, but must never be represented as an approved HR policy.
