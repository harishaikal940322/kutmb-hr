# SEC-002 | MFA and Password Policy Transition Plan

> **Date:** 2 November 2026  
> **Status:** OWNER-SELECTED PLANNING DIRECTION / LOCAL TECHNICAL SLICE IMPLEMENTED / ORGANIZATIONAL APPROVAL OPEN
> **Project:** KUTMB HR System, MOD-001 Identity & Access with MOD-003 security/audit  
> **Linked decisions:** TD-013, TD-015, UM-DEC-005/006/011; requirements IAM-FR-003/004/009, SYS-SEC-001/002  
> **Scope boundary:** System Core only. HR Core remains ON HOLD.

## Decision and security condition

The technical owner selected **MFA planning with a target minimum password length of 8 characters** for the application's future MFA-protected authentication process. This is a *conditional target*, **not authorization to lower the effective password minimum while any password-only login, enrollment, recovery or alternate path remains available**.

- **Single-factor password authentication:** minimum **15 characters** while it remains a permissible sign-in path.
- **Password that is always verified as part of a fully enforced MFA authentication flow:** minimum **8 characters**, once MFA enrollment, verification, recovery, lockout behavior, audit and rollout gates are approved and implemented.
- Support passwords of at least **64 characters**, including spaces and passphrases. Do not require arbitrary mixtures of upper/lowercase letters, numerals and symbols, or periodic changes without evidence of compromise. Check new passwords against common/compromised-password blocklists; apply throttling and secure one-way password hashing.
- No production deployment of 8-character password-only authentication. Avoid claims of NIST compliance until every login and recovery path has been assessed and verified.

Reference: [NIST SP 800-63B-4, password verifiers](https://pages.nist.gov/800-63-4/sp800-63b/authenticators/) and [OWASP MFA Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Multifactor_Authentication_Cheat_Sheet.html).

## Proposed MFA design

1. **Default architecture:** retain Laravel Fortify username-only first factor, session cookies, CSRF and backend ACTIVE-account checks. MFA must be verified server-side **before a full authenticated session may access any protected feature**. Regenerate session identifiers at authentication/privilege transitions and reject attempts to bypass challenge through alternative routes, remembered sessions, APIs or stale tabs.
2. **Initial candidate second factor:** authenticator-app TOTP with a secure, standards-compatible implementation and strict replay protection, time-window validation, time skew policy, throttling and audit. TOTP is a pragmatic initial option, not phishing-resistant. Evaluate WebAuthn/passkeys as a future phishing-resistant option; do not silently enable SMS/email as a weak MFA fallback.
3. **Enrollment:** require verified first-factor identity and an approved secure enrollment policy; bind the factor only after a valid proof. Store MFA secrets protected at rest; never expose them through Inertia props, URLs, logs, backups accessible to users, screenshots or response dumps. Re-authenticate/step up for factor removal, replacement, or other sensitive account changes.
4. **Recovery:** approve an identity-verification and operator-authorized recovery process **before enabling MFA enforcement**. If recovery codes are used, generate them with a secure random source, store only verifier-safe hashes, make each single-use, show them once, rate-limit consumption and record redacted audit events. No secret delivery via WhatsApp, logs or default passwords. No hidden or unaudited MFA bypass.
5. **System Admin:** privileged MFA is mandatory before production. The proposed 8-character minimum also implies mandatory MFA for **every user who may set an 8-character password**, not just admins. First-admin bootstrap, delegated admin, session revocation and last-active-admin safeguards remain separately gated.
6. **Accessibility and usability:** support password managers and paste, clear Malay-first prompts, accessible verification forms and recovery instructions; show safe error messages without disclosing whether a username exists.

## Suggested phased rollout (not implementation authorization)

| Stage | Deliverable | Exit condition |
|---|---|---|
| P0 | Document and approve MFA method, supported roles, recovery, credential policy, rollout, UI and threat model | IT/security owner approval captured; UM-DEC-011 and related decisions reviewed |
| P1 | MFA data/secrets model and protected enrollment/challenge/recovery design | Security review; no changes to effective minimum |
| P2 | Implement and test complete MFA lifecycle behind explicit safe gates | No password-only bypass; negative and concurrent tests PASS on isolated MySQL |
| P3 | Pilot/mandatory enrollment using currently safe credential policy | Every affected account has a verified second factor; recovery tested; sessions reviewed |
| P4 | Enable **8-character minimum only for accounts on enforced MFA-only paths**, after controls and release gate approval | Rollback and monitoring rehearsed; no password-only fallback in scope |

If any mandatory MFA dependency fails, **do not turn off MFA while retaining an 8-character password-only path**. Roll back the policy safely, deny sign-in until recovery, or apply an explicitly reviewed alternative consistent with the required assurance.

## Planned acceptance and security test evidence

- Passwords of 7 characters rejected; 8-character candidates accepted **only after MFA enforcement gates**; 15-character minimum preserved for permitted password-only paths.
- Check 64+ character passphrases, Unicode/space handling, blocklist rejection, no composition-rule requirements, input not truncated.
- Correct password but no/invalid/replayed/expired second factor cannot access dashboard, Inertia endpoints or protected mutations; invalid challenges are throttled.
- Enrollment and reset cannot be forged or used to replace another user's factor; lost-factor recovery requires separate verification, is audited, and revokes affected sessions/credentials as appropriate.
- ACTIVE/INACTIVE state, server RBAC, CSRF, last-admin protection, session rotation/revocation and password-guessing controls still work across MFA transitions.
- Accessibility/browser acceptance and end-to-end negative/security tests with synthetic users and isolated MySQL; no real credentials or authenticator secrets in fixtures/artifacts.

## Documentation and traceability follow-up

This file records the **technical owner's requested planning direction**, not a change to approved normative SRS text or runtime behavior. Before treating the 8-character condition as an accepted organizational requirement, perform reviewed change control and synchronize:

- `docs/decisions/DOC-013_MOD-001_IAM_Technical_Decisions_v0.1_DRAFT.md` (TD-013/015)
- `docs/security/SEC-001_System_Core_Security_Baseline_v0.1_DRAFT.md` and `DOC-014` authentication contracts
- `docs/requirements/MOD-001_USER_MANAGEMENT_REVIEW_v0.3.md` (UM-DEC-011)
- `docs/srs/DOC-005_Identity_Access_SRS.md` and system security requirements, `docs/requirements/RTM.md`
- `docs/quality/` planned security scenarios and `docs/traceability/` CSV mirrors **and operational master XLSX in the same change**
- `docs/implementation/CURRENT_HANDOFF.md` and relevant release gates

Keep existing requirement and test IDs; record reviewer, date, impact and evidence. Do not mark UM-DEC-011 or production security gates approved merely from a source commit. No implementation or production rollout is authorized by this plan.

## 5 November 2026 implementation status

The local/testing implementation now covers TOTP enrollment and challenge, encrypted secrets, replay prevention, throttling, server-side enforcement behind `IAM_MFA_ENFORCED=false` by default, and hashed single-use recovery codes. The local first-System-Admin bootstrap accepts an 8-character password only after successful TOTP proof and the resulting account still requires TOTP at login. Other password defaults and CLI bootstrap retain the 15-character minimum.

Automated technical checks passed against disposable MySQL, including negative replay/recovery cases and migration rollback. This completes the bounded local implementation slice only; it does not complete the approved P2/P3/P4 rollout conditions. Organizational approval, production enrollment/recovery operations, browser/accessibility acceptance, security review and release gates remain open. Keep global enforcement off until those conditions are resolved, and do not interpret this implementation note as normative requirement approval.
