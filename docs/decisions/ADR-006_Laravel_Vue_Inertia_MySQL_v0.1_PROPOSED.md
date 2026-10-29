# ADR-006 | Laravel + Vue + MySQL with Inertia 3 for System Core

> **Date:** 2026-10-29  
> **Status:** PROPOSED TECHNICAL DECISION / REVIEW REQUIRED  
> **Delegated planning:** technical requestor authorized System Core security and module planning, and selected Laravel + Vue + MySQL families.  
> **Authority:** still subject to technical security review and existing project release gates.

## Context

- Development target is System Core, beginning with MOD-001 User Management.
- Developer is proficient with Laravel, has no prior production Vue experience.
- HR Core requirements are not available; no employee schema may be assumed.
- Physical file structure is explicitly reserved for a later discussion.
- Avoid duplicating authentication, permissions, API infrastructure or deployment units without a requirement.

## Preferred decision

**Use Laravel 13 + Vue 3 + Inertia 3 + TypeScript + MySQL 8.4 LTS as a single modern Laravel monolith.** Versions are targeted baselines, to be revalidated for supported releases/patches during actual bootstrap. Use Laravel cookie/session auth with CSRF; Laravel is the sole authorization source. Install no additional auth/RBAC package without a focused decision and threat review.

**Default planning choices** (not yet policy approval):
- Username unique and independent of employee/staff ID, with email optional as a contact field pending recovery policy.
- Public self-registration disabled.
- No mandatory Employee record for System Core accounts.
- Audit and privileged security work delivered concurrently with account management.
- SSR, separately deployed API, external SSO and client global store are out of initial scope.
- Admin MFA required as a recommended production security control; mechanism and recovery path must be accepted.
- MySQL DB version chosen only after hosting compatibility and recovery requirements review.

## Alternatives reviewed

| Alternative | Benefit | Trade-off | Current judgment |
|---|---|---|---|
| Separate Laravel REST API + standalone Vue SPA | Independent API/mobile integration from day one | Duplicated client auth, CSRF/CORS/token integration, 2 builds/deployments, extra learning overhead | Not justified by current requirements |
| Laravel Blade + Livewire | Developer already familiar; quick integrated forms | Does not fulfill selected Vue stack and frontend learning goal | Not selected |
| Laravel + Vue + Inertia | Reuse backend routing/auth while learning Vue progressively | Inertia protocol coupling, not a generic public API contract | Preferred |
| Pure client-side SPA auth with browser JWT | Decoupled frontend | Added token storage, expiry/refresh and cross-origin threat surface | Avoid for initial browser UI |

## Consequences

**Positive:** simpler operations and identity boundary; Vue learning is staged; no unnecessary API surface; easier HTTP test coverage and audit integration.

**Negative / risks:** Inertia props and shared data can accidentally disclose sensitive account info; requires explicit response projection. Team must learn Vue Composition API/TypeScript, form behavior, and Inertia navigation. Future mobile/external integration may need separately defined API contracts.

## Verification before adopting

- [ ] Confirm Laravel 13/Vue 3/Inertia 3 starter versions and safe upgrade strategy.
- [ ] Confirm source code isolation, production hosting support, MySQL LTS version and backups.
- [ ] Review authentication routes and disable public registration.
- [ ] Approve role/permission matrix, bootstrap, recovery channel and privileged MFA policy.
- [ ] Ensure the user/session/audit security baseline has testable requirements and mapped tests.
- [ ] Agree on physical file structure **later**, without turning this ADR into a directory specification.
- [ ] Document reviewer, decision date, approved SHA and any change request implications.

**No business requirement was added, amended or approved by this proposal.**

## References

- [DOC-008 System Core Architecture](../architecture/DOC-008_System_Core_Solution_Architecture_v0.1_DRAFT.md)
- [SEC-001 Security Baseline](../security/SEC-001_System_Core_Security_Baseline_v0.1_DRAFT.md)
- [DOC-012 Vue Onboarding](../learning/DOC-012_Vue_Onboarding_MOD-001_v0.1_DRAFT.md)
