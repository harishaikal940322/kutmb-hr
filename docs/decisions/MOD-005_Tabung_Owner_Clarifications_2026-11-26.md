# MOD-005 — Tabung Cuti: Owner development clarifications

Date: 26 November 2026 (Asia/Kuala_Lumpur). Authority: explicit Owner decisions in development discussion; not HR/IT production approval. Source: `docs/issue/KUTMB_HR_Cuti_Tahunan_56_Isu_Keputusan_dan_Pending.docx` (CT-DEC-004/016/018/031/033/038/039/040), and `docs/requirements/modules/CUTI_TAHUNAN_V1.docx`. Preserve the original HR answer document unchanged. These clarifications supersede older development-only blocking notes only for the specified choices.

| ID | Decision | Acceptance |
|---|---|---|
| TB-Q01 | FIFO use: AL first, then oldest available Tabung source year, then applicable negative AL policy. | Preserve source year per session and per debit; subtract valid pending holds per cohort; no negative Tabung. |
| TB-Q02 | FIFO excess disposal from oldest available Tabung source, protecting valid pending holds. | Distinct auditable disposal, no current-year AL reduction for existing Tabung excess; no expiry solely by age. If protected holds preclude disposal, HR exception, no invalidated holds. |
| TB-Q03 | Automatic annual close 1 January 00:00 MYT, close previous year, apply confirmed new-year full cap, transfer eligible free AL, burn excess, credit new-year AL and settle approved commitments once, audit/report. | Preserve pending original year/source. Missing confirmed entitlement/rate => defer affected employee and report; retry without double posting. Unresolved negative-year transition rules are not overridden. |
| TB-Q04 | Use full cap effective on actual reconciliation date. | Only newly eligible returned units; never restore previously burned balance automatically. |
| TB-Q05 | Use actual posted Tabung balance on reconciliation date. | If full, burn newly eligible excess; if room, transfer only within room and burn remainder. Actual leave use requires HR review; no double carry. |
| TB-Q06 | Interim Penyelesaian Baki submodule inside Cuti Tahunan, with reporting and tracking of outstanding versus settled post-exit residual negative AL; external Payroll performs financial settlement. | Recalculate final prorated AL only under documented rules, offset with valid Tabung FIFO, record residual days, employee, exit date, original deficit, offset, balance, status, payroll reference, settlement date, confirmer and audit. Owner accepted proposed minimum flow with OUTSTANDING / IN_PROGRESS / SETTLED and HR executive prepares, HR manager independently confirms; no RM calculation, payroll integration, automatic deduction or deletion of leave history. |

Do not treat these as approval of unstated exact entitlement-band boundaries, partial-month/tie rounding, negative December rollover, evidence retention or other outstanding HR decisions. Formal HR/IT confirmation remains required before production. For development, source implementation and Owner functional review remain separate.

## Implementation tracking

- TB-01: effective-dated confirmed full cap, source implemented; promotion AL top-up and lower-cap handling outstanding.
- TB-02: FIFO same-year allocation across cohorts, per-source pending totals, approval and retrospective EL debit; header source is oldest cohort for compatibility; per-slot map authoritative, additive forward trigger migration 000052. Cross-year shortage routing and old-year reconciliation remain gated.
- TB-03: current-year pre-start cancellation refunds all original cohorts and disposes new excess via FIFO free units. Current-year same-year partial cancellation with AM/PM implemented under CR-Q01/02; cross-year partial, special HR route and full audit snapshots outstanding.
- TB-04: manual close transfer/burn and FIFO Tabung excess disposal implemented; 000053 required. Existing both-credited manual prerequisite remains; midnight automation and sequence pending.
- TB-05: closed-year partial cancellation refund reconciliation implemented in 000056. Migration 000057 adds protected late-rejection claims and reconciliation on explicit HR finding that all sessions were not used. Ordinary non-attendance release of closed-year AL (if eligible) uses same transaction helper. Existing ambiguous/actual absence findings do not trigger carry. Migration 000058 implements positive historical AL adjustment maker/checker attestations and immediate exact transfer/burn; does not restore burned units or allow historical Tabung direct credit. Legacy cases without claims, mixed actual usage, negative historical correction and historical special preparation remain outstanding; full pre-start cancellation still follows its date gate. TB-06 remains pending.
- No runtime/database/build execution by remote editor. Owner review required.

- TB-05 legacy follow-up: protected unresolved historical legacy late-rejection holds and bounded source recovery on explicit HR all-session non-use, limited to provable single-year annual-only sources. Closed-year history must reconcile exactly; ambiguous or already recorded cases remain flagged, not automatically reopened. No migration or runtime verification.

- TB-05 partial non-use: 000059 records exact original historical AL sessions independently of protected residual claims. Confirmed unused subset alone is eligible; unselected sessions remain protected. No absence debit, payroll or reopening. Complete historical annual-only sources with existing claims only. Runtime unverified.
