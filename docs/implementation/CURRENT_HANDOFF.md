# Current Development Handoff

## 5 November 2026 — Spatie RBAC administration foundation

- Adopted `spatie/laravel-permission` 8.3.2 using the existing `Role`, `Permission`, and `User` models. Existing role and permission codes are preserved as package permission/role names; existing user-role and role-permission rows are copied into package pivots. Legacy pivot tables are retained for rollback compatibility. Menu access mappings use registered code-owned menu keys.
- Added protected System Admin screens for role creation/editing, the permission catalog, and menu-to-permission mapping. Backend routes require `iam.rbac.manage`; menu visibility is a server-computed presentation hint. The access-control permission is system-only, the System Admin role is immutable, and assigned roles cannot be deleted. Changes are audited.
- The sidebar groups Roles, Permissions, and Menu Access under an expandable **System Administration** parent. It opens when a child screen is active; other authorized navigation remains at the workspace level.
- The local/testing first-System-Admin bootstrap seeds only the access-control grant needed to operate these screens. Role assignment to normal users, invitations, public registration, production bootstrap, account lifecycle administration, and HR Core remain out of scope. The permission catalog and menu labels/routes remain code-owned; the UI maps registered menus to catalog permissions.
- Permission relations are cleared from the authenticated model before each protected request to ensure changes are effective on the next request, including long-lived workers. Existing active-account, target-policy, and last-admin protections remain in place.
- Checks completed with `./bin/dev mysql-test`: dependency install/audit (npm audit 0 vulnerabilities), asset build, migrations up/down/up on disposable MySQL 26.10.0, Pint (84 files), PHPStan (no errors), frontend format/lint/type checks, and **118 tests / 858 assertions passed**. `git diff --check` passed. The UI was source-reviewed and compiled; no live browser visual acceptance was performed in this pass.
- Technical implementation does not approve organizational role policy, production access, or account-invitation requirements. Open decisions and release gates remain open; MFA enforcement and password rules were not changed.
- Applied both additive RBAC migrations to the current development database with `./bin/dev migrate`; the migration command completed successfully. No destructive database operation was run. Next: commit and push the verified implementation on `main`. Preserve unrelated local Dashboard, My Profile, User Directory, and `docker/` edits.

## 12 November 2026 — MFA enforcement environment toggle

- The `MFA` setting now controls the complete MFA feature. Local development defaults to `MFA=no`, which disables TOTP enrollment routes and login challenges without deleting stored factors. `APP_ENV=production` or `APP_ENV=prod` defaults to `MFA=yes` and configuration fails closed if MFA is explicitly set to `no`.
- For the owner's development workflow, only `APP_ENV=local` with `MFA=no` uses an 8-character password minimum for password-only sign-in and local bootstrap. Testing and production password-only paths retain the 15-character minimum; production cannot start with MFA disabled.
- `IAM_MFA_ENFORCED` remains a fallback only when `MFA` is absent. `.env.example` is a development template; production must set `MFA=yes` in the deployment environment. This technical switch does not close organizational approval, recovery or release gates in SEC-002.
- Checks: `git diff --check` passed. PHP lint and feature tests could not run because the host has no `php` executable and Docker access to `/var/run/docker.sock` is denied. Frontend build could not run because `vp` is not installed in the workspace; `vue-tsc` is also unavailable. Fetching `origin` was attempted before edits but the environment denied writing `.git/FETCH_HEAD`; the checked-out `main` and `origin/main` both reported `3577ac8` beforehand.

## 12 November 2026 — Light/dark appearance toggle

- Light mode is now the default regardless of operating-system preference. Existing saved `dark` preferences continue to apply; older `system` values normalize to light. The shared user menu exposes a keyboard-operable light/dark toggle, available in sidebar and header layouts, including the responsive mobile sidebar menu. The choice persists in browser local storage and the appearance cookie.
- Removed the obsolete system appearance option from the shared appearance selector and aligned the server-rendered default with light mode. Existing CSS theme tokens and component-level light/dark styles are reused.
- Checks: `git diff --check` passed. The frontend build and type check were not run because the workspace lacks the project build executable and `vue-tsc`. Browser/device visual acceptance remains pending; no authenticated browser session is available in this workspace.

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
| Identity and account management | Username/password authentication, active-account enforcement, self display-name update, read-only profile and local/testing-only User Directory preview; one-time local/testing first-System-Admin registration | Production account creation, activation/deactivation and bootstrap remain unavailable; bootstrap/operator decisions remain open |
| Roles and permissions | RBAC schema and server-side authorization foundation; local directory preview checks `iam.users.view`; MySQL last-admin transaction foundation | No role-grant UI/API or approved organizational role/permission matrix; do not grant access by inference |
| Authentication and authorization | Fortify username login, session authentication, active status rechecked on protected requests, server authorization | Public signup and unsupported recovery remain disabled/gated; role-change/session revocation policy and privileged production access remain gated |
| Password policy | Local/testing-only first-System-Admin bootstrap accepts 8+ characters only with a TOTP proof; application password defaults and CLI bootstrap retain a 15-character minimum | 8-character use is only a technical local bootstrap path. UM-DEC-011, security/policy approval and production rollout remain open |
| MFA | TOTP enrollment, Fortify login challenge, hashed single-use recovery codes, throttling, and mandatory-route gate are implemented; global enforcement remains off by default | Production enforcement requires approved recovery and rollout policies, review, and release gates; no weak fallback or public bootstrap is enabled |
| System Core dependencies | Browser visual/accessibility acceptance remains outstanding; release gates remain 0/8 | Resolve only the security/operator decisions needed for the next account-management slice; HR Core `MOD-004..011` stays HOLD |

### This resume's work and checks

- Synchronized the checkout to `origin/main` at `c7f2242` before edits. Existing local Docker, login/dashboard, and UI changes were preserved and left out of this documentation commit.
- Updated `AGENTS.md` and DOC-020 to adopt the simplified workflow and remove the old branch-per-task instruction from current operating guidance.
- Files changed: `AGENTS.md`, `docs/implementation/DOC-020_MOD-001_Implementation_Blueprint_v0.1_DRAFT.md`, and this handoff.
- No application code was changed: the immediate remaining account-management features depend on open first-admin, role, recovery and security decisions; implementing MFA or enabling eight-character passwords now would violate SEC-002's explicit conditions.
- Checks: `git diff --check` (run before commit); no application tests were needed for these documentation-only edits.

### Next development-ready scope

Login UI browser review is complete. Profile/User Directory visual review still needs an authorized local account and preview permission. Account lifecycle and role assignment are **not ready** until the operator/role matrix and related security requirements are resolved. Continue into requirements review for those open decisions; keep the 15-character floor on every password-only path.

### Local first-System-Admin registration — 4 November 2026

- Added `/register` as a one-time local/testing bootstrap page. It is available only when `IAM_LOCAL_ACCOUNT_BOOTSTRAP_ENABLED=true` and the users table is empty; it remains unavailable in production. It serializes first-account creation with the shared `iam_guard` lock, creates an active account, attaches the `system-admin` role, and records an audit event. It does not auto-login or grant any role permissions.
- The owner selected System Admin for this local bootstrap. This does not close `UM-DEC-003/004` or establish an approved production bootstrap, role catalogue, or permission matrix. No account was created during implementation; the local dev database was empty when checked. The local private `.env` flag is enabled for browser review and is not tracked.
- The shared Docker proxy had an `app` DNS name collision with SDH Business Portal. KUTMB now advertises `kutmb-hr-app` on `docker_proxy`, and the external Nginx vhost routes to that unique alias. No external Nginx file is tracked in this repository.
- Files changed for this scope: `.env.example`, `README.md`, `docker-compose.dev.yml`, `phpunit.xml`, `routes/web.php`, `app/Http/Controllers/LocalSystemAdminBootstrapController.php`, `resources/js/pages/auth/Register.vue`, `tests/Feature/Auth/LocalSystemAdminBootstrapTest.php`, and this handoff. Existing Dashboard, MyProfile, User Directory and `docker/` local changes remain untouched and unstaged.
- Checks: focused disposable-MySQL feature tests passed (4 tests, 38 assertions); PHP lint, targeted Pint, PHPStan (512 MB), frontend type check/build, Compose validation, `nginx -t`, and HTTP `/register` smoke check passed. No persistent database was modified by the tests. Browser page displayed the registration form; no form was submitted.

### Browser review follow-up — 4 November 2026

- Started the existing local stack with `./bin/dev up`; it reported **Nothing to migrate**. The existing MySQL volume was preserved. The script removed the stopped orphan `kutmb-hr-frontend-1` container while reconciling services.
- Initial login page visual check found that the password visibility control did not change input type/label; a later browser test identified Vite CORS preventing client hydration and this was corrected below.
- `/users` now redirects to `/login` through `http://kutmb-hr.localhost:8080`. Protected profile and directory screens remain unreviewed: no local account was created, no credentials were changed, and no preview permission was granted. Local bootstrap remains opt-in and granting a preview permission requires authorization.
- Profile and directory content remain unreviewed: no local account was created, no credentials were changed, and no preview permission was granted. Local bootstrap is not enabled; an authorized local account/permission is required for full authenticated UI review.
- No application tests were run. Browser visual/accessibility acceptance remains **INCOMPLETE** pending an interactive client session and authorized access to the protected views.

### Shared Docker development setup — 4 November 2026

- Reworked `docker-compose.dev.yml` and `bin/dev` to run only the KUTMB app, attaching it to the standalone `docker_proxy` and `docker_data` networks. App DB and Redis hosts are `mysql` and `redis`; browser URL is `http://kutmb-hr.localhost:8080` through the standalone Nginx container.
- Added `/home/haris/docker/nginx/conf.d/kutmb-hr.conf` for the KUTMB route and recreated only the Nginx container so its configured bind mounts load. Shared MySQL and Redis stayed running. `./bin/dev up` created/used the dedicated `kutmb_hr_dev` schema and initially provisioned a project-specific MySQL account; the account was later removed per the owner’s clarification below. It removed the former project-local app/Nginx/MySQL containers but did not remove their persistent volume. The KUTMB app uses the unique `kutmb-hr-app` proxy alias to avoid collisions with other projects.
- Checks: `bash -n bin/dev`; Compose config validation; `nginx -t`; `/login` returned HTTP 200 through port 8080; app DNS resolution for MySQL and Redis succeeded. No application test suite was run.
- Files changed in the repository for this setup: `.env.example`, `README.md`, `bin/dev`, `docker-compose.dev.yml` (replacing the old all-in-one development Compose file), and this handoff. Local `.env` and Nginx files under `/home/haris/docker/nginx/conf.d/` are not tracked.
- Next: continue the approved System Core scope after the browser UI review follow-up; HR Core remains on hold and the account-management/security decisions listed above remain unresolved.

### Shared MySQL account clarification — 4 November 2026

- The owner requested one shared local MySQL account for projects. KUTMB now uses the existing shared `dev` account, while retaining the isolated `kutmb_hr_dev` schema. `bin/dev` creates the schema through the configured shared account and never creates or changes MySQL users; `.env.example` and the local private `.env` use `DB_USERNAME=dev`.
- Removed the previously created `kutmb_hr_dev` MySQL account after switching the app to `dev`; the `kutmb_hr_dev` schema and its data were retained. No application data was dropped.
- Verification: `./bin/dev setup` completed with “Nothing to migrate”; `php artisan migrate:status` showed all seven migrations applied under the shared account. No application test suite was run.

### Login UI and Vite integration review — 4 November 2026

- Fixed the Vite development CORS origin to match `APP_URL`, allowing Vue/Inertia to mount when the app is served through shared Nginx at `kutmb-hr.localhost:8080`.
- Made the password visibility control keyboard reachable and removed positive `tabindex` overrides so the natural login order is username → password → show/hide → submit. Mouse and keyboard activation both changed the field type and accessible button label.
- Browser review at 1280×720 and 368×886: login content fits with no horizontal overflow; username/password labels and button names are available in the accessibility tree. Empty submit triggered required-field validation. `/login` returned HTTP 200; unauthenticated `/users` redirected to `/login`.
- Checks: Vite CORS response matched the app origin; `npm run types:check` passed; `npm run build` passed; `git diff --check` passed. No authentication credentials were submitted and no full app test suite was run.
- Still pending: authenticated profile and User Directory visual review. That requires an authorized local account and preview permission. Account lifecycle and role assignment remain blocked on open HR/IT requirements and security decisions; HR Core stays on hold.

### MFA and local System Admin password path — 5 November 2026

- Implemented TOTP enrollment, login challenge, encrypted factor secret, server-side enforced-route middleware, challenge throttling, audit events, hashed one-time recovery codes, and blocking of Fortify's unreviewed factor-management endpoints. TOTP time steps are stored and consumed under a database row lock to reject replay, including the code used during enrollment/bootstrap.
- Authenticator setup and local first-admin creation now show QR codes from private, no-store SVG responses scoped to the signed-in account or pending bootstrap session. The TOTP seed is not included in Inertia props or shown as manual text; the user must still prove setup by entering a current six-digit code.
- The local/testing-only first-System-Admin bootstrap now accepts passwords from 8 characters only after verifying the authenticator code before account creation. The new account must complete a TOTP challenge to log in. `Password::defaults()` and CLI bootstrap remain at 15 characters; no 8-character password-only path was introduced.
- `IAM_MFA_ENFORCED` defaults to `false`; a gated code path forces unenrolled accounts into enrollment when enabled. Production remains blocked for local bootstrap, and this technical delivery does not resolve UM-DEC-011 or approve production enforcement. Recovery process, rollout, security/organizational approval and release gates remain outstanding.
- Files changed for this slice: MFA controller/request/middleware/service/model, migration, Fortify/provider/config/routes, local bootstrap, Vue auth pages, MFA/bootstrap/disabled-route tests, the security plan, dependency lockfile, and this handoff. Existing Dashboard, MyProfile, User Directory, and `docker/` local changes were preserved.
- Verification: after updating the affected Symfony packages to patched releases, the latest `./bin/dev mysql-test` passed against disposable MySQL 26.10.0 with 110 tests and 776 assertions; migration up/rollback/up, Pint, PHPStan, frontend lint/format, TypeScript, and production asset build passed. Composer audit reported no advisories; npm audit reported 0 vulnerabilities. No persistent development database was used for suite tests.
- QR verification: enrollment and bootstrap page props omit the raw seed; both QR endpoints return private no-store SVG only during pending setup, and become unavailable after enrollment/account creation. The flow still verifies a live TOTP before MFA activation or first-admin account creation.
- Follow-up: complete browser/accessibility review of MFA enrollment, challenge and recovery screens; obtain required IT/security decisions; keep production enforcement disabled until those gates are recorded. Do not change the normative SRS/RTM/XLSX while requirements remain unapproved.

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
- No production System Admin is seeded or created by default. The local bootstrap creates a role membership only and grants no permissions. No production first-admin bootstrap, role-grant workflow, MFA or recovery is implemented.
- No privileged account-management mutation endpoints were added. The directory exposes only display name, username and status; it does not expose email, credentials, permission graphs or Employee/HR Core joins.
- There is no Employee schema or HR Core feature. MOD-004..011 stays ON HOLD.
- WP-060C verifies the shared-lock transaction foundation with MySQL concurrent worker tests. **Future privileged mutation endpoints do not yet exist and are not yet integrated with that service.**

## Outstanding decisions and blockers

1. Browser visual and accessibility acceptance remains pending.
2. Production first System Admin operator, bootstrap authorization and emergency handling remain unapproved (`UM-DEC-003`); the local-only bootstrap does not resolve these decisions.
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
3. Implement controlled production first System Admin bootstrap only after required approvals.
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
