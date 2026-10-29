# Coding-agent instructions: KUTMB HR

1. Treat this Git repository as the source of truth for system requirements and decisions.
2. Current baseline is **v0.1 DRAFT**. No full HR domain or business policy has been approved.
3. Do not implement or fabricate employee, payroll, attendance, leave, OT, claims, sensitive-personal-data workflows or policy rates without an approved requirements update.
4. For module scope changes, open a CR with unique ID; update `docs/traceability/` Excel operational master and CSV mirrors alongside SRS/RTM, do not delete/reuse retired module IDs.
5. Consult `docs/requirements/OPEN_QUESTIONS.md`, `docs/decisions/ADR_REGISTER.md`, `docs/requirements/RTM.md`, and module SRS before architecture/coding.
5. No Laravel/PHP/database/framework/hosting choice is approved yet. Architecture decisions belong in DOC-008 + ADR.
6. Respect user/account vs employee/person separation as **proposed**, not settled; flag decisions requiring HR approval.
7. Keep IDs stable and maintain Feature → Requirement → Acceptance Criterion → Planned Test → Evidence traceability. Do not claim test passes without execution evidence.
8. No secrets, real personal HR data, database exports, tokens or confidential configuration in Git history, PRs, fixtures or logs. This repo is **public**.
9. Scope changes require documentation review via PR. A merged proposal is not equivalent to business sign-off.
