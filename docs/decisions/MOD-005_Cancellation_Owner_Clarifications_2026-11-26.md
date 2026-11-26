# MOD-005 — Cancellation & Refund: Owner development clarifications

26 November 2026, Asia/Kuala_Lumpur. Source HR CT-DEC-016/017/034/039, plus TB-Q01–06. Original HR register remains unchanged. Owner development acceptance, not formal HR/IT approval.

| ID | Owner decision | Acceptance |
|---|---|---|
| CR-Q01 | A: AM/PM return session | HR verifies actual return date and session; unused sessions from that boundary are refundable, actual used sessions remain. |
| CR-Q02 | A: early request during leave | Employee may request during leave before returning; HR verifies only once actual return occurred; GM confirms before any refund. |
| CR-Q03 | A: HR-only after leave end | HR executive prepares special adjustment with attendance evidence; HR manager reviews; GM confirms final correction. No ordinary employee cancellation after period ends. |
| CR-Q04 | Original source and reconciliation | Current AL returned to original account; closed-year AL returned then reconciled using TB-Q04/05; original Tabung cohorts refunded and FIFO excess disposed; unposted future commitments cancelled without artificial credit. |

## Implementation status
- CR-Q01/02: source implemented for current-year posted same-year requests, including mixed/multiple Tabung cohorts. Migration 000054 records return session on case and audit event, preserves legacy null session meaning strictly after return date. HR verification cannot precede actual return session. GM confirmation and atomic source reversal; protects valid pending Tabung holds. A repeat confirmed partial case is blocked; historical corrections must use special HR path. Existing request must have started; new ordinary request after final date is refused. Exact end-of-day enforcement and handling of a case submitted after the last session on final day require further integration review.
- CR-Q03: implemented HR-only preparation in Pelarasan Baki, immutable AFTER_END case and mandatory evidence reference (000055), independent HR verification and distinct GM confirmation/rejection in approval queue, original-source refund. Current-year same-year posted approved AL only; prior confirmed partial case blocks duplicates. Ordinary request after final session now refused based on confirmed working schedule. HR verification accepts/reviews evidence reference; separate HR rejection action and historical-year reconciliation remain open. Runtime unverified.
- CR-Q04: full pre-start current/next-year annual commitment handling already exists; cross-year partial and closed-year return reconciliation remain gated until atomic TB-05 integration. No assumed new policy.
- No database migration, build or runtime tests executed by remote editor. Owner review required.
