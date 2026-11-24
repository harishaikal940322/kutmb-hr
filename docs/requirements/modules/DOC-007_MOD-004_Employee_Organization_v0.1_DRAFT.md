# DOC-007 | MOD-004 Employee & Organization
> v0.3 DRAFT · 24 November 2026 · Requestor accepted Option A, minimum employee fields, optional manual account link and proposed permission matrix; independent major HR Core module.
> F-009 · Existing MOD-004 registry ID · Implementation HOLD pending HR requirements approval and explicit development authorization.

## 1. Module boundary and release direction

MOD-004 owns employee identity (distinct from account identity), organization structure and effective-dated employee/office assignments. It is an independent major module, not a submodule of MOD-005. Initial release is limited to the minimum foundation needed by Cuti Tahunan, delivered before CT-P02–09. Later employee features (personal/family/education records, contracts and documents, broader employee lifecycle) are not included or approved; extend this module only through requirements/change control. No payroll, attendance, claims, performance or speculative fields.

Source: existing F-009 module registry; requestor choice A on 24 November 2026; MOD-005 SRS v0.5 FR-01–03/07/19–20 and current CT-DEC-005/006/032/048/050/051; existing SCOPE-001. Detailed HR decisions and actual staff records remain to be confirmed. This is the formerly reserved DOC-007, not an approved replacement to the MOD-005 SRS.

## 2. Proposed submodule breakdown (planning labels, not new registry IDs)

| Submodule | Foundation release scope | Deferred / excluded |
|---|---|---|
| Employee Directory & Profile | HR list/search, register and update minimum employee details and confirmed employment dates; read own limited profile | Personal/family/education, sensitive IDs, documents and broader lifecycle |
| Organization & Units | HR manage unit code/name and active status; retain historical references | Multi-company, branches/hierarchy unless required and approved |
| Employment Category | HR manage actual categories/levels needed for leave; no inferred rates | Grading, compensation and job catalogue beyond foundation |
| Employee Assignment | Effective-dated unit/category assignment; transfer/promotion history and validation | Broader talent management |
| Employee–Account Association | Explicit HR/IT-authorized optional unique link to existing user; no automatic matching or account creation | Account provisioning outside MOD-001 |
| Approval Office Assignment | Named PU per unit, PB and Secretary ALK assignment, effective dates, no automatic delegation; authorized HR role ownership for adjustment | New approval hierarchies or workflow engine |

These areas belong to MOD-004. Leave requests, balance/entitlement policy, leave approvals and adjustments belong to MOD-005 and consume MOD-004 data. MOD-001 owns account lifecycle and role/permission grants; MOD-003 audit remains shared.

## 3. Minimum field baseline accepted by requestor

| Record | Proposed fields | Notes |
|---|---|---|
| Employee | employee number, full name (display_name), service start date | Required; actual number format/authority to confirm; no IC, home address or personal contact collection by default |
| Employee | confirmation date, service end date | Nullable when not yet confirmed/not ended; dates chronological; HR-confirmed actual values |
| Employee | linked user ID | Nullable unique FK; explicit association; no forced account for every staff record; no automatic link by email/name |
| Unit | code, name, active status | Required; approved reference values maintained by HR |
| Employment category | code, name, active status | Required; real category/level mapping HR to supply; no guessed entitlement |
| Assignment | employee, unit, category, effective from/to | Unit, category and from required for complete employee foundation record; to nullable; no overlapping active period; transfer history retained |
| Approval office | existing user, office code, unit where applicable, effective from/to | Explicit scope; PU requires unit; PB/Secretary organization-wide; no self-support/approval; Secretary may be nonemployee |

Actual schema follows MOD-005 technical plan section 6A (five proposed tables). No duplication of physical design or schema migrations here. Employee number is an organizational reference, not username. Create an employee and initial required unit/category assignment atomically; no incomplete employee published to leave workflow. Missing mandatory current assignment blocks affected leave submission rather than granting a default approver. A record can exist before an account is created; without explicit account link it cannot use own self-service. Account disablement does not delete or terminate employee records; employment end does not silently change account status (MOD-001 controls account lifecycle). Record association changes with authorized actor and reason; avoid reassigning historical leave identity.

## 4. Access and UI proposal

Independent main menu Employee & Organization (BM display: Pekerja & Organisasi), with Directory, Units, Categories and Assignments. Account linking is an explicitly authorized action, not a public directory feature. Authorized HR manages employee/organization records and effective dates; IT keeps IAM management without HR-data access by default. Role permission and office assignment are distinct checks. HR Executive/HR Manager adjustment distinction is consumed by MOD-005; no automatic ordinary leave approval for HR. Actual actor/permission owners and sensitive field scope require HR/IT approval. UI follows existing shared Vue components, Bahasa Melayu, light default/dark, responsive desktop/tablet/mobile; no new navigation framework or role switcher in functional UI.

## 4A. Least-privilege permission baseline (requestor accepted; formal HR/IT approval pending)

Inspected existing `config/iam.php`, PermissionCatalog, NavigationMenuService, protected routes and AppServiceProvider active-account Gate. Currently IAM catalogue contains iam.rbac.manage and iam.users.view; HR permissions do not yet exist. Reuse existing permission catalogue, menu configuration and Spatie assignment process; candidate codes below are not grants. Each operation also checks active user, target and effective assignment; a visible menu or role title is not authorization.

| Candidate permission | Proposed holder / scope |
|---|---|
| hr.employees.view | Authorized HR Executive and HR Manager, employee directory and minimum profile only |
| hr.employees.manage | Authorized HR Executive and HR Manager, register/update minimum employee records and employment dates |
| hr.organization.manage | HR Manager; unit/category reference maintenance; Executive views approved references for employee work |
| hr.assignments.manage | Authorized HR Executive and HR Manager; record effective unit/category history |
| hr.accounts.link | HR Manager, explicit employee-to-existing-account link/unlink; IT supplies/manages account in MOD-001, no general HR-data access or link grant by IT role |
| hr.offices.manage | HR Manager, record formally appointed office holders and effective scope; no implicit grant of IAM roles, self-appointment or delegation |
| hr.profile.self.view | Linked active employee only, own minimum profile; read-only |
| hr.organization.scoped.view | Relevant office holder only, minimum names/unit/assignment data needed for assigned leave responsibility; not unrestricted directory |

HR Manager and Executive names are role descriptions, not automatically assigned accounts. Existing authorized System Admin manages Spatie role/permission grants, not business data by default. Separate business appointment data from permission grants: assigning a PU/PB/Secretary office does not grant system permission, and granting a role does not create office authority. Manager's own office assignment or account link must be handled by an independently authorized actor, never self-authorized; if no such actor is approved, block that operation pending HR/IT instruction. No additional approval workflow for routine employee maintenance proposed; no broad directory for Staf, PU, PB, Secretary or IT. Do not introduce HR leave adjustment approval in MOD-004; CT-DEC-040 belongs to MOD-005.

Use proposed `Pekerja & Organisasi` menu with Profil Staf, Unit, Kategori Pekerjaan, Penempatan and Penetapan Pegawai; only authorized screens visible. Link/unlink is a protected profile action, not an open user lookup. Limit candidate account results to necessary non-sensitive identifiers and only authorized actors; never expose credentials/MFA fields. Server denies direct unauthorized routes even if menu mappings change. Audit action/target/actor/date and safe structured change references; keep reasons and sensitive data within authorized records, not general logs.

## 4B. Role templates (requestor direction, proposed implementation)

The requestor accepted the section 4A permission matrix and requested templates, noting existing RBAC support. Remote code review confirms `resources/js/pages/identity/Roles.vue` creates reusable roles with manually selected permissions and edits custom roles; `IdentityRoleController` validates permission codes against `PermissionCatalog::assignableCodes()` and saves/audits role mutations transactionally. `config/iam.php` defines permission and menu catalogues; existing migration seeds only the two IAM permissions, not HR roles. Protected System Admin is immutable through the role UI. Account invitations and user-role assignment are explicitly unavailable on that screen. No preset selector or HR permission catalogue currently exists.

Use existing MOD-001 role creation UI with optional template dropdown; no new authorization engine, template database, separate role management screen or automatic role grants. Suggested implementation: versioned preset definitions in existing IAM configuration (or a focused sibling config if required by local conventions), exposed as safe Inertia props only to authorized access administrators. Each template preselects an editable subset of current assignable permission codes; the final role is an ordinary custom Spatie role, not a live inheritance relationship. Show name, description, available permissions and any not-yet-delivered capabilities; only offer ready codes from the actual catalogue. Unavailable required permission blocks template application with an explanation, not silent partial grant. The administrator explicitly reviews and saves using the existing create action. Never overwrite existing custom roles, change protected System Admin, assign a role to a user, change menu mappings or create an office appointment merely by choosing a template. Changes to template definitions affect new drafts only, never existing roles.

| Template (candidate key) | Initial MOD-004 permission defaults |
|---|---|
| Staf (staff) | hr.profile.self.view; own linked employee record only |
| Eksekutif HR (hr-executive) | hr.employees.view, hr.employees.manage, hr.assignments.manage; minimum approved reference reads |
| Pengurus HR (hr-manager) | hr.employees.view, hr.employees.manage, hr.assignments.manage, hr.organization.manage, hr.accounts.link, hr.offices.manage; own-employee read only if separately applicable |
| Pengurus Unit (unit-manager) | hr.profile.self.view, hr.organization.scoped.view; actual named unit assignment required |
| Pengurus Besar (general-manager) | hr.profile.self.view, hr.organization.scoped.view; actual named office assignment required |
| Setiausaha ALK (alk-secretary) | hr.organization.scoped.view restricted to necessary assigned workflow context; no employee entitlement/self-service by virtue of office |
| Custom / Kosong | No preselected permissions |

Existing protected System Admin is not a selectable template; preserve existing administrative authority, not HR-data access. MOD-005 abilities (leave submit/support/approval, HR adjustment prepare/approve, reporting) will be added to the relevant templates only when authorized and implemented; don't register speculative permission codes or grant them in P01. If an HR officer is also a linked employee, own-profile access follows separately assigned staff permission. Use current backend assignable-code validation and per-target authorization for all operations; prevent unauthorized template access, system-only codes, unregistered permissions, duplicate role keys, self-appointment and unauthorized direct access. Preview checkbox selections in a local form; changing preset must not silently discard an edited draft (confirm replacement). No extra approval workflow or custom template editor in the initial slice.

Ownership: role template UI/config belongs to MOD-001 supporting scope and is a bounded dependency of CT-P01; HR role defaults documented here, no duplicate module. Extend existing role catalogue/creation path only when explicitly authorized alongside P01; local agent must confirm IAM role-assignment capability or plan a separately authorized bounded action, not claim it exists. Focused planned tests: preset populates exact available codes, manual edits persist only after explicit save, stale/unknown/system-only codes denied, existing role and user assignments unchanged, protected role immutable, no HR privilege from IT role, office assignment checks remain independent, light/dark responsive selection. No source code changed or tests run by this design.

## 5. Delivery sequence (CT-P01 dependency)

1. Requestor minimum fields/link and permission matrix accepted; formal HR requirements/appointment authority and explicit development authorization remain. Include bounded MOD-001 role-template support in P01 plan. Use synthetic configurable unit/category values for development only; actual HR-approved values and named appointments before real usage.
2. Add authorized schema/models and constraints, no production import.
3. Implement employee/unit/category lists/forms and effective-dated assignments, link controls and office mapping using Laravel/Inertia/Vue patterns.
4. Verify positive and negative scoped access, duplicate employee number/account link, invalid date/overlap, inactive account, nonemployee Secretary, transfer history, audit and rollback; focus checks then consolidated phase review.
5. Deliver independent MOD-004 foundation to MOD-005; no leave ledger or leave workflow code in MOD-004.

## 6. Interaction gate

Choice A, independent module declaration, eight minimum profile fields (five required, three optional), and optional manual one-to-one account association accepted by requestor. Required: employee number, full name, service start date, unit, category; optional: confirmation date, service end date, user link. Permission matrix accepted by requestor on 24 November 2026; role template direction recorded in section 4B. Remaining pre-coding gate: formal HR requirements and actual appointment/permission authority confirmation, plus explicit development authorization for CT-P01 and bounded MOD-001 role-template support. Actual employee number format, real unit/category values and named appointments remain HR inputs before real-data setup; no need to delay synthetic configurable CRUD design for those values if explicitly authorized. Q-005 partially resolved at requestor level; HR formal confirmation remains. Do not treat requestor choice of scope as HR/IT sign-off or code permission. Existing module registry lifecycle stays HOLD until controlled approval. Full employee-management requirements remain deferred, not rejected. No implementation tests run.
