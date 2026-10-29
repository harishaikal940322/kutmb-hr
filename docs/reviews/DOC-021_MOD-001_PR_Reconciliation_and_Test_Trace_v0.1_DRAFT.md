# DOC-021 | Cross-PR Reconciliation & MOD-001 Test Coverage

> v0.1 DRAFT · 29 October 2026 · **DOCUMENT REVIEW ONLY**  
> Source branches: [PR #1 Functional Design](https://github.com/harishaikal940322/kutmb-hr/pull/1) and [PR #2 Architecture](https://github.com/harishaikal940322/kutmb-hr/pull/2). Both remain draft until separately reviewed.  
> **Doc content consolidated into PR #2 branch; no GitHub PR merge, no tests executed and no business approval inferred.**

## 1. Source-of-truth convergence

There were two draft streams based on `main`. **Both contain necessary, distinct documents**. DOC-011, 26 functional scenarios, RTM navigation and the five shared guidance files have now been consolidated into the PR #2 branch. No branch has been merged into `main`; all requirements and review gates remain DRAFT.

| Stream | Own documents | Special status |
|---|---|---|
| PR #1 | `DOC-011` functional design and 26 `SCN-UM-...` functional scenario proposals | **Copied into PR #2 review branch; not merged into main** |
| PR #2 | `DOC-008`, `SEC-001`, `DOC-013..020`, 18 `DI-TC-...` data scenarios, architecture/data/UI planning | **Now also contains DOC-011 and functional scenarios; not merged or approved** |
| Main branch | Normative `DOC-004..006` and v0.3 RTM Excel/CSV baseline | SRS wording and requirement IDs remain DRAFT |

### Shared edited files reconciled in consolidated branch (review pending)

Both PR branches currently edit exactly these five files:

| Shared file | Keep from PR #1 | Keep from PR #2 | Resolution rule |
|---|---|---|---|
| `AGENTS.md` | Read DOC-011 and functional test scenario guidance | Architecture/security/data and work-package boundaries | Preserve both guidance sets, remove contradictory versions |
| `README.md` | Links to functional design and functional scenarios | Links to architecture and DOC-013..020 planning | Preserve all valid links |
| `docs/CHANGELOG.md` | MOD-001 functional design entry | P0.4..P0.9 architecture/readiness entries | Append both; do not rewrite old historical entries |
| `docs/README.md` | Functional review navigation | Architecture, security, data, implementation planning navigation | Preserve both sections and accurate PR status |
| `docs/governance/DOC_REGISTER.md` | Register `DOC-011` | Register `DOC-008` and `DOC-012..020` | Union by document ID, with no duplicate/reserved ID overwrite |

Additionally, the PR #1 edits to `docs/requirements/RTM.md` and `MOD-001_USER_MANAGEMENT_REVIEW_v0.3.md` were copied into the consolidated PR #2 branch. They add review links only, retaining all 32 requirement/AC/base test IDs. **Workbook/CSV must be updated in the same reviewed change only when normative requirement or module lifecycle changes**, not for mere navigation links.

### Completed branch reconciliation and pending main review

1. **Completed:** Copied DOC-011 and 26 functional scenarios; preserved RTM and MOD-001 review links; reconciled the shared AGENTS, README, CHANGELOG, docs README and Document Register.
2. **Pending:** Technical reviewer compares functional and architecture assumptions (identity, credentials, role assignment, state/audit, recovery) and signs off any normative decision through its existing governance process.
3. **Pending:** Verify merged documentation diff and references, then decide whether to merge consolidated PR #2 to main. **Do not merge PR #1 separately after consolidation**, because its content is included in PR #2 and a second merge could reintroduce outdated shared-file changes.
4. **Not done:** Business/IT policy approval, acceptance tests, coding or production work. A GitHub merge alone does not grant any of those.

## 2. Test coverage consolidation

Existing SRS RTM contains **32 base requirement IDs and 32 corresponding planned T IDs**. The following focused reviews add **44 unique proposed scenario identifiers**: 26 functional scenarios (PR #1) and 18 integrity/security scenarios (PR #2). They map to **18 distinct existing base T IDs**, with **15 shared between both plans**. Shared T IDs mean the plans may exercise the same requirement; they are *not proof* of 44 independent executable tests nor evidence of duplicate completed work.

| Existing RTM base Test ID | Functional PR #1 scenario IDs | Data/Concurrency PR #2 scenario IDs | Review |
|---|---|---|---|
| `T-IAM-AUD-001` | `SCN-UM-021` | `DI-TC-013` | SHARED RTM ID; compare assertions and reuse fixtures |
| `T-IAM-FR-001` | `SCN-UM-001`, `SCN-UM-002` | `DI-TC-004` | SHARED RTM ID; compare assertions and reuse fixtures |
| `T-IAM-FR-002` | `SCN-UM-003` | `DI-TC-002`, `DI-TC-003` | SHARED RTM ID; compare assertions and reuse fixtures |
| `T-IAM-FR-003` | `SCN-UM-004`, `SCN-UM-005`, `SCN-UM-006` | `DI-TC-018` | SHARED RTM ID; compare assertions and reuse fixtures |
| `T-IAM-FR-004` | `SCN-UM-007`, `SCN-UM-008` | `DI-TC-015` | SHARED RTM ID; compare assertions and reuse fixtures |
| `T-IAM-FR-005` | `SCN-UM-009` | `DI-TC-008` | SHARED RTM ID; compare assertions and reuse fixtures |
| `T-IAM-FR-006` | `SCN-UM-010`, `SCN-UM-011` | `DI-TC-006` | SHARED RTM ID; compare assertions and reuse fixtures |
| `T-IAM-FR-007` | `SCN-UM-012` | `DI-TC-005` | SHARED RTM ID; compare assertions and reuse fixtures |
| `T-IAM-FR-008` | `SCN-UM-013`, `SCN-UM-014` | `DI-TC-007` | SHARED RTM ID; compare assertions and reuse fixtures |
| `T-IAM-FR-009` | `SCN-UM-015` | `DI-TC-009`, `DI-TC-010` | SHARED RTM ID; compare assertions and reuse fixtures |
| `T-IAM-FR-010` | `SCN-UM-016`, `SCN-UM-017` | `DI-TC-011`, `DI-TC-012` | SHARED RTM ID; compare assertions and reuse fixtures |
| `T-IAM-FR-011` | `SCN-UM-018` | None | FUNCTIONAL ONLY |
| `T-IAM-FR-012` | `SCN-UM-019`, `SCN-UM-020` | None | FUNCTIONAL ONLY |
| `T-SYS-AUD-001` | `SCN-UM-026` | `DI-TC-016` | SHARED RTM ID; compare assertions and reuse fixtures |
| `T-SYS-DR-001` | `SCN-UM-023` | `DI-TC-001` | SHARED RTM ID; compare assertions and reuse fixtures |
| `T-SYS-NFR-001` | `SCN-UM-024` | None | FUNCTIONAL ONLY |
| `T-SYS-SEC-001` | `SCN-UM-025` | `DI-TC-017` | SHARED RTM ID; compare assertions and reuse fixtures |
| `T-SYS-SEC-002` | `SCN-UM-022` | `DI-TC-014` | SHARED RTM ID; compare assertions and reuse fixtures |

**Execution-design instruction:** where functional and data scenarios cover the same base test ID, compare actual preconditions/assertions. Reuse synthetic fixture setup and runner jobs where helpful, but **retain distinct assertions** for transaction races, forbidden direct requests, session revocation and audit. Do not automatically delete a scenario simply because the parent `T-...` ID is shared.

## 3. Explicit overlap examples and how to handle them

| Topic | Functional / data scenarios | Expected consolidated testing |
|---|---|---|
| Unique username | `SCN-UM-003` + `DI-TC-002/003` | Basic validation plus separate actual-MySQL concurrent duplicate test |
| Last-admin protection | `SCN-UM-016/017` + `DI-TC-011/012` | One functional denial + distinct simultaneous two-admin transaction test |
| Unauthorized privilege | `SCN-UM-011/012/014/025` + `DI-TC-005/006/007/017` | Shared synthetic roles; separately assert server-side denial and no persisted mutation |
| Session revocation | `SCN-UM-015` + `DI-TC-009/010` | Account deactivation and role-revocation each tested against an old session |
| Redacted audit/props | `SCN-UM-021/022/026` + `DI-TC-013/014/016` | Shared redaction fixture; retain audit completeness, actor history and secret-leak cases |
| Recovery | `SCN-UM-007/008` + `DI-TC-015` | Remains **BLOCKED** until verified channel and credential policy approved |

## 4. Reconciliation acceptance checklist

- [ ] Reviewers compare DOC-011 functional proposals against DOC-008/013/014/015/016/017/018/019/020.
- [ ] Check usernames, user status, first-admin bootstrap, role assignment, recovery, MFA, audit and `User ≠ Employee` for contradictions.
- [x] Reconcile five shared files on the PR #2 review branch without discarding either PR's changes (GitHub review/merge remains pending).
- [x] Maintain one `DOC_REGISTER` row per ID, preserving `DOC-011` and `DOC-020/021` in the consolidated branch.
- [ ] Confirm all 26 + 18 proposed scenario IDs and all 18 mapped base test IDs remain traceable, with 0 execution claims.
- [ ] Maintain 11 module IDs, 32 original normative requirement/AC/test IDs, v0.3 Excel and CSV mirror integrity.
- [ ] Do not merge without review; do not mark requirement/IT approvals without a recorded actor, date, SHA and decision.
- [ ] Do not build HR Core or perform production operations.

**Current state: DOCUMENT CONTENT CONSOLIDATED IN PR #2, NOT REVIEW-APPROVED OR MERGED INTO MAIN; NO TESTS EXECUTED.**
