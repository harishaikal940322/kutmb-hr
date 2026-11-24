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
- Confirmation is blocked if `leave_requests` exists, pending reviewed calendar-impact handling. That table-name guard is temporary: before adding any request storage (whatever its final name), replace it with an integrated schema-independent impact gate and test concurrent migrations/workflow interactions. The technical lookup is now itself local/testing-only; it still cannot prove external HR authority or actual calendar completeness.
- Added one technical guard table on the verified local target after SQL preview; it contains only its lock row, and synthetic calendar data rolled back. Focused MySQL tests passed (2 tests, 32 assertions) for authorization, acknowledgement, bounds, overlap, immutable confirmation, temporary request guard, lookup consistency and simulated production denial. Targeted Pint/PHPStan, frontend format/lint, type check, build and `git diff --check` passed. No browser confirmation or simultaneous confirmation-worker test; formal approval remains absent.
- Next: policy confirmation must remain closed until unresolved service bands/category mappings and effective-rule completeness are resolved. Continue independent ready technical work, preserving CT-P03–09 dependency and operational gates.

## 24 November 2026 — CT-P02 fail-closed technical version lookup

- Added an unexposed lookup requiring exactly one technically confirmed, noncancelled version for a date. It rejects missing/ambiguous/partially confirmed or cancelled states, incomplete working-time settings, invalid weekday/time ordering and inconsistent active calendar replacement links; cancelled days are excluded. It does not authorize an actor, establish HR approval or prove calendar coverage, and it is not wired to leave operations. Future callers must also establish formal authority, full-range coverage, transaction locking, snapshot consistency and calendar-change impact before use.
- Rollback-isolated MySQL test passed (1 test, 13 assertions) covering missing/incomplete/overlapping versions, inconsistent state, cancelled entries, broken replacement and invalid date. Targeted Pint/PHPStan and `git diff --check` passed; no persistent policies/calendars/dates remain. No confirmation UI, HR values or operational release introduced.
- Next: reconcile full-range calendar/version selection and HR-dependent policy rules with CT-P03/04; do not infer service bands or enable posting/submission while those prerequisites are unresolved.

## 24 November 2026 — CT-P02 reversible draft-version cancellation

- Added audited cancellation/restoration of unconfirmed calendar and policy versions. Cancelled versions and their contents remain visible but are locked against edits and excluded from draft overlap warnings; restoration reinstates warnings. Confirmed/partially confirmed versions cannot be cancelled. Fixed edit/cancel presentation guards for cancelled child drafts. Future version selection must explicitly exclude cancelled and inconsistent states; no confirmation or operational selection exists.
- Applied only additive nullable cancellation fields/actor FKs after local target and SQL preview. Focused rollback-isolated integration/unit checks passed (4 tests, 189 assertions) for cancellation, restoration, child lock, overlap changes and confirmed lock. Targeted Pint/PHPStan, frontend formatting/lint, Vue type check, build and `git diff --check` passed. Browser and concurrent-worker acceptance of this slice remain outstanding; no HR values or permissions granted.
- Next: assess dependency readiness and remaining scoped security/requirements checks. CT-P03 posting needs resolved policy/rate mapping and upstream confirmation; CT-P04–09 remain dependent and operational approval remains absent.

## 24 November 2026 — CT-P02 explicit working-session counter

- Added an unexposed, pure half-day session counter. It accepts explicitly supplied working weekdays and complete holiday dates, counts integer half-day units, rejects missing inputs, invalid dates/sessions and duplicate slots, and has no defaults, database access or route. It does not select a policy/calendar or prove calendar completeness; any future integration must first establish confirmed versions, coverage, cancellation handling and request-impact safety.
- Unit checks passed (4 tests, 6 assertions) for working/holiday/weekend and half-day examples, missing calendar, duplicate slot and invalid date. Targeted Pint/PHPStan and `git diff --check` passed; no operational calculation or production activation.
- Consolidated prior to this slice: employee/leave/RBAC rollback-isolated and unit tests passed (17 tests, 948 assertions), calendar guidance Node test passed, production config had zero HR/leave menu and permission definitions, and all new HR/leave business tables and grants were empty after rollback. Formal T-CT acceptance and nine destructive-harness IAM tests remain unexecuted.
- Next: finish safe calendar/policy selection and impact contracts without asserting unapproved HR data; CT-P03 financial posting and CT-P04–09 require their recorded dependencies. No HR/IT or release approval.

## 24 November 2026 — CT-P02 entitlement proposal cancellation

- Added audited, reversible cancellation of unconfirmed entitlement proposals; cancelled proposals cannot be edited until restored and retain their reserved label/history. Confirmed or partially confirmed versions stay locked. A future policy selector must exclude cancelled proposals and reject inconsistent cancellation state; no selection, rate application or posting exists yet.
- Applied nullable cancellation fields and restricted actor FK after local target/SQL preview. Focused rollback-isolated MySQL test passed (1 test, 37 assertions), including cancellation/restore, audit, blocked edits and confirmed lock. Targeted Pint/PHPStan, frontend format/lint, type check, build and `git diff --check` passed. No browser or concurrent-worker acceptance of this form; no actual values or HR approval.
- Next: consolidated CT-P01/02 security and integrity checks, review unresolved policy sources, then proceed only with genuinely dependency-ready pieces. Do not activate leave requests or infer HR policy.

## 24 November 2026 — CT-P02 entitlement draft correction history

- Added local corrections to descriptive entitlement proposals, retaining previous category, label, nullable rate and actor in a restricted history table with atomic audit. Version locks, active-category checks, duplicate labels and confirmed-policy denial apply. These drafts remain non-executable; service-band mapping and rates are not inferred or approved, and no ledger or policy activation was added.
- Applied the additive history migration after target/SQL preview. Draft/history rows remained empty after the rollback-isolated MySQL test (1 test, 27 assertions), including duplicate rollback, prior null preservation, audit, cross-version denial and confirmed lock. Targeted Pint/PHPStan, frontend format/lint, Vue type check, build and `git diff --check` passed. Browser and concurrency acceptance for the new edit form remain untested.
- Next: review remaining draft cancellation and perform a consolidated security/integrity pass; CT-P03–09 posting and workflows require resolved upstream rules and dependencies. HR/IT and production approval remain pending.

## 24 November 2026 — CT-P02 non-executable entitlement drafts and verification follow-up

- Added local, audited entitlement-rate proposals tied to a policy draft and active employment category. A free-text band label is descriptive only, and the optional rate is stored as integer half-day units; null remains different from explicit zero. No service-band boundaries, actual rates or category mappings were inferred or seeded. These proposals have no selector, confirmation, ledger or calculation path; additions to confirmed/partially confirmed policies are denied. Editing/cancelling individual proposals remains unavailable.
- Added the empty draft table on the verified local MySQL target after SQL preview. Focused rollback-isolated test passed (1 test, 17 assertions) for permission, active category, null vs zero, validation, duplicate label, audit and confirmed lock. Targeted Pint/PHPStan, frontend format/lint, type check, build and `git diff --check` passed; no draft rows remain after rollback.
- Browser follow-up: using temporary local routes with synthetic props only, inspected the employee, self-profile, unit/category, office, calendar and policy screens, disclosure controls, PU unit field and Sunday suggestion. At a requested 390×844 viewport the measured document width/scroll width were 375/375 or 390/390; no page-level horizontal overflow measured. Preview routes were removed, viewport reset and no form submitted. Authenticated functional flows, dark theme and full accessibility review remain untested. Separately, two existing-DB connections showed the office guard's second lock waited 2.85 seconds for the first to release; this verifies lock serialization, not a concurrent appointment end-to-end test.
- CT-DEC-005 still lacks confirmed service-band edges, calculation basis and category mapping; CT-DEC-003/033 rounding/partial-month and promotion effects remain unresolved. Do not turn descriptive draft labels into rules or post entitlements. CT-P02 confirmation/calendar impact and CT-P03–09 dependent operations remain incomplete; HR/IT sign-off, actual values and production activation remain gated.

## 24 November 2026 — CT-P02 reversible draft-date cancellation

- Added audited cancellation/restoration of unconfirmed calendar dates without deleting records. A holiday with an active replacement cannot be cancelled until its replacement is cancelled; a replacement cannot be restored while its source is cancelled. Confirmed/partially confirmed versions are locked, and cancelled entries cannot be edited until restored. A cancelled entry continues to reserve its date under the existing unique key; restore it to correct/reuse that date. Future calendar calculations must explicitly exclude cancelled entries and reject inconsistent cancellation state before activation.
- Added only nullable cancellation fields and a restricted actor foreign key on the verified local database after migration preview. Focused rollback-isolated MySQL test passed (1 test, 65 assertions), including source dependencies, duplicate date retention, audit, restore and confirmed lock. Targeted Pint/PHPStan, frontend format/lint, type check, build and `git diff --check` passed. No browser acceptance, simultaneous concurrency or active calendar impact tested.
- Next: entitlement policy drafts and safe source reconciliation; confirmation and request impact remain disabled until HR and downstream workflow requirements are resolved. No operational approval.

## 24 November 2026 — CT-P02 version-period overlap warnings

- Added server-calculated inclusive effective-period overlap warnings to calendar and policy draft lists, including open-ended periods. Drafts may coexist for review; warnings do not confirm, select or activate a version. Future confirmation must enforce conflict resolution transactionally rather than relying on this display.
- Checks: focused unit and rollback-isolated MySQL integration tests passed (3 tests, 102 assertions), including adjacent/inclusive dates, open ends and returned warnings; targeted PHPStan/Pint, frontend formatting/lint, Vue type check, build and `git diff --check` passed. No database migration or browser acceptance in this slice.
- Next: remaining calendar draft cancellation/replacement review and policy entitlement configuration need safe historical contracts; CT-P03 posting and later workflows still require confirmed upstream policy and safeguards. Production and HR approval remain gated.

## 24 November 2026 — CT-P02 policy draft revision history

- Added edits to unconfirmed working-time policy drafts, with previous settings retained in a restricted history table and an audit event in the same transaction. Confirmed or partially confirmed versions remain locked. Input continues to preserve null/unset values and validate date/time ordering; no confirmation, entitlement values or operational calculations were added.
- Applied the additive history migration after verifying local target and previewing SQL. Policy and history tables remain empty after rollback testing. Focused MySQL test passed (1 test, 36 assertions) for validation, retained prior settings, audit, confirmed lock, missing target and access; targeted Pint/PHPStan, frontend format/lint, type check, build and `git diff --check` passed. No browser acceptance, concurrent-worker test or production approval.
- Next: continue unambiguous CT-P02 draft calendar/policy capability, then reassess dependency-ready CT-P03 foundation; confirmation, actual HR rates and leave request impact remain gated.

## 24 November 2026 — CT-P02 draft working-time policy

- Added local/testing-only draft policy input behind ungranted `leave.policy.manage`. Effective dates, optional explicit weekdays and session times are validated; missing weekdays/times remain null, and supplied session times must be ordered. Drafts are audited and cannot be confirmed, edited, deleted or used for leave calculations. No entitlement rates, automatic defaults, actual HR values or operational policy were introduced.
- Registered permission after local target/SQL preview; policy rows and grants remain zero after rollback tests. Focused MySQL test passed (1 test, 28 assertions) for access, empty vs configured values, invalid dates/weekdays/times, audit, revocation and simulated production denial. Targeted Pint/PHPStan, frontend format/lint, type check, build and `git diff --check` passed; no browser acceptance or policy-effective-date concurrency checks. Auto-increment counters may advance.
- Next: draft policy revision/history and unambiguous policy-independent CT-P02 work. Confirmation, effective policy selection and calendar/request impact require HR and later workflow integration; no operational use or production activation.

## 24 November 2026 — CT-P02 policy schema foundation

- Added an empty leave-policy version table for effective dates, explicitly supplied working weekdays and session boundaries. Settings are nullable so missing values remain missing; no policy rates, configuration values, confirmation, defaults, routes or calculations were added. Future commands must validate complete settings, effective-date conflicts and immutable confirmed history before any use.
- Applied the additive migration only after verifying local database and previewing SQL. The table is InnoDB and empty after a rollback-isolated MySQL test (1 test, 10 assertions) of absent defaults and confirmation-actor foreign key; targeted Pint and `git diff --check` passed. Auto-increment counters may advance.
- Next: implement permission-gated draft policy input with explicit validation, leave confirmation/activation disabled pending HR and impact handling. CT-P03–09 and production remain incomplete/unapproved.

## 24 November 2026 — CT-P02 draft calendar corrections

- Added date/name corrections for unconfirmed calendar days with prior values and actor preserved in a restricted history table, transactionally with the safe audit event. Confirmed or partially confirmed versions remain locked; cross-version targets, duplicate dates and dates outside version bounds are rejected. Holiday type and replacement source cannot be silently changed, and no delete, confirmation or leave impact operation was introduced.
- Applied the additive history table after checking local database target and SQL preview; versions, days and history remain empty after rollback testing. Focused MySQL test passed (1 test, 42 assertions) including prior values, duplicate rollback, audit, cross-version target and confirmed lock; targeted Pint/PHPStan, Vue formatting/lint, type check, build and `git diff --check` passed. Browser, concurrent-worker and full calendar-impact checks remain outstanding.
- Next: evaluate policy-configuration foundation and remaining calendar revision/cancellation requirements without activating unconfirmed rules. CT-P03–09 remain dependent on safe upstream contracts and HR decisions; no operational approval.

## 24 November 2026 — CT-P02 draft calendar warnings

- Added presentation-only warnings for duplicate and weekend dates in draft entry. For a Sunday holiday the form suggests Monday, or Tuesday if Monday is already occupied; if that candidate is occupied or outside the draft version it asks for HR review. Saturday explicitly does not create a Monday replacement. Suggestions never create or confirm a replacement and server-side uniqueness/source checks remain authoritative. Rules beyond the documented Monday/Tuesday case remain for HR review rather than being inferred.
- Checks: Node test passed covering Sunday/Monday/Tuesday collisions and bounds, Saturday, duplicate dates, replacement selection and empty input; focused frontend formatting/lint, Vue type check, build and `git diff --check` passed. No browser visual or calendar activation test was run; no database operations or business records created.
- Next: draft corrections with retained history; policy configuration must distinguish missing values from approved values. Confirmation and request impact remain gated on later workflow work.

## 24 November 2026 — CT-P02 local draft calendar entry

- Added local/testing-only version and holiday/replacement draft entry behind the separate ungranted `leave.calendar.manage` permission. Draft dates must be within the version, unique per version, and replacement entries reference a holiday in the same version. Confirmed versions cannot accept new days; every successful draft mutation records a safe audit event. There is no confirmation, publication, modification, deletion, automatic replacement, leave calculation or request-impact action. Dates, names and versions were not seeded.
- Registered permission on verified `local` / `kutmb_hr_dev` after SQL preview; no role/user grant or calendar row remains after rollback. Checks: rollback-isolated MySQL test passed (1 test, 32 assertions) for unauthorized/revoked access, date bounds and collisions, same-version source, confirmed lock, audit and simulated production denial. Targeted Pint/PHPStan, frontend formatting/lint, Vue type check, build and `git diff --check` passed. No browser acceptance or concurrent-worker test; auto-increment counters may advance.
- Next: implement weekend/collision warnings and explicit replacement suggestions without auto-creation, and safe draft correction/history. Calendar confirmation and impact on requests remain gated on CT-P05–06; unconfirmed HR calendar data and policy values must never be used for operational leave.

## 24 November 2026 — CT-P02 calendar schema foundation

- Reviewed the Cuti Tahunan SRS and current CT-DEC-010/012/043/044/047 source answers. Added separate versioned calendar and date tables with per-version date uniqueness, optional replacement-to-original reference and restricted historical deletion. No holidays, policy values, routes or calendar confirmation were created. This partial CT-P02 schema is independent of the remaining CT-P01 browser/concurrency work, not a usable leave calendar.
- Verified `local` / `kutmb_hr_dev`, absent target tables and migration SQL before applying the single additive migration. Both tables are InnoDB and remain empty after the rollback-isolated synthetic MySQL test (1 test, 6 assertions: version isolation, unique date and deletion restrictions). Targeted Pint and `git diff --check` passed. Auto-increment counters may advance.
- Next: validate and audit calendar versions and replacement proposals; enforce version/date/source consistency and confirmation immutability. Do not activate calendar changes affecting requests before CT-P05–06 impact handling. Policy rates, HR confirmation, real holidays and release remain unresolved.

## 24 November 2026 — CT-P01 menu integration correction and focused regression

- Fixed an integration defect found during CT-P01 review: HR menu definitions and permissions now exist only in local/testing configuration, so production menu editing has no references to unavailable HR permissions. Fixed menu mappings use their code-defined permission in navigation and server-side menu updates reject attempts to replace them; existing editable System Core mappings retain their normal behavior. Restored directory-viewer template ordering for the existing RBAC regression test.
- Checks: combined rollback-isolated employee and existing transactional RBAC tests passed (11 tests, 740 assertions); includes synthetic fixed-menu tampering. A production-configuration probe showed zero HR menus and permission definitions. Targeted PHPStan/Pint and `git diff --check` passed. Browser visual/accessibility review and concurrent mutation tests remain incomplete; nine older RefreshDatabase IAM tests were not run on the shared database. No grants or operational release implied.
- Next: dependency-ready CT-P02 work may begin with documented, policy-independent calendar/configuration foundations while CT-P01 visual, concurrency and workflow-integration limitations remain open. Office-scoped approver views and account reassociation with leave history remain gated until the actual leave workflow is designed.

## 24 November 2026 — CT-P01 local HR role templates

- Added local/testing-only editable role-template definitions for Staff, HR Executive and HR Manager using the delivered permission catalogue. Unit Manager, General Manager and Secretary templates are visible but unavailable because `hr.organization.scoped.view` has not been implemented; no partial preset is applied. Templates do not create roles, assign permissions or appoint office holders. Production catalogue remains unchanged.
- Checks: rollback-isolated catalogue test passed (1 test, 11 assertions) for exact ready template sets, unavailable approver templates and unchanged role/grant counts; targeted Pint and `git diff --check` passed. Existing role UI confirmation and protection behavior was not changed or retested. No HR/IT authority or operational approval is implied.
- Next: CT-P01 consolidated security and UI checks, then dependency-ready CT-P02 calendar/policy work; office-scoped views require a real leave responsibility, and HR policy-dependent operations must remain closed.

## 24 November 2026 — CT-P01 own employee profile

- Added local/testing-only read-only own employee profile requiring `hr.profile.self.view` and an explicit employee/account link. It shows only the linked minimum profile and, when exactly one assignment applies during employment on the Malaysia-local date, the current unit/category. Missing or ambiguous current assignment is not guessed. No general employee directory or account link is exposed by this permission.
- Registered the permission on the verified local development database after SQL preview; it has no grants and synthetic records rolled back. Checks: rollback-isolated MySQL test passed (1 test, 26 assertions) for permission denial, missing link, own profile, ambiguous assignments and revocation. Targeted Pint/PHPStan, frontend format/lint, Vue type check, build and `git diff --check` passed. No browser acceptance or real-data use.
- Next: role templates and CT-P01 consolidated security/UI checks. Scoped office-holder views are deferred until actual leave tasks provide a bounded purpose; no broad approver directory is inferred. HR/IT authority and production remain unapproved.

## 24 November 2026 — CT-P01 approval-office assignments

- Added local/testing-only appointment and end actions for PU (unit-scoped), PB and Setiausaha ALK (organization-wide), with `hr.offices.manage` plus active-account checks. A dedicated InnoDB guard row serializes appointment writes; the server rejects overlapping office/scope dates, inactive targets/units, invalid scope and actors appointing or ending their own office. Non-employee account holders are supported; appointments grant no permissions or roles. Audit records safe references; no delete or delegation was added.
- Added guard schema and registered the ungranted permission on the verified existing local database after SQL preview. Guard contains its single technical lock row; no synthetic appointment or privilege grant remains after testing. Appointment changes are not yet integrated with leave workflow snapshots or pending requests and must be revisited before CT-P05.
- Checks: rollback-isolated MySQL test passed (1 test, 35 assertions), including denied/revoked permission, self-appointment/end, unit scope, same-day overlap, successive periods, non-employee Secretary, audit and simulated production denial. Targeted Pint/PHPStan, Vue lint/format, type check, build and `git diff --check` passed; no concurrent-worker or authenticated browser testing. Auto-increment counters may advance.
- Next: complete CT-P01 self-profile/scoped access, role templates and consolidated security/UI verification before dependent workflows. Formal appointments, HR/IT authority and operational approval remain pending.

## 24 November 2026 — CT-P01 explicit employee/account association

- Added local/testing-only explicit link/unlink, requiring employee-view plus `hr.accounts.link`, an active target account, an independent actor and a nonempty reason. Self-link/unlink, direct reassignment, duplicate links and inactive targets are rejected. Employee row, restricted association-history row and safe audit event change together in a transaction; reasons are stored only in the HR association history, not general audit metadata. Account IDs/usernames are supplied only to actors with link permission. No user lifecycle or grants change.
- Added empty association-history table and registered ungranted permission on the verified local MySQL target after checking migration preview. All new employee/history records and synthetic grants rolled back after tests. A conservative guard denies association changes if `leave_requests` exists; before any leave schema/workflow is introduced, integrate a schema-independent historical-identity rule and explicitly retest this boundary. The guard is not a substitute for future leave-history authorization. Identity of an unlinked employee cannot be inferred from names; the independent-actor control rejects linking the actor's own account and changing an existing link to the actor, but organizational verification of the person remains a real-use governance gate.
- Checks: rollback-isolated MySQL test passed (1 test, 34 assertions) for access, hidden account props, actor/target safeguards, reason, unique association, link/unlink history and audit, revocation and simulated leave-schema guard. Targeted Pint/PHPStan, Vue lint/format, type check, build and `git diff --check` passed. No authenticated browser, simultaneous concurrency or production acceptance performed; auto-increment counters may advance.
- Next: approval-office assignment with serialized scope/overlap validation and independent-actor safeguards, then complete CT-P01 role templates and end-to-end review. HR/IT authority, real data and operational approval remain pending.

## 24 November 2026 — CT-P01 employee profile edits

- Added local/testing-only edits to minimum employee identity and employment dates, guarded by both employee-view and employee-manage permissions. Updates lock the employee and assignment rows, reject dates inconsistent with existing assignment history, ignore account-link input and write an audit event atomically. No employee account, office assignment or leave data is changed.
- Checks: rollback-isolated MySQL test passed (1 test, 13 assertions), covering missing/revoked permission, invalid dates against assignment history, allowed update, ignored account ID, audit and missing target. Targeted PHPStan/Pint, Vue lint/format, type check, build and `git diff --check` passed. No browser acceptance or concurrency test was run; profile dates must be reconciled with future leave records before those workflows are enabled.
- Next: separately authorized account association and approval-office appointments, with independent-actor and overlap safeguards; CT-P01 and CT-P02–09 remain incomplete. No operational approval.

## 24 November 2026 — CT-P01 effective assignment transfers

- Added local/testing-only employee assignment history and forward transfer action. An actor needs employee-view and assignment-manage permissions; the server locks the employee, active references and assignment history, validates dates against employment and existing periods, closes the latest open assignment on the preceding day, and creates the next assignment with an audit event in one transaction. Existing closed periods are retained. Backdated insertion before the latest assignment and overlapping or inconsistent history fail closed; correction of existing history remains unavailable.
- Registered local `hr.assignments.manage` with no user or role grants after target/SQL preview; no employee or assignment rows remain after testing. Files: assignment controller, employee directory props/pages, route/catalogue, migration and focused test.
- Checks: rollback-isolated MySQL test passed (1 test, 19 assertions): missing permission, invalid/overlap/out-of-service dates, invalid reference, preserved and closed prior period, new assignment, audit and revoked access. Targeted Pint, frontend lint/format, Vue type check, build, targeted PHPStan and `git diff --check` passed. Actual simultaneous concurrent transactions and browser visual acceptance were not tested; the employee row lock is the serialization mechanism. Auto-increment counters may advance.
- Next: finish profile edits, separately authorized account links and office appointments; preserve temporal and self-appointment safeguards. CT-P01 and all later packages remain incomplete; no production activation or formal approval.

## 24 November 2026 — CT-P01 local employee creation

- Added a local/testing-only employee directory and creation form. Server requires `hr.employees.view` for the directory and both view/manage for creation; an active unit and category are locked and rechecked inside the transaction. Employee, required initial assignment and safe audit event are written atomically. Date ordering, unique employee number and active references are validated. User association is deliberately ignored and remains a separate protected operation. The initial assignment starts on the explicitly supplied effective date and has an open end; downstream scope calculations must also respect any employee service-end date. No update, transfer, account link, office appointment or leave function is exposed yet.
- Registered `hr.employees.view` and `hr.employees.manage` in the existing local database after target and SQL preview; neither has user or role grants. No employees or assignments remain after tests. Files: employee foundation controller/page, local permission migration, navigation/catalogue/routes and focused feature test.
- Checks: rollback-isolated MySQL test passed (1 test, 32 assertions) covering denied/view-only/manage access, invalid dates and inactive references, atomic initial records, ignored user link, audit, duplicate number, revocation and simulated production denial. Focused Pint, PHPStan, frontend lint/format, type check, build and `git diff --check` passed. Browser and authenticated visual acceptance not performed; auto-increment counters may advance. No real data, formal sign-off or operational approval.
- Next: finish CT-P01 profile edits, effective-dated transfer history, separate authorized account links and office appointments; enforce temporal scope and self-appointment safeguards before leave work. Then proceed to dependency-ready CT-P02.

## 24 November 2026 — CT-P01 local organization references

- Added local/testing-only unit and employment-category create/edit/deactivate screens and server actions, protected by `hr.organization.manage`, active-account and existing MFA middleware. The permission was registered on the existing local database without role or user grants; no HR reference records were seeded. The non-editable navigation hint is local/testing-only and the route independently enforces the permission. Mutations validate unique code and required fields, lock updates, preserve referenced rows and audit safe field names in the same transaction. No delete action, employee operations or production HR route access was added.
- Files: organization reference controller, local HR middleware, route, IAM catalogue/navigation, two Vue components, local permission migration and focused feature test. Permission migration ran only after checking the `local` / `kutmb_hr_dev` target and previewing SQL; it invalidated the permission cache. There are zero direct or role grants for this permission and both reference tables remained empty after testing. Existing account privileges were not changed.
- Checks: rollback-isolated MySQL feature test passed (1 test, 29 assertions) for denied access, authorized unit/category creation and edit, audit, duplicate/blank validation, invalid targets, revoked/inactive access and simulated production denial. Targeted Pint, PHPStan, frontend formatting/lint, Vue type check, build and `git diff --check` passed. Initial test needed a frontend build for the new Vite page; the final test passed. No browser or authenticated visual review was performed; auto-increment counters may advance. The UI has not been accepted for operational use.
- Next: CT-P01 employee creation and effective assignments, explicit account association and office appointments need atomic validation, authorization and audit before exposure; then complete local HR navigation and role template integration. No formal HR/IT or release approval is implied.

## 24 November 2026 — CT-P01 additive schema foundation

- Added the five proposed MOD-004 foundation tables in `2026_11_24_000001_create_employee_organization_foundation.php`, separate from System Core. Unique employee numbers and optional user links, foreign keys with restricted deletion, and assignment indexes are present. No reference values, roles, permissions, routes or records were seeded. This is a partial CT-P01 slice, not a usable HR workflow; dates, non-overlap, office scope, authorization, audit and atomic employee/assignment creation still require application enforcement before any mutation interface is exposed.
- Preflight: clean `main` fast-forwarded to `8e1c0fd`; existing application reported local, MySQL `kutmb_hr_dev` on `mysql`; all five tables were absent and users was InnoDB. Reviewed `migrate --pretend`, then applied only this additive migration to that existing target. All five new tables are InnoDB and empty. No rollback, new database or container was used.
- Verification: targeted Pint passed; `FoundationSchemaTest` passed on the existing MySQL connection within a rolled-back transaction (1 test, 8 assertions): nullable unlinked records, duplicate employee number and user link, and restricted deletion of referenced unit, employee and user. The five new tables remained empty afterward; auto-increment counters may advance. `git diff --check` passed. This does not test application validation, concurrency or access control.
- Next: implement CT-P01 domain operations and local/testing-only authorized interfaces with transaction locks, temporal validation, target checks and audit; do not expose incomplete mutation paths. Continue dependent packages only when prerequisites are met. Formal HR/IT approval, real reference values and production activation remain outstanding.

## 24 November 2026 — autonomous local development direction (latest)

- Requestor directed continuation of all planned work with minimal interaction and independent technical blocker resolution. Latest SCOPE-001 and AGENTS.md authorize bounded MOD-004 minimum foundation and MOD-005 Cuti Tahunan local/testing development with synthetic records only; formal HR/IT approval, actual business values, appointments and operational/production activation remain pending. No broader HR modules. UI/UX decisions already accepted; no repeat approvals.
- Next: CT-P01, then dependency-ready CT-P02–09 portions from existing technical plan. Local Codex performs preflight, exact source reconciliation, additive schema review, implementation, focused safe tests, handoff and verified commit/push for each coherent slice; continue next ready task automatically. Do not repeat MOD-001 selector delivered at 90642c9. Nine original tests and authenticated form submission remain documented limitations, not passed.
- Investigate/fix technical blockers autonomously, split or defer affected scope, continue independent ready tasks. Escalate only genuine decisions/manual access/authority or safety blockers that cannot be resolved within scope. Never guess business policy; missing or conflicting values fail closed. No production routes/operations enabled, actual HR data/notifications, shared privilege changes, destructive testing, extra databases/containers, branches or infrastructure.
- Remote documentation only; no CT-P01 source or schema work performed in this update. Existing local checkout is the required execution target. Normative SRS/RTM/registry approval status unchanged; bounded development direction is an explicit overlay, not formal HR sign-off.

## 24 November 2026 — MOD-001 RBAC selector delivered

- Delivered shared Create/Edit permission matrix, catalogue grouping and a directory-viewer template using registered assignable IAM permissions. Template replacement uses the existing Reka dialog, defaults focus to Cancel, restores focus to the selector and supports Escape. Server blocks editing a role containing protected or unrecognized grants; protected System Admin remains read-only. No HR permissions, employee tables, user assignments or automatic grants were added.
- Checks: focused frontend formatting/lint, Vue type check and production build passed after the dialog change; `git diff --check` passed. The prior rollback-only MySQL verification (1 test, 290 assertions, matching before/after snapshots) remains accepted and was not repeated. The nine existing `IdentityAccessManagementTest` cases below remain unexecuted, not passed. Earlier unrelated full-check findings remain as recorded.
- Authenticated browser: on the local role screen, verified template application and confirmed replacement, Cancel and Escape preservation, focus on Cancel, Tab focus trap and return to selector, search/collapse preservation, counts and protected role display. Inspected 1440×900, 768×1024 and 390×844 in light and dark; no page-level horizontal overflow measured. Restored light appearance and viewport. No form was submitted and no database record was created or changed. The only local role was protected, so Edit Role interactions and unrecognized-permission warning/disabled save were checked on the actual Vue component using a temporary synthetic preview; authenticated end-to-end create/edit submission remains untested under the restriction on persistent test records. Server create/edit was covered by the rollback test. The preview was removed.
- Next: continue a requirements-backed System Core slice; HR Core remains on hold and organizational/security/release approval is not implied.

## 24 November 2026 — RBAC rollback verification and browser follow-up

- Reviewed the existing `IdentityAccessManagementTest`: its nine cases rely on `RefreshDatabase`; the helpers reuse or mutate `system-admin`, and the menu case changes shared mappings. None was run against the development database. The count and catalogue assumptions also require an isolated database. The revocation test specifically revokes from that shared role. Do not substitute the transaction trait in that suite without redesigning its fixtures and assertions.
- Added `TransactionalRbacVerificationTest` with uniquely named synthetic users, roles and a synthetic unknown permission, and existing permissions attached only to synthetic records. On the existing MySQL database, with MFA enabled and array cache/session, it passed **1 test, 290 assertions**: authorized/unauthorized access, template props, create/edit, permission pivot and audit writes, protected/unknown grant rejection and conflict preservation. A query listener checked the default connection, active transaction and absence of DDL for each query. The ten relevant tables were confirmed InnoDB; sorted row-content SHA-256 snapshots of users, roles, permissions, both Spatie pivots, role-permission pivot, audit, menu and both legacy pivots matched before and after. MySQL auto-increment counters can advance despite rollback. No extra database/container or migration was used.
- Browser: the live roles URL redirected to login; no authorized browser session was available. A temporary standalone preview rendered the actual Vue Roles component with synthetic props, without submitting forms or changing database data. Verified initial create/edit counts, directory template selection, expand/collapse, search with no matches and a matching term, preservation of selected checkbox, protected read-only display and unknown-permission warning/disabled save. The preview was removed. A native confirmation dialog stalled the browser control; cancellation, keyboard, desktop/tablet/mobile and light/dark visual passes, actual authenticated submission and complete end-to-end UI verification remain outstanding. Do not treat preview results as authenticated acceptance.
- Work remains uncommitted and unpushed pending complete browser verification. Earlier frontend build/type checks, targeted lint/Pint/PHPStan results and unrelated full-check failures are recorded below. HR Core remains on hold; no release or business approval inferred.

## 24 November 2026 — MOD-001 permission selector implementation in progress

- Implemented shared compact Create/Edit Role selector with module/submodule/action metadata, search, counts, disclosure controls and responsive layouts; selections remain in form state. Added an optional, editable directory-viewer preset using the existing assignable IAM permission and availability checks. Protected/unknown existing role permissions block edits in both UI and server. No HR permissions, user assignments, employee schema or automatic grants added.
- Files: IAM config/catalog/controller, `resources/js/pages/identity/Roles.vue`, shared `identity/components/PermissionSelector.vue`, and focused RBAC feature tests. The preset is a draft convenience, not an approved organizational role.
- Checks: frontend type check and production build passed; targeted frontend formatting/lint, Pint and targeted PHPStan passed. Full `npm run check` reports pre-existing formatting issues in UserMenuContent, AnnualLeavePreview and Login; full PHPStan reports existing `LogoutKeycloakSession::getLogoutUrl()` type error. The focused RBAC feature suite could not execute: SQLite in-memory migration fails on MySQL-only `ascii_bin` collation. Its normal MySQL harness creates a new container/database, which the requestor expressly prohibited; running `RefreshDatabase` against the existing development database would be destructive. Browser responsive/theme/keyboard review is also outstanding.
- Work is uncommitted and unpushed pending a safe authorized MySQL test environment or an alternative non-destructive test strategy and UI review. HR Core stays on hold; no business or release approval inferred.

## 24 November 2026 — delegated implementation direction / first System Core slice

- Requestor clarified HR approval not required for UI/UX and delegated technical sequencing/development decisions. Recorded SCOPE-001 clarification; requestor design acceptance retained, formal HR business requirements/actual authority not inferred.
- Selected first authorized scope: existing MOD-001 Roles UI shared compact permission selector for create/edit; optional preset mechanism using only currently registered assignable IAM codes. Reuse RoleController, PermissionCatalog, current Inertia form and explicit save; no new grants/roles assigned, HR permissions, employee tables, business workflows, auth/security changes, migrations or infrastructure. HR presets unavailable until corresponding approved/implemented catalogue codes exist. Preserve protected admin and unknown saved permission safety. Focused frontend/build/type/lint and relevant existing RBAC tests, verify actual results and local browser where available.
- Local preflight blocker: required checkout /home/haris/www/kutmb-hr is absent in this remote runtime (checked). No source code or runtime tests performed. Local Codex should fetch origin, inspect main/worktree, preserve unrelated changes, implement first slice under AGENTS.md, update handoff, commit/push verified work and stop only for actual blockers. Do not create clone/worktree/branch or alter CI. Remote documentation updates only.
- CT-P01 HR Core functional work still HOLD pending formal HR requirements and actual permission/appointment authority; UI/UX not an HR sign-off gate. Do not repeat previously accepted design decisions. No production approval.

## 24 November 2026 — compact permission matrix design acceptance

- Requestor accepted compact module/submodule permission-matrix concept for Create Role and Edit Role. DOC-007 v0.4 section 4C specifies collapsed groups, search, counts, actual action mapping, shared selector, mobile/dark/light and preserved selections. No invented rights, automatic grants or changes to protected/existing roles. CT-P01 planned deliverables and checks updated.
- Documentation only; no UI code, permission grants, schema or runtime tests. Formal HR requirement/authority confirmation and explicit CT-P01 development authorization remain before functional work; local preflight required.

## 24 November 2026 — MOD-004 permission matrix and role template direction

- Requestor accepted the proposed MOD-004 permission matrix and requested templates using existing support. DOC-007 v0.3 section 4B records a bounded MOD-001 supporting design: optional presets in existing role creation, editable permission selection, server catalogue validation, explicit save, no new authorization engine/table or automatic grant/assignment. Current Roles.vue/IdentityRoleController/PermissionCatalog/config and seed migration confirm manual reusable roles and menu control; no preset selector, HR catalogue or user-role assignment in that screen.
- CT-P01 plan includes authorized IAM catalogue/preset work only when formal HR requirement/authority and explicit development authorization recorded. Q-004 partially resolved at requestor level; actual appointed holders and wider IAM authority still blocked. No source, migration, data, permissions, role assignments, SRS/RTM or infrastructure changes. Tests planned only. Next genuine interaction: HR formal approval/authority and CT-P01 development authorization.

## 24 November 2026 — MOD-004 minimum fields and account link accepted

- Requestor accepted eight minimum profile fields (employee no, full name, service start, unit, category required; confirmation date, service end and linked user optional), explicit manual nullable unique employee/account association. DOC-007 v0.2 records acceptance and proposed least-privilege permission matrix based on current IAM catalogue/menu/protected routes. Employee and required initial assignment created atomically in proposed design; user lifecycle remains MOD-001.
- Q-005 partially resolved at requestor level. CT-P01 scope and field decisions resolved; remaining gate: permission ownership (DOC-007 section 4A), formal HR requirements approval, explicit development authorization, local preflight. Actual unit/category/employee reference values and appointments before real use. No source, DB, permission grants, Docker or normative SRS/RTM changes; no tests run.

## 24 November 2026 — MOD-004 Option A accepted as independent module

- Requestor selected minimum foundation and explicitly retained Employee & Organization as a major module, not a Cuti Tahunan submodule. Existing MOD-004/F-009 IDs unchanged. Filled reserved DOC-007 with independent scope, proposed submodules, minimum fields, role boundaries and delivery plan; physical schema stays in existing technical plan section 6A. Updated scope and planning records only.
- Scope choice resolved. CT-P01 remains blocked on minimum field/account association confirmation, actual unit/category and authority ownership, formal HR requirements approval and explicit implementation authorization, then local preflight. Other HR Core features stay deferred; no code, database, Docker, normative SRS/RTM or registry status change. Remote documentation only.

## 24 November 2026 — delegated MOD-005 technical planning baseline v0.3

- Requestor directed autonomous continuation until a genuine interaction gate. Existing plan expanded (no duplicate register): proposed physical data contracts, transaction/status handling, feature-local Laravel/Vue and role permission design, backend/frontend deliverables and planned acceptance evidence for CT-P01–09. Reused existing CT-P00–09 IDs. No new GitHub Issues or permission grants.
- Remote DOCX extraction verified 56 headings/56 populated HR answer fields, with targeted reconciliation of CT-DEC-010/012/015/018/020–025/029/034/036–040/043–054/056 and prior reviewed answers. Resolved obsolete negative cap assumption (no cap configuration), half-day boundaries, actual-absence and EL rejected adjustment, executive/manager HR adjustment separation, calendar changes, year commitments/close. Flagged real residual policy confirmations instead of reopening resolved answers. Full formal SRS/RTM reconciliation and sign-off remain gated.
- UX v0.5 stays frozen; technical refinement needed for derived short-notice reason and separate HR adjustment actions in eventual functional pages. No demo/source, schema, data, SRS/RTM, Docker or infrastructure changed. Planned checks not executed.
- CT-P00 technical planning deliverables prepared, authorization gate open. CT-P01 is first blocked slice: confirm bounded MOD-004 scope/fields, account association, unit/category taxonomy, authorized HR/office role ownership, formal HR requirements and explicit functional authorization; local preflight then required. Later decisions scoped to affected packages; no automatic release or production approval.

## 24 November 2026 — MOD-005 UX acceptance and technical design review

- Requestor reviewed updated demo, accepted remaining screens and directed proceeding. DOC-023 v0.5 design frozen at source commit `ffdeda34062ae0dd5c9edbd08ec5e7ca44cab6a5` including UX-R01–R05. Existing filenames retained. This is requester acceptance, not independently executed device/build/UAT checks or formal HR approval.
- Updated existing technical plan v0.2: inspected current Laravel/Vue directories and IAM schema; proposed bounded CT-P01 employee/org/approval assignment physical design and source/menu layout for review; aligned CT-DEC-050 route and current HR answers for negative balance, correction window, EL, cancellation, unit transfer, late approval, cross-year and HR recording. No change to SRS/RTM or permission grants. Remaining policy details and Q-004/005/006 must be resolved before migration; G2–G4 remain open.
- CT-P00 IN REVIEW (UX accepted, other gates open); CT-P01 BLOCKED pending scope/field/authorization, subsequent packages HOLD. No source code, database, Docker or production changes. Remote documentation verification only; local preflight and focused build/browser checks remain pending. Next: review minimum MOD-004 dependency and formal HR/project authorization, finalize P01 physical schema in existing plan, then authorized implementation one slice at a time.

## 24 November 2026 — MOD-005 application form UX-R01–R05

- Requestor accepted the revised form design and confirmed SRS approval threshold unchanged. Vue and HTML previews now use AL/EL dropdown, remove manual AL emergency checkbox, show four pre-form balance metrics and projected AL/Tabung separately; ordinary AL reason optional, EL reason required, attachment optional. All values synthetic; pending deducted once. Unit Manager preview aligns to FR-20; five days ends at GM, above five at Secretary ALK; GM own requests go to Secretary.
- Updated DOC-023 and draft technical plan. FR-18/BR-20/CT-DEC-009 notice policy not repealed by UI change; resolve functional handling before coding. No normative SRS/RTM changes, real records, APIs, backend workflow or database changes. Functional implementation remains on hold.
- Remote source checks: targeted replacement anchors, removed obsolete checkbox bindings, threshold/source-allocation checks and preview-only boundary reviewed. Local build, lint/type check and browser acceptance not run in this remote session. Next: sync existing local checkout, run focused frontend checks, review UI on PC/tablet/mobile and both themes.

## 24 November 2026 — local SSO logout

- HR logout now ends the local session and redirects SSO-authenticated browsers to Keycloak RP-initiated logout, using the ID token captured in the server session. Local-password logout retains its existing behavior. The local Keycloak client post-logout redirect is set to `http://kutmb-hr.localhost:8080/login`.
- Verified focused SSO feature tests, including Inertia logout and local logout; full real-browser logout remains to be verified. Sessions created before this change must sign in through SSO again to receive the logout marker. This remains a local trial; production SSO decisions and review remain open.

## 24 November 2026 — local Keycloak SSO trial

- Added an opt-in, local-only Keycloak login using authorization code with PKCE and session state. A manually configured Keycloak subject maps to one existing active HR username; no account is auto-created or linked by email. Existing Fortify login and application authorization remain in place. SSO is unavailable when MFA is enabled because the external authentication flow has not been integrated with the HR MFA challenge.
- Realm `kutmb` and confidential client `kutmb-hr` were configured in the local Keycloak instance. Browser and HR container use `http://keycloak.localtest.me:8081`; the client secret and explicit mapping are in the ignored local `.env`. Initial user must complete profile and change the temporary password. This is a local technical trial; Q-003, UM-DEC-001, SSO policy and production security approvals remain open.
- Verified: six focused feature tests (linked/unknown/inactive accounts, state and local/MFA gates), frontend build and Vue type check. Real browser-style redirect reached Keycloak and its profile-completion action; the full callback awaits the user's first-login actions. `composer audit` reported an existing low-severity Laravel framework advisory (CVE-2026-128083), not yet remediated.

# Current Development Handoff

## 23 November 2026 — General proxy managed outside repository

- At the owner's direction, removed the proxy Compose and Nginx configuration from this repository. General Nginx is to be configured separately on the server; KUTMB HR retains its external `shared-proxy` connection and `kutmb-hr-app` alias alongside `shared-data`, with no published app port. Removed proxy-only variables from the application environment example and updated README.
- Checks: server and local Compose config validation with a synthetic private env, and `git diff --check` passed. Server access and independent proxy configuration remain to be verified. No server service or data was changed. HTTP has no TLS; account provisioning and database migrations remain gated. Local Compose is unchanged.

## 23 November 2026 — General development proxy for hr.kutmb.test

- Added a separate Nginx Compose project and `hr.kutmb.test` virtual host. The app now joins external `shared-proxy` with a dedicated alias as well as `shared-data`, and publishes no host port. Nginx alone binds the server IP/port supplied in `.env.server`; no WordPress service, volume or configuration is changed. The owner reported WordPress containers/networks were removed and its volumes retained on the server.
- README records network setup, separate project names, hosts/DNS mapping, private environment and startup commands. This is office-only HTTP with no TLS; credentials are not encrypted in transit. The server requires an existing compatible schema and account; no migration, database creation or deployment was performed. Local Compose is unchanged.
- Checks: Nginx `nginx -t`, both server Compose configurations with synthetic values, local Compose validation, and isolated Nginx-to-app `/login` HTTP 200 with built assets and no Vite reference. No target-server or real MySQL smoke test was possible. Next: choose the server bind IP/port, map the hostname, and verify on the development server.

## 23 November 2026 — Development server Compose prepared

- Replaced the legacy `docker-compose.dev.yml` with an app-only server configuration, leaving `docker-compose.local.yml` and `bin/dev` unchanged. `Dockerfile.server` builds Composer dependencies and frontend assets into the image; its dedicated ignore file excludes local environment, data, logs and stale build state. The server Compose joins the user-provisioned external `shared-data` network and requires an explicit bind IP and port; no database, Redis, proxy or Vite service is started.
- `server.env.example` documents private runtime settings. The app uses `APP_ENV=development`, disables local-only bootstrap/preview and defaults to MFA in the example; server account provisioning remains gated. No migration or database creation occurs. Server IP/port, existing schema and credentials must be configured on the target. Direct HTTP has no TLS; file sessions/cache are ephemeral.
- Checks passed: server image build and frontend build, Compose config validation for both server (synthetic values) and local, image manifest/route checks without network access, and isolated local container `/login` HTTP 200 with built assets and no Vite reference. No server connection, deployment, database operation or migration was performed. Next: configure the server's private environment and verify target connectivity and access; do not run migrations without authorization.

## 23 November 2026 — Separate local Compose name

- Copied the existing local configuration unchanged to `docker-compose.local.yml` and switched `bin/dev` and current README commands to that file. The legacy `docker-compose.dev.yml` remains unchanged and is not ready for a server; it is reserved for the requested development server configuration once the network error and server topology are known.
- Checks: both Compose configurations validate and resolve identically with the same project name; `bash -n bin/dev` and `git diff --check` passed. No containers were started or stopped, and no image build, migration or database change was performed. Next: obtain the full server network error and service/proxy network details before preparing or testing server deployment.

## 23 November 2026 — Development server network investigation pending

- Inspected the local Compose, Dockerfile, README and Docker context. The reachable `default` context uses the local Docker socket; its existing proxy/data networks and shared services do not establish the separate development server's topology. The full reported network error and server details were not supplied, so the cause remains unconfirmed and no server Compose or deployment was created.
- Local Compose syntax passed `docker compose -f docker-compose.dev.yml config --quiet`. Its app uses a source bind mount and external local networks; the Dockerfile does not copy the application or build assets, and `.dockerignore` excludes `public/build`. No image build or server smoke check was run because a server configuration and target were not established. No migration, database or shared-service changes were made.
- Updated README with read-only discovery steps and the built-asset constraint. Next: obtain the full network error and the development server's Docker networks, existing service host names and proxy routing details, without credentials, before selecting a server configuration.

## 23 November 2026 — Compose serves built frontend assets

- Updated the development Compose app to run Laravel only. It no longer starts `npm run dev`, uses `concurrently`, or publishes host port 5173. On startup it removes Laravel's stale generated `public/hot` marker so Vite selects the existing `public/build/manifest.json` assets.
- Updated `bin/dev up` output and local-development instructions to describe the build-then-start flow. The existing separate process listening on ports 5173/5174 was left untouched.
- Checks passed: `docker compose ... config --quiet`, `bash -n bin/dev`, and `git diff --check`. Started only `app` with `docker compose up -d --no-deps app`; container logs show Laravel serving on port 8000, with no host port bindings. `/login` returned HTTP 200 through Nginx and HTML referenced `build/assets`; `public/hot` is absent. Port 5173 remains occupied by the pre-existing unrelated process and was not touched. No migrations or application tests were run.

## 23 November 2026 — Vue Cuti Tahunan UI/UX review surface

- Added `resources/js/pages/AnnualLeavePreview.vue` and a local preview entry point in the existing Dashboard. The review surface provides responsive, role-specific navigation for Staf, Pengurus Unit, Pengurus Besar, Setiausaha ALK, HR and Pentadbir IT, plus application, request detail/status, support/approval, team calendar, staff profile/entitlement, Johor calendar, AL/Tabung ledger, year-end, prorata, changes/cancellation, reports, notifications, audit and SRS coverage views. Main-system menus and routes were not added.
- The Vue coverage index maps FR-01–32, BR-01–39, NFR-01–13, RR-01–08, workflow statuses and CT-DEC-001–056. Pending HR details remain configurable/reviewable; GM leave routes to Setiausaha ALK; staff calendar shows approved leave only; role selector is explicitly a design preview, not authorization. Interactions use synthetic data/local messages only, with no API, persistence, submission, real approval, notification or balance transaction.
- Updated DOC-023 to v0.4 to describe both the static HTML and Vue review surfaces. MOD-005 backend, authorization, API and database implementation remain on hold.
- Checks passed: targeted `vp check --fix` (format and lint, two Vue files, no warnings), `npm run types:check` (`vue-tsc --noEmit`), `npm run build`, and `git diff --check`. No application tests were run. Visual browser acceptance was unavailable because no active local app session was present; no account was created and no database operation was run.
- Next: requestor reviews the Vue screens and role scopes. Organize the permanent menu only after that review; keep backend and workflow execution on hold.

## 23 November 2026 — Role dashboards aligned to FR-07 and FR-29

- Extended the single-file MOD-005 prototype to v0.3 with distinct dashboards/navigation for Staff, Unit Manager, General Manager, Secretary ALK, HR and IT Admin, following the actor responsibilities in FR-07 and dashboard content in FR-29. Secretary ALK sees final-decision items and prior support context, not self-service or personal balance; IT Admin has no leave-request, balance, support or approval access; HR has aggregate monitoring and staff/unit/year/status filters.
- Updated DOC-023 SRS coverage and the role-boundary explanation. This is a design-only representation; Vue, backend authorization, APIs, database and workflow remain unchanged/on hold. Data remains synthetic.
- Browser accessibility review confirmed HR, Secretary ALK and IT Admin views and navigation scopes. `git diff --check` passed; the HTML parser found 68 unique IDs, all six role dashboards, and no stray patch markers. No application tests were run because this change is a static design prototype.
- Next: requestor reviews the role dashboards; after design review, a Vue-only presentation pass may be scoped separately.

## 23 November 2026 — Full Cuti Tahunan SRS UI/UX prototype

- The requestor prefers reviewing the full Cuti Tahunan design as one self-contained prototype first; main-system menu organization will be decided later. Backend remains on hold.
- Expanded the prototype to v0.2 with a Liputan SRS index and role-oriented views for all 32 FRs, NFRs, reports, workflow sections, and the accepted 56 CT-DEC baseline. Added staff profile/entitlement, year-end carry-forward and ledger, prorata, changes/cancellation, re-recording, reports/notifications/audit, and negative-balance cases. Updated DOC-023 to map SRS coverage. All displayed data remains synthetic.
- Browser review confirmed the SRS index and new staff, application, HR and balance views are reachable. The sample AL/EL reason rules, Johor holiday exclusions, GM route to Setiausaha ALK, role-specific screens, and HR-configurable EL backdate setting with pending policy details were reviewed. No application tests were run and no Vue/backend/data code was changed.
- Next: requestor review of the full prototype. A Vue-only presentation pass can follow the design review; backend, API, database, and workflow actions remain on hold.

## 23 November 2026 — Cuti Tahunan UI/UX baseline and precedence clarification

- The requestor considers all 56 Cuti Tahunan decisions final for UI/UX and directed the team to design before functional implementation. MOD-005 UI/UX design is authorized; backend, database, and workflow implementation remain on hold.
- A conflict between CT-DEC-001 and CT-DEC-006 on the final approver for General Manager leave was raised and resolved by the requestor: Setiausaha ALK is final. CR-HR-2026-002 records that precedence and the conflicting CT-DEC-006 authority is superseded.
- Created DOC-023 and a self-contained clickable HTML prototype with synthetic data only. It has no app routes, APIs, persistence, actual requests, or approval transactions.
- Checks: source decision precedence and SRS/RTM/CSV/XLSX references reconciled; browser visual/accessibility-tree review covered the summary, AL/EL form, Pengurus Besar and Setiausaha ALK queues, GM final-approver route, and HR calendar/adjustment review. A stale queue badge was corrected to follow the selected role. No application tests were run; no application code/data changed.
- Next: requestor review of the prototype and refine any requested UX changes. Functional MOD-005 implementation remains on hold pending separate project authorization.

## 23 November 2026 — Cuti Tahunan requirements decision update

- The requestor confirmed that responses to the 56 CT-DEC issues were discussed with HR and should be treated as accepted. The four formerly blank response fields (CT-DEC-027/028/048/055) were completed; CT-DEC-010/015/020 were reaffirmed. The decision log records that a named formal HR signatory was not supplied.
- Updated the issue decision DOCX, ADR status note, Cuti Tahunan SRS to v0.4, all 56 MOD-005 rows in the RTM and traceability CSV/XLSX, plus CR-HR-2026-001 and impact analysis. Existing CT-DEC, FR and BR IDs were preserved; 56 planned checks are marked NOT TESTED. The SRS addendum restates seven decisions in detail and points to the issue register for all 56 outcomes.
- No code, schema or database changed. MOD-005 remains on implementation hold pending formal document sign-off and project authorization. The change does not approve payroll deductions by the system; the SRS records HR review and applicable-law conditions only.
- Checks: issue/SRS DOCX renders reviewed; traceability workbook rendered and reviewed; all 56 issue IDs checked against RTM, CSV and workbook; `git diff --check` passed. No application tests were run for this documentation-only update.
- Next: obtain formal document sign-off for the HR requirements baseline, then plan implementation only after separate project authorization. MOD-005 remains on hold.

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
- **Current scope:** System Core, primarily MOD-001 Identity & Access. **HR Core MOD-004..011 remains ON HOLD** for functional implementation; MOD-005 UI/UX design is proceeding under the requestor direction recorded 23 November 2026.
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
