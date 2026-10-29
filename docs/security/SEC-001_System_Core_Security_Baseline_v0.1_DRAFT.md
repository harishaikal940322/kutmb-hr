# SEC-001 | System Core Security Baseline and Threat Review

> Version v0.1 DRAFT · 29 Oktober 2026 · **NOT APPROVED / NOT IMPLEMENTED**  
> Scope: MOD-001 User Management; required supporting MOD-003 audit/security; minimal MOD-002 settings.  
> Source requirements: SYS-SEC-001/002, SYS-AUD-001, IAM-FR-002..011, IAM-AUD-001, SYS-ERR-001, SYS-NFR-002 and existing planned tests.  
> Guidance: [OWASP ASVS 5.0](https://asvs.owasp.org/), [Laravel 13 auth](https://laravel.com/docs/13.x/authentication), [Laravel 13 authorization](https://laravel.com/docs/13.x/authorization), [Vue security](https://vuejs.org/guide/best-practices/security).

## Security objectives and authority

- **Fail closed, deny by default, least privilege.**
- **Laravel backend is authoritative** for request validation, authorization, authentication, critical session state and audit events. Vue/Inertia-provided flags can only assist display.
- No real employee/HR records in System Core development or demos; synthetic users only.
- No production release/privileged access before approved permission policy, MFA decision, session revocation tests, backup/restore and review.
- This is a **risk-based security proposal**, not a certification or compliance claim. Operational owner, audit retention and privacy/legal decisions remain open.

## Threats, controls and planned verification

| Risk | Proposed control | Test(s) / requirement links | Risk level | Decision |
|---|---|---|---|---|
| Broken access control / direct URL edits | Laravel policy/permission per operation, per target, not UI-only; default deny | T-IAM-FR-007, T-SYS-SEC-001 | CRITICAL | Permission matrix open |
| Privilege escalation via self-profile or mass assignment | Separate account/admin DTO allowlists; reject role/status in self-service edits; forbid privilege grants beyond actor scope | T-IAM-FR-008, T-IAM-FR-006 | CRITICAL | UM-DEC-002/004 |
| Loss of last active admin | Concurrency-safe transaction + invariant; deny simultaneous conflicting mutations; controlled emergency recovery | T-IAM-FR-010 | CRITICAL | Bootstrap/recovery open |
| Session survives account deactivation or role revocation | Session invalidate/recheck authorization; protected requests must fail on revoked access; cache invalidation strategy | T-IAM-FR-009 | CRITICAL | UM-DEC-006 |
| Credential theft / credential stuffing | Framework password hashing, rate limiting, safe errors, secure cookie session, login monitoring; MFA for privileged users prior to production (proposal) | T-IAM-FR-003, T-SYS-SEC-002 | HIGH | UM-DEC-011 |
| CSRF / cross-origin attacks | Laravel CSRF middleware, same-origin browser design, HTTPS, cookie SameSite policy | T-SYS-SEC-001 | HIGH | Domain/hosting TBD |
| XSS / HTML injection | Vue auto-escaping, no untrusted `v-html`, CSP review, response encoding and strict prop shaping | T-SYS-SEC-002 | HIGH | CSP deployment TBD |
| Reset link/token abuse | Short-lived single-use approved recovery, validation, rate limiting, do not leak token to logs | T-IAM-FR-004 | HIGH | UM-DEC-005 |
| User enumeration | Generic authentication/recovery responses; generic denied response, throttle repeated probes | T-IAM-FR-003/004 | MEDIUM | Review UX |
| Data leakage via Inertia shared props | Explicit minimal props and account listing projection; never serialize entire User object, secrets, full role matrix or confidential config | T-SYS-SEC-002, T-IAM-FR-012 | HIGH | Data contract TBD |
| Secrets in code, logs or repository | Environment secret handling, allowlists, no real account data in fixtures, secret scanning; revoke leaked credentials | T-SYS-SEC-002 | CRITICAL | Deployment TBD |
| MySQL privilege abuse / SQL injection | Parameterized queries; least-privilege app user; independent migration/admin privileges; no unsafe raw SQL with user strings | T-SYS-SEC-001 | HIGH | Database design deferred |
| Audit gaps or untrusted audit edits | Record privileged actions with actor, action, target, time, result; protected audit access; tamper detection/retention strategy | T-IAM-AUD-001, T-SYS-AUD-001 | HIGH | UM-DEC-012 |
| Broken backup / restore | Backups with tested synthetic restore, isolated keys/access and retention, documented RPO/RTO | T-SYS-NFR-002 | HIGH | IT operation decisions |
| Third-party dependency compromise | Pin/review compatible dependencies; Composer/npm audits, provenance and CI policy, no unreviewed auth packages | Release gate QA/Security | HIGH | CI architecture TBD |

Risk labels are preliminary and not assigned from a formal organizational risk assessment.

## Session and authentication design gates

1. For same-origin Inertia browser pages, prefer Laravel server-managed sessions. Protect cookies with `Secure`, `HttpOnly`, carefully configured `SameSite`, HTTPS and well-defined trusted hosts/proxies.
2. Regenerate the session identifier upon authentication and invalidate session+rotate CSRF token on logout.
3. Rate-limit login and sensitive recovery; do not expose identity existence through messaging.
4. Account inactivity and role revocation must be rechecked against backend state. A formerly authorized browser with an old tab cannot perform a protected operation after deactivation.
5. Starter kit **public register routes must be disabled** unless an approved policy permits self-registration.
6. System Admin bootstrap: one-time protected process, explicit operator approval, no seeded default password. Record bootstrap provenance.
7. Privileged MFA: recommended required before production; exact mechanism (authenticator/recovery codes/provider) needs security review and safe reset path.
8. If emergency admin recovery is needed, design an audited break-glass process with separate authorization; **do not override last-admin check with a hidden bypass**.
9. Password reset/invitation must wait for approved delivery method; no hardcoded admin password, default SMTP, WhatsApp delivery of credentials, or logs containing secrets.

## Roles and authorizations

- Existing conceptual roles from DOC-011 draft: System Admin, delegated User Admin, Standard User. **Names and permissions remain DRAFT.**
- Explicitly distinguish authentication (who) from authorization (what and which user).
- Role assignment must consider actor-to-target privilege hierarchy. Prevent self-escalation and mass assignment.
- Do not infer HR Admin authority or grant blanket HR data rights from System Admin.
- Record security audit for account creation, deactivation/reactivation, role changes, denied privileged attempts and relevant auth anomalies; do not log passwords, recovery codes, tokens or private HR fields.

## Frontend security rules for Vue/Inertia

- Never trust client-side permission booleans to protect backend mutations.
- Vue form fields and Inertia props may be tampered with. Server must validate requested fields/IDs/permissions.
- Do not use `v-html` with untrusted content, arbitrary user-authored Vue templates or dynamic unsafe HTML.
- Avoid storing access tokens or session secrets in localStorage/sessionStorage.
- Ensure global shared props are purpose-limited, and administrative lists expose only necessary account data.
- Treat SSR server, CSP and third-party UI components as deployment/review decisions, not automatic assumptions.

## Security verification exit criteria

- [ ] Approved privileged role/permission matrix; forbidden endpoints tested for each role.
- [ ] Login, logout, recovery and account deactivation attack cases defined and executed.
- [ ] Admin bootstrap / last-admin concurrency tests PASS against MySQL with evidence.
- [ ] CSRF, authorization, mass-assignment, XSS and secret-exposure regression tests PASS.
- [ ] Audit events tested and access/retention decision recorded.
- [ ] Dependency scan, secrets scan and production error handling checked.
- [ ] MySQL permission, backups, restore dry-run and transport/cookie settings checked.
- [ ] Security reviewer or delegated technical authority records sign-off with commit SHA and residual risk.

**Current result: 0 PASS, no tests executed, all security gates OPEN.**

## IAM decision and contract cross-reference

Proposed technical defaults and residual gates are documented in [DOC-013](../decisions/DOC-013_MOD-001_IAM_Technical_Decisions_v0.1_DRAFT.md). The Inertia/Laravel trust boundary, permission denial cases and concurrency invariants are in [DOC-014](../design/DOC-014_MOD-001_Authentication_Authorization_Contracts_v0.1_DRAFT.md). **Both remain DRAFT** and do not close any existing release/security gate.
