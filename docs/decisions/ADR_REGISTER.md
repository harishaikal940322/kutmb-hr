# DOC-009 | Decision / ADR Register

> v0.1, 29 October 2026 • **All decisions provisional**. None recorded as approved.

| ID | Decision | Status | Rationale/proposal | Requirement or document | Open question |
|---|---|---|---|---|---|
| ADR-001 | User ≠ Employee | PROPOSED | Pemisahan identity account dan person record; modul employee belum diputuskan | SYS-DR-001 | Q-005 |
| ADR-002 | Tiada self-signup secara lalai | PROPOSED | Pendaftaran hanya oleh pentadbir sehingga polisi diluluskan | IAM-FR-011 | Q-009 |
| ADR-003 | Panggilan = gelaran sapaan | ASSUMPTION | Contoh Encik/Puan/Dr; semak dengan pemberi arahan | CFG-FR-006 | Q-002 |
| ADR-004 | Architecture integration/detail belum dibekukan | OPEN | Laravel + Vue + MySQL dipilih sebagai stack family; versi, Inertia, keselamatan dan deployment memerlukan review DOC-008/ADR-006 | DOC-008 | Q-001 / UM-DEC |
| ADR-005 | Nilai rujukan sejarah tidak dibuang | PROPOSED | Gunakan inactive dan kekalkan referential integrity | CFG-FR-003 | Q-006 |
| SCOPE-001 | System Core first; HR Core waits for requirements | RECORDED SCOPE DIRECTION | MOD-001 user management priority, MOD-002/003 supporting; MOD-004 onward HOLD | [Decision](SCOPE-001_system-core-vs-hr-core.md) | HR requirements pending |
| ADR-006 | Laravel 13 + Vue 3 + Inertia 3 + MySQL 8.4 LTS for System Core | PROPOSED / TECHNICAL REVIEW | Single Laravel session and authorization boundary; frontend Vue with TypeScript; no physical file structure yet | [ADR-006](ADR-006_Laravel_Vue_Inertia_MySQL_v0.1_PROPOSED.md) / [DOC-008](../architecture/DOC-008_System_Core_Solution_Architecture_v0.1_DRAFT.md) | UM-DEC-001/003/004/005/006/011 and deployment decisions |

## ADR lifecycle

`PROPOSED` / `ASSUMPTION` / `OPEN` → reviewed decision with explicit approver/date/rationale → `ACCEPTED` or `REJECTED` → `SUPERSEDED` if replaced. Never equate proposal with business authorization. Later technical ADRs should use separate Markdown files and reference this register.
