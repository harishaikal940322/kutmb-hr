# Current Development Handoff

> **Checkpoint:** 4 November 2026 · **Starting `main` sync:** `c7f22426e6958a3ef12c5101056d207d46119cd8`
> **Status:** System Core baseline through WP-070D1; simplified owner-directed development workflow adopted. This records technical state, not organizational approval.

## Project identity and scope

- **Project:** KUTMB HR System · **Repository:** [harishaikal940322/kutmb-hr](https://github.com/harishaikal940322/kutmb-hr) · **Canonical checkout:** `/home/haris/www/kutmb-hr`
- **Development architecture:** Laravel 13, Vue 3, Inertia 3, TypeScript; PHP 8.5.12; MySQL 26.10.0; Docker development environment.
- **Current scope:** System Core, primarily MOD-001 Identity & Access. **HR Core MOD-004..011 remains ON HOLD** pending approved HR requirements.
- Requirements remain DRAFT. The stack family is selected, while organizational policies and production readiness remain unapproved.

## 2 November 2026 authentication planning addendum

The technical owner selected **planning for enforced MFA** and a **conditional 8-character minimum password length**. The transition design and required gates are recorded in [SEC-002 MFA/Password Policy Plan](../security/SEC-002_MFA_Password_Policy_Implementation_Plan_2026-11-02.md), [DOC-013](../decisions/DOC-013_MOD-001_IAM_Technical_Decisions_v0.1_DRAFT.md) and [SEC-001](../security/SEC-001_System_Core_Security_Baseline_v0.1_DRAFT.md). The old **15-character minimum remains applicable to any single-factor login**. No MFA or 8-character runtime change has been implemented or tested from this documentation decision. UM-DEC-011, normative SRS/RTM/Excel alignment, recovery/security approval and production release gates remain open. This addendum does not alter the historical 30 October verified implementation checkpoint recorded above.

## 4 November 2026 development resume

The owner replaced the former branch-per-task/planning-heavy method with the concise operating method in [AGENTS.md](../../AGENTS.md) and §11 of [DOC-020](DOC-020_MOD-001_Implementation_Blueprint_v0.1_DRAFT.md): one canonical checkout, ordinary work on `main`, focused planning/checks, preserve existing changes, update this handoff, commit and push verified work to `origin/main`. Historical work-package records remain context, not a reason to create another plan.

### Current feature status

| Area | Implemented state | Boundary / next dependency |
|---|---|---|
| Identity and account management | Username/password authentication, active-account enforcement, self display-name update, read-only profile and local/testing-only User Directory preview | Production account creation, activation/deactivation and first-admin bootstrap are not implemented; bootstrap/operator decisions remain open |
| Roles and permissions | RBAC schema and server-side authorization foundation; local directory preview checks `iam.users.view`; MySQL last-admin transaction foundation | No role-grant UI/API or approved organizational role/permission matrix; do not grant access by inference |
| Authentication and authorization | Fortify username login, session authentication, active status rechecked on protected requests, server authorization | Public signup and unsupported recovery remain disabled/gated; role-change/session revocation policy and privileged production access remain gated |
| Password policy | No 8-character policy is implemented. Local bootstrap enforces at least 15 characters. `Password::defaults()` currently applies a 12-character minimum plus composition rules in production when used | SEC-002's 8-character target is conditional on complete enforced MFA; UM-DEC-011 and policy/security approval remain open. Review the existing 12-character default against the documented single-factor 15-character direction before enabling any production password-setting flow |
| MFA | Not implemented; MFA routes are disabled and have regression coverage | Enrollment, challenge, recovery and rollout decisions/approval must precede implementation or any 8-character minimum |
| System Core dependencies | Browser visual/accessibility acceptance remains outstanding; release gates remain 0/8 | Resolve only the security/operator decisions needed for the next account-management slice; HR Core `MOD-004..011` stays HOLD |

### This resume's work and checks

- Synchronized the checkout to `origin/main` at `c7f2242` before edits. Existing local Docker, login/dashboard, and UI changes were preserved and left out of this documentation commit.
- Updated `AGENTS.md` and DOC-020 to adopt the simplified workflow and remove the old branch-per-task instruction from current operating guidance.
- Files changed: `AGENTS.md`, `docs/implementation/DOC-020_MOD-001_Implementation_Blueprint_v0.1_DRAFT.md`, and this handoff.
- No application code was changed: the immediate remaining account-management features depend on open first-admin, role, recovery and security decisions; implementing MFA or enabling eight-character passwords now would violate SEC-002's explicit conditions.
- Checks: `git diff --check` (run before commit); no application tests were needed for these documentation-only edits.

### Next development-ready scope

Complete browser visual and accessibility acceptance of the existing profile/User Directory UI when the current local UI work is ready for review. For feature implementation, the next coherent System Core slice is approved account lifecycle and role assignment, but it is **not ready** until the operator/role matrix and relevant security/recovery decisions are resolved. Do not start MFA or lower the effective single-factor password minimum as a substitute for those decisions.

## Completed implementation

These work packages are evidenced by merged PRs and commits on `main`. Their merges and CI are technical evidence only; they do not approve HR/IT policy.

| Work package | Delivered scope | Merged PR and main commit |
|---|---|---|
| WP-020 | Laravel/Vue/Inertia/TypeScript application, Docker and MySQL foundation | [PR #3](https://github.com/harishaikal940322/kutmb-hr/pull/3) · `c143994` |
| WP-030 | Disposable-MySQL test and GitHub Actions foundation | [PR #4](https://github.com/harishaikal940322/kutmb-hr/pull/4) · `29fd060` |
| WP-040 | PHP/MySQL runtime baseline, identity/RBAC schema, and MySQL integrity hardening | [PR #5](https://github.com/harishaikal940322/kutmb-hr/pull/5) · `85ec85a`; [PR #6](https://github.com/harishaikal940322/kutmb-hr/pull/6) · `37c7b19`; [PR #7](https://github.com/harishaikal940322/kutmb-hr/pull/7) · `d67bd05` |
| WP-050A | Username authentication and active-account enforcement | [PR #8](https://github.com/harishaikal940322/kutmb-hr/pull/8) · `8851a9f` |
| WP-050B1 | Opt-in local developer account bootstrap; creates no role grant | [PR #11](https://github.com/harishaikal940322/kutmb-hr/pull/11) · `0ed9576` |
| WP-060A/B | Server-side authorization foundation and Gate/Policy hardening | [PR #9](https://github.com/harishaikal940322/kutmb-hr/pull/9) · `7c152d7`; [PR #10](https://github.com/harishaikal940322/kutmb-hr/pull/10) · `e08488c` |
| WP-060C | Shared transaction-lock and last-admin concurrency foundation | [PR #15](https://github.com/harishaikal940322/kutmb-hr/pull/15) · `23f2b74` |
| WP-070A | Authenticated dashboard and read-only My Profile | [PR #12](https://github.com/harishaikal940322/kutmb-hr/pull/12) · `c53316b` |
| WP-070B | Self-service display-name update | [PR #13](https://github.com/harishaikal940322/kutmb-hr/pull/13) · `010e03e` |
| WP-070C | Read-only current-user role-name display | [PR #14](https://github.com/harishaikal940322/kutmb-hr/pull/14) · `862c783` |
| WP-070D1 | Local/testing-only, read-only User Directory preview | [PR #16](https://github.com/harishaikal940322/kutmb-hr/pull/16) · `143e3e1` |

## Latest qualification evidence

- PR #16 merged at `143e3e163fd8ab1339d1d94bfb9b5b05c99c33f5`.
- Local `./bin/dev check` reported **99 tests and 590 assertions passed** against disposable MySQL 26.10.0. Pint, PHPStan, frontend lint/format, TypeScript, frontend build, Composer audit and npm audit passed.
- [GitHub Actions run #28](https://github.com/harishaikal940322/kutmb-hr/actions/runs/41027515244) completed successfully, including its MySQL CI and dependency audit steps.
- Existing persistent MySQL volumes were preserved. The disposable test database was separate from them.
- These are automated/local qualification results. **Browser visual and accessibility acceptance has not been completed**; no browser surface was available for WP-070D1.

## Current feature and security boundaries

- User Directory is **local/testing only**, controlled by `IAM_LOCAL_USER_DIRECTORY_PREVIEW_ENABLED=false` by default. Production remains denied even if the flag is enabled.
- Access requires an active authenticated account and the candidate RBAC permission `iam.users.view`. No permission is automatically granted. This permission code is technical preview plumbing, not approved organizational policy.
- No production System Admin is seeded or created by default. No production first-admin bootstrap, role-grant workflow, MFA or recovery is implemented.
- No privileged account-management mutation endpoints were added. The directory exposes only display name, username and status; it does not expose email, credentials, permission graphs or Employee/HR Core joins.
- There is no Employee schema or HR Core feature. MOD-004..011 stays ON HOLD.
- WP-060C verifies the shared-lock transaction foundation with MySQL concurrent worker tests. **Future privileged mutation endpoints do not yet exist and are not yet integrated with that service.**

## Outstanding decisions and blockers

1. Browser visual and accessibility acceptance remains pending.
2. First System Admin operator, bootstrap authorization and emergency handling remain unapproved (`UM-DEC-003`).
3. Organizational role catalogue, permission matrix and grant boundaries remain unapproved (`UM-DEC-004`).
4. Recovery channel, MFA/recovery, session timeout and revocation policies remain unresolved (`UM-DEC-005`, `UM-DEC-006`, `UM-DEC-011`).
5. Audit access, retention and privacy approvals remain unresolved (`UM-DEC-012`, `Q-010`, `Q-011`).
6. Username/account-field decisions remain open (`UM-DEC-001`, `UM-DEC-002`); all `UM-DEC-001..012` remain **OPEN** in the MOD-001 review register.
7. DOC-019 `PD-001..PD-010` are selected for bounded development, not formally accepted by IT/HR/security. Related organizational and release approvals remain open.
8. PR #15 and PR #16 have no submitted GitHub review records; recent PR merges do not establish independent reviewer approval.
9. The release register remains **0 of 8 gates passed**. **Production readiness, UAT acceptance and go-live authorization have NOT been granted.**

## Proposed future sequence — not authorized

1. Review and resolve the minimum organizational decisions for first-admin bootstrap and role permissions.
2. Perform browser visual, accessibility and security acceptance when a suitable browser environment is available.
3. Implement controlled first System Admin bootstrap only after required approvals.
4. Implement approved User Management and Role Assignment workflows.
5. Connect every protected privileged mutation to reviewed authorization, transaction and audit services.
6. Perform integrated security and UAT verification.

This sequence is a planning proposal. It grants no implementation, policy or deployment authorization.

## HOW TO RESUME DEVELOPMENT

For a future ChatGPT/Codex session:

1. Read this checkpoint first.
2. Fetch and verify `origin/main` and the working tree in `/home/haris/www/kutmb-hr`.
3. Inspect commits and PRs newer than the baseline recorded here.
4. Treat GitHub source, merged PRs and CI evidence as the technical source of truth.
5. Review unresolved decisions and security gates before planning privileged work. Proposed policies are not approved policies.
6. Create the next bounded Codex work package only after reviewing the current repository state; do not repeat completed work.
7. Preserve existing requirement IDs, SRS, RTM, traceability records, and the System Core / HR Core scope boundary.

Do not infer HR/IT approval, independent review, production readiness or release authorization from a merge or green CI result.
