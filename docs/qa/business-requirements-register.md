# Business Requirements Register

Dokumen ini adalah register kebutuhan bisnis untuk mendefinisikan perilaku yang diharapkan sebelum menyusun dan menjalankan test case. Requirement di sini berasal dari kebutuhan stakeholder dan perlu disepakati pemilik proses. Kode, halaman, data seed, dan perilaku aplikasi saat ini hanya menjadi petunjuk cakupan; semuanya bukan bukti bahwa suatu perilaku sudah menjadi kebutuhan bisnis.

Gunakan [`business-requirements-test-scenarios.md`](business-requirements-test-scenarios.md) untuk rancangan skenario dan traceability BR↔TC, serta [`test-execution-and-handover-guide.md`](test-execution-and-handover-guide.md) untuk tool, perintah, lokasi hasil, dan format handover.

## Tujuan dan cara menggunakan

1. Pemilik proses meninjau requirement pada kolom **Requirement** dan **Acceptance criteria**.
2. Lengkapi atau koreksi status, prioritas, aktor, aturan, serta keputusan terbuka sebelum requirement dianggap disetujui.
3. Beri setiap skenario uji ID tersendiri dan tautkan melalui kolom **Linked test case(s)**. Satu requirement dapat memiliki beberapa skenario; satu skenario boleh menguji beberapa requirement hanya jika tujuannya tetap jelas.
4. Perbarui status requirement, bukan menghapus requirement yang sudah disepakati. Catat alasan perubahan pada **Catatan/keputusan**.
5. Turunkan pengujian dari requirement yang disetujui. Jangan menyimpulkan kebutuhan bisnis hanya dari test atau implementasi yang sudah ada.

## Format ID dan legenda

Format: `BR-{MODULE}-{ROLE}-{OBJECT}-{NNN}`. ID bersifat tetap; jangan gunakan ulang ID requirement yang sudah dihapus atau digabung.

| Kode | Arti |
| --- | --- |
| BR | Business Requirement / kebutuhan bisnis |
| FM | Farm Management / manajemen kebun, blok, dan sub-blok |
| ACT | Agricultural Activity / aktivitas pertanian dan logbook |
| CYC | Planting Cycle & SOP / siklus tanam, SOP, dan tugas |
| MON | Monitoring & Harvest / pemantauan tanaman, insiden, dan panen |
| RPT | Dashboard & Reporting / dashboard dan pelaporan |
| EXS | Executive Summary / ringkasan eksekutif Owner |
| ODS | Owner Dashboard / dashboard Owner |
| PMB | Petugas Mobile / alur mobile Petugas |
| FIN | Finance & Operations / keuangan dan operasional |
| NTF | Notification / notifikasi dan tindak lanjut |
| ADM | Admin |
| PET | Petugas |
| OWN | Owner |
| ALL | Semua aktor yang ditetapkan dalam requirement |
| TBD | Aktor belum disepakati; perlu keputusan stakeholder |
| DEC | Keputusan lingkup/kebijakan yang harus diselesaikan, bukan perilaku final |
| FARM | Kebun/lahan |
| BLK | Blok |
| SBL | Sub-blok |
| DAT | Integritas data lintas objek |
| LOG | Aktivitas/logbook |
| EVD | Bukti aktivitas |
| APR | Review/persetujuan |
| PLAN | Rencana tanam |
| HST | Hari Setelah Tanam |
| SOP | Standard Operating Procedure |
| TASK | Tugas |
| OBS | Pengamatan |
| INC | Insiden |
| HRV | Panen |
| PRJ | Proyeksi |
| SUM | Ringkasan |
| REG | Wilayah |
| FLT | Filter |
| VIS | Visualisasi |
| EXP | Ekspor |
| SCP | Cakupan data/akses |
| TRX | Transaksi |
| CAT | Kategori transaksi |
| BUD | Anggaran/target |
| TRG | Pemicu notifikasi |
| RCP | Penerima |
| READ | Status dibaca |
| FUP | Follow-up / tindak lanjut |
| DEL | Pengiriman |
| HOME | Beranda mobile |
| LIST | Daftar |
| NAV | Navigasi |
| WX | Informasi cuaca |
| KPI | Indikator ringkasan |
| MAP | Peta lokasi |
| UI | Konsistensi tampilan/interaksi |

> Jika konflik penamaan muncul, pertahankan ID yang telah dipakai dan klarifikasi melalui kolom Catatan; jangan mengganti ID secara diam-diam.

## Batas aktor dan acuan RBAC

Kebutuhan autentikasi, role, izin, dan batas akses tidak didefinisikan ulang di register ini. Skenario otorisasi harus diturunkan dari **business requirement RBAC yang disepakati**, lalu dicocokkan dengan panduan teknis proyek [`rbac-authentication-guide.md`](../rbac-authentication-guide.md) dan [`authorization-scaffolding.md`](../authorization-scaffolding.md). Dokumen teknis membantu menemukan implementasi; ia tidak menggantikan persetujuan kebutuhan bisnis RBAC.

| Ruang pengalaman | Aktor utama | Cakupan pendefinisian |
| --- | --- | --- |
| Manajemen kebun desktop | Admin | Membuat dan mengelola kebun, blok, sub-blok, lokasi, legalitas, dan penugasan sesuai keputusan bisnis. |
| Executive Summary | Owner | Ringkasan eksekutif portofolio kebun dan indikator bisnis Owner. |
| Dashboard dan proyeksi Owner | Owner | Ikhtisar Owner, pengelompokan kebun/wilayah, progres, target, dan proyeksi panen. |
| Aplikasi mobile Petugas | Petugas | Beranda, Kebun, Tugas, Laporan, notifikasi, dan aksi cepat sesuai alur lapangan pada referensi visual stakeholder. |

Role selain aktor utama di atas hanya dimasukkan bila business requirement RBAC atau pemilik proses menetapkannya. Jangan menyimpulkan bahwa role tertentu boleh mengakses data hanya karena sebuah halaman atau route tersedia.

## Status, prioritas, dan kolom register

| Nilai | Definisi |
| --- | --- |
| Draft | Usulan awal; belum disetujui pemilik proses. |
| In Review | Sedang ditinjau stakeholder. |
| Approved | Disepakati dan boleh menjadi dasar test case. |
| Deferred | Diakui sebagai kebutuhan, tetapi ditunda dari lingkup saat ini. |
| Rejected | Ditolak stakeholder; alasan wajib dicatat. |
| Must | Wajib untuk tujuan/rilis yang disepakati. |
| Should | Bernilai tinggi, tetapi dapat dijadwalkan setelah Must. |
| Could | Opsional jika waktu/lingkup memungkinkan. |
| Decide | Belum dapat diberi prioritas sebelum keputusan bisnis. |

Setiap tabel menggunakan kolom **Req ID**, **Aktor**, **Requirement**, **Acceptance criteria**, **Priority**, **Status**, **Linked test case(s)**, dan **Catatan/keputusan**. Kolom test case dibiarkan `—` sampai skenario dirancang; status requirement pada dokumen ini dimulai sebagai `Draft` kecuali tertulis lain.

## 1. Manajemen Kebun (FM)

| Req ID | Aktor | Requirement | Acceptance criteria | Priority | Status | Linked test case(s) | Catatan/keputusan |
| --- | --- | --- | --- | --- | --- | --- | --- |
| BR-FM-ADM-FARM-001 | ADM | Admin dapat mendaftarkan kebun dengan identitas, luas, target panen, status legalitas/penguasaan, lokasi, dan PIC sesuai aturan bisnis. | Data valid tersimpan; kebun dapat ditemukan dan dibuka kembali. | Must | Draft | — | Tetapkan field wajib. |
| BR-FM-ADM-FARM-002 | ADM | Admin dapat melihat dan memperbarui informasi kebun yang berada dalam tanggung jawabnya. | Perubahan tervalidasi dan tersimpan; riwayat yang wajib dipertahankan tetap tersedia. | Must | Draft | — | Aturan koreksi/riwayat perlu disepakati. |
| BR-FM-ADM-FARM-003 | ADM | Sistem mencatat luas kebun dengan satuan yang jelas dan presisi yang disepakati. | Nilai nol, negatif, atau format tidak valid ditolak; nilai belum diketahui tidak dianggap nol. | Must | Draft | — | Kandidat satuan utama: hektare; tetapkan presisi. |
| BR-FM-ADM-FARM-004 | ADM | Admin dapat menetapkan target volume panen kebun. | Nilai ditampilkan dengan satuan dan periode/cycle yang jelas; validasi mengikuti aturan yang disepakati. | Should | Draft | — | Tetapkan target total vs per cycle dan batas minimum. |
| BR-FM-ADM-FARM-005 | ADM | Admin dapat mencatat status penguasaan/legalitas kebun dan pihak terkait bila relevan. | Pilihan berasal dari daftar yang disetujui; data pihak diwajibkan atau dikosongkan sesuai status. | Must | Draft | — | Sepakati daftar status dan istilah pihak/instansi. |
| BR-FM-ADM-FARM-006 | ADM | Admin dapat memilih PIC kebun atau membiarkannya belum ditentukan jika kebijakan mengizinkan. | PIC yang dipilih merupakan orang yang dapat ditugaskan; kondisi belum ditentukan terlihat jelas. | Should | Draft | — | Sepakati apakah PIC wajib. |
| BR-FM-ADM-FARM-007 | ADM | Admin dapat mencatat alamat dan wilayah administratif untuk membantu identifikasi lokasi. | Provinsi, kabupaten/kota, dan kecamatan konsisten; pilihan turunan direset ketika induk berubah; wilayah yang belum diketahui ditangani jelas. | Should | Draft | — | Tetapkan apakah seluruh hierarki wajib. |
| BR-FM-ADM-FARM-008 | ADM | Admin dapat menandai titik lokasi kebun secara opsional. | Titik dapat dikoreksi sebelum simpan; sistem menjelaskan bahwa titik bukan batas/poligon lahan. | Could | Draft | — | Putuskan kebutuhan geometri batas lahan terpisah. |
| BR-FM-ADM-FARM-009 | ADM | Admin dapat menambahkan referensi dokumentasi kebun jika diizinkan kebijakan. | Tautan/berkas mengikuti tipe, batas ukuran, dan aturan keamanan yang disepakati; kegagalan tidak menghilangkan data form. | Could | Draft | — | Putuskan URL saja atau unggah berkas. |
| BR-FM-ADM-FARM-010 | ADM | Admin dapat menunjuk, mengganti, atau melepas penugasan Petugas kebun sesuai kebijakan operasional. | Penugasan aktif terlihat oleh aktor terkait; riwayat pergantian dan tanggal efektif tercatat bila diperlukan. | Must | Draft | — | Tetapkan jumlah Petugas per kebun dan dampak pergantian pada tugas/riwayat. |
| BR-FM-ADM-BLK-001 | ADM | Admin dapat membuat, melihat, mengubah, dan menonaktifkan blok di dalam kebun. | Setiap blok tetap terkait ke kebun induk yang benar; perubahan status/luas terlihat pada ringkasan kebun. | Must | Draft | — | Tetapkan kondisi kapan blok boleh dihapus. |
| BR-FM-ADM-BLK-002 | ADM | Kode dan nama blok mengidentifikasi blok secara tidak ambigu dalam lingkup kebun. | Kode dan nama wajib sesuai kebijakan; kode duplikat pada kebun yang sama ditolak. | Must | Draft | — | Keunikan lintas kebun perlu diputuskan jika diperlukan. |
| BR-FM-ADM-BLK-003 | ADM | Admin mencatat luas blok dengan satuan yang disepakati. | Sistem menampilkan kapasitas kebun tersisa dan menolak alokasi aktif yang melebihi batas kebun. | Must | Draft | — | Tetapkan cara hitung blok nonaktif dan pembulatan. |
| BR-FM-ADM-BLK-004 | ADM | Admin dapat menetapkan komoditas blok dan SOP default jika relevan. | Nilai boleh belum ditentukan sesuai kebijakan; kombinasi komoditas/SOP yang tidak valid tidak dapat dipilih. | Should | Draft | — | Tetapkan apakah satu blok boleh multi-komoditas. |
| BR-FM-ADM-BLK-005 | ADM | Perubahan default blok tidak mengubah riwayat penanaman yang sudah berjalan. | Siklus/sub-blok yang telah dimulai tetap menampilkan data dan versi acuan yang berlaku saat dimulai. | Must | Draft | — | Konfirmasi kebutuhan versioning. |
| BR-FM-ADM-SBL-001 | ADM | Admin dapat menambahkan sub-blok di bawah blok yang dipilih. | Sub-blok selalu memiliki relasi ke blok dan kebun yang tepat serta memiliki identitas unik pada induknya. | Must | Draft | — | — |
| BR-FM-ADM-SBL-002 | ADM | Label sub-blok dapat dibuat otomatis bila pengguna tidak mengisinya. | Label otomatis berurutan dan tidak bentrok dengan label yang sudah ada; label manual yang bentrok ditolak. | Should | Draft | — | Tetapkan format label. |
| BR-FM-ADM-SBL-003 | ADM | Pengelola dapat menyimpan tanggal rencana tanam yang terpisah dari tanggal realisasi. | Rencana tidak dianggap realisasi; perubahan rencana tidak mengubah aktivitas/tanggal aktual. | Should | Draft | — | Aktor yang boleh mengubah rencana perlu ditetapkan. |
| BR-FM-ADM-SBL-004 | ADM | Sistem menampilkan sisa luas blok untuk penanaman dan melindungi kapasitasnya. | Realisasi luas sub-blok/penanaman tidak melebihi sisa kapasitas berdasarkan aturan agregasi yang disepakati. | Must | Draft | — | Tentukan apakah luas berasal dari input atau laporan realisasi. |
| BR-FM-ADM-DAT-001 | ADM | Sistem menjaga hierarki kebun → blok → sub-blok saat dibuat, diperbarui, dan ditampilkan. | Objek turunan tidak dapat berpindah induk secara tidak sah; penghapusan induk tidak menghapus riwayat tanpa aturan eksplisit. | Must | Draft | — | Tetapkan arsip, nonaktif, dan penghapusan. |
| BR-FM-ADM-DAT-002 | ADM | Pengiriman form berulang tidak menciptakan objek ganda tanpa sengaja. | Klik berulang/koneksi ulang menghasilkan satu operasi atau pesan bahwa objek sudah tercatat. | Should | Draft | — | Definisikan perilaku submit idempoten. |

## 2. Aktivitas Pertanian (ACT)

| Req ID | Aktor | Requirement | Acceptance criteria | Priority | Status | Linked test case(s) | Catatan/keputusan |
| --- | --- | --- | --- | --- | --- | --- | --- |
| BR-ACT-PET-LOG-001 | PET | Petugas dapat mencatat aktivitas dengan tanggal kerja dan waktu pelaksanaan yang benar. | Tanggal kerja dibedakan dari waktu submit; format, zona waktu, dan aktivitas lintas tengah malam mengikuti kebijakan. | Must | Draft | — | Tetapkan apakah waktu diperlukan per aktivitas atau cukup per sesi. |
| BR-ACT-PET-LOG-002 | PET | Petugas mengaitkan aktivitas ke kebun serta blok/sub-blok/siklus bila relevan. | Pilihan lokasi sesuai hubungan yang valid; jenis aktivitas tanpa lokasi hanya boleh bila didefinisikan. | Must | Draft | — | Tentukan aktivitas mana yang wajib berlokasi. |
| BR-ACT-PET-LOG-003 | PET | Petugas mencatat jenis kegiatan, rincian terstruktur, pekerja, dan catatan sesuai tipe kegiatan. | Field wajib dan satuan ditentukan per jenis; nilai tidak sesuai aturan ditolak dengan pesan yang membantu. | Must | Draft | — | Rincian mengacu ke spesifikasi domain yang disepakati. |
| BR-ACT-PET-EVD-001 | PET | Petugas menyertakan bukti foto/dokumen ketika diwajibkan untuk suatu aktivitas. | Tipe/jumlah/ukuran bukti mengikuti aturan; status unggah jelas dan kegagalan dapat dicoba ulang. | Should | Draft | — | Konfirmasi apakah bukti selalu wajib atau bergantung kegiatan. |
| BR-ACT-PET-LOG-004 | PET | Petugas dapat melihat kembali aktivitas yang pernah dicatat dalam cakupan kerjanya. | Riwayat menampilkan tanggal, pelapor, lokasi, rincian, status, dan bukti yang tersedia. | Must | Draft | — | — |
| BR-ACT-ADM-LOG-005 | ADM | Perubahan atau pembatalan aktivitas mengikuti kewenangan dan proses koreksi yang disepakati. | Alasan serta pelaku/waktu perubahan tercatat; aktivitas final tidak berubah diam-diam. | Should | Draft | — | Tetapkan batas edit dan pembatalan. |
| BR-ACT-TBD-APR-001 | TBD | Pihak yang ditunjuk dapat meninjau aktivitas bila proses bisnis memerlukan persetujuan. | Keputusan setuju/tolak/perlu koreksi, alasan, pelaku, dan waktu tercatat; status terlihat oleh pihak terkait. | Decide | Draft | — | Tentukan apakah approval diperlukan dan siapa approver. |

## 2a. Aplikasi Mobile Petugas (PMB)

Bagian ini mendefinisikan pengalaman mobile Petugas berdasarkan referensi layar yang diberikan stakeholder: Beranda dengan ringkasan dan to-do, Kebun, Tugas, dan Laporan. Kebutuhan akses/otorisasi tetap mengacu pada register RBAC; tabel ini hanya menyatakan hasil kerja yang dibutuhkan Petugas.

| Req ID | Aktor | Requirement | Acceptance criteria | Priority | Status | Linked test case(s) | Catatan/keputusan |
| --- | --- | --- | --- | --- | --- | --- | --- |
| BR-PMB-PET-NAV-001 | PET | Petugas dapat berpindah antara Beranda, Kebun, Tugas, dan Laporan melalui navigasi mobile yang konsisten. | Setiap pilihan membuka bagian yang tepat; keadaan aktif mudah dikenali; kembali dari detail tidak membuat pengguna kehilangan konteks yang penting. | Must | Draft | — | Rujukan: layar mobile Petugas dari stakeholder. |
| BR-PMB-PET-HOME-001 | PET | Beranda merangkum tugas utama Petugas hari ini dan menyediakan akses cepat ke tindakan yang sering dilakukan. | Daftar to-do menampilkan tugas bila ada dan pesan keadaan kosong bila tidak ada; tombol aksi cepat membuka pilihan/tindakan yang sesuai. | Must | Draft | — | Tetapkan daftar aksi cepat dan prioritasnya. |
| BR-PMB-PET-HOME-002 | PET | Beranda menampilkan jumlah batch/tahapan kerja yang relevan, seperti pra-tanam, penanaman, dan panen. | Label, angka, satuan hitung, periode, dan cakupan kebun jelas; nilai cocok dengan daftar sumber dan keadaan kosong ditangani. | Should | Draft | — | Pastikan definisi batch konsisten dengan proses tanam. |
| BR-PMB-PET-WX-001 | PET | Jika informasi cuaca ditampilkan, Petugas dapat mengetahui kapan data tidak tersedia dan mencoba memuat ulang. | Keadaan gagal/tidak tersedia dijelaskan; aksi coba lagi memberikan umpan balik; informasi kedaluwarsa tidak disajikan sebagai kondisi terkini. | Could | Draft | — | Konfirmasi apakah cuaca termasuk lingkup bisnis/MVP. |
| BR-PMB-PET-FARM-001 | PET | Petugas dapat melihat daftar kebun yang ditugaskan kepadanya. | Setiap item menunjukkan identitas kebun dan ringkasan yang diperlukan untuk memilih kebun; keadaan daftar kosong dijelaskan. | Must | Draft | — | Otorisasi dan lingkup assignment ditentukan RBAC. |
| BR-PMB-PET-FARM-002 | PET | Petugas dapat melihat ringkasan kebun, blok, dan sub-blok untuk mendukung pekerjaan lapangan. | Nama/alamat, luas, jumlah blok/sub-blok, kondisi/progres yang disepakati, dan data belum tersedia ditampilkan dengan jelas. | Must | Draft | — | Tetapkan indikator yang benar-benar dibutuhkan Petugas. |
| BR-PMB-PET-MAP-001 | PET | Petugas dapat melihat titik lokasi kebun dan memahami batas informasi yang ditampilkan peta. | Peta membedakan titik lokasi dari batas/poligon; jika koordinat tidak tersedia, tampilkan keadaan kosong dan akses alternatif lokasi bila disepakati. | Should | Draft | — | Batas/poligon blok adalah kebutuhan terpisah. |
| BR-PMB-PET-LIST-001 | PET | Petugas dapat memilih blok dan melihat ringkasan blok serta sub-blok terkait. | Pilihan blok tidak mencampur kebun lain; luas, aktivitas/progres terkini, dan sub-blok tampil untuk pilihan aktif. | Must | Draft | — | Konfirmasi apakah sub-blok bisa difilter/dipilih langsung. |
| BR-PMB-PET-TASK-001 | PET | Petugas dapat melihat tugas berdasarkan urgensi/waktu seperti terlambat, hari ini, dan mendatang. | Kelompok jumlah dan daftar tugas mengikuti aturan tenggat serta status yang disepakati; tugas tanpa hasil tampil sebagai keadaan kosong. | Must | Draft | — | Selaras dengan penyelesaian tugas pada BR-CYC-PET-TASK-001. |
| BR-PMB-PET-TASK-002 | PET | Petugas dapat mencari dan memfilter tugas menurut kebun/blok, status, dan kategori yang tersedia. | Filter yang dipilih terlihat; hasil daftar dan hitungan sesuai filter; reset mengembalikan cakupan awal. | Should | Draft | — | Konfirmasi kombinasi filter untuk layar kecil. |
| BR-PMB-PET-REPORT-001 | PET | Petugas dapat meninjau riwayat aktivitas dalam daftar mobile yang dapat ditelusuri. | Daftar dikelompokkan atau diurutkan secara konsisten menurut tanggal; item menunjukkan jenis, lokasi, ringkasan, dan tautan ke detail. | Must | Draft | — | Ini adalah riwayat kerja Petugas, bukan Executive Summary Owner. |
| BR-PMB-PET-REPORT-002 | PET | Petugas dapat mencari dan memfilter riwayat berdasarkan tanggal, kategori, sumber, dan catatan miliknya bila proses memerlukannya. | Hasil mengikuti seluruh filter aktif; aksi terapkan dan reset memberi hasil yang dapat dikenali; keadaan tanpa hasil dijelaskan. | Should | Draft | — | Definisikan arti “sumber” dan “input saya”. |
| BR-PMB-PET-UI-001 | PET | Alur mobile Petugas dapat digunakan pada ukuran layar ponsel dan kondisi lapangan yang disepakati. | Teks, kontrol utama, navigasi, status, serta aksi tidak terpotong atau tertutup; target sentuh dapat digunakan; keadaan loading/error/kosong dapat dipahami. | Must | Draft | — | Tetapkan daftar perangkat/viewport dan kebutuhan konektivitas offline. |

## 3. Siklus Tanam dan SOP (CYC)

| Req ID | Aktor | Requirement | Acceptance criteria | Priority | Status | Linked test case(s) | Catatan/keputusan |
| --- | --- | --- | --- | --- | --- | --- | --- |
| BR-CYC-ADM-PLAN-001 | ADM | Pengelola dapat membuat dan memperbarui rencana tanam untuk lokasi dan komoditas. | Rencana dibedakan dari realisasi dan perubahan mengikuti kewenangan serta riwayat yang disepakati. | Must | Draft | — | — |
| BR-CYC-ADM-CYCLE-001 | ADM | Pengelola memulai siklus berdasarkan realisasi tanam yang menjadi tanggal acuan. | Siklus terkait ke lokasi, komoditas, tanggal aktual, dan aturan pengelompokan area yang telah disetujui. | Must | Draft | — | Tetapkan window penggabungan tanam dan kondisi siklus baru. |
| BR-CYC-ALL-HST-001 | ALL | Umur tanaman/HST dihitung konsisten dari tanggal acuan siklus. | Definisi HST 0/HST 1, zona waktu, tanggal koreksi, dan hari kalender dijelaskan dan diuji. | Must | Draft | — | — |
| BR-CYC-ADM-SOP-001 | ADM | Pengelola dapat menyusun SOP dari langkah-langkah dengan instruksi, offset HST, dan bukti yang diminta. | Langkah dan urutannya dapat ditinjau; perubahan versi tidak menimpa siklus yang telah menggunakan versi sebelumnya. | Must | Draft | — | Tentukan kebutuhan approval/publikasi SOP. |
| BR-CYC-ADM-TASK-001 | ADM | Tugas siklus dibuat dari langkah SOP yang berlaku untuk siklus tersebut. | Tenggat dihitung dari tanggal acuan dan offset; tugas menunjuk siklus/lokasi yang benar serta tidak terduplikasi. | Must | Draft | — | Tentukan perubahan tugas ketika SOP/siklus dikoreksi. |
| BR-CYC-PET-TASK-001 | PET | Petugas dapat melihat dan menyelesaikan tugas yang ditugaskan kepadanya. | Penyelesaian menyimpan waktu, pelaksana, rincian, dan bukti yang diminta serta terkait dengan aktivitas bila relevan. | Must | Draft | — | — |
| BR-CYC-TBD-EXC-001 | TBD | Pihak berwenang dapat menunda, melewati, atau menandai tugas tidak relevan dengan alasan. | Status, alasan, pelaku, waktu, dan pengaruh ke progres siklus dapat ditelusuri. | Should | Draft | — | Tetapkan status, batas waktu, dan approval pengecualian. |

## 4. Pemantauan Tanaman dan Panen (MON)

| Req ID | Aktor | Requirement | Acceptance criteria | Priority | Status | Linked test case(s) | Catatan/keputusan |
| --- | --- | --- | --- | --- | --- | --- | --- |
| BR-MON-PET-OBS-001 | PET | Petugas dapat mencatat pengamatan tanaman untuk lokasi dan siklus yang tepat. | Tanggal, fase, parameter, satuan, dan jumlah sampel tersedia sesuai komoditas yang diamati. | Must | Draft | — | Tentukan parameter per komoditas/fase. |
| BR-MON-PET-OBS-002 | PET | Kondisi hama/penyakit dicatat menggunakan kategori dan skala yang konsisten. | Definisi tingkat keparahan dapat dipahami; hasil dapat dibandingkan antar waktu tanpa mencampur skala. | Should | Draft | — | Susun kamus kategori dan skala. |
| BR-MON-PET-INC-001 | PET | Petugas dapat melaporkan insiden yang memerlukan perhatian. | Kategori, uraian, dampak/lokasi, urgensi, waktu, dan tindakan awal dicatat sesuai kebutuhan; status tindak lanjut dapat dilihat. | Must | Draft | — | Tetapkan field wajib dan siklus penanganan. |
| BR-MON-PET-HRV-001 | PET | Petugas dapat mencatat hasil panen aktual berdasarkan lokasi dan siklus. | Tanggal, luas panen, berat, satuan, dan metode (bila diperlukan) tervalidasi serta tertaut ke objek yang benar. | Must | Draft | — | Sepakati definisi hasil untuk setiap komoditas. |
| BR-MON-ADM-HRV-002 | ADM | Pengguna berwenang dapat mengoreksi data panen dengan jejak perubahan. | Nilai lama/baru, alasan, pelaku, dan waktu koreksi tersedia untuk ditinjau. | Should | Draft | — | Tetapkan batas koreksi dan persetujuan. |
| BR-MON-OWN-PRJ-001 | OWN | Owner dapat melihat proyeksi panen yang dibedakan dari realisasi. | Periode, satuan, cakupan, asumsi/rumus, serta tanggal pembaruan proyeksi disampaikan dengan jelas. | Should | Draft | — | Pemilik bisnis menyetujui rumus dan sumber proyeksi. |

## 5. Dashboard dan Pelaporan (RPT)

| Req ID | Aktor | Requirement | Acceptance criteria | Priority | Status | Linked test case(s) | Catatan/keputusan |
| --- | --- | --- | --- | --- | --- | --- | --- |
| BR-RPT-OWN-SUM-001 | OWN | Owner melihat ringkasan kebun sesuai lingkup tanggung jawabnya. | Definisi total kebun, luas, progres, target, dan periode terdokumentasi; angka cocok dengan detail sumber yang termasuk. | Must | Draft | — | Setujui definisi KPI dan status yang dihitung. |
| BR-RPT-OWN-REG-001 | OWN | Owner dapat mengelompokkan ringkasan kebun berdasarkan wilayah bila datanya tersedia. | Total wilayah dihitung hanya dari kebun dalam grup; data tanpa wilayah diberi perlakuan eksplisit dan tidak hilang diam-diam. | Should | Draft | — | Tentukan pengelompokan kabupaten/kota dan label kosong. |
| BR-RPT-OWN-FLT-001 | OWN | Owner dapat memfilter laporan dengan dimensi yang disepakati untuk analisis portofolionya. | Filter aktif terlihat; hasil, total, dan ekspor konsisten dengan filter yang diterapkan. | Should | Draft | — | Detail per laporan perlu ditetapkan. |
| BR-RPT-OWN-VIS-001 | OWN | Visualisasi Owner membedakan nilai aktual, target, dan proyeksi. | Judul, satuan, periode, legenda, nilai nol, dan data kosong tidak ambigu; informasi nilai dapat diakses tanpa bergantung pada warna saja. | Should | Draft | — | — |
| BR-RPT-OWN-EXP-001 | OWN | Owner dapat mengekspor laporan portofolio yang disediakan untuknya. | Berkas mengikuti filter/cakupan yang tampil, memiliki satuan/periode, dan sesuai dengan angka yang ditampilkan. | Could | Draft | — | Tetapkan format dan batas ukuran ekspor. |
| BR-RPT-OWN-SCP-001 | OWN | Data pada dashboard dan laporan Owner mengikuti cakupan Owner yang ditentukan oleh requirement RBAC. | Halaman, rincian, hitungan, pencarian, dan ekspor konsisten dengan cakupan RBAC yang disetujui. | Must | Draft | — | Jangan mendefinisikan ulang matriks akses di sini; tautkan ke BR RBAC. |

## 5a. Executive Summary Owner (EXS)

| Req ID | Aktor | Requirement | Acceptance criteria | Priority | Status | Linked test case(s) | Catatan/keputusan |
| --- | --- | --- | --- | --- | --- | --- | --- |
| BR-EXS-OWN-SUM-001 | OWN | Owner dapat melihat ringkasan eksekutif portofolio kebun pada satu tempat. | Indikator utama, periode, satuan, dan waktu pembaruan terlihat; nilai ringkasan dapat ditelusuri ke rincian sumber. | Must | Draft | — | Tetapkan indikator executive summary dan definisinya. |
| BR-EXS-OWN-INS-001 | OWN | Ringkasan eksekutif memisahkan kondisi aktual dari target dan proyeksi. | Setiap nilai diberi label/status dan periode yang jelas; data belum tersedia tidak ditampilkan sebagai nol atau keberhasilan. | Must | Draft | — | — |
| BR-EXS-OWN-DRL-001 | OWN | Owner dapat membuka rincian dari indikator ringkasan yang memiliki sumber data. | Rincian memakai periode dan cakupan yang sama dengan indikator asal; kembali ke ringkasan mempertahankan konteks. | Should | Draft | — | Tetapkan indikator mana yang dapat ditelusuri. |

## 5b. Dashboard dan Proyeksi Owner (ODS)

| Req ID | Aktor | Requirement | Acceptance criteria | Priority | Status | Linked test case(s) | Catatan/keputusan |
| --- | --- | --- | --- | --- | --- | --- | --- |
| BR-ODS-OWN-FARM-001 | OWN | Dashboard Owner menampilkan ringkasan jumlah kebun dan luas lahan pada cakupan Owner. | Nilai total cocok dengan kebun yang termasuk; satuan dan perlakuan data yang belum lengkap dijelaskan. | Must | Draft | — | Definisi kebun aktif/nonaktif perlu disepakati. |
| BR-ODS-OWN-COM-001 | OWN | Dashboard Owner merangkum komoditas dan luas tanam sesuai data yang tersedia. | Komoditas ditampilkan dengan nilai dan proporsi yang dihitung dari dasar data/periode yang sama; keadaan data kosong jelas. | Should | Draft | — | Definisikan luas tanam vs luas kebun. |
| BR-ODS-OWN-MAP-001 | OWN | Owner dapat melihat persebaran titik kebun pada peta bila koordinat tersedia. | Marker mewakili lokasi kebun dan tidak menyiratkan batas lahan; kebun tanpa koordinat tetap masuk ringkasan daftar/total. | Should | Draft | — | Geometri batas lahan adalah kebutuhan berbeda. |
| BR-ODS-OWN-REG-001 | OWN | Owner dapat membuka dan meringkas kebun per kabupaten/kota. | Luas, progres, komoditas, dan proyeksi pada grup dihitung dari kebun di wilayah tersebut; ekspansi baris menampilkan kebun anggota yang sama. | Must | Draft | — | Tetapkan progres agregat dan perlakuan kabupaten yang belum tercatat. |
| BR-ODS-OWN-PRJ-001 | OWN | Owner dapat melihat proyeksi panen agregat dan rinciannya menurut wilayah serta komoditas jika data mendukung. | Total proyeksi sama dengan jumlah rincian yang memenuhi aturan; periode/siklus, satuan ton, dan perbedaan dari hasil aktual/target dijelaskan. | Must | Draft | — | Rumus, siklus aktif, dan cara menangani proyeksi kosong perlu disetujui. |
| BR-ODS-OWN-PRJ-002 | OWN | Visualisasi proyeksi panen Owner menyampaikan komposisi tiap komoditas dan nilai saat pengguna meminta detail. | Warna, legenda, label, dan tooltip dapat dibaca; angka tidak mengganggu kategori/periode; total dan rincian konsisten. | Should | Draft | — | Jenis visualisasi dan palet final merupakan keputusan UX setelah definisi metrik. |
| BR-ODS-OWN-REF-001 | OWN | Owner dapat memperbarui data dashboard dan mengetahui kapan data terakhir diperbarui. | Aksi muat ulang menghasilkan umpan balik; waktu pembaruan mencerminkan sumber data yang dipakai. | Could | Draft | — | Tetapkan kebutuhan refresh otomatis vs manual. |

## 6. Keuangan dan Operasional (FIN)

| Req ID | Aktor | Requirement | Acceptance criteria | Priority | Status | Linked test case(s) | Catatan/keputusan |
| --- | --- | --- | --- | --- | --- | --- | --- |
| BR-FIN-DEC-001 | TBD | Stakeholder menetapkan apakah pencatatan keuangan dan operasional termasuk lingkup produk. | Lingkup menyebut transaksi, biaya, pembelian, anggaran, target, persetujuan, pembayaran, rekonsiliasi, dan laporan yang termasuk/di luar. | Decide | Draft | — | Jika tidak disepakati, fitur finansial tidak dianggap requirement. |
| BR-FIN-ADM-TRX-001 | ADM | Jika masuk lingkup, pengguna berwenang dapat mencatat transaksi dengan tanggal, kategori, nilai, mata uang/satuan, dan referensi. | Field wajib dan format tervalidasi; bukti transaksi dan koreksi mengikuti aturan yang disetujui. | Decide | Draft | — | Perlu persetujuan lingkup FIN. |
| BR-FIN-ADM-CAT-001 | ADM | Pengelola dapat mengklasifikasikan transaksi memakai daftar kategori yang disepakati. | Kategori yang dipakai historis tetap dapat dikenali setelah daftar kategori berubah. | Decide | Draft | — | Perlu persetujuan lingkup FIN. |
| BR-FIN-ADM-BUD-001 | ADM | Jika anggaran/target diperlukan, pengelola menetapkan periode, nilai, kategori, dan lingkup kebun. | Perbandingan realisasi dan anggaran menggunakan periode serta satuan sepadan; perubahan target memiliki riwayat bila diperlukan. | Decide | Draft | — | Perlu persetujuan lingkup FIN. |
| BR-FIN-OWN-RPT-001 | OWN | Pengguna berwenang melihat dan mengekspor ringkasan biaya/realisasi dalam lingkupnya. | Rumus agregasi, perlakuan transaksi batal, mata uang, pembulatan, filter, dan cakupan akses dijelaskan. | Decide | Draft | — | Perlu persetujuan lingkup FIN. |

## 7. Notifikasi dan Tindak Lanjut (NTF)

| Req ID | Aktor | Requirement | Acceptance criteria | Priority | Status | Linked test case(s) | Catatan/keputusan |
| --- | --- | --- | --- | --- | --- | --- | --- |
| BR-NTF-ADM-TRG-001 | ADM | Sistem memberi notifikasi untuk peristiwa bisnis yang telah disepakati. | Pemicu, kondisi, waktu, tingkat prioritas, dan kemungkinan pengulangan didefinisikan; peristiwa yang tidak memenuhi kondisi tidak mengirim notifikasi. | Should | Draft | — | Tentukan daftar pemicu awal. |
| BR-NTF-ADM-RCP-001 | ADM | Notifikasi diterima oleh pihak yang ditentukan berdasarkan tanggung jawab aktifnya. | Penerima sesuai aturan; perubahan penugasan berlaku sesuai waktu efektif dan tidak membuka data ke pihak yang tidak berwenang. | Must | Draft | — | Tetapkan penerima untuk tiap jenis peristiwa. |
| BR-NTF-ALL-READ-001 | ALL | Penerima dapat membedakan notifikasi baru dan yang telah dibaca. | Status baca tetap konsisten saat daftar dibuka kembali dan tidak mengubah status penanganan peristiwa. | Should | Draft | — | Definisikan apakah ada tandai semua dibaca. |
| BR-NTF-ALL-FUP-001 | ALL | Notifikasi tindak lanjut membuka konteks yang benar dan menunjukkan status penanganan. | Tautan menuju objek terkait; saat dibuka, akses objek diperiksa; tindakan dan hasilnya dapat dikenali. | Should | Draft | — | — |
| BR-NTF-ADM-DEL-001 | ADM | Kegagalan pengiriman ditangani menurut kebijakan kanal yang disepakati. | Status kegagalan dapat dikenali; percobaan ulang tidak membuat penerima menerima duplikasi yang membingungkan. | Could | Draft | — | Tentukan kanal, retry, dan apakah pengiriman eksternal diperlukan. |
| BR-NTF-DEC-001 | TBD | Stakeholder menentukan kebijakan kanal, preferensi, retensi, prioritas, dan waktu pengiriman. | Keputusan dicatat untuk setiap jenis notifikasi sebelum acceptance criteria final dibuat. | Decide | Draft | — | — |

## Keputusan lintas modul yang perlu dimatangkan

- Aktor, pemilik proses, approver, dan cakupan data yang boleh dilihat setiap aktor.
- Untuk aturan akun/akses, gunakan requirement RBAC yang telah disepakati. Register modul ini hanya menautkan kebutuhan ke RBAC dan tidak membuat matriks izin kedua.
- Pisahkan Executive Summary dan dashboard/proyeksi Owner dari Laporan Petugas: Owner memerlukan ringkasan portofolio, sedangkan Petugas memerlukan alur kerja lapangan melalui pengalaman mobile.
- Definisi istilah, satuan, status, tanggal rencana vs aktual, zona waktu, dan periode laporan.
- Aturan validasi batas angka, nilai kosong, nol, pembulatan, serta konversi satuan.
- Siklus koreksi, pembatalan, penghapusan/arsip, versioning, audit trail, dan retensi data.
- Bukti yang diwajibkan, tipe berkas, batas ukuran, akses, serta masa penyimpanan.
- Definisi rumus KPI, progres, agregasi, target, dan proyeksi.
- Lingkup finance dan kebijakan notification channel.

## Menurunkan requirement menjadi test case

| Level uji | Gunakan untuk | Contoh fokus |
| --- | --- | --- |
| Unit | Aturan kecil yang dapat diuji terisolasi. | Perhitungan HST, validasi satuan/luas, agregasi proyeksi. |
| Module | Alur satu kemampuan dalam satu modul. | Membuat kebun beserta validasi dan hasil yang terlihat. |
| Integration | Hubungan beberapa komponen atau modul. | Penyelesaian tugas SOP menghasilkan aktivitas terkait; perubahan penugasan memengaruhi daftar tugas. |
| End-to-end | Tujuan bisnis lintas alur dari perspektif pengguna. | Admin membuat kebun dan blok, Petugas menyelesaikan aktivitas melalui alur mobile, lalu Owner meninjau progres/proyeksi pada dashboard sesuai BR RBAC. |

Jangan menduplikasi skenario pada semua level. Pilih level yang membuktikan risiko/hasil yang berbeda, dan tautkan skenario pada Req ID terkait. Untuk skenario, catat minimal: Test Case ID, Req ID, tujuan, prasyarat, aktor, data uji, langkah, hasil yang diharapkan, hasil aktual, status, lingkungan/versi, bukti, dan defect ID.

## Petunjuk pemeliharaan

- Satu baris menyatakan satu kebutuhan yang dapat diuji. Pecah baris jika acceptance criteria membuktikan beberapa hasil bisnis yang independen.
- Tulis hasil yang wajib dipenuhi, bukan detail implementasi seperti nama tabel, route, komponen, framework, atau pilihan UI.
- Hindari kata kabur seperti “mudah”, “cepat”, “sesuai”, atau “aman” tanpa ukuran/definisi yang disepakati.
- Gunakan acceptance criteria yang dapat diamati; batas numerik dan aturan yang belum disepakati dicatat sebagai pertanyaan, bukan ditebak.
- Pertahankan Req ID ketika redaksi diperbaiki. Jika kebutuhan digabung/dipecah, tandai ID lama `Rejected` atau `Deferred` dengan alasan lalu buat ID baru dan tautkan.
- Perbarui daftar test case ketika requirement berubah. Requirement `Draft`, `In Review`, `Deferred`, atau `Rejected` bukan dasar sign-off kelulusan fitur.

## Peta cakupan repository (referensi saja)

Daftar ini membantu pembaca menemukan bagian proyek untuk dibandingkan setelah kebutuhan bisnis disepakati. Daftar ini tidak menetapkan requirement dan tidak membatasi kebutuhan hanya pada fitur yang sudah diimplementasikan.

| Area | Petunjuk lokasi di proyek |
| --- | --- |
| Manajemen kebun | `routes/web.php`; `FarmManagementController`; `FarmService`; `resources/js/pages/farms/` dan `resources/js/pages/farmer/`. |
| Aktivitas pertanian | Rute Petugas aktivitas/daily logs; `PetugasActivityController`; `PetugasDailyActivityController`; `app/Services/Activity/`. |
| Siklus tanam dan SOP | Rute penanaman, tugas, SOP; `PlantingCycleTaskService`; `SopTemplateService`; `resources/js/pages/sops/`, `tasks/`, `petugas/`. |
| Pemantauan tanaman dan panen | `PlantingTransactionController`; `HarvestTransactionController`; layanan terkait dalam `app/Services/Activity/`. |
| Dashboard dan pelaporan | Dashboard Owner/Petugas; rute proyeksi; `OwnerReadService`; `HarvestProjectionService`. |
| Executive Summary Owner | Halaman ringkasan eksekutif dan komponen indikator Owner. |
| Dashboard/proyeksi Owner | Dashboard Owner, ringkasan wilayah, visualisasi proyeksi; `OwnerReadService`; `HarvestProjectionService`. |
| Aplikasi mobile Petugas | Halaman Petugas Beranda, Kebun, Tugas, Laporan; navigasi mobile dan detail alur aktivitas. |
| Keuangan dan operasional | Rute finance/transaksi/anggaran/ekspor; `app/Http/Controllers/Finance/`; `app/Services/Finance/`. |
| Notifikasi dan tindak lanjut | `NotificationController`; `app/Services/Notifications/`. |
