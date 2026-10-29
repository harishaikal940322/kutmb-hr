# HR Traceability: operational master and Git mirrors

**Baseline:** v0.2 DRAFT, 2026-10-29. No business approvals; no test execution. **Repo visibility: PUBLIC**, keep out HR personal data and secrets.

## Sources of authority

1. **Normative requirement text**: `docs/srs/DOC-004...`, `DOC-005...`, `DOC-006...` Markdown. Never silently overwrite wording using a spreadsheet cell.
2. **Operational traceability and change control**: [`KUTMB_HR_Traceability_Core_v0.2.xlsx`](KUTMB_HR_Traceability_Core_v0.2.xlsx) in this folder. It records module IDs and status, requirement-ID links, CR entries, and impact review. Open questions and release gates remain in their dedicated Markdown registers. This core workbook is intentionally compact; normative SRS wording remains in Markdown.
3. **Text mirrors**: `module_registry.csv`, `requirement_links.csv`, `change_requests.csv`, `impact_analysis.csv`. These carry a Git-diff-friendly expanded register schema for proposed changes. Where fields overlap with the core workbook, they must match on every commit; additional CSV-only detail (such as approval timestamps, test evidence or security impact) is supplementary until reflected in the applicable SRS/ADR and approvals. **No silent independent sources of truth.**
4. **History**: accepted pull requests, commit SHA, signed approvals and linked test evidence. Merging to GitHub `main` does not itself mean the HR owner approved a requirement.

## To add, change or remove a module

1. Open the workbook: `Module Registry` and `Change Requests`.
2. Create unique `CR-YYYY-NNN` for **ADD**, **MODIFY**, **REMOVE**, **DEPRECATE**, **SPLIT**, **MERGE** or **REACTIVATE**; record before/after states, requestor and reason.
3. Run impact review: requirements, permissions, privacy, historical data, migrations, integration, UI, tests and rollback.
4. Update SRS Markdown and RTM for all impacted requirement IDs, maintaining AC/test traceability. Use a new unique MOD-NNN for new modules.
5. Obtain HR/IT review and attach approver, date, decisions and reference to the specific PR/commit. Until approved, keep `PROPOSED`/`IN REVIEW`/`DEFERRED`.
6. Update workbook and deterministic CSV mirrors in the **same PR** as SRS/RTM and decision records.
7. Never delete or reuse a retired module ID. Record `DEPRECATED` then `RETIRED` (or `OUT OF SCOPE`) with retention/migration plan after approval.
8. Once implemented, link test and UAT execution evidence; do not claim PASS/GO-LIVE before release gates.

## Baseline inventory

- 11 module IDs: 3 foundation proposals, 1 deferred employee module, 7 future HR modules out of scope.
- 32 requirement IDs with matching acceptance and planned test IDs.
- 14 unresolved questions, 8 unpassed gates, 0 approved requirements and 0 CR entries.

### Excel file name policy

Retain a **versioned** workbook per approved baseline when superseding, or update the working DRAFT core workbook and use Git history. Keep historical rows and IDs, never overwrite the history of module deletion. The workbook name contains a version because it represents the initial governance baseline; newer versions will follow change control.
