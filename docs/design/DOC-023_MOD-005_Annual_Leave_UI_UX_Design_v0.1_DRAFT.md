# DOC-023 | MOD-005 Annual Leave UI/UX Design

> **Version:** v0.1 DRAFT · **Date:** 23 November 2026 · **Review status:** Awaiting requestor review
>
> **Baseline:** The requestor considers all 56 Cuti Tahunan decisions final for UI/UX design. They are frozen as the design baseline; any later policy change must use the existing change-control process. No new policy is introduced here. The requestor clarified that Setiausaha ALK is the final approver for all General Manager leave; this supersedes the conflicting approval wording in CT-DEC-006 and is recorded in CR-HR-2026-002.
>
> **Boundary:** This is a reviewable visual prototype only. It uses synthetic data and has no Laravel routes, API calls, database operations, leave transactions, notifications, or real approval actions. It does not authorize MOD-005 functional implementation.

## Prototype

Open [MOD-005 Annual Leave UI/UX prototype](MOD-005_Annual_Leave_UI_UX_Prototype_v0.1.html) in a browser. The prototype is self-contained and has no external dependencies.

## Design direction

- Use Bahasa Melayu for the interface, a responsive desktop/mobile layout, clear status labels, keyboard-visible focus, and reduced-motion support.
- Give staff a clear view of annual-leave balance, Tabung Cuti, pending reservations, projected balance, current requests, and the path to apply.
- Make AL and EL distinct choices. AL reason is optional; EL emergency reason is required and supporting documentation is optional. EL may be backdated; the current decision sets no maximum and the interface must not invent one.
- Show a transparent workday estimate and the likely review route. Recalculate from the current calendar before submission; never bypass required workflow stages.
- Let staff inspect their own requests and decisions. A unit schedule shows absence information within the user's permitted team scope; detailed balance transactions and manual balance adjustments are restricted to authorized HR.
- Give reviewers a focused queue and the request details relevant to their role. Show the next workflow step and keep the history visible. The prototype does not perform approval actions.
- Let HR review public-holiday calendar changes and affected requests. For approved leave, display a proposed adjustment for HR confirmation; do not change the original approval status or apply an extra deduction automatically.

## Initial screen coverage

| Prototype view | Main actor | Decision coverage (selected) |
|---|---|---|
| Ringkasan | Staf | CT-DEC-020, 034, 043, 044, 054 |
| Mohon cuti | Staf | CT-DEC-002, 009, 011–014, 020, 026–028, 041–044, 055–056 |
| Permohonan saya / Butiran | Staf | CT-DEC-010, 016–017, 021–026, 029, 034, 045–046, 056 |
| Jadual unit | Staf / Pengurus Unit | CT-DEC-048–049, 052–053 |
| Sokongan & kelulusan | Penyokong / pelulus | CT-DEC-001, 006–009, 019, 032, 050; CR-HR-2026-002 (Setiausaha ALK final for GM) |
| Kalendar & semakan baki | HR | CT-DEC-010, 015, 018, 030–031, 033, 035–040, 044, 047, 056 |

The view map is an initial design slice, not an assertion that each of the 56 decisions has completed UI acceptance. Some policy cases are represented by a status, message, or audit cue and will need detailed screen review before functional implementation.

## Review boundary

The requestor's review should focus on the main path from balance summary to application, the wording of status and warning messages, the role-specific navigation, and the amount of information visible to a unit manager. Any request that changes an accepted HR decision should be recorded as a requirements change before the prototype is revised.
