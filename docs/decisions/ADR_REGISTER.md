# DOC-009 | Decision / ADR Register

> v0.1, 29 October 2026 • **All decisions provisional**. None recorded as approved.

| ID | Decision | Status | Rationale/proposal | Requirement or document | Open question |
|---|---|---|---|---|---|
| ADR-001 | User ≠ Employee | PROPOSED | Pemisahan identity account dan person record; modul employee belum diputuskan | SYS-DR-001 | Q-005 |
| ADR-002 | Tiada self-signup secara lalai | PROPOSED | Pendaftaran hanya oleh pentadbir sehingga polisi diluluskan | IAM-FR-011 | Q-009 |
| ADR-003 | Panggilan = gelaran sapaan | ASSUMPTION | Contoh Encik/Puan/Dr; semak dengan pemberi arahan | CFG-FR-006 | Q-002 |
| ADR-004 | Architecture & stack belum dibekukan | OPEN | SRS fokus WHAT; pilihan stack ke architecture/ADR kemudian | DOC-008 | Q-001 |
| ADR-005 | Nilai rujukan sejarah tidak dibuang | PROPOSED | Gunakan inactive dan kekalkan referential integrity | CFG-FR-003 | Q-006 |

| SCOPE-001 | System Core first; HR Core waits for requirements | RECORDED SCOPE DIRECTION | MOD-001 user management priority, MOD-002/003 supporting; MOD-004 onward HOLD | [Decision](SCOPE-001_system-core-vs-hr-core.md) | HR requirements pending |

## ADR lifecycle

`PROPOSED` / `ASSUMPTION` / `OPEN` → reviewed decision with explicit approver/date/rationale → `ACCEPTED` or `REJECTED` → `SUPERSEDED` if replaced. Never equate proposal with business authorization. Later technical ADRs should use separate Markdown files and reference this register.
