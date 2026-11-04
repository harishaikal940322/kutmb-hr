# KUTMB HR coding instructions

## Scope and authority

- Treat the repository's SRS, RTM, decisions and current handoff as the source of truth. Requirements are DRAFT unless explicitly approved; a commit or merge is not HR/IT sign-off.
- The current target is System Core, beginning with `MOD-001` Identity & Access. `MOD-004..011` HR Core remains ON HOLD until HR requirements are provided and approved. Never infer employee, payroll, attendance, leave, overtime, claims, sensitive-personal-data workflows or policy rates.
- Before work that touches requirements, authentication, authorization or data, consult `docs/requirements/OPEN_QUESTIONS.md`, `docs/decisions/ADR_REGISTER.md`, `docs/requirements/RTM.md`, the applicable SRS, [SCOPE-001](docs/decisions/SCOPE-001_system-core-vs-hr-core.md), and the relevant design/security records. Resolve only decisions needed for the current change; do not reopen settled technical choices without concrete evidence.
- Keep account identity separate from employee/person concepts as a proposed boundary. Do not add employee tables, joins or data to System Core.
- Keep requirement and test IDs stable. Update SRS/RTM and the traceability CSV/XLSX master together only when approved requirements actually change; use the existing change-control process for normative scope changes.
- Laravel, Vue and MySQL are the selected stack families. Follow the existing application architecture and current code conventions. Do not introduce a new folder layout, API topology, hosting model, package or infrastructure without a specific need and accepted decision.

## Security boundaries

- Backend authorization is authoritative; Vue permission hints are presentation only. Preserve active-account checks, per-target authorization, last-admin safety, fresh authorization after privilege changes, validation and relevant audit behavior.
- Username is the current login principal. Public self-registration, delegated User Admin, production bootstrap, account recovery and privileged mutation flows remain disabled or gated unless their requirements and security decisions are accepted.
- MFA is planned, not implemented. The 8-character password minimum is conditional on fully enforced MFA and required approvals. Preserve the 15-character minimum on every permitted single-factor path; do not weaken password policy or add an incomplete MFA path.
- Never commit secrets, credentials, real personal HR data, database exports or confidential infrastructure details. Use synthetic data only.
- Never run destructive database operations without explicit authorization. Use the existing development environment/database; do not create pretend staging, UAT or production databases in the development server.

## Simplified development method

- Work only in `/home/haris/www/kutmb-hr`. Fetch `origin`, inspect `main` and the working tree before changes. Preserve unrelated local changes; never overwrite, reset or discard them.
- Use `main` for ordinary development. Do not create clones, worktrees or feature branches. Do not modify or disable existing CI or add PR gates without owner approval.
- Plan only the current implementation slice. Reuse the existing SRS, RTM, architecture and implementation records. Do not create duplicate plans, reports, checklists or work packages.
- Implement one coherent, requirements-backed System Core feature at a time. Reuse Laravel/Vue/Inertia patterns and avoid speculative requirements and unnecessary abstractions.
- Run focused checks relevant to changed code. Do not repeatedly run the full suite for minor edits. Keep security, authorization, validation and integrity checks; run consolidated requirement, error-handling, UI/UX and dependency checks at the relevant phase exit. Do not claim a check passed unless it ran.
- Update `docs/implementation/CURRENT_HANDOFF.md` and existing chronological records with delivered scope, files, checks/results, decisions/blockers, limitations and the next ready scope. Keep records concise and accurate.
- Commit completed, verified work on `main`, push to `origin/main`, then verify the remote SHA and local status. Never bypass failing CI, enforced protection or required reviews. Report a genuine blocker with evidence.
- Do not deploy, create production credentials, merge external PRs or claim release/business approval unless explicitly authorized.

## Current handoff

Read [CURRENT_HANDOFF.md](docs/implementation/CURRENT_HANDOFF.md) before implementation. It is the current operational status. [DOC-020](docs/implementation/DOC-020_MOD-001_Implementation_Blueprint_v0.1_DRAFT.md) is historical planning context; its older package, branch and gate instructions are superseded by the simplified method recorded in its current workflow section and this file.
