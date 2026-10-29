# SCOPE-001 | Separate System Core from HR Core

> **Recorded:** 2026-10-29  
> **Source:** Direct project scope clarification by the requestor  
> **Status:** SCOPE DIRECTION RECORDED; detailed SRS, HR policy and implementation approvals remain pending  
> **Type:** Clarification of terminology and implementation sequencing, not an ADD/REMOVE of a module.

## Definitions

- **KUTMB HR System** is the overall product.
- **System Core / Platform Core** is the technical application foundation, independent of HR domain workflows. It covers identity, authentication, user administration and access control, plus the minimum configuration/security/audit required to support those functions.
- **HR Core** is the HR business domain, including employee records, organizational/employment information and future HR workflows. **Do not begin HR Core implementation until HR supplies and approves its requirements.**
- The previous phrase **"HR Foundation"** in v0.1 documentation should be read only as the historical *system foundation for the HR product*, **not** as approval to develop HR Core. Existing document IDs and requirement IDs remain stable until a controlled rebaseline.

## Development sequence

1. **Now / first implementation module:** `MOD-001 Identity & Access` with priority on **User Management**. Plan: create/list/search/update account, login/logout, enable/disable account, roles and permissions as required for a usable and secure user administration module. Final behavior and permission matrix still require explicit review.
2. **Supporting system capabilities:** `MOD-003` security/auditing and a minimal subset of `MOD-002` System Config only when necessary for MOD-001. General settings including `panggilan` must not be mistaken for HR Core approval; confirm meaning and scope before implementation.
3. **Later:** Broader configuration and reference data once its requirements are confirmed.
4. **On hold:** Employee & Organization and all other **HR Core** domain modules (`MOD-004` onward), until formal HR requirements, review, SRS, RTM and explicit release/development authorization are available.

## Boundary rules

- **User Account ≠ Employee Record**. No mandatory employee table or employee-linked account assumptions in the System Core implementation.
- No leave, payroll, attendance, OT, claims, performance, organizational master or HR approval workflow implementation from inference.
- Keep system extensible for later HR features without designing speculative HR business rules.
- Current v0.1 normative requirement status is DRAFT; this scope clarification does not change any requirement to APPROVED or any release gate to PASS.
- There is **no module add/remove in this clarification**, so existing module IDs and Excel/CSV registry values remain unchanged. The next RTM/SRS baseline review should rename the presentation headers, explicitly distinguish System Core vs HR Core, and track any actual ADD/MODIFY/REMOVE through a CR with synchronized Excel+CSV+SRS.
- Architecture and technology choices remain pending DOC-008.

## Immediate next documentation work

1. Review MOD-001 user management SRS requirement-by-requirement (fields, login identifier, account lifecycle, RBAC, role assignment, security).
2. Rebaseline Module Registry / Excel UI labels and RTM with explicit **Platform Core** and **HR Core (HOLD)** classification, without silently changing requirement IDs or scope.
3. Prepare DOC-008 Solution Architecture and MOD-001 implementation plan after decisions that affect security and data model are agreed.

**Approval interpretation:** The requestor has clarified the target development stream and sequencing, but has **not** approved unknown HR business requirements, final user fields, permission details, technology choices or release readiness.
