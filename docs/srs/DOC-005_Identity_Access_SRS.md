# DOC-005 | Identity & Access Module SRS

> **Scope clarification v0.3 (2026-10-29):** This is the **System Core** first-module specification for `MOD-001` User Management / Identity & Access. **HR Core remains on hold**. The 13 requirements below are still DRAFT; review open decisions in [MOD-001 Review v0.3](../requirements/MOD-001_USER_MANAGEMENT_REVIEW_v0.3.md) before implementation. No employee master is required for user-account creation.

> **Version:** v0.1 • **Date:** 29 October 2026 • **Status:** DRAFT / NOT APPROVED • **Owner/approval:** HR/IT TBD
>
> This Markdown edition is derived from the draft DOC-005 DOCX baseline. All SHALL/HENDAKLAH clauses are **proposals**, not approved policy. IDs, acceptance criteria and planned tests retain their original meaning. No tests have been executed.

## 1. Boundary modul

Mengawal siapa boleh mengakses sistem, bagaimana pengguna dibina/dinyahaktifkan, kebenaran role dan profil sendiri. Pengurusan profil pekerja adalah modul berasingan. Akaun pengguna bukan rekod pekerja; hubungan pilihan dengan rekod pekerja tidak boleh dipaksa sebelum DOC-007 diterbitkan.

## 2. Actor & role proposal

System Admin: Boleh mencipta dan menyahaktifkan akaun, mengurus role dan konfigurasi (cadangan). HR Admin: Calon role untuk operasi HR kelak; akses sebenar TBD. User: Boleh log masuk/log keluar dan akses diri sendiri yang dibenarkan. Security reviewer: Perlu menyemak permission matrix sebelum implementasi.

## 3. Workflow aras tinggi

Pengguna pentadbir membuat akaun → tetapkan status dan role → pengguna mengesahkan identiti → permission disemak pada setiap operasi → akaun boleh dinyahaktifkan tanpa kehilangan audit. Aliran reset/undangan memerlukan keputusan saluran sah (emel, SSO atau manual) dan tidak boleh dihardcode atas andaian.

## 4. Data logikal & dependencies

Data konseptual: User, Role, Permission, UserRole, RolePermission, Session, AuditEvent. Ini bukan arahan fizikal untuk DB table. Pengenal login, unique scope, mandatory profile fields, role catalogue, recovery, inactivity/session policy, dan MFA adalah open items.

## 5. Negative & security verification

Uji pengguna tidak berizin cuba edit role melalui URL/API, pengguna disabled cuba log masuk/sesi sedia ada, last-admin lockout, duplikasi login ID dan credential invalid.

## Requirements baseline (13)

### IAM-FR-001 | Cipta pengguna

**Requirement:** Pentadbir berizin hendaklah boleh mencipta akaun pengguna dengan maklumat minimum yang diluluskan.

**Acceptance criterion (AC-IAM-FR-001):** Pentadbir berjaya mencipta akaun sah; pengguna tanpa hak ditolak.

**Test case (T-IAM-FR-001):** UAT: create user positive/negative

**Status:** DRAFT / NOT APPROVED. **Evidence:** NOT TESTED. **Feature:** F-001. **Priority:** Must. **Origin:** Arahan awal pengguna.

**Nota / dependency:** Senarai medan minimum TBD

### IAM-FR-002 | Pengenal log masuk unik

**Requirement:** Sistem hendaklah memastikan pengenal log masuk unik dalam skop organisasi yang dikonfigurasikan.

**Acceptance criterion (AC-IAM-FR-002):** Percubaan mewujudkan dua akaun dengan pengenal sama ditolak dengan mesej boleh difahami.

**Test case (T-IAM-FR-002):** Validation: duplicate login ID

**Status:** DRAFT / NOT APPROVED. **Evidence:** NOT TESTED. **Feature:** F-001. **Priority:** Must. **Origin:** Cadangan analisis.

**Nota / dependency:** Pilihan email/staff ID/username TBD

### IAM-FR-003 | Log masuk dan log keluar

**Requirement:** Pengguna aktif hendaklah boleh mengesahkan identiti untuk masuk dan menamatkan sesi melalui log keluar.

**Acceptance criterion (AC-IAM-FR-003):** Credential sah dibenarkan; tidak sah ditolak; logout menamatkan akses sesi itu.

**Test case (T-IAM-FR-003):** Auth: login/logout

**Status:** DRAFT / NOT APPROVED. **Evidence:** NOT TESTED. **Feature:** F-001. **Priority:** Must. **Origin:** Arahan awal pengguna.

### IAM-FR-004 | Tetap semula akses

**Requirement:** Sistem hendaklah menyediakan aliran pemulihan akses melalui kaedah pengesahan yang diluluskan.

**Acceptance criterion (AC-IAM-FR-004):** Pemulihan memerlukan pengesahan sah; permintaan lapuk atau tidak sah ditolak.

**Test case (T-IAM-FR-004):** Auth: reset valid/invalid

**Status:** DRAFT / NOT APPROVED. **Evidence:** NOT TESTED. **Feature:** F-003. **Priority:** Must. **Origin:** Cadangan analisis.

**Nota / dependency:** Saluran emel/SSO/manual dan polisi pemulihan TBD

### IAM-FR-005 | Nyahaktif dan aktif semula

**Requirement:** Pentadbir berizin hendaklah boleh menyahaktifkan dan mengaktifkan semula akaun tanpa memadam sejarahnya.

**Acceptance criterion (AC-IAM-FR-005):** Akaun nyahaktif tidak boleh masuk; sejarah dan identiti rekod kekal.

**Test case (T-IAM-FR-005):** UAT: deactivate/reactivate

**Status:** DRAFT / NOT APPROVED. **Evidence:** NOT TESTED. **Feature:** F-003. **Priority:** Must. **Origin:** Cadangan analisis.

### IAM-FR-006 | Tugas peranan

**Requirement:** Pentadbir berizin hendaklah boleh mengaitkan atau menanggalkan peranan daripada akaun pengguna.

**Acceptance criterion (AC-IAM-FR-006):** Perubahan role hanya dibenarkan kepada pentadbir dan dapat dikesan dalam audit.

**Test case (T-IAM-FR-006):** Authz: assign/unassign role

**Status:** DRAFT / NOT APPROVED. **Evidence:** NOT TESTED. **Feature:** F-002. **Priority:** Must. **Origin:** Cadangan analisis.

### IAM-FR-007 | Matriks role-permission

**Requirement:** Sistem hendaklah menyimpan pemetaan permission kepada peranan sebagai asas kawalan akses.

**Acceptance criterion (AC-IAM-FR-007):** Pengguna dengan role sesuai boleh melakukan operasi; role tanpa permission ditolak.

**Test case (T-IAM-FR-007):** Authz: permission matrix

**Status:** DRAFT / NOT APPROVED. **Evidence:** NOT TESTED. **Feature:** F-002. **Priority:** Must. **Origin:** Cadangan analisis.

**Nota / dependency:** Senarai role/permission disahkan bersama HR/IT

### IAM-FR-008 | Profil sendiri

**Requirement:** Pengguna hendaklah boleh melihat profil sendiri dan hanya mengubah medan kendiri yang dibenarkan.

**Acceptance criterion (AC-IAM-FR-008):** Pengguna tidak dapat mengubah role/status akaun melalui fungsi profil sendiri.

**Test case (T-IAM-FR-008):** UAT: self-profile boundary

**Status:** DRAFT / NOT APPROVED. **Evidence:** NOT TESTED. **Feature:** F-001. **Priority:** Must. **Origin:** Cadangan analisis.

### IAM-FR-009 | Penamatan sesi

**Requirement:** Sistem hendaklah membatalkan sesi yang tidak lagi dibenarkan selepas akaun dinyahaktifkan.

**Acceptance criterion (AC-IAM-FR-009):** Sesi pengguna yang dinyahaktifkan tidak boleh meneruskan operasi terlindung.

**Test case (T-IAM-FR-009):** Security: revoke disabled session

**Status:** DRAFT / NOT APPROVED. **Evidence:** NOT TESTED. **Feature:** F-003. **Priority:** Must. **Origin:** Cadangan analisis.

### IAM-FR-010 | Lindungi pentadbir terakhir

**Requirement:** Sistem hendaklah menolak tindakan yang mengakibatkan tiada satu pun akaun pentadbir aktif berizin.

**Acceptance criterion (AC-IAM-FR-010):** Cubaan nyahaktif atau buang akses pentadbir aktif terakhir ditolak.

**Test case (T-IAM-FR-010):** Negative: last admin protection

**Status:** DRAFT / NOT APPROVED. **Evidence:** NOT TESTED. **Feature:** F-002. **Priority:** Must. **Origin:** Cadangan analisis.

### IAM-FR-011 | Pendaftaran awam tertutup

**Requirement:** Sistem hendaklah menyekat pendaftaran akaun awam selagi tiada kelulusan polisi self-registration.

**Acceptance criterion (AC-IAM-FR-011):** Pengunjung tidak boleh daftar sendiri tanpa aliran kelulusan.

**Test case (T-IAM-FR-011):** Security: unauthorized signup

**Status:** DRAFT / NOT APPROVED. **Evidence:** NOT TESTED. **Feature:** F-001. **Priority:** Should. **Origin:** Cadangan analisis.

**Nota / dependency:** Keputusan self-service account registration TBD

### IAM-FR-012 | Senarai pengguna

**Requirement:** Pentadbir berizin hendaklah boleh mencari dan menapis senarai akaun mengikut pengenal, status dan peranan.

**Acceptance criterion (AC-IAM-FR-012):** Carian/paparan status dan peranan tepat untuk data ujian.

**Test case (T-IAM-FR-012):** UAT: users list/filter

**Status:** DRAFT / NOT APPROVED. **Evidence:** NOT TESTED. **Feature:** F-001. **Priority:** Must. **Origin:** Cadangan analisis.

### IAM-AUD-001 | Audit identiti

**Requirement:** Sistem hendaklah merekod penciptaan, penyahaktifan, pengaktifan dan perubahan role akaun.

**Acceptance criterion (AC-IAM-AUD-001):** Audit mengandungi pelaku, masa, tindakan dan sasaran bagi empat jenis perubahan.

**Test case (T-IAM-AUD-001):** Audit: IAM events

**Status:** DRAFT / NOT APPROVED. **Evidence:** NOT TESTED. **Feature:** F-007. **Priority:** Must. **Origin:** Cadangan analisis.

## Traceability, changes & approval

Consult [RTM](../requirements/RTM.md), [open questions](../requirements/OPEN_QUESTIONS.md) and [release gates](../quality/RELEASE_GATES.md). Any behavioral change must update the SRS and its traceability in the same PR. No `DEV READY` or production approval is implied.
