# DOC-004 | System-Wide Software Requirements Specification

> **Version:** v0.1 • **Date:** 29 October 2026 • **Status:** DRAFT / NOT APPROVED • **Owner/approval:** HR/IT TBD
>
> This Markdown edition is derived from the draft DOC-004 DOCX baseline. All SHALL/HENDAKLAH clauses are **proposals**, not approved policy. IDs, acceptance criteria and planned tests retain their original meaning. No tests have been executed.

## 1. Tujuan & skop

Tujuan: Bina kerangka teknikal/operasi sistem HR yang boleh digunakan untuk akaun, akses dan tetapan sementara requirement HR penuh belum diperoleh. Termasuk: Identiti pengguna, access control, tetapan rujukan termasuk gelaran sapaan, audit dan keperluan bukan fungsi minimum. Tidak termasuk: Employee master penuh, payroll, cuti, kehadiran, tuntutan, overtime, penilaian dan approval workflow.

## 2. Actors & boundaries

System Admin: Pentadbir berizin yang mengurus akses dan tetapan. Identiti sebenar TBD. HR Admin: Role calon; permission belum diluluskan. Pengguna biasa: Boleh log masuk dan menyemak profil; ciri HR lain deferred. Integrasi: SSO, payroll, email gateway, external API tidak dianggap tersedia sehingga disahkan.

## 3. Prinsip requirement

Keutamaan: Normative requirements mempunyai ID stabil, observable AC dan test case. Semantik: SRS menentukan WHAT; pilihan Laravel, database, hosting, backup engine dan skema teknikal masuk Architecture/ADR. Asal: Arahan pengguna vs cadangan analisis dibezakan dalam RTM; tiada inferens dipromosikan menjadi polisi.

## 4. Ketidakpastian kritikal

Bentuk organisasi dan skop pemilikan, skema login/SSO, hak admin, kategori rujukan, maklumat peribadi, operasi backup dan tempoh retention masih memerlukan keputusan requestor. Rujuk Q-001 hingga Q-014. Tiada tarikh release atau nisbah capacity ditetapkan.

## 5. Release policy

Sistem tidak dianggap DEV READY atau production ready selagi GATE-01 hingga GATE-07 gagal atau belum disahkan. GATE-08 khusus sebelum release. Sasaran prestasi khusus, MFA, RPO/RTO dan retention kekal TBD sehingga konteks beban/risiko diketahui.

## Requirements baseline (9)

### SYS-BR-001 | Skop foundation

**Requirement:** Sistem hendaklah menyediakan asas pengurusan pengguna dan konfigurasi tanpa mewajibkan modul proses HR seperti cuti, gaji atau kehadiran.

**Acceptance criterion (AC-SYS-BR-001):** Pemasangan foundation boleh digunakan untuk log masuk, mentadbir pengguna dan tetapan walaupun modul HR proses belum tersedia.

**Test case (T-SYS-BR-001):** UAT: foundation tanpa modul HR

**Status:** DRAFT / NOT APPROVED. **Evidence:** NOT TESTED. **Feature:** F-001. **Priority:** Must. **Origin:** Arahan awal pengguna.

### SYS-DR-001 | Pemisahan pengguna-pekerja

**Requirement:** Sistem hendaklah membezakan identiti akaun pengguna daripada rekod pekerja dan tidak menganggap setiap pengguna ialah pekerja.

**Acceptance criterion (AC-SYS-DR-001):** Akaun pentadbir boleh wujud tanpa rekod pekerja; rekod pekerja kelak tidak semestinya mempunyai akaun.

**Test case (T-SYS-DR-001):** Model data: pemisahan identiti

**Status:** DRAFT / NOT APPROVED. **Evidence:** NOT TESTED. **Feature:** F-004. **Priority:** Must. **Origin:** Cadangan analisis.

**Nota / dependency:** Hubungan user-employee akan diputuskan dalam SRS modul pekerja kelak.

### SYS-SEC-001 | Kawalan akses server

**Requirement:** Sistem hendaklah mengesahkan kebenaran pengguna pada setiap operasi dan sumber terlindung di sisi pelayan.

**Acceptance criterion (AC-SYS-SEC-001):** Operasi tanpa permission ditolak walaupun URL atau permintaan dibuat terus tanpa antaramuka.

**Test case (T-SYS-SEC-001):** Negatif: bypass URL/API

**Status:** DRAFT / NOT APPROVED. **Evidence:** NOT TESTED. **Feature:** F-007. **Priority:** Must. **Origin:** Cadangan analisis.

### SYS-SEC-002 | Perlindungan rahsia

**Requirement:** Sistem hendaklah melindungi credential, token dan data sensitif daripada paparan kepada pengguna tidak berhak atau log aplikasi biasa.

**Acceptance criterion (AC-SYS-SEC-002):** Kata laluan tidak dapat dibaca semula; token dan maklumat sensitif tidak bocor dalam respon/log.

**Test case (T-SYS-SEC-002):** Security: secret exposure

**Status:** DRAFT / NOT APPROVED. **Evidence:** NOT TESTED. **Feature:** F-007. **Priority:** Must. **Origin:** Cadangan analisis.

### SYS-AUD-001 | Audit merentas modul

**Requirement:** Sistem hendaklah merekod peristiwa keselamatan dan perubahan pentadbiran penting dengan aktor, tindakan, sasaran dan masa.

**Acceptance criterion (AC-SYS-AUD-001):** Audit bagi sekurang-kurangnya perubahan peranan, status akaun dan master data boleh ditelusuri.

**Test case (T-SYS-AUD-001):** Audit: privileged mutations

**Status:** DRAFT / NOT APPROVED. **Evidence:** NOT TESTED. **Feature:** F-007. **Priority:** Must. **Origin:** Cadangan analisis.

### SYS-NFR-001 | Kebolehgunaan asas

**Requirement:** Skrin foundation hendaklah boleh digunakan dengan papan kekunci dan pada lebar paparan 360px serta 1280px tanpa menyembunyikan fungsi kritikal.

**Acceptance criterion (AC-SYS-NFR-001):** Fungsi log masuk, pengguna dan tetapan dapat dicapai di dua saiz viewport serta melalui papan kekunci.

**Test case (T-SYS-NFR-001):** UI: keyboard/responsive

**Status:** DRAFT / NOT APPROVED. **Evidence:** NOT TESTED. **Feature:** F-008. **Priority:** Must. **Origin:** Cadangan analisis.

### SYS-NFR-002 | Kebolehpulihan data

**Requirement:** Sistem hendaklah menyokong sandaran dan pemulihan data foundation yang boleh diverifikasi dalam persekitaran bukan produksi.

**Acceptance criterion (AC-SYS-NFR-002):** Restorasi ujian memulangkan akaun, role dan master data yang konsisten; RPO/RTO kekal TBD.

**Test case (T-SYS-NFR-002):** DR: restore dry-run

**Status:** DRAFT / NOT APPROVED. **Evidence:** NOT TESTED. **Feature:** F-008. **Priority:** Must. **Origin:** Cadangan analisis.

**Nota / dependency:** Kekerapan, retention, RPO/RTO perlu keputusan operasi.

### SYS-ERR-001 | Ralat selamat

**Requirement:** Sistem hendaklah menunjukkan mesej ralat yang tidak mendedahkan query, stack trace, secret atau butiran dalaman pelayan.

**Acceptance criterion (AC-SYS-ERR-001):** Permintaan tidak sah dan pengecualian tidak memaparkan rahsia atau jejak stack kepada pengguna.

**Test case (T-SYS-ERR-001):** Negative: error disclosure

**Status:** DRAFT / NOT APPROVED. **Evidence:** NOT TESTED. **Feature:** F-007. **Priority:** Must. **Origin:** Cadangan analisis.

### SYS-CFG-001 | Tarikh dan zon masa

**Requirement:** Sistem hendaklah menggunakan tetapan zon masa aplikasi yang konsisten untuk paparan dan audit.

**Acceptance criterion (AC-SYS-CFG-001):** Masa pada audit dan UI dipaparkan secara konsisten mengikut konfigurasi yang dipersetujui.

**Test case (T-SYS-CFG-001):** Integration: timezone handling

**Status:** DRAFT / NOT APPROVED. **Evidence:** NOT TESTED. **Feature:** F-005. **Priority:** Must. **Origin:** Cadangan analisis.

**Nota / dependency:** Cadangan awal Asia/Kuala_Lumpur, belum diluluskan.

## Traceability, changes & approval

Consult [RTM](../requirements/RTM.md), [open questions](../requirements/OPEN_QUESTIONS.md) and [release gates](../quality/RELEASE_GATES.md). Any behavioral change must update the SRS and its traceability in the same PR. No `DEV READY` or production approval is implied.
