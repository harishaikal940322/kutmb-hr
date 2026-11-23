# DOC-023 | MOD-005 Annual Leave UI/UX Design

> **Version:** v0.2 DRAFT · **Date:** 23 November 2026 · **Review status:** Awaiting requestor review
>
> **Baseline:** All 56 Cuti Tahunan decisions are frozen for UI/UX design per requestor direction. Setiausaha ALK is the final approver for all General Manager leave; this resolves the CT-DEC-001/006 conflict under CR-HR-2026-002. No new policy or status is introduced.
>
> **Boundary:** One self-contained visual prototype maps the complete Cuti Tahunan SRS v0.5 into role-based views. It uses synthetic data only. It has no Laravel routes, API calls, database operations, leave transactions, notifications, or real approval actions. Backend and functional workflow implementation remain on hold.

## Prototype

Open [MOD-005 Annual Leave UI/UX prototype v0.2](MOD-005_Annual_Leave_UI_UX_Prototype_v0.2.html) in a browser. The **Liputan SRS** view acts as the design index and links to the feature views. Its navigation is for review only; the main system menu will be organized later.

## SRS coverage map

| Prototype view | SRS coverage | Main design content |
|---|---|---|
| Liputan SRS | FR-01–32; NFR-01–03; RR-01–03; Sections 11–14; CT-DEC-001–056 | Design index, actor roles, feature mapping and implementation boundary |
| Ringkasan | FR-08, FR-21, FR-29 | Entitlement, used, pending reservation, available balance, Tabung Cuti and upcoming leave |
| Profil & kelayakan | FR-01–03, FR-06–08; FR-07 role boundary | Staff profile, unit/approver structure, category, yearly entitlement, opening/current/future balance |
| Kalendar & pelarasan | FR-04–05, FR-28, FR-31–32; CT-DEC-010, CT-DEC-027 | Johor holidays, replacement-day rules, affected requests, HR confirmation, audit history, and configurable EL backdate limits with pending policy details |
| Mohon cuti | FR-16–18, FR-21–23, FR-28; CT-DEC-009, 027, 055 | AL/EL, dates, half-day sessions, three-workday notice, emergency reason, attachment, balance estimate and route preview |
| Permohonan saya / Butiran | FR-08, FR-16, FR-21–28 | Request list, status, workflow timeline, balance source and change/cancellation entry points |
| Jadual unit | FR-07, FR-19, FR-29 | Team absence calendar with limited details for the user's permitted scope |
| Sokongan & kelulusan | FR-07, FR-19–23, FR-28, FR-31; CR-HR-2026-002 | Role queue, request context, reason, balance summary and required next approver |
| Baki & penutupan tahun | FR-06, FR-08–12, FR-15, FR-21, FR-25, FR-27, FR-32; RR-01–03 | Separate AL/Tabung ledgers, source order, pending amount, carry forward, cap/burn and audit transaction |
| Prorata perkhidmatan | FR-13–14 | Notice/termination dates, service months, calculated entitlement, overuse, Tabung Cuti and HR decision |
| Pindaan, pembatalan & perekodan | FR-24–27; Sections 11.0 workflow | Status-sensitive edit, cancellation by GM, return to original balance source, reapplication and staff confirmation of HR-recorded leave |
| Laporan, notifikasi & jejak audit | FR-28–31; NFR-01–03; RR-01–03 | Balance/status/prorata reports, role-specific notices, action history and balance transactions |

The prototype groups requirements into screens for review; the original FR, BR, workflow-status, report and decision IDs remain unchanged. The 56 decisions are a design baseline, not 56 separate pages. Values shown in example rows are synthetic and do not establish new rates or policies.

## Design direction

- Use Bahasa Melayu, responsive layouts, clear status labels, keyboard-visible focus and reduced-motion support.
- Show annual entitlement and Tabung Cuti separately; show pending reservations before the user submits a request.
- Make AL and EL distinct. AL reason is optional; EL emergency reason is required, supporting documents are optional, and backdated EL has no hardcoded maximum.
- Explain workday estimates and the ordinary review route. Keep required workflow stages visible and do not imply automatic approval.
- Give staff their own requests and history. Limit unit schedule details to the user's permitted scope; reserve full balance transactions and manual adjustments for authorized HR.
- Keep approved-request status intact during calendar-impact review. Require HR confirmation for retrospective balance adjustment and keep it as a separate transaction.
- Use the accepted Setiausaha ALK final-approver decision for GM leave in every route preview and queue.
- Keep all buttons as visual examples. The prototype must not save data or perform an approval, balance or notification action.

## Review boundary

Review the SRS map first, then the staff application path, role queues, HR record maintenance, year-end/prorata and reports/audit views. Changes that alter an accepted HR decision go through the existing change-control process before the design is revised. After UX review, the Vue presentation can be considered separately; backend, API, database and workflow implementation remain gated.
