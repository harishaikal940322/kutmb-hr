Warning: truncated output (original token count: 28269)
Total output lines: 590

## 25 November 2026 — CT-P03 calendar impact and employee history guard

- Replaced the `leave_requests` table-name checks with explicit immutable history markers and per-date calendar/policy snapshots. Calendar confirmation and schedule simulation now fail closed when a draft period overlaps any recorded leave-date snapshot; account reassociation checks the employee history marker under the employee row lock. Existing calendar/schedule confirmation guard rows continue to serialize their respective mutations. The snapshot writer is not yet connected to a submission flow; P04/P05 remain integrated and unexposed.
- Applied additive migration `2026_11_25_000025_create_leave_request_impact_guards.php` only after verifying `kutmb_hr_dev` (MySQL 8.4.13), confirming the existing account/ledger/operation/reservation tables had no rows, and reviewing `migrate --pretend`. New marker and snapshot tables contain zero rows. No calendar policy is inferred: CT-DEC-010 still lacks HR direction for already-submitted/approved/taken/cancelled requests, so overlapping calendar/schedule changes are rejected without recalculation.
- Focused rollback-isolated checks passed: 3 tests, 65 assertions for calendar impact, schedule impact and account-link history. Pint, targeted PHPStan for the three controllers, and `git diff --check` passed. A first PHPUnit launch used the repository's isolated test default and failed authentication before assertions; rerunning with `--no-configuration` against the verified development connection passed using `DatabaseTransactions`. No synthetic rows remained after the run.
- Next: finish the same-year internal reservation/ledger contract on top of this guard, including snapshot creation under both guard locks and employee lock, operation idempotency, aggregate reversal limits and concurrency ordering. No request route or partial submission UI is present. An earlier transaction migration attempt left only empty operation/reservation schema objects and a non-null ledger operation reference; migration 26 is being made safely resumable and remains unapplied/unrecorded.

## 25 November 2026 — post-P03 targeted blocker reconciliation

- Remote main verified at 376e2c25; latest local report/record: 23 focused tests, 334 assertions, three empty ledger tables. No CT-P03–09 completion claimed. Reopened current 56-issue DOCX (f396cdd53480caff34cb0fe0225c44729ad31ab0) and recorded section 8B of existing technical plan: separate already documented AL/Tabung/pending/posting/cap/prorata rules from actual unresolved service-band edges, mappings, partial-month/tie rules, Dec 25 boundary, post-close basis and formal authority.
- Local Codex should reassess safe unexposed same-year synthetic transaction/invariant work with explicit fixture entitlement amounts, resolve technical impact/snapshot/history design without weakening current guards, and keep P04/P05 integrated. Only implement if prerequisites can actually be satisfied; no request storage before guard replacement/review, no unapproved actual rates, operational posting/route, shared grants or production activation. Do not substitute speculative policy for HR decisions. If still blocked, consolidate exact residual questions; continue independent work where safe.
- Remote documentation only. No new runtime verification, schema or implementation changes; no formal HR sign-off or release.

## 25 November 2026 — CT-P02/P03 regression and dependency gate

- Ran all `tests/Feature/Leave` cases plus the two leave arithmetic/calendar unit files on the existing local database with rollback-isolated feature tests: **23 tests, 334 assertions passed**. Afterwards, `kutmb_hr_dev` still had zero rows in all three ledger tables; all three append-only/reversal triggers were present. This is focused P02/P03 evidence, not the full suite or P09 acceptance.
- CT-P03 operational posting remains deferred: CT-DEC-005 service-band edges/category mapping and actual rates, CT-DEC-003 rounding/partial-month basis, CT-DEC-004 Tabung limit/sign-off, CT-DEC-018/020 negative balance and reservation semantics, CT-DEC-034–039 year/source allocation and carry-forward reconciliation, and CT-DEC-040 adjustment authority/evidence remain HR-governed. Structural ledger and exact explicit-input projection do not resolve them.
- CT-P04 submission depends on P03 reservations and P05's complete transaction-safe workflow; P06–08 also depend on unresolved cancellation, authority, year-close and prorata contracts. Before adding any request storage (regardless of table name), replace the calendar/schedule confirmation checks for `leave_requests` with the documented schema-independent impact/snapshot contract and tests. A third check in `EmployeeAccountLinkController` currently fails closed on that table name to prevent reassociation without leave-history review; keep account relinking fail closed or make it explicitly history-aware before request storage. No safe independent P04–09 application feature is ready on the current source; keep these operations deferred, do not create request tables or activate workflows. Resume after the required HR decision/authority inputs, then continue dependency order. P09 integrated acceptance and UAT remain incomplete.

## 25 November 2026 — CT-P03 exact posted-ledger totals reader

- Added an unexposed transactional reader for explicitly selected annual and continuous Tabung accounts belonging to the same employee. It locks Annual then Tabung account rows, sums signed integer units as exact MySQL text values and rejects totals outside PHP's integer range. This reports posted ledger totals only; pending reservations, entitlements and available balances are not inferred. Any future appender must take the same account locks in the same order; no ledger writer or route was added.
- Focused schema and reader checks passed together (4 tests, 18 assertions), including separate-bucket totals, cross-employee rejection and overflow rejection. Targeted PHPStan passed for the reader, and Pint passed for the reader and its tests. All synthetic fixture rows rolled back.
- Next: ledger posting/availability and CT-P04–09 request operations still depend on unimplemented reservation/workflow contracts and unresolved rate, source-year and authority decisions; continue independent safe foundations and verification only.

## 25 November 2026 — CT-P03 reversal account/sign integrity

- Added a MySQL insert trigger requiring each compensating ledger entry to reference an existing original entry in the same AL or Tabung account and use the opposite nonzero sign. It permits partial reversals; it does not cap cumulative reversal units or define source-year semantics. Applied additive migration 2026_11_25_000024 after verifying the existing `kutmb_hr_dev` target was empty for ledger data and reviewing its SQL preview.
- Rollback-isolated synthetic MySQL test passed (1 test, 14 assertions) for a valid partial reversal, wrong bucket, same sign, account separation and prior ledger constraints. Pint passed for all three ledger migrations and the focused test. Test fixtures rolled back; no account or ledger rows were retained.
- Next: treat CT-P03 posting as blocked on policy mapping, source-year/carry-forward provenance, concurrency, aggregate reversal limits and HR authority. No request/workflow slice can safely depend on this foundation yet.

## 25 November 2026 — CT-P03 empty account and append-only ledger foundation

- Added empty per-employee/year Annual Leave accounts, one continuous Tabung account per employee and a signed integer half-day ledger with actor/reversal references, per-entry unique operation key and optional source-year provenance. MySQL enforces exactly one account bucket per entry; database triggers reject ledger updates and deletes. Applied only additive migrations 2026_11_24_000022 and 2026_11_25_000023 to the verified existing `kutmb_hr_dev` MySQL 8.4.13 target after reviewing the second migration's SQL preview. All three tables are InnoDB and contain zero rows.
- Focused rollback-isolated synthetic MySQL test passed (1 test, 11 assertions), including duplicate account/key rejection, the one-bucket check, restricted account deletion and append-only update/delete guards. Pint passed for both migrations and the test. No real employee, leave balance, permission grant or operational route was created; migration rollback was not run.
- This is structural groundwork only, not completion of CT-P03. It does not implement account provisioning, entitlements, reservations, balance posting, adjustments, audit integration or authorization. A unique key currently identifies one ledger entry, not a multi-entry operation; source-year, reversal-to-original-account and concurrent Tabung nonnegative/provenance rules are not enforced. Source-year remains nullable; no allocation policy or HR values are inferred. The append-only triggers target MySQL, matching the selected database family; cross-database enforcement is unverified.
- Next: keep posting, reservations and adjustments gated until entitlement mapping, source-year/carry-forward, concurrency and HR authority contracts are resolved. CT-P04–09 workflow slices remain dependent on the missing operational contracts; continue only independent authorized verification/foundation work, without exposing an incomplete leave workflow.

## 24 November 2026 — CT-P03 independent projection arithmetic

- Added an unexposed pure integer half-day projection for explicitly supplied annual/Tabung balances and already allocated pending holds. It deducts those holds once, applies a hypothetical request to available AL then Tabung, places any deficit in AL, rejects negative/inconsistent Tabung holds and guards integer overflow. It does not derive entitlements, allocate or persist holds, authorize users, select years or post ledger entries; those operations remain gated on CT-P01/02 and unresolved HR rules.
- Unit checks passed (5 tests, 14 assertions), covering the documented 2 AL + 3 Tabung − 8 = −3 AL/0 Tabung example, pending once, existing deficit, invalid holds and underflow. Targeted Pint/PHPStan and `git diff --check` passed. No database, route, grant or HR policy change.
- Next: independently review the account/ledger integrity design; do not expose posting or request flows until policy, source-year provenance, concurrency and authority prerequisites are satisfied.

## 24 November 2026 — CT-P02 session simulation page

- Added a read-only local/testing-only single-date AM/PM simulation page requiring both calendar-manage and policy-manage permissions. It uses only technically simulated versions, reports missing/inconsistent versions without calculating, and produces integer half-day units for display. It creates no request, balance, reservation, audit mutation or approval. Calendar page links to it only when the policy permission is also present; server checks both permissions independently.
- Focused rollback-isolated MySQL test passed (1 test, 39 assertions) for both permission gates, missing versions, synthetic result, duplicate session validation and simulated production denial. Targeted Pint/PHPStan, frontend format/lint, type check, build and `git diff --check` passed. Initial test attempt failed because the new Vite page had not built; final build and test passed. No authenticated browser submission or real HR data.
- Next: review outstanding CT-P02 confirmation/impact and P03 dependencies; simulation is not HR policy, entitlement calculation or release approval.

## 24 November 2026 — CT-P02 separate working-schedule simulation

- Added local/testing-only schedule simulation confirmation, using separate `schedule_simulated_at/by` fields rather than whole-policy approval. It requires authorized draft management, explicit synthetic-schedule acknowledgement, complete valid weekdays/times, finite effective dates and no overlapping simulated/confirmed or inconsistent version. A dedicated InnoDB guard serializes confirmation; simulated versions are locked against further changes. Entitlement rules and formal policy remain unconfirmed and unusable. The unexposed technical lookup now selects only these schedule-simulation markers, never whole-policy confirmation.
- As with synthetic calendar confirmation, simulation confirmation is blocked when `leave_requests` exists. Before any request storage, replace both table-name checks with a reviewed schema-independent impact/snapshot gate and retest; technical simulation is not HR sign-off or operational authority. No real policy, schedule or leave data was persisted.
- Applied additive simulation markers/guard after local target and SQL preview. Focused rollback-isolated MySQL tests passed (3 tests, 40 assertions), including missing settings, acknowledgment, nonoverlap, version locking, simulation with a synthetic calendar, no whole-policy confirmation and request-storage guard. Targeted Pint/PHPStan, frontend format/lint, Vue type check, build and `git diff --check` passed. Browser and true concurrent confirmation tests remain outstanding.
- Next: assess remaining CT-P02 scope and CT-P03 prerequisites; service-band interpretation, actual category mapping, HR authority and leave impact remain unresolved. Production stays closed.

## 24 November 2026 — CT-P02 technical session simulation

- Added an unexposed local/testing-only session simulation that resolves a technically confirmed schedule and calendar separately for every supplied date, then counts integer half-day units. A missing or inconsistent version on any date aborts the whole simulation. It neither confirms schedules nor creates requests, balances or approvals; technical confirmation/acknowledgement is not HR approval, and future workflows still require authority, full-range coverage and transaction/snapshot guarantees.
- Rollback-isolated synthetic MySQL test passed (1 test, 6 assertions) for half/full-day counts, holiday/weekend exclusions, missing subsequent version and production denial. Targeted Pint/PHPStan and `git diff --check` passed; no data or routes added. Synthetic confirmed working-schedule fixtures were transaction-only; no working policy was confirmed in the environment.
- Next: resolve the P02 schedule/rate confirmation and calendar-impact contracts before activating CT-P03–09; continue independent ready work without inventing service bands, category mapping or actual HR values.

## 24 November 2026 — CT-P01 employee directory search

- Added server-side name/employee-number search to the local authorized employee directory. The query remains behind existing active-account and employee-view checks, and assignment props are limited to returned employee IDs. This adds no broader directory permission or account data exposure.
- Focused rollback-isolated MySQL test passed (1 test, 33 assertions) for denied access, name/number filtering, assignment prop scope and input length. Targeted Pint/PHPStan, frontend formatting/lint, Vue type check, build and `git diff --check` passed. No browser search submission or real data used.
- Next: continue dependency-ready work; CT-P02 entitlement rule meaning and CT-P03–09 operational workflows remain gated as described below.

## 24 November 2026 — working-schedule lookup naming

- Renamed the unexposed technical policy lookup to `workingScheduleFor` and clarified its incomplete-data error: it validates schedule fields only, not entitlement bands, rates or whole-policy approval. No caller gains authorization or operational behavior from this helper.
- Focused rollback-isolated MySQL checks passed (2 tests, 32 assertions); targeted Pint/PHPStan and `git diff --check` passed. No schema, data or production action.
- Next: continue dependency-ready CT-P02 checks; entitlement rule boundaries, formal HR authority and request-impact integration remain open.

## 24 November 2026 — dependency advisory remediation

- Consolidated dependency check reported low-severity `PKSA-yxbx-3c9s-cjt9` / `GHSA-63hm-g96r-2m2q` affecting Laravel framework before 13.40.0. Updated only the existing framework lock from 13.39.0 to 13.40.0 within the existing `^13.17` constraint; no new package, infrastructure or CI change. Composer audit now reports no advisories and `composer validate --no-check-publish` passes; npm audit reported zero vulnerabilities.
- After the update, the focused rollback-isolated employee/leave/transactional-RBAC and unit selection passed (23 tests, 1,022 assertions), local `/login` returned HTTP 200, and `git diff --check` passed. The full destructive-harness suite was not run against the shared development database; no browser sign-in, release or HR/IT approval inferred.
- Next: continue dependency-ready CT-P02 safety work and reconcile CT-P03 prerequisites; retain the calendar/request-impact and policy/HR decision gates documented below.

## 24 November 2026 — CT-P02 bounded calendar simulation confirmation

- Added permission-gated, local/testing-only confirmation of a synthetic calendar for technical simulation. The form requires an explicit completeness acknowledgement and a finite effective period; a dedicated InnoDB guard serializes confirmation, overlapping confirmed/inconsistent versions are rejected, active dates/replacements are validated through the fail-closed lookup, and the version becomes immutable with an audit event. This is explicitly labelled simulation, not HR sign-off or operational approval. No policy confirmation, leave submission, posting or production route access was enabled.
- Confirmation is blocked if `leave_requests` exists, pending reviewed calendar-impact handling. That table-name guard is temporary: before adding any request storage (whatever its final name), replace it with an integrated schema-independent impact gate and test concurrent migrations/workflow interactions. The technical lookup is now itself local/testing-only; it still cannot prove external HR authority or…19269 tokens truncated…view, and release gates; no weak fallback or public bootstrap is enabled |
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
