# DOC-007 | MOD-004 Employee & Organization
> v0.1 DRAFT · 24 November 2026 · Requestor selected Option A: minimum foundation, retained as independent major HR Core module.
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

## 3. Minimum field proposal for review

| Record | Proposed fields | Notes |
|---|---|---|
| Employee | employee number, display name, service start date | Required; actual number format/authority to confirm; no IC, home address or personal contact collection by default |
| Employee | confirmation date, service end date | Nullable when not yet confirmed/not ended; dates chronological; HR-confirmed actual values |
| Employee | linked user ID | Nullable unique FK; explicit association; no forced account for every staff record; no automatic link by email/name |
| Unit | code, name, active status | Required; approved reference values maintained by HR |
| Employment category | code, name, active status | Required; real category/level mapping HR to supply; no guessed entitlement |
| Assignment | employee, unit, category, effective from/to | From required, to nullable; no overlapping active period; transfer history retained |
| Approval office | existing user, office code, unit where applicable, effective from/to | Explicit scope; PU requires unit; PB/Secretary organization-wide; no self-support/approval; Secretary may be nonemployee |

Actual schema follows MOD-005 technical plan section 6A (five proposed tables). No duplication of physical design or schema migrations here. Employee number is an organizational reference, not username. Missing mandatory assignment blocks affected leave submission rather than granting a default approver.

## 4. Access and UI proposal

Independent main menu Employee & Organization (BM display: Pekerja & Organisasi), with Directory, Units, Categories and Assignments. Account linking is an explicitly authorized action, not a public directory feature. Authorized HR manages employee/organization records and effective dates; IT keeps IAM management without HR-data access by default. Role permission and office assignment are distinct checks. HR Executive/HR Manager adjustment distinction is consumed by MOD-005; no automatic ordinary leave approval for HR. Actual actor/permission owners and sensitive field scope require HR/IT approval. UI follows existing shared Vue components, Bahasa Melayu, light default/dark, responsive desktop/tablet/mobile; no new navigation framework or role switcher in functional UI.

## 5. Delivery sequence (CT-P01 dependency)

1. Confirm minimum data, permission owners and authorization; document actual approved categories/unit values or synthetic fixtures for development.
2. Add authorized schema/models and constraints, no production import.
3. Implement employee/unit/category lists/forms and effective-dated assignments, link controls and office mapping using Laravel/Inertia/Vue patterns.
4. Verify positive and negative scoped access, duplicate employee number/account link, invalid date/overlap, inactive account, nonemployee Secretary, transfer history, audit and rollback; focus checks then consolidated phase review.
5. Deliver independent MOD-004 foundation to MOD-005; no leave ledger or leave workflow code in MOD-004.

## 6. Interaction gate

Choice A and independent module declaration are accepted by requestor. Remaining required confirmations: employee number and mandatory/nullable field proposal; optional one-to-one account association and HR/IT ownership of linking; actual unit/category taxonomy and authority to assign office/HR permissions; formal HR requirements approval and explicit development authorization. Do not treat requestor choice of scope as HR/IT sign-off or code permission. Existing module registry lifecycle stays HOLD until controlled approval. Full employee-management requirements remain deferred, not rejected. No implementation tests run.
