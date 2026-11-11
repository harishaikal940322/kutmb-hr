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

## HR Core decision intake | Cuti Tahunan V1

> **Recorded:** 11 November 2026. **Source:** direct owner decision in the SRS clarification discussion. **Status:** owner direction for documentation reconciliation only; **NOT HR/IT sign-off**, not an approved normative SRS baseline, and not implementation authorization. The original DOCX remains unchanged pending controlled SRS/RTM reconciliation.

| ID | Topic | Owner direction | Source conflict | Status / next action |
|---|---|---|---|---|
| CT-DEC-001 | Pelulus akhir Cuti Tahunan Pengurus Besar | **Pengerusi ALK** dipilih sebagai pelulus akhir semua permohonan Cuti Tahunan Pengurus Besar, tanpa mengira tempoh cuti. | `docs/requirements/modules/CUTI_TAHUNAN_V1.docx` v0.3: FR-20 dan FR-23 menyatakan Setiausaha ALK; BR-27 menyatakan Pengerusi ALK. | **OWNER DIRECTION / PENDING HR SIGN-OFF.** Apabila dibenarkan, selaraskan percanggahan di FR-20, FR-23, matriks peranan/workflow, RTM dan SRS. Jangan anggap keputusan owner ini sebagai kelulusan HR atau memulakan HR Core implementation. |

| CT-DEC-002 | Permohonan dan kelulusan apabila baki cuti tidak mencukupi | **Benarkan permohonan dan kelulusan yang menghasilkan baki cuti negatif**, tertakluk kepada kawalan authorization dan audit yang akan diperincikan. Tiada had negatif, mekanisme pelarasan, pembawa ke tahun berikutnya, atau kuasa kelulusan pengecualian dianggap diluluskan dalam keputusan ini. | `docs/requirements/modules/CUTI_TAHUNAN_V1.docx` v0.3: FR-08/FR-21 menekankan tempahan baki dan pencegahan melebihi kelayakan; BR-23 membenarkan permohonan apabila baki tidak mencukupi atau negatif dengan amaran. | **OWNER DIRECTION / PENDING HR SIGN-OFF.** Semak semula FR-08, FR-21, BR-23, tempahan pending, status baki negatif, akses pelulus, audit transaksi serta acceptance criteria/RTM melalui kawalan perubahan yang sah. **Bukan** HR approval atau authorization implementation. |

## ADR lifecycle

`PROPOSED` / `ASSUMPTION` / `OPEN` → reviewed decision with explicit approver/date/rationale → `ACCEPTED` or `REJECTED` → `SUPERSEDED` if replaced. Never equate proposal with business authorization. Later technical ADRs should use separate Markdown files and reference this register.
