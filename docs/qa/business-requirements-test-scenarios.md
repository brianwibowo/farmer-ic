# Business Requirements Test Scenarios

Dokumen ini menerjemahkan requirement dalam [`business-requirements-register.md`](business-requirements-register.md) menjadi skenario uji yang dapat ditinjau, dijalankan, dan dipakai sebagai bukti handover. Skenario berasal dari hasil bisnis yang diminta stakeholder; implementasi yang ada hanya dipakai untuk memilih kandidat level otomatisasi.

## Status dan aturan penggunaan

- Seluruh requirement register saat ini berstatus **Draft**. Skenario berikut adalah rancangan (**Proposed**), bukan kriteria penerimaan final atau bukti fitur sudah lulus.
- Pemilik proses perlu menyetujui requirement dan keputusan terbuka sebelum skenario yang terkait dijadikan release gate.
- Requirement berstatus `Decide`/aktor `TBD` hanya memiliki skenario klarifikasi. Jangan memberi status lulus/gagal untuk perilaku bisnis sebelum keputusan disetujui.
- Jangan menambah expected result berdasarkan route, tabel, seeder, atau perilaku aplikasi yang kebetulan sekarang ada. Bila hasil bisnis ambigu, tandai `Needs decision`.
- Setiap uji harus merekam versi/commit, lingkungan, data fixture, hasil aktual, bukti, dan defect. Jangan gunakan data produksi.

## Konvensi ID dan status

ID skenario memakai `TC-{MODUL}-{LEVEL}-{NNN}`. Level: `UNIT`, `MOD` (module), `INT` (integration), `E2E`. Status skenario: `Proposed`, `Ready` (requirement disetujui dan fixture tersedia), `Blocked`, `Passed`, `Failed`, `Not Run`. Status hasil hanya diisi pada eksekusi tertentu; jangan menyimpan hasil lama sebagai status permanen skenario.

| Skenario | Level utama | Requirement terkait | Cakupan hasil bisnis |
| --- | --- | --- | --- |
| TC-FM-MOD-001 | Module | BR-FM-ADM-FARM-001–010 | Buat, lihat, koreksi kebun; validasi, lokasi/wilayah, legalitas, target, PIC, dokumentasi, penugasan. |
| TC-FM-MOD-002 | Module | BR-FM-ADM-BLK-001–005 | Siklus hidup blok, identitas unik, kapasitas, komoditas/SOP, pelestarian acuan historis. |
| TC-FM-MOD-003 | Module | BR-FM-ADM-SBL-001–004 | Buat sub-blok manual/otomatis, tanggal rencana, kapasitas tersisa, batas luas. |
| TC-FM-INT-001 | Integration | BR-FM-ADM-DAT-001–002; BR-FM-ADM-FARM-002; BR-FM-ADM-BLK-003; BR-FM-ADM-SBL-004 | Integritas hierarki, alokasi agregat, arsip/riwayat, submit berulang. |
| TC-ACT-MOD-001 | Module | BR-ACT-PET-LOG-001–003 | Catat aktivitas dengan tanggal/waktu, konteks kebun/blok, rincian, pekerja dan validasi. |
| TC-ACT-MOD-002 | Module | BR-ACT-PET-EVD-001; BR-ACT-PET-LOG-004; BR-ACT-ADM-LOG-005 | Bukti, riwayat, retry upload, koreksi/pembatalan dan jejak audit. |
| TC-ACT-INT-001 | Integration | BR-ACT-PET-LOG-002–004; BR-CYC-PET-TASK-001; BR-CYC-ADM-TASK-001 | Aktivitas dari lapangan terkait pada objek/siklus/tugas yang tepat dan dapat ditelusuri. |
| TC-ACT-DEC-001 | Module | BR-ACT-TBD-APR-001 | Putuskan kebutuhan approval, aktor dan state review sebelum acceptance test dibuat. |
| TC-PMB-MOD-001 | Module | BR-PMB-PET-NAV-001; BR-PMB-PET-HOME-001–002; BR-PMB-PET-WX-001; BR-PMB-PET-UI-001 | Navigasi mobile, ringkasan/to-do/aksi cepat, jumlah batch, cuaca gagal, viewport dan state UI. |
| TC-PMB-MOD-002 | Module | BR-PMB-PET-FARM-001–002; BR-PMB-PET-MAP-001; BR-PMB-PET-LIST-001 | Daftar kebun Petugas, ringkasan, peta titik, blok dan sub-blok yang benar. |
| TC-PMB-MOD-003 | Module | BR-PMB-PET-TASK-001–002 | Pengelompokan urgensi, pencarian, filter, reset, hitungan dan empty state tugas. |
| TC-PMB-MOD-004 | Module | BR-PMB-PET-REPORT-001–002 | Riwayat kerja, detail aktivitas dan filter tanggal/kategori/sumber/inputter. |
| TC-PMB-E2E-001 | E2E | BR-PMB-PET-NAV-001; BR-PMB-PET-HOME-001; BR-PMB-PET-FARM-001; BR-PMB-PET-TASK-001; BR-PMB-PET-REPORT-001 | Petugas berangkat dari beranda, membuka kebun/tugas, menyelesaikan pekerjaan, lalu menemukan jejak di laporan. |
| TC-CYC-UNIT-001 | Unit | BR-CYC-ALL-HST-001 | Batas window pengelompokan HST/cycle, tanggal acuan dan kasus tepat di batas; angka final menunggu keputusan bisnis. |
| TC-CYC-MOD-001 | Module | BR-CYC-ADM-PLAN-001; BR-CYC-ADM-CYCLE-001; BR-CYC-ADM-SOP-001 | Rencana dibedakan dari realisasi; mulai cycle; struktur/versi SOP sesuai keputusan. |
| TC-CYC-INT-001 | Integration | BR-CYC-ADM-TASK-001; BR-CYC-PET-TASK-001; BR-CYC-TBD-EXC-001; BR-ACT-PET-LOG-002–003 | Generate jadwal dari SOP/cycle, daftar Petugas, penyelesaian melalui aktivitas dan pengecualian. |
| TC-MON-MOD-001 | Module | BR-MON-PET-OBS-001–002; BR-MON-PET-INC-001 | Catat pengamatan dan insiden dengan nilai, lokasi, batas, bukti dan validasi yang disepakati. |
| TC-MON-MOD-002 | Module | BR-MON-PET-HRV-001; BR-MON-ADM-HRV-002 | Catat hasil panen; koreksi berwenang dengan nilai lama/baru, alasan, pelaku dan waktu. |
| TC-MON-INT-001 | Integration | BR-MON-OWN-PRJ-001; BR-ODS-OWN-PRJ-001–002; BR-ODS-OWN-COM-001 | Proyeksi dibedakan dari target/aktual dan agregasi komoditas/wilayah konsisten dengan detail. |
| TC-OWN-MOD-001 | Module | BR-EXS-OWN-SUM-001; BR-EXS-OWN-INS-001; BR-EXS-OWN-DRL-001; BR-RPT-OWN-SUM-001; BR-ODS-OWN-FARM-001; BR-ODS-OWN-REF-001 | Executive Summary dan dashboard Owner, cakupan KPI, empty/null, refresh, drill-down ke detail. |
| TC-OWN-INT-001 | Integration | BR-RPT-OWN-REG-001; BR-RPT-OWN-FLT-001; BR-RPT-OWN-VIS-001; BR-RPT-OWN-EXP-001; BR-RPT-OWN-SCP-001; BR-ODS-OWN-REG-001; BR-ODS-OWN-MAP-001 | Filter, agregasi kabupaten, visualisasi, ekspor, cakupan RBAC dan marker tanpa koordinat. |
| TC-FIN-DEC-001 | Decision | BR-FIN-DEC-001; BR-FIN-ADM-TRX-001; BR-FIN-ADM-CAT-001; BR-FIN-ADM-BUD-001; BR-FIN-OWN-RPT-001 | Tetapkan finance in/out of scope dan kebijakan transaksi, kategori, anggaran, laporan. |
| TC-NTF-DEC-001 | Decision | BR-NTF-ADM-TRG-001; BR-NTF-ADM-RCP-001; BR-NTF-ADM-DEL-001; BR-NTF-DEC-001 | Setujui event pemicu, penerima, kanal, retry, retensi dan efek pergantian penugasan. |
| TC-NTF-INT-001 | Integration | BR-NTF-ALL-READ-001; BR-NTF-ALL-FUP-001 | Status baca, persistensi, tautan konteks, cek akses, aksi dan hasil follow-up. |

> Rentang seperti `001–010` bermakna semua ID dalam rentang bernomor pada modul/objek itu. Pemetaan ini tidak menggantikan review pemilik proses.

## Prosedur skenario

Isi aturan yang masih terbuka hanya setelah keputusan bisnis dicatat di register. Fixture harus sintetis dan mencakup data lengkap serta keadaan kosong/tidak diketahui.

### TC-FM-MOD-001 — Siklus data kebun

**Tujuan:** membuktikan Admin dapat membuat, melihat, dan memperbarui kebun tanpa menyamakan data tidak diketahui dengan nol.

**Aktor/prasyarat:** Admin berwenang; daftar wilayah/legalitas/PIC sesuai kebijakan disetujui; kebun uji belum ada.

| Langkah | Hasil yang diharapkan |
| --- | --- |
| Isi identitas, luas valid, target/periode, legalitas/pihak, alamat/wilayah, titik opsional, dokumentasi, dan PIC sesuai aturan. Simpan. | Kebun tercatat satu kali; satuan, presisi, nilai kosong, dan konfirmasi berhasil jelas. |
| Buka kebun dari daftar dan detail. | Seluruh nilai tampil konsisten dan hanya dalam lingkup RBAC yang disetujui. |
| Ubah nilai yang diizinkan lalu simpan; periksa field lain. | Nilai perubahan tervalidasi; field lain tidak berubah; riwayat tersedia bila diwajibkan. |
| Uji luas nol/negatif/format salah, wilayah turunan tanpa induk, PIC tidak valid, tautan/berkas tidak diizinkan. | Input ditolak dengan pesan jelas; tidak menyimpan perubahan parsial; perilaku opsional sesuai keputusan. |
| Ulangi submit/refresh saat submit sesuai rancangan idempotensi. | Tidak ada duplikasi atau pengguna mendapat status hasil operasi sebelumnya. |

**Requirement:** BR-FM-ADM-FARM-001–010. Validasi domain dapat memiliki Unit; persistensi dan akses diuji lewat Integration.

### TC-FM-MOD-002 / TC-FM-MOD-003 — Blok dan sub-blok

**Prasyarat:** kebun dengan luas disetujui; daftar komoditas/SOP tersedia bila relevan.

| Kasus | Langkah | Hasil yang diharapkan |
| --- | --- | --- |
| Blok normal | Buat blok berkode/nama unik, luas dan status; atur komoditas/SOP bila disetujui. | Blok terikat ke kebun benar; luas, satuan, status dan sisa kapasitas konsisten. |
| Validasi blok | Coba kode duplikat pada kebun sama, luas tidak valid, alokasi aktif melewati kapasitas. | Data ditolak; keadaan sebelum submit tetap utuh. |
| Ubah/nonaktifkan | Ubah nama/luas/status; tinjau dampak pada siklus berjalan. | Acuan historis tidak berubah; aturan hapus/nonaktif diterapkan. |
| Sub-blok otomatis | Tambahkan sub-blok tanpa label dan tanggal rencana. | Label unik berurutan; tanggal rencana bukan tanggal tanam aktual. |
| Sub-blok manual/batas | Tambahkan label manual; uji label bentrok dan luas melebihi sisa. | Label valid tersimpan; konflik/over-allocation ditolak; sisa dihitung menurut rumus disetujui. |

**Requirement:** BR-FM-ADM-BLK-001–005, BR-FM-ADM-SBL-001–004.

### TC-FM-INT-001 — Integritas hierarki dan kapasitas

Buat kebun → blok → sub-blok → cycle/aktivitas terkait; pastikan detail kembali ke induk benar. Ubah/nonaktifkan induk dan cek riwayat. Jalankan dua pembuatan sub-blok bersamaan pada kapasitas sama. Hasil: tidak ada relasi silang, riwayat hilang, over-allocation, atau record ganda; aturan arsip/penghapusan dan transaksi bersamaan harus disepakati. **Requirement:** BR-FM-ADM-DAT-001–002 serta aturan kapasitas terkait.

### TC-ACT-MOD-001 / TC-ACT-MOD-002 — Catat dan telusuri aktivitas

**Prasyarat:** Petugas memiliki konteks kerja; kategori dan definisi unit aktivitas telah disepakati.

| Langkah | Hasil yang diharapkan |
| --- | --- |
| Catat aktivitas dengan tanggal kerja, waktu, lokasi relevan, kategori, rincian, pekerja, catatan dan bukti sesuai kewajiban. | Aktivitas memakai tanggal/waktu dan lokasi tepat; format dan unit valid. |
| Coba nilai wajib kosong/salah, tanggal/waktu batas, lokasi tidak berkaitan, bukti tipe/ukuran tak diizinkan. | Sistem menolak dengan pesan jelas; tidak ada record setengah tersimpan. |
| Simulasikan unggah bukti gagal lalu retry. | Status unggah jelas; retry tidak menggandakan bukti/aktivitas dan aktivitas gagal tak dianggap lengkap. |
| Cari aktivitas di riwayat, buka detail dan bukti. | Nilai, pelapor, waktu, lokasi, status dan bukti dapat ditelusuri sesuai RBAC. |
| Koreksi/batalkan sesuai role dan batas waktu yang disepakati. | Perubahan memiliki pelaku/waktu/alasan; nilai lama dapat diaudit bila diwajibkan. |

**Requirement:** BR-ACT-PET-LOG-001–004, BR-ACT-PET-EVD-001, BR-ACT-ADM-LOG-005. Waktu lintas tengah malam, bukti wajib/opsional dan aturan edit memerlukan keputusan.

### TC-ACT-INT-001 — Tugas menjadi aktivitas

Pilih tugas dari cycle/lokasi tertentu; selesaikan lewat alur aktivitas; kirim rincian dan bukti; buka kembali task, cycle, dan riwayat. Hasil: relasi, lokasi, status, tanggal aktual, dan bukti merujuk pekerjaan sama; task lain tidak berubah; kegagalan dapat dipulihkan tanpa duplikasi. **Requirement:** BR-ACT-PET-LOG-002–004, BR-CYC-ADM-TASK-001, BR-CYC-PET-TASK-001.

### TC-PMB-MOD-001 sampai 004 — Pengalaman mobile Petugas

Uji viewport ponsel kecil, standar, besar serta tema terang/gelap. Cakupan: Beranda dengan/tanpa to-do; ringkasan dibanding daftar sumber; cuaca tidak tersedia/retry; navigasi dan kembali; daftar kebun kosong/berisi; detail dan peta dengan/tanpa koordinat; blok/sub-blok; tugas terlambat/hari ini/mendatang; pencarian/filter/reset; laporan berisi/kosong; jaringan gagal. Hasil: loading/empty/error/success jelas; kontrol tidak terpotong/tertutup; hitungan dan daftar konsisten; aksi mempertahankan konteks. **Requirement:** semua BR-PMB-PET pada tabel cakupan.

### TC-PMB-E2E-001 — Petugas menyelesaikan pekerjaan

Masuk sebagai Petugas uji → Beranda → pilih kebun/tugas → tinjau blok/sub-blok → catat aktivitas dan bukti → konfirmasi → buka Laporan dan temukan aktivitas. Cocokkan lokasi, kategori, tanggal, bukti dan status. Ulangi dengan task kosong dan submit gagal. Hak akses ditentukan BR RBAC yang disetujui.

### TC-CYC-UNIT-001 / TC-CYC-MOD-001 / TC-CYC-INT-001 — Cycle, SOP, pengecualian

Uji perhitungan tanggal/HST sebelum, tepat pada, dan sesudah batas window yang disepakati. Buat rencana tanpa realisasi, mulai cycle dari realisasi, tautkan SOP/versi, lalu cocokkan tanggal task dari offset. Ubah SOP setelah cycle berjalan dan cek versi acuan. Selesaikan task atau catat pengecualian sesuai kebijakan. Hasil harus cocok kalender dan aturan **yang disetujui**. Bila window, revisi SOP, toleransi, atau skip belum diputuskan, tandai `Blocked – business decision`.

### TC-MON-MOD-001 / TC-MON-MOD-002 — Pengamatan, insiden, panen

Catat pengamatan pada batas angka/unit/jumlah sampel yang disepakati; insiden dengan tingkat/luas/bukti/status tindak lanjut; dan hasil panen dengan satuan/periode. Buka riwayat dan pastikan observasi, insiden, target, proyeksi dan aktual tidak tercampur. Koreksi panen sebagai aktor yang ditetapkan dan verifikasi jejak audit. Ambang agronomi, field wajib, dan alur koreksi perlu disetujui.

### TC-MON-INT-001 / TC-OWN-MOD-001 / TC-OWN-INT-001 — Agregasi Owner dan laporan

Siapkan dataset acuan sintetis: beberapa kebun di beberapa kabupaten, komoditas berbeda, koordinat ada/tidak, target/proyeksi/aktual, progres lengkap/tidak diketahui, serta kebun di luar cakupan Owner. Hitung hasil acuan independen. Buka Executive Summary/dashboard → cocokkan KPI → drill-down → kelompokkan wilayah → filter → visualisasi/tooltips → ekspor. Hasil: detail grup menjumlah tepat ke agregat menurut aturan pembulatan; unknown tidak menjadi nol; filter, peta, dashboard, ekspor konsisten periode/rumus/cakupan; data di luar RBAC tidak muncul di total atau ekspor.

### TC-FIN-DEC-001 — Keputusan finance

Pemilik proses tetapkan finance in/out of scope serta aktor, kategori, periode, mata uang/satuan, transaksi batal/koreksi, approval, anggaran, laporan/ekspor. Catat di BR-FIN-DEC-001. Sebelum disetujui, transaksi bukan release gate dan tidak diberi PASS.

### TC-NTF-DEC-001 / TC-NTF-INT-001 — Notifikasi dan tindak lanjut

Setelah event/penerima/kanal disetujui, picu kondisi yang memenuhi/tidak memenuhi; verifikasi penerima, retry, pengulangan dan idempotensi. Tandai dibaca, muat ulang, pastikan status bertahan tanpa mengubah status penyelesaian event. Buka follow-up dan cek objek/konteks, authorization, hasil aksi. Uji pergantian assignment dan kegagalan kanal. Gunakan provider palsu/terisolasi; jangan hubungi kanal eksternal.

### TC-ACT-DEC-001 — Approval aktivitas

Pemilik proses tentukan apakah approval diperlukan, approver, state, alasan penolakan/koreksi, edit setelah submit, bukti/audit. Rancang acceptance test sesudah keputusan; saat ini tandai `Blocked – business decision`.

## Matriks traceability per requirement

| Req ID | Test case | Status saat dokumen dibuat |
| --- | --- | --- |
| BR-FM-ADM-FARM-001–010 | TC-FM-MOD-001 | Draft |
| BR-FM-ADM-BLK-001–005 | TC-FM-MOD-002 | Draft |
| BR-FM-ADM-SBL-001–004 | TC-FM-MOD-003 | Draft |
| BR-FM-ADM-DAT-001–002 | TC-FM-INT-001 | Draft |
| BR-ACT-PET-LOG-001–003 | TC-ACT-MOD-001 | Draft |
| BR-ACT-PET-EVD-001; BR-ACT-PET-LOG-004; BR-ACT-ADM-LOG-005 | TC-ACT-MOD-002 | Draft |
| BR-ACT-PET-LOG-002–004; BR-CYC-ADM-TASK-001; BR-CYC-PET-TASK-001 | TC-ACT-INT-001 | Draft |
| BR-ACT-TBD-APR-001 | TC-ACT-DEC-001 | Draft / Decide |
| BR-PMB-PET-NAV-001; BR-PMB-PET-HOME-001–002; BR-PMB-PET-WX-001; BR-PMB-PET-UI-001 | TC-PMB-MOD-001 | Draft |
| BR-PMB-PET-FARM-001–002; BR-PMB-PET-MAP-001; BR-PMB-PET-LIST-001 | TC-PMB-MOD-002 | Draft |
| BR-PMB-PET-TASK-001–002 | TC-PMB-MOD-003; TC-PMB-E2E-001 | Draft |
| BR-PMB-PET-REPORT-001–002 | TC-PMB-MOD-004; TC-PMB-E2E-001 | Draft |
| BR-CYC-ALL-HST-001 | TC-CYC-UNIT-001 | Draft |
| BR-CYC-ADM-PLAN-001; BR-CYC-ADM-CYCLE-001; BR-CYC-ADM-SOP-001 | TC-CYC-MOD-001 | Draft |
| BR-CYC-ADM-TASK-001; BR-CYC-PET-TASK-001; BR-CYC-TBD-EXC-001 | TC-CYC-INT-001 | Draft / Decide untuk pengecualian |
| BR-MON-PET-OBS-001–002; BR-MON-PET-INC-001 | TC-MON-MOD-001 | Draft |
| BR-MON-PET-HRV-001; BR-MON-ADM-HRV-002 | TC-MON-MOD-002 | Draft |
| BR-MON-OWN-PRJ-001; BR-ODS-OWN-PRJ-001–002; BR-ODS-OWN-COM-001 | TC-MON-INT-001 | Draft |
| BR-EXS-OWN-SUM-001; BR-EXS-OWN-INS-001; BR-EXS-OWN-DRL-001; BR-RPT-OWN-SUM-001; BR-ODS-OWN-FARM-001; BR-ODS-OWN-REF-001 | TC-OWN-MOD-001 | Draft |
| BR-RPT-OWN-REG-001; BR-RPT-OWN-FLT-001; BR-RPT-OWN-VIS-001; BR-RPT-OWN-EXP-001; BR-RPT-OWN-SCP-001; BR-ODS-OWN-REG-001; BR-ODS-OWN-MAP-001 | TC-OWN-INT-001 | Draft |
| BR-FIN-DEC-001; BR-FIN-ADM-TRX-001; BR-FIN-ADM-CAT-001; BR-FIN-ADM-BUD-001; BR-FIN-OWN-RPT-001 | TC-FIN-DEC-001 | Draft / Decide |
| BR-NTF-ADM-TRG-001; BR-NTF-ADM-RCP-001; BR-NTF-ADM-DEL-001; BR-NTF-DEC-001 | TC-NTF-DEC-001 | Draft / Decide |
| BR-NTF-ALL-READ-001; BR-NTF-ALL-FUP-001 | TC-NTF-INT-001 | Draft |

### Kebutuhan RBAC

Requirement RBAC tetap menjadi sumber aturan akses. Untuk tiap BR RBAC yang disetujui, buat skenario berisi aktor/grant, objek dalam dan luar cakupan, aksi diizinkan/ditolak, hasil HTTP/UX, serta total/count/search/export. Gabungkan pemeriksaan akses lintas modul pada skenario integrasi dan E2E. Keberadaan role/route tidak otomatis menyetujui hak akses bisnis.

## Form rekaman hasil eksekusi

Salin untuk setiap test case pada setiap run:

```text
Execution ID:
Test Case ID / Version:
Linked Req ID(s) / Requirement version:
Requirement status and approval reference:
Tester / Reviewer:
Executed at (timezone):
Environment / application URL:
Git commit / build identifier:
Test level / tool / tool version:
Preconditions and fixture/data-set ID:
Steps executed:
Expected result:
Actual result:
Result: Passed | Failed | Blocked | Not Run
Evidence path/link (logs, screenshot, recording, report):
Defect ID / severity / owner:
Notes / next action:
```

`Blocked` karena requirement belum disetujui bukan bug dan bukan PASS. Defect merujuk TC dan BR, memiliki bukti, serta langkah reproduksi.

## Sign-off

| Peran | Nama | Keputusan | Tanggal | Referensi |
| --- | --- | --- | --- | --- |
| Pemilik proses |  | Setuju / Revisi |  |  |
| Perwakilan pengguna |  | Setuju / Revisi |  |  |
| QA/test lead |  | Siap diuji / Blocked |  |  |
| Reviewer hasil |  | Terima / Tolak |  |  |
