# DOC-023 | MOD-005 Annual Leave UI/UX Design

> **Version:** v0.5 · **Date:** 24 November 2026 · **Review status:** REQUESTOR UX ACCEPTED / DESIGN FROZEN
>
> **Baseline:** All 56 Cuti Tahunan decisions are frozen for UI/UX design per requestor direction. Setiausaha ALK is the final approver for all General Manager leave; this resolves the CT-DEC-001/006 conflict under CR-HR-2026-002. No new policy or status is introduced.
>
> **Boundary:** The design has two review surfaces: the static HTML prototype and a Vue UI/UX design surface opened from the main module sidebar. Both use synthetic data and local-only preview interactions. No leave route, API, database operation, real transaction, notification or approval action is connected. Backend and functional workflow implementation remain on hold.

## Prototype

Open [MOD-005 Annual Leave UI/UX prototype v0.3](MOD-005_Annual_Leave_UI_UX_Prototype_v0.3.html) for the standalone design index. In the main system, **Modul Utama → Cuti Tahunan** opens the Vue review surface at `/leave/annual`. The role selector and submodule navigation remain synthetic review controls.

## 25 November 2026 — integrated dashboard design surface (historical, superseded below)

The requestor authorized integrating the accepted UI directly into the existing Papan Pemuka. The authenticated system admin with `iam.rbac.manage` in local/testing opens the Cuti Tahunan design surface directly at /dashboard, without the former preview button or full-screen overlay. Existing system sidebar/header and profile links remain; the module uses compact responsive content-area navigation. The server supplies a Boolean design-review capability; no new permissions or grants. Other accounts and production retain the normal account dashboard. The simulation role selector is explicitly labelled and available only inside this authorized design review; it never represents the actual account's HR authorization. All example balances, dates, records, and actions remain synthetic and non-persistent. No real submission/decision routes, API, ledger writes or notifications are added by this UI integration. Functional role-scoped dashboard and AL transaction integration remain separate next work. Source review only, browser/build/feature tests not yet run remotely.

## 25 November 2026 — main module navigation correction (current)

After reviewing the integrated dashboard, requestor directed that Cuti Tahunan be a separate main module in the system sidebar. Restore the original account Papan Pemuka at /dashboard. Add **Modul Utama → Cuti Tahunan** with its own protected GET /leave/annual Inertia page, title, breadcrumb and active sidebar state; reuse the accepted responsive content-area submodule navigation and existing Vue design. The fixed menu and route require local/testing and existing iam.rbac.manage authority. No new permission, assignment, migration or data changes. The module remains a synthetic admin design review, not an operational leave workflow. The previous dashboard placement is superseded. Source commit 7b54a644; local build/browser/feature verification pending.

## 25 November 2026 — content-area sub-sidebar refinement

Requestor accepted the main sidebar module placement and requested replacing the horizontal submodule tabs with a vertical sub-sidebar inside the Cuti Tahunan content area. Keep the application sidebar and /leave/annual route unchanged. The sub-sidebar groups the existing role-filtered screens, indicates the selected page (including its detail view), stays visible on sufficiently wide content areas, and becomes a compact disclosure menu on narrower areas with keyboard focus management. Use content-width container queries so the remaining panels and grids adapt when the system sidebar expands or collapses. Preserve existing light/dark tokens, synthetic review notice, role simulation and all workflow/data boundaries. This is a presentation-only refinement; local build and browser acceptance pending.

## 25 November 2026 — functional Profile & Entitlement read slice

The requestor accepted the content-area sub-sidebar and authorized implementation of Profil & Kelayakan. The existing /leave/annual entry now resolves authorization server-side. Accounts explicitly granted leave.entitlement.view see the development database employee directory, current effective unit/category, service dates, selected-year recorded entitlement and distinct account/confirmation/credit states. Admin-only accounts retain the synthetic design; accounts with both permissions may open /leave/annual?view=design separately. No simulation role selector appears in the functional page and no data is supplied to the preview. A single sidebar module entry is retained. New permission registration is local/testing-only, without automatic grants or changes to protected admin. The functional read page does not calculate entitlement rates, infer missing confirmation, create accounts, mutate ledger, or expose leave submission or approval. Empty records remain explicit; amount is confirmed entitlement, not available balance. Current assignment is evaluated on the as-of date, not the selected entitlement year. Source c43fcbd; local migration and focused verification pending.

## SRS coverage map

| Prototype view | SRS coverage | Main design content |
|---|---|---|
| Liputan SRS | FR-01–32; BR-01–39; NFR-01–13; RR-01–08; Sections 11–14; CT-DEC-001–056 | Design index, actor roles, feature mapping, quality boundaries and implementation boundary |
| Ringkasan ikut peranan | FR-07–08, FR-19, FR-21, FR-29 | Vue + HTML: Staff balances and own requests; Unit Manager scoped support and schedule; GM support/final queue, changes and statistics; Secretary ALK final queue for >5-day requests and all GM requests; HR aggregate monitoring and search; IT technical boundary only. |
| Profil & kelayakan | FR-01–03, FR-06–08; FR-07 role boundary | Staff profile, unit/approver structure, category, yearly entitlement, opening/current/future balance |
| Kalendar & pelarasan | FR-04–05, FR-28, FR-31–32; CT-DEC-010, CT-DEC-027 | Johor holidays, replacement-day rules, affected requests, HR confirmation, audit history, and configurable EL backdate limits with pending policy details |
| Mohon cuti | FR-16–18, FR-21–23, FR-28; CT-DEC-009, 027, 055 | AL/EL, dates, half-day sessions, three-workday notice, emergency reason, attachment, balance estimate and route preview |
| Permohonan saya / Butiran | FR-08, FR-16, FR-21–28 | Request list, status, workflow timeline, balance source and change/cancellation entry points |
| Jadual unit | FR-07, FR-19, FR-29 | Team absence calendar with limited details for the user's permitted scope |
| Sokongan & kelulusan | FR-07, FR-19–23, FR-28, FR-31; CR-HR-2026-002 | Role queue, request context, reason, balance summary and required next approver |
| Baki & penutupan tahun | FR-06, FR-08–12, FR-15, FR-21, FR-25, FR-27, FR-32; RR-01–03 | Separate AL/Tabung ledgers, source order, pending amount, carry forward, cap/burn and audit transaction |
| Prorata perkhidmatan | FR-13–14 | Notice/termination dates, service months, calculated entitlement, overuse, Tabung Cuti and HR decision |
| Pindaan, pembatalan & perekodan | FR-24–27; Sections 11.0 workflow | Status-sensitive edit, cancellation by GM, return to original balance source, reapplication and staff confirmation of HR-recorded leave |
| Laporan, notifikasi & jejak audit | FR-28–31; NFR-01–13; RR-01–08 | Balance/status/prorata reports, role-specific notices, action history and balance transactions |

The two review surfaces map FR-01–32, BR-01–39, NFR-01–13, RR-01–08, workflow statuses and CT-DEC-001–056. The Vue SRS index distinguishes UI coverage from platform/backend requirements such as backup, concurrency and transaction idempotency. Original IDs remain unchanged. Values shown in example rows are synthetic and do not establish new rates or policies.

## Role dashboard and navigation boundary

The role selector is a design-review preview only. It changes the example dashboard and visible navigation for Staff, Unit Manager, General Manager, Secretary ALK, HR and IT Admin. Secretary ALK does not see self-service requests or personal leave balances; IT Admin sees no leave requests, balances, support or approval actions. HR receives staff and entitlement maintenance views, while unit and approval views are scoped to their SRS responsibilities. Actual access remains controlled by server-side permissions and is not implemented in this prototype.

## Design direction

- Use Bahasa Melayu, responsive layouts, clear status labels, keyboard-visible focus and reduced-motion support.
- Show annual entitlement and Tabung Cuti separately; show pending reservations before the user submits a request.
- Make AL and EL distinct. AL reason is optional; EL emergency reason is required, supporting documents are optional, and backdated EL has no hardcoded maximum.
- Explain workday estimates and the ordinary review route. Keep required workflow stages visible and do not imply automatic approval.
- Give staff their own requests and history. Limit unit schedule details to the user's permitted scope; reserve full balance transactions and manual adjustments for authorized HR.
- Keep approved-request status intact during calendar-impact review. Require HR confirmation for retrospective balance adjustment and keep it as a separate transaction.
- Use the accepted Setiausaha ALK final-approver decision for GM leave in every route preview and queue.
- Keep all buttons as visual examples. The prototype must not save data or perform an approval, balance or notification action.

## 24 November 2026 — requestor-accepted application form refinement

- UX-R01–R04: use one AL/EL dropdown, remove the manual AL emergency/short-notice checkbox, show AL, Tabung Cuti, pending reservations and total available before the form, and display AL/Tabung projected balances separately. Ordinary AL reason is optional; EL reason is required and attachment optional. Examples are synthetic; a pending hold is subtracted only once.
- UX-R05: retain the existing SRS FR-19/20/23 and BR-24/25/26 threshold: up to and including five days ends at Pengurus Besar; above five days requires Setiausaha ALK. Unit Manager applies directly to GM for up to five days; above five GM supports, Secretary decides. All GM requests go to Secretary ALK under CR-HR-2026-002.
- The checkbox removal is a UI decision, not a repeal of FR-18/BR-20/CT-DEC-009. The actual three-working-day notice rule and exception handling remain governed by the SRS; derive notice from dates in a later authorized functional design, or obtain a controlled policy change if removal is intended. No HR policy, backend or production approval is implied.

## Final UX acceptance / freeze (24 November 2026)

The requestor confirmed after reviewing the updated demo that the remaining screens look satisfactory and directed the project to proceed. Record requester acceptance of the design across Staf, Pengurus Unit, Pengurus Besar, Setiausaha ALK, HR and Pentadbir IT, including UX-R01–R05. Frozen reference: commit `ffdeda34062ae0dd5c9edbd08ec5e7ca44cab6a5`, Vue `resources/js/pages/AnnualLeavePreview.vue` and the standalone HTML prototype linked above. Version v0.5 denotes the accepted design record; existing source filenames are retained to preserve links.

This acceptance is a user-reported design review, not an independent build, accessibility/device test, formal HR sign-off or production acceptance. Functional route/menu integration is still a planned technical task; role selector remains preview-only. Small presentation refinements may be made during authorized development and logged; policy/workflow changes require existing change control. FR-18/BR-20/CT-DEC-009 remain unchanged. The main-system menu, backend, API, database and workflow implementation remain gated.
