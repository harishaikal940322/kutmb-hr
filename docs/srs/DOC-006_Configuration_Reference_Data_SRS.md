# DOC-006 | Configuration & Reference Data Module SRS

> **Version:** v0.1 • **Date:** 29 October 2026 • **Status:** DRAFT / NOT APPROVED • **Owner/approval:** HR/IT TBD
>
> This Markdown edition is derived from the draft DOC-006 DOCX baseline. All SHALL/HENDAKLAH clauses are **proposals**, not approved policy. IDs, acceptance criteria and planned tests retain their original meaning. No tests have been executed.

## 1. Boundary modul

Tetapan di sini bermaksud master/reference data yang digunakan berulang, contohnya gelaran sapaan “panggilan”. Senarai kategori lain bukan requirement diluluskan. Konfigurasi sensitif aplikasi, rahsia server, lesen atau integrasi bukan untuk diedit sebagai lookup umum.

## 2. Contoh kategori: cadangan sahaja

Dalam arahan: Panggilan atau gelaran sapaan seperti Encik/Puan/Dr, perlu disahkan maksud sebenar. Untuk diputuskan: Jabatan, cawangan, jawatan, jenis pekerjaan, gred, status pekerja: jangan implementkan tanpa review HR. Tidak diminta: Data peribadi sensitif, sebab kesihatan, kategori gaji dan struktur polisi HR.

## 3. Model logikal minimum

LookupCategory dan LookupValue; nilai mempunyai identiti stabil, code, display label, active flag, sort order. Medan created_at/updated_at/actor dicadangkan untuk audit. Jangan padam entri yang masih digunakan: bezakan hidden for new selection vs historical display. Metadata perlu disemak apabila modul pekerja mula berhubung.

## 4. Workflow & izin

Pentadbir berizin menambah kategori/label → validasi unik → susun paparan → nyahaktif jika usang → audit perubahan. Pengguna tanpa permission tidak boleh memintas melalui HTTP request.

## 5. Acceptance dependencies

Integration form yang menggunakan panggilan hanya boleh diuji penuh selepas modul konsumer tersedia; buat test adapter/mock dahulu dalam P1. Perubahan lookup yang mempengaruhi polisi bukan alasan untuk mengelak change control/approval.

## Requirements baseline (10)

### CFG-FR-001 | Jenis master data

**Requirement:** Pentadbir berizin hendaklah boleh mengurus kategori master data yang telah diluluskan bagi penggunaan modul.

**Acceptance criterion (AC-CFG-FR-001):** Kategori master data dapat didaftarkan dan dikenal pasti dengan kod unik.

**Test case (T-CFG-FR-001):** UAT: lookup category

**Status:** DRAFT / NOT APPROVED. **Evidence:** NOT TESTED. **Feature:** F-005. **Priority:** Must. **Origin:** Cadangan analisis.

**Nota / dependency:** Senarai kategori awal TBD

### CFG-FR-002 | Nilai rujukan

**Requirement:** Pentadbir berizin hendaklah boleh menambah dan mengemas kini nilai dalam kategori master data.

**Acceptance criterion (AC-CFG-FR-002):** Rekod rujukan baharu disimpan dan paparan menggunakan nilai kemas kini.

**Test case (T-CFG-FR-002):** UAT: create/edit lookup

**Status:** DRAFT / NOT APPROVED. **Evidence:** NOT TESTED. **Feature:** F-006. **Priority:** Must. **Origin:** Arahan awal pengguna.

### CFG-FR-003 | Nyahaktif nilai yang digunakan

**Requirement:** Sistem hendaklah membenarkan nyahaktif nilai rujukan sambil mengekalkan rujukan sejarah kepadanya.

**Acceptance criterion (AC-CFG-FR-003):** Nilai nyahaktif hilang dari pilihan baharu tetapi data terdahulu masih memaparkan nilai asal.

**Test case (T-CFG-FR-003):** Regression: inactive historical lookup

**Status:** DRAFT / NOT APPROVED. **Evidence:** NOT TESTED. **Feature:** F-006. **Priority:** Must. **Origin:** Cadangan analisis.

### CFG-FR-004 | Carian dan susunan

**Requirement:** Pentadbir berizin hendaklah boleh mencari, menapis status dan menyusun keutamaan paparan nilai rujukan.

**Acceptance criterion (AC-CFG-FR-004):** Senarai mencerminkan filter aktif/tidak aktif dan sort order ditetapkan.

**Test case (T-CFG-FR-004):** UAT: lookup filter/sort

**Status:** DRAFT / NOT APPROVED. **Evidence:** NOT TESTED. **Feature:** F-006. **Priority:** Must. **Origin:** Cadangan analisis.

### CFG-FR-005 | Kod unik dalam kategori

**Requirement:** Sistem hendaklah menolak kod rujukan yang berulang dalam kategori yang sama.

**Acceptance criterion (AC-CFG-FR-005):** Dua nilai berkod sama dalam kategori sama ditolak; kod sama antara kategori berbeza dibenarkan.

**Test case (T-CFG-FR-005):** Validation: lookup code uniqueness

**Status:** DRAFT / NOT APPROVED. **Evidence:** NOT TESTED. **Feature:** F-006. **Priority:** Must. **Origin:** Cadangan analisis.

### CFG-FR-006 | Panggilan / gelaran

**Requirement:** Sistem hendaklah menyokong kategori panggilan (gelaran sapaan) yang nilainya boleh diurus oleh pentadbir berizin.

**Acceptance criterion (AC-CFG-FR-006):** Contoh Encik, Puan dan Dr. boleh dikonfigurasi tanpa perubahan kod aplikasi.

**Test case (T-CFG-FR-006):** UAT: manage salutations

**Status:** DRAFT / NOT APPROVED. **Evidence:** NOT TESTED. **Feature:** F-006. **Priority:** Must. **Origin:** Arahan awal pengguna.

**Nota / dependency:** Tafsiran panggilan = gelaran sapaan, perlu disahkan.

### CFG-FR-007 | Nilai aktif dalam borang

**Requirement:** Sistem hendaklah memaparkan hanya nilai rujukan aktif untuk pemilihan data baharu apabila kategori tersebut digunakan.

**Acceptance criterion (AC-CFG-FR-007):** Pilihan rujukan dalam borang tidak menawarkan rekod inactive; bacaan rekod lama kekal.

**Test case (T-CFG-FR-007):** Integration: active lookup selection

**Status:** DRAFT / NOT APPROVED. **Evidence:** NOT TESTED. **Feature:** F-005. **Priority:** Must. **Origin:** Cadangan analisis.

**Nota / dependency:** Borang penggunaan sebenar muncul dalam modul seterusnya

### CFG-FR-008 | Had akses tetapan

**Requirement:** Sistem hendaklah menghadkan operasi menukar tetapan dan master data kepada pengguna yang mempunyai permission berkaitan.

**Acceptance criterion (AC-CFG-FR-008):** Pengguna tanpa permission hanya menerima akses baca jika diluluskan, dan operasi menulis ditolak.

**Test case (T-CFG-FR-008):** Security: config RBAC

**Status:** DRAFT / NOT APPROVED. **Evidence:** NOT TESTED. **Feature:** F-005. **Priority:** Must. **Origin:** Cadangan analisis.

### CFG-DR-001 | Medan asas nilai rujukan

**Requirement:** Setiap nilai master data hendaklah mempunyai sekurang-kurangnya identiti stabil, kod, label, status aktif dan urutan paparan.

**Acceptance criterion (AC-CFG-DR-001):** Semua rekod rujukan mengandungi lima atribut logikal dan rujukan stabil tidak berubah apabila label diedit.

**Test case (T-CFG-DR-001):** Data: lookup invariant

**Status:** DRAFT / NOT APPROVED. **Evidence:** NOT TESTED. **Feature:** F-006. **Priority:** Must. **Origin:** Cadangan analisis.

### CFG-AUD-001 | Audit tetapan

**Requirement:** Sistem hendaklah merekod siapa mengubah kategori, label, status dan susunan nilai rujukan serta bila perubahan berlaku.

**Acceptance criterion (AC-CFG-AUD-001):** Kemaskini nilai rujukan dapat diaudit beserta aktor, masa dan tindakan.

**Test case (T-CFG-AUD-001):** Audit: config changes

**Status:** DRAFT / NOT APPROVED. **Evidence:** NOT TESTED. **Feature:** F-007. **Priority:** Must. **Origin:** Cadangan analisis.

## Traceability, changes & approval

Consult [RTM](../requirements/RTM.md), [open questions](../requirements/OPEN_QUESTIONS.md) and [release gates](../quality/RELEASE_GATES.md). Any behavioral change must update the SRS and its traceability in the same PR. No `DEV READY` or production approval is implied.
