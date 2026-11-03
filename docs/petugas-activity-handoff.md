# Handoff Petugas: Aktivitas, Penanaman, SOP, dan Laporan Harian

Tanggal checkpoint: **3 November 2026**  
Branch: **`feat/admin-sop`**

Dokumen ini merangkum hasil diskusi dan implementasi untuk dilanjutkan pada
sesi berikutnya. Status di bawah merupakan kondisi pada checkpoint ini,
bukan klaim bahwa seluruh lifecycle operasional sudah selesai.

## 1. Keputusan bisnis yang disepakati

- `daily_activity_logs` adalah **header bersama per kebun + tanggal kerja**,
  bukan per aktivitas dan bukan per Petugas.
- Jam kerja keseluruhan hari, jumlah pekerja, dan catatan umum berada di header.
  Jumlah pekerja tidak dipisahkan atau dijumlahkan otomatis dari aktivitas.
- `recorded_by` pada header merupakan pembuat pertama laporan harian.
- `farm_activities.performed_by` tetap mencatat Petugas masing-masing aktivitas.
- Menambahkan aktivitas tidak menimpa informasi header yang sudah ada.
- Petugas yang berwenang pada kebun serta Admin sesuai izin boleh mengubah
  header melalui aksi terpisah. Owner tetap hanya membaca.
- Harus ada unique constraint database untuk `(farm_id, work_date)` dan
  penanganan penyimpanan bersamaan.
- Pengguna memilih menghapus laporan lama, detail, dan fotonya; bukan
  mengimplementasikan penggabungan historis yang membutuhkan resolusi konflik.

## 2. Sudah diimplementasikan

### Laporan harian bersama

- [x] Header otomatis dibuat saat aktivitas pertama disimpan.
- [x] Aktivitas berikutnya pada kebun dan tanggal yang sama memakai header itu,
      termasuk aktivitas dari Petugas lain yang ditugaskan ke kebun tersebut.
- [x] Alur tugas SOP, penanaman awal, dan pemetaan blok memakai mekanisme yang sama.
- [x] Jam, jumlah pekerja, catatan umum, dan pembuat pertama tidak ditimpa oleh
      penyimpanan aktivitas berikutnya.
- [x] Catatan aktivitas terpisah dari catatan umum harian (`daily_notes` pada
      form SOP/penanaman; form pemetaan lama memakai `notes` untuk header).
- [x] Pengubahan header tersedia sebagai aksi terpisah; tidak dapat mengubah
      kebun, tanggal, pembuat pertama, atau memindahkan aktivitas.
- [x] Unique constraint `daily_logs_farm_date_unique` pada `(farm_id, work_date)`.
- [x] Penguncian farm dalam transaksi sebelum pencarian/pembuatan header dan
      alokasi urutan aktivitas; urutan memakai `max(position) + 1`.
- [x] Constraint urutan unik per header tetap digunakan.
- [x] Migrasi menolak duplikat historis, tidak diam-diam menggabungkan atau
      menghapus data pada lingkungan lain.

### UI Petugas dan logbook

- [x] `/petugas/aktivitas` menjadi halaman laporan harian dengan pilihan tanggal.
- [x] Ringkasan jam, jumlah pekerja, catatan umum, dan pembuat pertama.
- [x] Daftar seluruh aktivitas yang terlihat, nama pelaksana, blok/subblok,
      rincian pekerjaan, dan foto privat.
- [x] Aksi **Ubah** untuk header bersama.
- [x] **Tambah aktivitas / Catat aktivitas pertama** menuju tugas SOP atau
      penanaman awal, dengan tanggal pilihan diteruskan ke form.
- [x] Form mengecek header berdasarkan tanggal: tampilkan ringkasan jika sudah
      ada, atau minta informasi harian untuk aktivitas pertama.
- [x] Loading, error/retry saat pengecekan header, empty state, validasi, dan
      feedback setelah penyimpanan.
- [x] Petugas tanpa assignment mendapat empty state tanpa data operasional.
- [x] Logbook dan detail laporan umum mendukung header bersama dan rincian
      aktivitas, tidak hanya rincian pemetaan blok.
- [x] Tema terang/gelap dan layout responsif mengikuti `design.md`.

### Penanaman awal dan generasi tugas SOP

- [x] Pencatatan penanaman dimulai dari subblok, bukan tugas SOP turunan.
- [x] Laporan, detail benih, foto, dan realisasi tanam disimpan dalam transaksi.
- [x] Mengisi `planted_at` dari tanggal pelaksanaan, `variety_id`, luas aktual,
      dan status siklus menjadi aktif.
- [x] Satu realisasi awal per subblok; form tidak menimpa realisasi yang sudah ada.
- [x] Tanggal rencana dan aktual tetap nullable.
- [x] Tanpa kedua tanggal tersebut, tugas belum dibuat.
- [x] Untuk snapshot baru, SOP default harus aktif, dalam tenant yang sama,
      dan memiliki versi published.
- [x] Tanggal aktual menjadi prioritas penjadwalan dibanding tanggal rencana.
- [x] Generate saat batch baru siap, realisasi tanam disimpan, atau aksi
      **Siapkan tugas** pada subblok yang sudah ada.
- [x] Hanya tugas `pending` dijadwalkan ulang, tanpa duplikasi.
- [x] Snapshot mempertahankan versi SOP terikat, termasuk versi retired yang sah;
      tidak otomatis berpindah ke default atau versi terbaru.
- [x] SOP belum siap tidak membatalkan realisasi tanam; alasan penundaan ditampilkan.
- [x] Kegagalan teknis membatalkan perubahan database dan membersihkan foto baru.
- [x] Tahap penanaman diselesaikan; tahap persiapan lain tidak dibuat selesai
      secara otomatis.

### Pencatatan aktivitas dari tugas SOP

- [x] Daftar/detail tugas dari database sesuai kebun penugasan.
- [x] Form berdasarkan jenis tugas: pengairan, pemupukan, pengendalian,
      penyiangan, pengamatan pertumbuhan, dan panen.
- [x] Kebun, subblok, kategori, dan tugas asal diturunkan dari record server,
      bukan dipercaya dari input browser.
- [x] Tautan aktivitas ke tugas, siklus, header harian, detail, dan foto.
- [x] Foto privat dengan pemeriksaan otorisasi saat diakses.
- [x] Validasi server dan penolakan akses lintas kebun/tenant.
- [x] Pesan kegagalan unggahan membedakan batas ukuran, folder sementara PHP,
      kegagalan tulis, unggahan sebagian, dan penolakan ekstensi.

### Seed dan dokumentasi

- [x] Demo activity seeder memasangkan aktivitas contoh ke header harian bersama.
- [x] Diagram database dan kontrak aktivitas diperbarui.
- [x] Dokumentasi otorisasi menjelaskan shared header dan scope pembacaannya.

## 3. Hubungan data

```text
daily_activity_logs (satu kebun + tanggal)
  └─ farm_activities.daily_activity_log_id (banyak aktivitas)
       ├─ farm_id                  → farms.id
       ├─ planting_cycle_id        → planting_cycles.id
       ├─ planting_cycle_task_id   → planting_cycle_tasks.id (jika dari SOP)
       ├─ performed_by             → users.id
       ├─ activity_type_id         → activity_types.id
       ├─ detail sesuai jenis      → activity_id
       └─ activity_photos          → activity_id
```

Penanaman awal tidak mengisi `planting_cycle_task_id`. Panen membuat
`harvest_details` untuk luas panen, berat ton, dan kadar air opsional.
Isian tugas juga disimpan sebagai snapshot JSON `farm_activities.task_details`.

Jam pada timestamp aktivitas mengikuti jam mulai header saat aktivitas dicatat;
ini bukan pencatatan durasi terpisah per aktivitas. Timestamp tahap penanaman
mengikuti jam harian saat laporan tanam disimpan. Pengubahan header berikutnya
tidak menulis ulang timestamp aktivitas/tahap.

## 4. Belum diimplementasikan / keputusan lanjutan

- [ ] **Pembatasan sebelum tanggal jadwal SOP.** Saat ini tanggal jadwal adalah
      acuan, bukan pengunci. Laporan panen sebelum jadwal masih memungkinkan.
      Perlu keputusan apakah panen lebih awal ditolak atau diberi pengecualian.
- [ ] Lifecycle tugas **mulai → ajukan selesai → review/setujui/tolak**.
      Menyimpan laporan belum mengubah tugas menjadi selesai.
- [ ] Penyelesaian otomatis tahap panen dan penutupan siklus tanam.
- [ ] Validasi akumulasi luas seluruh laporan panen. Saat ini luas diperiksa
      per laporan terhadap luas tanam, bukan total beberapa laporan.
- [ ] Integrasi hasil panen ke stok gudang, penjualan, dan transaksi keuangan.
- [ ] Koreksi realisasi tanam awal dan penanaman bertahap/parsial.
- [ ] Alur khusus aktivitas insidental/persiapan di luar jalur yang sudah ada.
- [ ] Penggabungan historis header duplikat dan UI resolusi konflik; tidak
      dibuat karena pengguna memilih membersihkan laporan demo lama.

Header bersama tidak menambahkan workflow pengajuan/persetujuan laporan harian.
Tidak ada keputusan untuk memasukkan workflow itu dalam implementasi ini.

## 5. Checkpoint database lokal

- Database pada saat pengerjaan: **`farmer_ic_dev`**, environment lokal.
- Pembersihan sebelumnya, atas persetujuan pengguna: 4 header, 11 aktivitas
  beserta detail, 4 referensi foto, dan 3 file unggahan privat.
- Aset placeholder bersama tidak dihapus.
- Akun, kebun, realisasi tanam, tugas SOP, dan keuangan tidak direset.
- Migrasi unik header sudah diterapkan secara aditif di database lokal.
- Seeder tidak dijalankan ulang ke database lokal dalam pekerjaan ini.
- Pengujian browser dan concurrency menggunakan database uji terpisah.

Pembersihan data lokal bukan operasi yang dijalankan oleh Git atau migrasi.
Seeder hanya membuat ulang contoh yang didefinisikan dalam kode; tidak dapat
mengembalikan persis semua input manual yang sudah dihapus. Karena realisasi
tanam dipertahankan, menghapus laporan tidak membuka kembali form tanam awal
untuk siklus yang sudah ditanam.

## 6. Verifikasi terakhir

### Integrasi remote sebelum push

- Commit lokal awal: `fa7a24e`.
- Remote `origin/feat/admin-sop` telah bergerak ke `1908285` saat akan push;
  perubahan remote digabung, bukan ditimpa atau force-push.
- Manajemen tugas Admin (skip/cancel/reschedule), pembatasan kategori SOP ke
  maintenance/harvest, perubahan UI Admin/settings, dan shared select remote
  dipertahankan.
- Jadwal efektif Petugas menghormati `rescheduled_date` dari Admin. Sinkronisasi
  HST memperbarui jadwal dasar tugas pending tanpa menghapus override Admin
  atau membuat due date-nya tidak sesuai override.
- Task list tanpa assignment menampilkan data kosong. Flag start/submit tetap
  nonaktif karena endpoint lifecycle Petugas belum diimplementasikan; fixture
  banner lama tidak digunakan.
- Form kategori generik lama tetap digantikan oleh form tugas yang sudah
  menyimpan data backend. Select baru mengikuti primitive bersama dari remote.
- `docs/petugas-flow-handoff.md` dipertahankan sebagai catatan historis dengan
  penunjuk ke handoff terbaru ini.

### Verifikasi setelah integrasi remote

- **539 tes: 538 lulus, 1 dilewati; 58.189 assertions.**
- Regresi penuh dijalankan pada database terpisah
  `farmer_ic_crud_test_merge_verification`, dengan batas waktu PHP CLI
  dinonaktifkan untuk suite yang berlangsung sekitar dua menit.
- Termasuk tes baru bahwa realisasi tanam mempertahankan override jadwal Admin
  beserta due date-nya.
- Build frontend, TypeScript, PHPStan terarah pada integrasi, Pint, serta
  lint/format form yang berubah: lulus.
- Warning build tetap font opsional dan ukuran chunk, bukan kegagalan build.

### Verifikasi sebelum integrasi remote

- **523 tes: 522 lulus, 1 dilewati; 10.895 assertions.**
- Tes dua proses independen: dua Petugas pada tugas berbeda menghasilkan satu
  header, dua aktivitas berurutan, dan tidak menimpa data pembuat pertama.
- Tes penyimpanan gabungan penanaman dan SOP pada tanggal yang sama.
- Tes pengubahan header oleh Petugas/Admin, Owner read-only, penolakan
  lintas kebun/tenant, field konteks palsu, dan constraint database.
- `npm.cmd run build`, `npm.cmd run types:check`, PHPStan terarah, Pint, dan
  lint/format frontend yang diubah: lulus.
- Browser: penyimpanan aktivitas/foto, pemakaian ulang header, pengubahan jumlah
  pekerja, empty state, dan pemeriksaan terang/gelap pada lebar 320–1280 px.
- Tidak ada warning/error console pada pemeriksaan halaman yang diuji.
- Build masih memberi warning font opsional `fontaine` dan chunk lebih dari
  500 kB; tidak ada paket baru yang dipasang.
- Semua endpoint baru dilindungi. Rute dan retrieval AI/RAG di luar cakupan
  tidak diaudit ulang; tidak ada klaim audit keamanan seluruh aplikasi.

## 7. Melanjutkan pekerjaan

1. Periksa status Git dan perubahan baru sebelum mengedit.
2. Pada checkout/database lain, jalankan migrasi normal; jangan gunakan
   `migrate:fresh` atau reset demo pada database operasional.
   Migrasi task-management dan pembatasan kategori dari remote ikut tersedia
   setelah merge; migrasi remote tersebut belum dijalankan ke database lokal
   oleh sesi commit/push ini. Periksa `php artisan migrate:status` sebelum
   melanjutkan aplikasi pada lingkungan lokal/deployment lain.
3. Jika migrasi header unik menemukan duplikat, hentikan dan tentukan penanganan
   data lingkungan itu. Migrasi tidak diberi izin menghapus laporan otomatis.
4. Pastikan PHP web server menyediakan folder `upload_tmp_dir` yang tersedia
   dan writable, `upload_max_filesize = 10M`, serta `post_max_size = 64M`.
   Restart server setelah mengubah konfigurasi PHP. Konfigurasi mesin ini
   tidak disertakan dalam commit dan `.env` tidak diubah.
5. Bila memakai cached configuration, rebuild cache setelah deployment dan
   restart worker jangka panjang sesuai perubahan konfigurasi/otorisasi.
6. Buka `/petugas/aktivitas`, pilih tanggal, lalu gunakan **Tambah aktivitas**.
7. Prioritas berikutnya yang disarankan: sepakati aturan tanggal pelaporan,
   implementasikan lifecycle/review tugas, lalu akumulasi panen dan penutupan siklus.

Jangan menjalankan dua suite yang melakukan reset pada database uji yang sama
secara bersamaan. Untuk regresi penuh yang lebih lama dari batas waktu PHP CLI,
gunakan `php -d max_execution_time=0 vendor/bin/phpunit --display-skipped` dan
database uji terpisah melalui `TEST_DB_DATABASE` serta
`FARMER_CRUD_TEST_DATABASE` dengan nama yang diizinkan `Tests\Support\TestDatabase`.

File rujukan:
- `docs/petugas-task-list-contract.md`
- `docs/authorization-scaffolding.md`
- `app/Services/Activity/DailyActivityHeaderService.php`
- `app/Services/Activity/DailyActivityReportService.php`
- `app/Services/Activity/TaskActivityService.php`
- `app/Services/Activity/PlantingActivityService.php`
- `app/Services/PlantingCycleTaskService.php`
- `resources/js/pages/petugas/Aktivitas.vue`
- `resources/js/components/petugas/DailyHeaderContext.vue`
- `resources/js/components/petugas/DailyReportContent.vue`
