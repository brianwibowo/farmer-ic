# Handoff Petugas: Aktivitas, Penanaman, SOP, dan Laporan Harian

Tanggal checkpoint terbaru: **4 November 2026**
Branch: **`feat/admin-sop`**

Dokumen ini merangkum hasil diskusi dan implementasi untuk dilanjutkan pada
sesi berikutnya. Status di bawah merupakan kondisi pada checkpoint ini,
bukan klaim bahwa seluruh lifecycle operasional sudah selesai. Bagian 1–7 memuat
checkpoint historis; bagian 8 adalah status terbaru yang menggantikan aturan lama.

**Ringkasan penyerahan:** implementasi Aktivitas/to-do, riwayat Laporan, input
mandiri seluruh kategori, header bersama, dan penanaman bertahap sudah selesai
untuk cakupan yang disepakati. Perubahan kode, migrasi, tes, serta dokumentasi
disertakan bersama pada branch `feat/admin-sop`. Hasil verifikasi dan batasannya
tercatat di bagian 8; tindak lanjut ada di bagian 9.

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

### Klarifikasi diskusi UX — 4 November 2026 (status terbaru di bagian 8)

- **Keputusan sementara lifecycle tugas:** menyimpan laporan SOP tetap tidak
  menyelesaikan tugas atau otomatis mengubahnya menjadi menunggu review.
- **Pertanyaan untuk tahap berikutnya:** setelah laporan SOP disimpan, apakah
  tugas tetap aktif, langsung selesai, atau diajukan untuk review? Bagaimana
  menangani beberapa laporan/progres untuk tugas yang sama?
- **Saran solusi untuk dipertimbangkan, bukan keputusan implementasi:** pisahkan
  pencatatan progres dari aksi **Ajukan selesai**. Pengajuan mengubah tugas
  menjadi `submitted` (Menunggu review), mengeluarkannya dari daftar pekerjaan
  aktif, tetapi tetap tersedia melalui filter status/detail dan laporan terkait.
  Admin yang berwenang kemudian menyetujui menjadi `completed` atau mengembalikan
  untuk perbaikan. Jika setiap laporan selalu merupakan hasil akhir, submit
  laporan dapat sekaligus menjadi pengajuan; pilihan ini masih perlu disepakati.
  Implementasi memerlukan aturan transisi, otorisasi, endpoint, dan pengujian.
- **Aktivitas mandiri:** boleh mencakup semua kategori, termasuk perawatan dan
  panen. Jika ada tugas serupa di subblok yang dipilih, tawarkan membuka tugas
  tersebut; jangan otomatis menghubungkan laporan mandiri ke tugas SOP.
- **Header:** tetap satu header bersama per kebun + tanggal kerja. Desain input
  dapat dilengkapi bertahap; jam selesai boleh kosong sementara dan dilengkapi
  melalui **Ubah info harian**. Kebiasaan pencatatan adalah sekaligus pada akhir
  pekerjaan/sore hari. Jam kerja merupakan rentang seluruh pekerjaan kebun pada
  tanggal tersebut, bukan durasi masing-masing aktivitas. Jumlah pekerja adalah
  total orang berbeda yang bekerja di hari itu lintas seluruh aktivitas; orang
  yang sama tidak dihitung ulang. Penghapusan catatan harian belum disepakati.
  Keputusan ini menjadi acuan dibanding bagian dokumen kategori yang bertentangan.
- **Riwayat:** tampilkan seluruh laporan dalam cakupan kebun penugasan yang
  diizinkan, bukan hanya laporan yang diinput sendiri.
- **Penanaman bertahap:** aktivitas mandiri dengan rentang maksimal tiga hari
  kalender inklusif sejak tanggal tanam pertama: tanggal 26, 27, dan 28 dapat
  dicatat pada subblok yang sama. Penanaman tanggal 29 di blok yang sama harus
  menjadi subblok baru. Ini mengubah target produk dari aturan implementasi
  lama yang hanya menerima satu realisasi awal per subblok.
- **Target operasional:** satu blok per hari, sekitar 1 Ha. Belum disepakati
  sebagai batas validasi yang menolak pencatatan.
- **Pemicu SOP:** generasi tugas dipicu pencatatan penanaman pertama, langsung
  memakai tanggal tanam pertama sebagai acuan sementara (contoh: 26).
  Sistem kemudian memeriksa penanaman dalam jendela tiga hari dan menyesuaikan
  acuan HST serta jadwal SOP. Jendela mengikuti contoh yang disepakati, yaitu
  26–28 inklusif; bukan diperpanjang hingga 29. Setelah jendela berakhir, lakukan
  rekonsiliasi meskipun tidak ada laporan tambahan. Mekanisme eksekusi otomatis
  pemeriksaan ini masih perlu diimplementasikan.
- **Acuan HST:** berdasarkan hari yang benar-benar ada pelaksanaan penanaman,
  bukan titik tengah jendela kalender atau urutan submit laporan. Untuk tiga
  hari penanaman, gunakan hari kedua; untuk dua hari, gunakan hari kedua juga.
  Contoh: 26/27/28 → 27; 26/27 → 27; 26/28 (27 kosong) → 28.
  Beberapa laporan pada tanggal yang sama tetap dihitung satu hari penanaman.
  Jika hanya ada penanaman pada satu hari, acuan tetap tanggal tersebut.
- **Penyesuaian jadwal disetujui:** ketika acuan HST berubah, hitung ulang jadwal
  tugas pending tanpa duplikasi, sambil mempertahankan snapshot SOP dan override
  jadwal Admin. Belum diimplementasikan untuk alur penanaman bertahap ini.
  Tanggal awal untuk batas tiga hari tetap dibedakan dari tanggal acuan HST.
  Laporan terlambat yang sah dalam jendela harus ikut diperhitungkan; pemeriksaan
  akhir jendela tidak boleh membuat laporan tersebut diabaikan.
- **Subblok baru:** Petugas memilih subblok yang sudah disiapkan Admin; tidak
  membuat subblok baru langsung dari form penanaman.
- **Tanggal patokan:** tanggal pelaksanaan, bukan tanggal input laporan.
  Pekerjaan tanggal 28 yang baru dilaporkan tanggal 29 tetap boleh masuk subblok
  dengan jendela penanaman 26–28, selama validasi lainnya terpenuhi.
- **Penyulaman:** di luar cakupan pembahasan/implementasi saat ini.

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

## 8. Implementasi UX dan penanaman bertahap — 4 November 2026

### Halaman dan alur aktif

- `/petugas/aktivitas` langsung menampilkan to-do SOP: kartu singkat, metrik
  jadwal, pencarian, filter kategori/blok/status, serta tombol + melayang.
  `/petugas/tasks` tetap alias kompatibel untuk daftar yang sama.
- Tombol + menampilkan 14 kategori selectable, termasuk penanaman;
  subkategori dikelompokkan sesuai parent Pengolahan Tanah/Perawatan dari database.
  Pemetaan blok tetap pada alur pengelolaan lahan terpisah.
- `/petugas/aktivitas/mandiri/{type:code}` GET/POST menerima laporan mandiri.
  Saran tugas serupa tidak otomatis menghubungkan laporan ke SOP.
- `/petugas/laporan` adalah riwayat paginated seluruh laporan kebun yang diizinkan,
  dengan pencarian, tanggal kerja, kategori, sumber SOP/Mandiri, dan “Input saya”.
  `/petugas/laporan/{activity}` menampilkan satu laporan;
  `/petugas/laporan/harian` menampilkan satu hari bersama.
- Detail/form menggunakan profil Farmer/mobile. Halaman berubah: `Aktivitas`,
  `Laporan`, `TaskShow`, `ActivityCreate`, `PlantingIndex`, `PlantingCreate`;
  halaman baru: `VoluntaryCreate`, `ReportShow`, `DailyReport`. Komponen bersama:
  `ActivityAddButton`, `ActivityDetailFields`, `DailyHeaderContext`, `DailyReportContent`.
- Asumsi visual: ikon kategori memakai Sprout dan kartu tetap ringkas; halaman
  Farmer menjadi referensi, tanpa wireframe baru. Semua kategori selectable
  tersedia. Penyulaman serta lifecycle review bukan cakupan implementasi ini.

### Data, header, dan HST

- Satu header per kebun/tanggal, lintas Petugas; jam selesai nullable. Jam mulai,
  jumlah orang berbeda, serta catatan harian tetap pada header. Editor inline
  memakai PATCH terpisah ke `/kebun/{farm}/daily-logs/{dailyLog}`.
- Admin kembali memiliki `daily_activity_logs.update`; create/delete dan UI
  Logbook Admin tetap read-only. Owner tidak memperoleh hak tulis.
- Kategori mandiri disimpan transaksional dengan foto privat, validasi kondisional,
  satuan asli dalam `farm_activities.task_details`, dan tabel detail yang tersedia.
  Tidak ada tabel detail placeholder baru.
- Form SOP menggunakan `schema: taxonomy`; payload legacy dengan satuan kanonis
  tetap diterima. Laporan tidak mengubah status tugas menjadi `submitted`/`completed`.
  Pertanyaan dan rekomendasi lifecycle pada bagian 4 tetap terbuka.
- Penanaman menerima m²/Ha dan benih kg/sak/butir; berat per sak diperlukan,
  butir harus bulat dan tidak diperlakukan sebagai kg. Kedalaman opsional;
  pola tanam dari pemetaan bila tersedia dapat dikoreksi.
- `planting_started_on` menyimpan tanggal pertama; `planted_at` menyimpan acuan
  HST (tanggal aktual berbeda kedua, atau pertama bila hanya satu). Akumulasi
  luas tidak boleh melebihi reservasi dan varietas lanjutan harus sama.
- Jendela tiga hari inklusif memakai tanggal pekerjaan, bukan submit. Laporan
  terlambat tetap dihitung. Rentang waktu tahap memakai tanggal paling awal/akhir
  sehingga laporan yang datang tidak berurutan tetap sah.
- SOP langsung digenerasi setelah laporan tanam pertama. Rekonsiliasi menggeser
  tugas pending tanpa duplikat, mempertahankan snapshot dan override Admin.
  `planting:reconcile-windows` dijadwalkan **setiap jam** setelah jendela berakhir;
  Laravel scheduler harus berjalan. Laporan valid berikutnya tetap menghitung
  ulang dan membuka penanda `planting_window_checked_at`.

### Deployment dan otorisasi

- Migrasi aditif `2026_11_04_000001_support_staged_planting_and_daily_hours.php`
  sudah diterapkan pada `farmer_ic_dev`; tidak ada reset/reseed database lokal.
  Checkout lain perlu migrasi normal. Baseline terisolasi tidak diubah.
- Controller mengotorisasi listing/detail/header/foto dan aksi tulis melalui
  policy; `FarmActivityPolicy::recordVoluntary` memeriksa farm, kategori persisted,
  serta izin detail/foto/header. Query daftar, total, detail, kategori, saran tugas,
  dan foto memakai `visibleTo` sebelum serialisasi/pagination.
- Command melakukan bootstrap identitas assigned, lalu memeriksa Gate sebagai
  aktor dan mengambil farm/cycle melalui scope sebelum rekonsiliasi.
- Tidak ditemukan endpoint baru tanpa guard dalam slice ini. Rute/query AI/RAG
  dan modul lain tidak diaudit ulang; ini bukan audit seluruh aplikasi.
- Bila config cache dipakai, rebuild sesudah deploy dan restart worker lama.

### Verifikasi iterasi ini

- **164/164 tes terarah lulus, 4.486 assertions**: Activity, Petugas, SOP,
  Authorization, Blocks pada database PostgreSQL uji terpisah.
- Cakupan: seluruh kategori mandiri, unit taxonomy penanaman, validasi kondisional,
  foto, scoped totals, 403 lintas kebun, header bersama/concurrency, status tugas
  tetap pending, tanggal tanam berbeda/berulang/terlambat, rekonsiliasi tanpa
  duplikat, dan perlindungan override Admin.
- Browser pada database terpisah berhasil menyimpan laporan mandiri, SOP, dan
  penanaman dengan foto, membuka detail hasil simpan, memakai ulang header, serta mengisi
  jam selesai melalui editor terpisah. Validasi minimal satu foto terbukti.
- Percobaan suite penuh terhenti oleh timeout PHP 120 detik ketika teardown
  `NotificationCommitTest` membangun ulang indeks schema. Tidak ada klaim suite
  penuh lulus; hasil 538 tes di bagian 6 adalah historis.
- TypeScript, build frontend, Pint, dan `git diff --check` lulus. Build masih
  memberi warning font opsional `fontaine` dan chunk >500 kB; tidak memasang paket.
- Pemeriksaan DOM seluruh halaman terkait dalam light/dark dengan lebar shell
  320/375/390/430 px tidak menemukan overflow horizontal atau warning/error console.
  Konversi input 1000 m² → 0,1 Ha terverifikasi di browser; tes server juga mencakup
  0,0001 Ha dan penolakan pecahan m². Pemeriksaan ini bukan emulasi viewport penuh.
- Screenshot awal Aktivitas berhasil diperiksa. Screenshot lanjutan terhalang
  browser desktop yang tidak terlihat (`UnknownVizError`); inspeksi visual akhir
  seluruh halaman/tema masih perlu dilakukan pada jendela browser yang terlihat.

### Perbaikan akhir yang sudah disertakan

- Memperbaiki unwrapping `useRemember` agar to-do dan filter benar-benar tampil.
- Menormalkan filter `mine` ke boolean di backend, sehingga query `mine=0` tidak
  membuat opsi **Input saya** tercentang setelah halaman dimuat ulang.
- Menyelaraskan kode kategori pemupukan/pengendalian SOP dengan taxonomy terbaru.
- Pergantian m²/Ha mempertahankan luas yang diinput; menerima presisi sampai
  0,0001 Ha dan menolak pecahan m². Satuan benih asli tampil pada riwayat tanam.
- Setelah penanaman berhasil disimpan, foto, luas tambahan, jumlah benih, dan
  catatan form dikosongkan agar laporan lanjutan memakai input baru.
- Tautan dari laporan tanam/tugas menuju detail laporan individual. Header
  bersama tetap bisa dibuka melalui halaman harian.
- Ekspektasi tes Admin diperbarui hanya untuk izin edit header bersama;
  create/delete tetap ditolak.
- Kontrak kategori, task list, daily log, dan otorisasi sudah diselaraskan;
  rancangan API batch lama diberi label historis.

## 9. Tindak lanjut sesudah penyerahan

1. Pada checkout lain, jalankan migrasi normal dan pastikan Laravel scheduler
   berjalan untuk `planting:reconcile-windows` setiap jam.
2. Lakukan inspeksi visual akhir light/dark pada browser desktop yang terlihat,
   termasuk viewport ponsel dan desktop; pemeriksaan DOM bukan penggantinya.
3. Investigasi timeout teardown `NotificationCommitTest` sebelum menyatakan
   seluruh suite regresi lulus. Suite terarah terakhir: 164 lulus, 4.486 assertions.
4. Sepakati lifecycle tugas: pencatatan progres vs aksi **Ajukan selesai**,
   lalu review Admin. Implementasi saat ini sengaja tidak mengubah status tugas
   ketika laporan disimpan, sesuai keputusan pengguna.
5. Akumulasi panen, penutupan siklus, integrasi stok/keuangan, koreksi laporan
   tanam, dan penyulaman tetap menjadi pekerjaan lanjutan yang perlu disepakati.

Perintah regresi terarah yang digunakan (PowerShell, database uji terpisah):

```powershell
$env:TEST_DB_DATABASE='farmer_ic_crud_test_petugas_verification'
$env:FARMER_CRUD_TEST_DATABASE='farmer_ic_crud_test_petugas_verification'
php -d max_execution_time=0 vendor/bin/phpunit tests/Feature/Activity tests/Feature/Petugas tests/Feature/Sop tests/Feature/Authorization tests/Feature/Blocks --display-skipped
```
