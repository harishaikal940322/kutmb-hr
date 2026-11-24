- 2026-11-24: Added an unexposed integer working-session counter using explicit schedule and holiday inputs. See CURRENT_HANDOFF.

- 2026-11-24: Added reversible audited cancellation for non-executable entitlement drafts. See CURRENT_HANDOFF.

- 2026-11-24: Added auditable correction history for non-executable entitlement drafts. See CURRENT_HANDOFF.

- 2026-11-24: Added non-executable local entitlement proposals with explicit unset rates and recorded synthetic UI/guard verification. See CURRENT_HANDOFF.

- 2026-11-24: Added reversible, audited calendar draft cancellation with replacement-source safeguards. See CURRENT_HANDOFF.

- 2026-11-24: Added inclusive effective-period conflict warnings for draft leave calendar and policy versions. See CURRENT_HANDOFF.

- 2026-11-24: Added audited draft policy edits with prior settings retained and confirmed versions locked. See CURRENT_HANDOFF.

- 2026-11-24: Added local audited working-time policy drafts with explicit unset values; no confirmation or calculations. See CURRENT_HANDOFF.

- 2026-11-24: Added empty CT-P02 policy-version schema with nullable settings and no inferred defaults. See CURRENT_HANDOFF.

- 2026-11-24: Added audited draft-calendar date/name corrections with retained prior values; confirmed versions stay locked. See CURRENT_HANDOFF.

- 2026-11-24: Added non-mutating weekend, collision and bounded replacement guidance to local calendar drafts. See CURRENT_HANDOFF.

- 2026-11-24: Added local, audited leave calendar drafts with date and replacement-source validation; no calendar activation. See CURRENT_HANDOFF.

- 2026-11-24: Added empty, versioned CT-P02 calendar schema to local development; no calendar or leave operation activated. See CURRENT_HANDOFF.

- 2026-11-24: Restricted local HR navigation configuration and enforced fixed menu permissions server-side; ran focused employee and RBAC regression. See CURRENT_HANDOFF.

- 2026-11-24: Added local HR role templates using delivered permissions; incomplete office templates remain unavailable. See CURRENT_HANDOFF.

- 2026-11-24: Added a local linked-employee self-profile with fail-closed current-assignment display. See CURRENT_HANDOFF.

- 2026-11-24: Added local approval-office appointments with scoped date overlap checks, independent actor and audit. See CURRENT_HANDOFF.

- 2026-11-24: Added local independently authorized employee/account association with restricted reason history and fail-closed leave-history boundary. See CURRENT_HANDOFF.

- 2026-11-24: Added local employee profile edits with assignment-date consistency checks and audit. See CURRENT_HANDOFF.

- 2026-11-24: Added local, audited employee assignment history and forward transfers with transaction-locked overlap checks. See CURRENT_HANDOFF.

- 2026-11-24: Added local employee directory and atomic employee/initial-assignment creation, with separate ungranted view/manage permissions. See CURRENT_HANDOFF.

- 2026-11-24: Added local-only, permission-gated unit and employment-category maintenance with audited mutations; no grants or reference data were added. See CURRENT_HANDOFF.

- 2026-11-24: Added and verified the additive, empty CT-P01 employee/organization schema on the existing local development database; application operations and operational approval remain outstanding. See CURRENT_HANDOFF.

# Documentation change log

| Date | Version | Change | Approval |
|---|---|---|---|
| 2026-10-29 | v0.1 | Initialize HR Foundation specification as Markdown source of truth; 32 draft requirements, 14 unresolved questions, 8 unpassed gates | NOT APPROVED |

No application features were implemented as part of the initial documentation baseline; later implementation entries are recorded below.

| 2026-10-29 | v0.2 | Add MOD-001..MOD-011, compact Excel traceability core, CSV change/impact registers and module change-control process | NOT APPROVED |

| 2026-10-29 | v0.3 | Reclassify MOD-001..003 as System Core, MOD-004..011 as HR Core HOLD; publish v0.3 Excel and 12-item MOD-001 review queue. All 32 requirements remain DRAFT. | NOT APPROVED |

| 2026-10-29 | DOC-008 arch v0.1 | Draft Laravel + Vue + MySQL System Core architecture, Inertia 3 preference, SEC-001 security threat model, ADR-006 and DOC-012 Vue onboarding; defer physical structure and code | NOT APPROVED |

| 2026-10-29 | MOD-001 TD v0.1 | Draft 22 IAM/security technical defaults, 8 additional readiness gates and Laravel–Vue auth/authorization contracts; reset, MFA and audit retention remain gated. No requirement, module, Excel or code changes. | NOT APPROVED |

| 2026-10-29 | P0.6 Logical data v0.1 | Draft DOC-015 System Core logical model, 12 proposed invariants, 12 unresolved data decisions and 18 MySQL/security test subcases. No migrations, physical ERD, file structure, module scope or SRS/RTM changes. | NOT APPROVED |

| 2026-10-29 | P0.7 Technical readiness v0.1 | Draft DOC-016 with 16 proposed engineering choices and 12 unresolved review items; no requirement/module/RTM/Excel changes, no code, no approval | NOT APPROVED |

| 2026-10-29 | P0.8 Physical Design Workshop v0.1 | DOC-017 proposed MySQL tables/constraints, DOC-018 Laravel/Vue source organization options and DOC-019 ten physical design review decisions; no migrations, code or approved schema | NOT APPROVED |

| 2026-10-29 | P0.9 Implementation Blueprint v0.1 | Draft DOC-020 (12 gated Codex work packages) and DOC-021 (PR #1/#2 reconciliation and 26+18 scenario mapping to 18 base test IDs); no app code or requirement/Excel changes | NOT APPROVED |

| 2026-10-29 | MOD-001 FD v0.1 | Draft DOC-011 user-management functional design and 26 planned positive/negative scenarios; no normative requirement changes, no test execution | NOT APPROVED |

| 2026-10-29 | Documentation exit / P1.1 handoff | Consolidated DOC-011 + functional tests and shared guidance into PR #2 review branch; prepared DOC-022 restricted starter bootstrap task; no code/approval/merge/test claims | NOT APPROVED |

| 2026-10-29 | Git workflow instruction | Enforce one canonical checkout, feature branch → tested/approved merge → main; avoid future extra worktrees, preserve local WP-020 commit awaiting integration | OWNER DIRECTION |

| 2026-10-30 | WP-040B identity/RBAC schema development direction | Owner selected DOC-019 PD-001..PD-010 for synthetic-data schema development; additive user identity fields, relational RBAC, IAM guard and restricted audit foundation implemented on review branch. Organizational approvals and release gates remain open. | OWNER DEVELOPMENT DIRECTION; NOT HR/IT/SECURITY APPROVAL |

| 2026-11-24 | MOD-005 UX-R01–R05 | Simplify AL/EL form and add pre-form and projected balances in Vue/HTML preview; retain >5-day threshold, align Unit Manager route, document unchanged notice policy. No functional or normative changes; local checks pending. | REQUESTOR UX DIRECTION ONLY |

| 2026-11-24 | DOC-023 v0.5 / MOD-005 TD v0.2 | Record requestor UX acceptance and freeze at ffdeda3; refine bounded employee/org physical proposal and current HR answer alignment. No normative or functional changes. | REQUESTOR UX ACCEPTED; TECHNICAL DESIGN IN REVIEW; IMPLEMENTATION HOLD |

| 2026-11-24 | MOD-005 TD v0.3 | Expand existing technical planning baseline with physical contracts, workflow, FE/BE integration, permission separation, all package delivery/acceptance and genuine interaction gates; verify 56 populated HR responses. No functional/normative changes or test execution. | PLANNING PREPARED; CT-P01 AUTHORIZATION BLOCKED |

| 2026-11-24 | DOC-007 v0.1 / MOD-004 Option A | Record independent major Employee & Organization module with minimum foundation release, proposed submodules/fields and CT-P01 dependency. No new IDs or normative/functional changes. | REQUESTOR SCOPE ACCEPTED; IMPLEMENTATION HOLD |

| 2026-11-24 | DOC-007 v0.2 | Record requestor acceptance of minimum employee profile and optional explicit account link; propose least-privilege permission matrix; Q-005 partially resolved. No grants or functional changes. | REQUESTOR FIELD DESIGN ACCEPTED; IMPLEMENTATION HOLD |

| 2026-11-24 | DOC-007 v0.3 | Record requestor acceptance of permission matrix and requested editable role presets reusing existing MOD-001 role UI/catalogue; update CT-P01 scope and Q-004. No grants or implementation. | REQUESTOR PERMISSION DESIGN ACCEPTED; IMPLEMENTATION HOLD |

| 2026-11-24 | DOC-007 v0.4 | Record requestor compact permission-matrix design acceptance for shared Create/Edit Role selector; update CT-P01 planned UI and checks. No functional changes. | REQUESTOR UI DESIGN ACCEPTED; IMPLEMENTATION HOLD |

| 2026-11-24 | Delegated development direction | Clarify requestor UI/UX authority; select bounded MOD-001 compact role selector/preset mechanism first, HR Core policy gate unchanged; local checkout unavailable remotely. | SYSTEM CORE SLICE AUTHORIZED; LOCAL PREFLIGHT PENDING; HR CORE HOLD |

| 2026-11-24 | MOD-001 RBAC enhancement | Deliver shared permission selector, editable IAM template and accessible confirmation; record rollback-only MySQL and browser evidence with limitations in current handoff. No HR permissions or schema changes. | SYSTEM CORE DEVELOPMENT; NOT BUSINESS OR RELEASE APPROVAL |

| 2026-11-24 | Autonomous local development direction | Record requestor authorization for bounded synthetic MOD-004/005 development, independent technical blocker resolution and automatic dependency-ready continuation; preserve formal policy/operational/release gates. | LOCAL DEVELOPMENT AUTHORIZED; NO BUSINESS/RELEASE APPROVAL |
