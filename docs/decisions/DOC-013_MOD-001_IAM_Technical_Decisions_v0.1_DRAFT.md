# DOC-013 | MOD-001 Identity & Security Technical Decision Baseline

> **v0.1 • 29 October 2026 • TECHNICAL DESIGN DEFAULTS / REVIEW REQUIRED**
>
> **Planning delegation:** Developer delegated System Core security/module planning and selected Laravel + Vue + MySQL; technical defaults here are recommendations for review, **not authorized HR policies or implementation approval**.
>
> **Coverage:** MOD-001 first, MOD-003 security/audit support, minimal MOD-002 settings. **MOD-004..011 HR Core ON HOLD.**
>
> **Source:** [SCOPE-001](../decisions/SCOPE-001_system-core-vs-hr-core.md), [DOC-005](../srs/DOC-005_Identity_Access_SRS.md), [UM-DEC review](../requirements/MOD-001_USER_MANAGEMENT_REVIEW_v0.3.md), [DOC-008](../architecture/DOC-008_System_Core_Solution_Architecture_v0.1_DRAFT.md), and [SEC-001](../security/SEC-001_System_Core_Security_Baseline_v0.1_DRAFT.md). [DOC-011 Functional Design](../design/DOC-011_MOD-001_Functional_Design_v0.1_DRAFT.md) was consolidated from the now-closed PR #1 into the PR #2 review branch; approval remains pending.

## Status meanings

- **DESIGN DEFAULT:** Engineering's recommended starting design, subject to review and change control. Not a requirement approval.
- **GATED:** No implementation or production use of the affected feature until reviewer decides the unresolved dependency.
- **DEFERRED:** Not planned for the first System Core release.
- **CONFIRMED DIRECTION:** Directly selected stack families or scope sequencing, not acceptance of detailed policies.

## Technical decision ledger

| Tech ID | Topic | Default / choice | Status | UM-DEC | Requirement(s) |
|---|---|---|---|---|---|
| TD-001 | Primary login ID | **Username**, unique per System Core application; independent of staff number and email | DESIGN DEFAULT | UM-DEC-001 | IAM-FR-002 |
| TD-002 | Account minimum fields | Stable opaque ID, display name, username, active status and role assignment; email optional and not assumed verified | DESIGN DEFAULT | UM-DEC-002 | IAM-FR-001/008 |
| TD-003 | Identifier normalization | Explicit documented canonicalization (trim, case-fold within permitted ASCII username profile), DB unique constraint, concurrency tests | DESIGN DEFAULT | UM-DEC-001 | IAM-FR-002 |
| TD-004 | Initial roles | **System Admin** + **Standard User** initially; proposed delegated **User Admin** role is **not automatically seeded/activated** without approved grant boundaries | DESIGN DEFAULT | UM-DEC-003/004 | IAM-FR-006/007 |
| TD-005 | First privileged account | One-time, operator-authorized, idempotent secure bootstrap; no known default credentials and no secrets written to terminal logs/artifacts | DESIGN DEFAULT / SECURITY GATED | UM-DEC-003 | IAM-FR-001/010 |
| TD-006 | Self-registration | Disabled by default, with no public signup route | DESIGN DEFAULT | UM-DEC-007 | IAM-FR-011 |
| TD-007 | Account lifecycle | Active/Inactive; optional Pending Setup only if approved onboarding demands it; no routine hard delete | DESIGN DEFAULT | UM-DEC-008 | IAM-FR-005 |
| TD-008 | Grant and target permission | Backend per-action and per-target authorization, default-deny; no self-escalation; no HR authorization inherited through System Admin | DESIGN DEFAULT | UM-DEC-004 | IAM-FR-006/007/008 |
| TD-009 | Last-admin protection | Atomic protection against removal/deactivation of the last active authorized System Admin, including simultaneous changes | DESIGN DEFAULT | UM-DEC-003/004 | IAM-FR-010 |
| TD-010 | Browser auth transport | **Laravel first-party session and CSRF**, same-origin Inertia/Vue; no browser JWT/localStorage tokens or separate API for standard pages | DESIGN DEFAULT | UM-DEC-006 | IAM-FR-003/009 |
| TD-011 | Session revocation | Back-end check on protected requests; deactivation or privilege revocation invalidates effective access immediately; database-backed sessions preferred for server-side control | DESIGN DEFAULT | UM-DEC-006 | IAM-FR-009 |
| TD-012 | Session lifetime | No exact idle/absolute timeout chosen until policy and risk review; disable indefinite privileged sessions / uncontrolled 'remember me' by default | GATED (TIMEOUT POLICY) | UM-DEC-006 | IAM-FR-003/009 |
| TD-013 | Password controls | If local single-factor passwords: minimum **15** characters, permit **at least 64**, no arbitrary character-class rules, block common/compromised values, rate limit; strong adaptive one-way hashing supported by runtime | DESIGN DEFAULT / SECURITY GATED | UM-DEC-011 | SYS-SEC-002 |
| TD-014 | Account activation/recovery | **No unverified reset or credential delivery flow**. Choose verified secure delivery and identity-proof process before enabling activation/reset; recovery is not assumed to be SMTP/WhatsApp/SSO | GATED (RECOVERY METHOD) | UM-DEC-005 | IAM-FR-004 |
| TD-015 | MFA | Require approved MFA method and secure recovery for privileged admins **before production**; no irreversible or bypass-prone half-implemented MFA in prototype | SECURITY GATED (MFA) | UM-DEC-011 | SYS-SEC-001/002 |
| TD-016 | Audit | Record actor, target, action, outcome, timestamp for privileged user/role events; redact credentials, tokens and sensitive profile values; retention/read permissions TBD | DESIGN DEFAULT / RETENTION GATED | UM-DEC-012 | IAM-AUD-001, SYS-AUD-001 |
| TD-017 | API exposure and shared props | Inertia page props explicit allowlist; exclude password hash, tokens, large full models and private account fields; authorize each route server-side | DESIGN DEFAULT | UM-DEC-004/011 | SYS-SEC-001/002 |
| TD-018 | Data and module isolation | User account does not require Employee or HR Core schema; MySQL transactional constraints and FK/index design **not yet frozen** | CONFIRMED SCOPE / DESIGN DEFAULT | UM-DEC-009 | SYS-DR-001 |
| TD-019 | Stack integration | Laravel + Vue + MySQL are **user-selected**; **Inertia 3, Vue 3 TS, Laravel 13 and MySQL 8.4 LTS are preferred technical targets**, version pins reviewed at bootstrap | CONFIRMED FAMILIES / PROPOSED DETAILS | N/A | DOC-008 |
| TD-020 | Physical structure | **No folders, concrete migrations, ORM relations or deployment topology decided yet** | EXPLICITLY DEFERRED | N/A | DOC-008 |
| TD-021 | User-facing language | BM-first user-facing wording proposed, with maintainable localization; official target language/device mix TBD | DESIGN DEFAULT / UX GATED | UM-DEC-010 | SYS-NFR-001 |
| TD-022 | External identity/API | No SSO, external/mobile API, JWT, SSR, Pinia or real-time stack unless future accepted requirement demands it | DEFERRED | UM-DEC-001 | DOC-008 |

**Passwords:** The proposed ≥15-character single-factor minimum, ≥64 supported maximum and no arbitrary composition rules follow NIST SP 800-63B (Revision 4) guidance. If MFA is used, formally set local password policy based on assurance level and operational review; never weaken the account protections silently. See official [NIST guidance](https://pages.nist.gov/800-63-4/sp800-63b/authenticators/).

## Privilege safety rules for detailed design

1. **No default privileged user in public seed/demo artifacts**. Bootstrapping is a separate operator-only process with identity verification, restrictive execution rights and an audit event. The method/CLI interface is not a file-structure commitment.
2. **No delegation by accident**. A User Admin role is a candidate only; do not enable role management or seed privileged grants without explicit policy.
3. **No hidden superuser bypass**. Emergency recovery needs a reviewed break-glass protocol, separate authorization and immutable evidence.
4. **No background password reset loophole**. If email delivery has not been approved/verified, reset and activation routes remain disabled (not 'temporary password by chat').
5. **No orphaned IAM history**. Deactivation/role changes preserve audit history. Deletion/retention obligations await IT/compliance.
6. **No HR scope creep**. Never implement `employee_id`, department, position, salary, or HR policy in System Core based solely on future module labels.

## Acceptance prerequisites and dependencies

| Gate ID | Before | Minimum evidence | Current |
|---|---|---|---|
| TD-GATE-01 | Start auth/account implementation | Confirm TD-001/002/003 fields, uniqueness and onboarding/first-admin policy | OPEN |
| TD-GATE-02 | Grant privileged User Admin rights | Reviewed role-permission matrix, target scope, negative permission tests | OPEN |
| TD-GATE-03 | Enable reset or account activation | Approved identity verification + secure delivery, token lifecycle tests | BLOCKED |
| TD-GATE-04 | Allow privileged production login | Approved MFA + recovery, hardened session/cookies, risk review | BLOCKED |
| TD-GATE-05 | Treat deactivation as secure | Tests show revoked sessions/permissions and last-admin concurrent protection | NOT TESTED |
| TD-GATE-06 | Accept production audit/data handling | Retention/access policy, data classification, privacy review and backup/restore evidence | BLOCKED |
| TD-GATE-07 | Freeze physical model/file layout | Explicit technical design workshop following approved decisions; DOC-008 update | DEFERRED |
| TD-GATE-08 | Claim DEV READY or release | Review relevant existing GATE-01..08, code/test evidence and accountable sign-off | NOT PASSED |

**No existing release gate is passed or replaced by these extra design gates.**

## Review handoff

Engineering can review these defaults now without knowing HR business requirements. To promote one to **ACCEPTED**, record actor/reviewer, date, exact decision, alternatives, why and linked SRS/RTM/Excel/CSV changes **if normative behavior changes**. Do not mark UM-DEC-001..012 as RESOLVED merely because a default is documented.

**Next technical deliverable after review:** contract-level security/authorization and onboarding verification, followed by *separate joint discussion* of ERD and physical file structure. **No implementation/code in this PR.**

## P0.6 Data model companion

[DOC-015 Logical Data Model](../data/DOC-015_MOD-001_Logical_Data_Model_v0.1_DRAFT.md) develops the logical entities and 12 data integrity invariants associated with the existing proposed defaults. [Data Integrity Test Matrix](../quality/MOD-001_Data_Integrity_Test_Matrix_v0.1_DRAFT.md) maps 18 proposed scenarios to existing test IDs. All physical database decisions, employee relationships, code layout and deployment choices remain **DEFERRED**, and all UM-DEC approvals remain OPEN.

## P0.7 Engineering review status

[DOC-016 Technical Readiness](../reviews/DOC-016_P0.7_System_Core_Technical_Readiness_v0.1_DRAFT.md) provides preferred engineering design defaults with precise distinction between **selected for design review** and **approved organizational policy**. All original UM-DEC decisions remain OPEN until signed off; no production session timeout, recovery channel, audit retention or privileged MFA recovery method has been accepted. File structure remains deferred.

## 30 October 2026 owner development direction

The technical owner selected the DOC-019 PD-001..PD-010 development baseline for the bounded WP-040B schema implementation. This records implementation direction only; it does **not** approve organizational security policy, close UM-DEC/DATA-DEC items, approve a release gate, or authorize production use. The resulting schema and code remain subject to review.

| PD | Selected for development | Boundary / status |
|---|---|---|
| PD-001 | BIGINT unsigned internal IDs | Development baseline selected; no public identifier implied |
| PD-002 | Username with canonical lowercase value, max 64 characters | ASCII syntax and deterministic no-loss normalization implemented; username login remains inactive |
| PD-003 | ACTIVE / INACTIVE | PENDING_SETUP deferred; authentication enforcement not implemented |
| PD-004 | Relational RBAC with Laravel Policies/Gates | Schema only; no assignment endpoints or approved role matrix |
| PD-005 | Shared `iam_guard` transaction lock primitive | Infrastructure row and MySQL lock compatibility only; no last-admin guarantee claimed |
| PD-006 | Native Laravel database sessions | Existing starter session table retained |
| PD-007 | Restricted `audit_events` with allowlisted metadata | Minimal schema/model foundation; no reader route or retention policy |
| PD-008 | Existing Laravel Vue starter structure | No custom framework or DDD structure |
| PD-009 | Typed Inertia props and server-side authorization | Existing application direction; no new privileged UI or authorization endpoints |
| PD-010 | Pinned PHP 8.5.12 / MySQL 26.10.0 runtime | Development and disposable-test target; production hosting remains undecided |

**Decision state:** owner-selected for development, not formal IT/HR/security approval. The 12 UM-DEC items, applicable DATA-DEC items, release gates, MFA/recovery, audit retention/privacy and production readiness retain their existing statuses. No normative SRS/RTM behavior or requirement ID is changed by this implementation direction.
