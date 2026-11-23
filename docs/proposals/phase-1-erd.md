# Usulan ERD lengkap phase 1 pertanian

> **Catatan historis & status implementasi (pembaruan 16 November 2026):**
> dokumen ini berawal sebagai usulan awal phase 1 dan telah diselaraskan dengan
> implementasi aktif:
> - `2026_11_09_000001_remove_planting_rounds.php`: tabel `planting_rounds`
>   dihapus dan `planting_cycles.block_id` menjadi relasi langsung ke blok.
> - `2026_11_16_000001_make_subblock_area_dynamic_from_planting.php`: luas
>   subblok didefinisikan secara dinamis oleh `planting_cycles.planted_area_ha`
>   dari realisasi aktivitas penanaman (menggantikan alokasi tetap
>   `reserved_area_ha > 0`); pembuatan subblok baru dicegah ketika sisa kapasitas
>   blok atau kebun penuh, dan dapat dilakukan oleh Admin maupun Petugas pada
>   kebun tugasnya.
> - `2026_11_16_000002_simplify_activity_categories_and_sops.php`: kategori
>   aktivitas budidaya disederhanakan menjadi enam kategori utama (**Pembersihan
>   Lahan**, **Pengolahan Tanah**, **Pemupukan Kandang**, **Penanaman**,
>   **Perawatan**, dan **Panen**) ditambah **Pelaporan Insiden**, **Lainnya**,
>   serta internal **Pemetaan Blok**. `activity_subcategory` pada SOP dan task
>   dinonaktifkan (selalu `null`), sedangkan `soil_preparation` dan `maintenance`
>   menyimpan luas area di `farm_activities.task_details` (`jsonb`) dan catatan
>   opsional tanpa membuat baris detail anak (`soil_preparation_details` usulan
>   tidak dipakai di schema aktif).
> - Schema aktif PostgreSQL sepenuhnya tercatat di
>   [database/schema.dbml](../../database/schema.dbml).

Tanggal rancangan awal dan penyelarasan RBAC: 29 Oktober 2026; diperbarui
mengikuti perubahan November 2026. Status: **bagian blok, subblok dinamis,
penyederhanaan 6 kategori aktivitas, dan definisi SOP sudah diimplementasikan;
flow pengajuan/approval `planting_cycle_task_submissions` masih usulan phase 1**.
Lihat [backend blok](../farm-block-backend.md) untuk rincian modul blok dan
subblok. DBML proposal mempertahankan struktur usulan (termasuk tabel
`planting_cycle_task_submissions` serta catatan historis `planting_rounds` dan
`soil_preparation_details`) agar jejak desain awal tetap utuh, sedangkan kontrak
schema aktif PostgreSQL ada di [database/schema.dbml](../../database/schema.dbml).
Batas akses mengikuti versi remote `e994d7c` yang diperluas dengan izin
pembuatan subblok bagi Petugas pada kebun tugasnya selama kapasitas blok masih
tersedia. Asumsi yang masih terbuka ditandai terpisah.

Dokumen ini menjelaskan usulan tanpa diagram gambar. File
[phase-1-erd.dbml](phase-1-erd.dbml) memuat seluruh tabel, tipe kolom, PK, FK,
nullability, serta unique key. Schema aktif tetap ada di
[database/schema.dbml](../../database/schema.dbml).

## Keputusan yang sudah dipahami

- Lahan memiliki blok permanen.
- Pembagian subblok dapat berubah pada penanaman berikutnya.
- Satu subblok pada suatu periode penanaman adalah satu batch.
- Penanaman pertama dapat menghasilkan Batch 1, 2, 3; penanaman berikutnya
  menghasilkan Batch 4, 5. Histori batch sebelumnya tetap ada.
- Stage dan jadwal setiap batch berbeda sesuai tanggal dan realisasinya.
- Stage tetap: pembersihan lahan (`land_cleaning`), pengolahan tanah
  (`soil_preparation`), pemupukan kandang (`basic_fertilizer` /
  `base_fertilization`), penanaman (`planting`), perawatan (`maintenance`),
  panen (`harvest`).
- Fokus phase 1: data lahan, aktivitas harian, transaksi penanaman, transaksi
  panen, template SOP. Transaksi di sini merupakan laporan operasional.
- Memakai kembali `planting_cycles` untuk identitas batch/subblok, bukan
  menambah tabel batch lain dengan fungsi yang sama. Tidak ada master subblok
  permanen.
- Tabel Finance, AI/RAG, autentikasi, serta histori yang sudah ada dipertahankan.
- Admin mengelola kebun dan akun Admin/Petugas dalam satu tenant; penugasan
  Petugas tetap satu kebun melalui `users.assigned_farm_id` (Admin maupun
  Petugas dapat membuat subblok pada blok aktif selama kapasitas masih tersedia).
- SOP dimiliki satu tenant dan wajib untuk satu komoditas tanaman. Varietas tidak
  memilih SOP. Setiap kebun menunjuk satu SOP default; Petugas tidak memilih SOP
  saat mencatat penanaman. Distribusi lintas tenant berada di luar draft ini.
- Petugas mengajukan penyelesaian task; Admin tenant melakukan approval.
  Pengiriman laporan tidak langsung membuat task berstatus selesai.
- Versi SOP yang sudah dipakai siklus berjalan dibekukan. Perubahan template
  hanya berlaku untuk siklus berikutnya.

Aturan role mengikuti [User Management](../user-management.md) dan
[Authorization scaffolding](../authorization-scaffolding.md). SuperAdmin
memiliki akses global; Owner membaca data tenant yang diikutinya, termasuk
User Management dalam mode baca. Dokumen ini tidak mengubah matriks izin aktif.

## Asumsi draft yang belum disepakati

| Hal | Pilihan dalam rancangan & pembaruan | Dampak jika berubah |
| --- | --- | --- |
| Cakupan nomor batch | Berurutan per lahan: unique `farm_id, batch_number` | Bila per blok atau global, ubah aturan nomor dan unique key |
| Pengelompokan periode | Historis proposal memakai `planting_rounds`; schema aktif memakai `planting_cycles.block_id` langsung | Bila pengelompokan periode diaktifkan kembali, tambah relasi di atas `planting_cycles` |
| Kapan subblok dibuat | Dapat dibuat saat persiapan dengan `planted_area_ha = 0` oleh Admin atau Petugas selama sisa kapasitas blok/kebun > 0; pembuatan ditolak jika blok sudah penuh | Jika hanya boleh dibuat saat tanam, histori persiapan perlu dikaitkan kemudian |
| Laporan penanaman baru | Luas subblok diturunkan dinamis dari akumulasi realisasi `planted_area_ha` aktivitas penanaman dalam batas sisa kapasitas blok/kebun | Bila kembali ke alokasi tetap di depan, aktifkan lagi `reserved_area_ha > 0` sebelum tanam |
| Acuan hari SOP | H+0 adalah tanggal acuan, H+7 tujuh hari sesudahnya | Bila istilah bisnis memakai Hari 1 untuk tanggal awal, tampilan menyesuaikan tanpa menggeser tanggal |
| Lokasi fisik subblok | Dicatat sebagai luas realisasi dinamis (`planted_area_ha`) dan label per subblok (`subblock_label`) | Penentuan batas/peta subblok belum diminta; pemeriksaan luas tidak membuktikan tidak ada tumpang tindih geografis |
| Persiapan bersama | Draft mendukung timeline persiapan per batch | Pembagian pekerjaan yang sekaligus mencakup satu blok penuh perlu dibahas bila diperlukan |
| Penolakan dan pengajuan ulang | Pengajuan rejected tetap disimpan; task kembali in_progress dan percobaan berikutnya membuat baris baru | Rincian teknis ini masih draft untuk memenuhi flow approval yang telah disepakati |
| Pembukaan kembali task approved | Tidak otomatis; alur koreksi terpisah belum ditetapkan | Jangan menimpa keputusan atau membuka task completed hanya karena laporan diedit |

## Susunan dan relasi inti

| Parent | Relasi | Child | Foreign key |
| --- | --- | --- | --- |
| `teams` | 1 ke banyak | `farms` | `farms.team_id` |
| `farms` | 1 ke banyak Petugas | `users` | `users.assigned_farm_id`; satu Petugas maksimal satu kebun |
| `farms` | 1 ke banyak | `farm_blocks` | `farm_blocks.farm_id` |
| `farm_blocks` | 1 ke banyak *(historis usulan; schema aktif langsung ke `planting_cycles.block_id`)* | `planting_rounds` | `planting_rounds.block_id` |
| `planting_rounds` | 1 ke banyak *(historis usulan)* | `planting_cycles` | `planting_cycles.planting_round_id` |
| `crop_varieties` | 1 ke banyak | `planting_cycles` | `planting_cycles.variety_id` |
| `teams` dan `commodities` | masing-masing 1 ke banyak | `sop_templates` | `team_id` dan `commodity_id` wajib |
| `sop_templates` | 1 ke banyak | `farms` | `farms.default_sop_template_id`, opsional; dipakai otomatis untuk penanaman |
| `sop_templates` | 1 ke banyak | `sop_template_versions` | `sop_template_versions.sop_template_id` |
| `sop_template_versions` | 1 ke banyak template salinan | `sop_templates` | `copied_from_version_id`, opsional dan hanya untuk penelusuran asal |
| `sop_template_versions` | 1 ke banyak | `sop_template_tasks` | `sop_template_tasks.sop_template_version_id` |
| `sop_template_versions` | 1 ke banyak | `planting_cycles` | `planting_cycles.sop_template_version_id`, opsional |
| `planting_cycles` | 1 ke 6 tahap | `planting_cycle_stages` | `planting_cycle_stages.planting_cycle_id` |
| `planting_cycle_stages` | 1 ke banyak | `planting_cycle_tasks` | `planting_cycle_stage_id`, wajib satu batch |
| `sop_template_tasks` | 1 ke banyak salinan | `planting_cycle_tasks` | `sop_template_task_id`, opsional untuk pekerjaan manual |
| `planting_cycles` | 1 ke banyak | `farm_activities` | `farm_activities.planting_cycle_id` |
| `planting_cycle_tasks` | 1 ke banyak realisasi | `farm_activities` | `planting_cycle_task_id`, opsional |
| `planting_cycle_tasks` | 1 ke banyak percobaan pengajuan | `planting_cycle_task_submissions` | `planting_cycle_task_id`; urutan unik per task |
| `users` | 1 ke banyak pengajuan dan review | `planting_cycle_task_submissions` | `submitted_by` wajib; `reviewed_by` terisi setelah review |
| `daily_activity_logs` | 1 ke banyak | `farm_activities` | `daily_activity_log_id`, wajib satu lahan jika terisi |
| `farm_activities` | 1 ke 1 detail (kategori bertipe) atau `task_details` JSONB (`soil_preparation`, `maintenance`) | Tabel detail aktivitas / `plant_observations` | `activity_id`; kategori bertipe saling eksklusif, sementara `soil_preparation` & `maintenance` mengizinkan 0 baris detail |
| `farm_activities` | 1 ke banyak | `activity_photos` | `activity_photos.activity_id` |

Relasi `farm_activities.planting_cycle_id` tetap opsional untuk histori lama dan
pekerjaan umum lahan. Laporan tanam/panen baru dalam flow batch mewajibkan batch.
`farm_activities.performed_by` adalah pelaksana; `daily_activity_logs.recorded_by`
adalah pencatat dan tidak harus orang yang sama. Batas baca dan mutasi laporan
mengikuti matriks dan Policy versi remote, termasuk scope kebun tugas Petugas.
Pembatasan tambahan berdasarkan penulis laporan tidak dianggap sudah diterapkan
oleh draft ini. Akses laporan tidak memberikan hak approval kepada Petugas.

## Penambahan dan perubahan

Draft awal memiliki **48 tabel aplikasi** (38 baseline dan 10 usulan; **56 tabel**
termasuk 7 tabel pendukung Laravel serta `migrations`), sedangkan struktur
PostgreSQL aktif setelah migrasi tambahan tercatat pada `database/schema.dbml`.
Kolom `users.deleted_at` mengikuti soft delete dari versi remote.
Lima tabel existing disesuaikan pada rancangan awal. Sisanya mempertahankan kolom yang sudah ada.

| Tabel baru | Tujuan |
| --- | --- |
| `farm_blocks` | Master blok permanen beserta luasnya |
| `planting_rounds` | Kelompok batch dalam satu periode penanaman di blok *(historis usulan; dihapus pada schema aktif)* |
| `sop_templates` | Identitas template SOP per tenant dan komoditas beserta asal salinan |
| `sop_template_versions` | Versi SOP yang dapat dipublikasikan dan dibekukan |
| `sop_template_tasks` | Daftar pekerjaan berdasarkan kategori (`maintenance` \| `harvest`, tanpa subkategori) dan hari HST relatif |
| `planting_cycle_stages` | Timeline enam stage masing-masing batch |
| `planting_cycle_tasks` | Salinan pekerjaan dan jadwal per batch |
| `planting_cycle_task_submissions` | Riwayat pengajuan selesai, snapshot bukti, dan keputusan approval setiap percobaan |
| `land_cleaning_details` | Detail realisasi pembersihan lahan (input aktif memakai `area_ha` + catatan opsional) |
| `soil_preparation_details` | Historis usulan detail pengolahan tanah *(tidak dipakai di schema aktif; digantikan `farm_activities.task_details` JSONB)* |

Pada implementasi aktif November 2026 (`2026_11_16_000002`), hanya kategori
tertentu yang membuat baris pada tabel detail anak: pembersihan lahan
(`land_cleaning_details`), pemupukan kandang (`base_fertilization` ->
`fertilization_details` dengan `fertilization_stage = 'kandang'`), penanaman
(`planting_details`), panen (`harvest_details`), insiden (`incident_details`),
dan lainnya (`other_details`). Pengolahan tanah (`soil_preparation`) dan
perawatan (`maintenance`) menyimpan `area_ha` pada `farm_activities.task_details`
(`jsonb`) serta catatan opsional (`description`) tanpa mewajibkan baris detail
anak baru, sementara baris detail lama (`loosening_details`, `weeding_details`,
`bed_preparation_details`, `liming_details`, `irrigation_details`,
`pesticide_details`) tetap dipertahankan untuk histori.

| Tabel existing | Penyesuaian |
| --- | --- |
| `planting_cycles` | Tambah relasi blok (`block_id` pada schema aktif; `planting_rounds` di proposal historis), nomor batch, label subblok, `reserved_area_ha` (legacy `>= 0` disinkronkan ke `planted_area_ha`), `planted_area_ha` sebagai luas subblok dinamis, tanggal rencana, versi SOP, pembuat, waktu penutupan dan pembaruan. `planted_at` nullable selama persiapan; `planted_area_ha` bernilai 0 sebelum penanaman |
| `farm_activities` | Tambah tautan opsional ke jadwal batch dan `task_details` (`jsonb`) untuk input area/detail bersama (termasuk `soil_preparation` dan `maintenance` tanpa baris detail anak) |
| `planting_details` | Input aktif mencatat luas area, nama bibit, dan jumlah/berat benih; `seedling_count` dan `planting_pattern` dipertahankan untuk histori |
| `block_mapping_details` | Tambah referensi opsional master blok; nama dan ukuran hasil pengukuran tetap menjadi snapshot historis |
| `activity_types` | Sediakan 6 kategori budidaya utama (`land_cleaning`, `soil_preparation`, `base_fertilization`, `planting`, `maintenance`, `harvest`) beserta `incident`, `other`, dan `block_mapping` (`is_selectable = false`); kode sub-aktivitas lama dimigrasikan ke kategori akar (`2026_11_16_000002`) |

`planting_cycles.farm_id` dipertahankan untuk hubungan existing dan batas akses.
Pada schema aktif, `planting_cycles.block_id` terhubung langsung ke blok pada
kebun yang sama (tanpa `planting_rounds`).
`current_stage` tidak ditambahkan pada batch: nilainya diperoleh dari timeline
agar tidak menyimpan dua status tahap yang dapat berbeda.

## Kategori, subkategori historis, dan stage batch

Sejak pembaruan `2026_11_16_000002`, form aktivitas harian memakai **6 kategori
budidaya utama** (**Pembersihan Lahan**, **Pengolahan Tanah**, **Pemupukan
Kandang**, **Penanaman**, **Perawatan**, dan **Panen**) ditambah **Pelaporan
Insiden** dan **Lainnya**, dengan field input area (`area` + `area_unit`) dan
**Catatan opsional** (`farm_activities.description`) yang seragam. Di level SOP
(`sop_template_tasks` dan `planting_cycle_tasks`), `activity_subcategory`
dinonaktifkan (selalu `null`) dan langkah SOP hanya memakai `maintenance` atau
`harvest` (penanaman menjadi jangkar H+0 HST).

| Kategori | Subkategori SOP (aktif) | Stage batch |
| --- | --- | --- |
| `land_cleaning` *(non-SOP)* | - | `land_cleaning` |
| `soil_preparation` *(non-SOP)* | - | `soil_preparation` |
| `base_fertilization` *(Pemupukan Kandang, non-SOP)* | - | `basic_fertilizer` |
| `planting` *(jangkar H+0 SOP)* | - | `planting` |
| `maintenance` | - *(dinonaktifkan; sebelumnya `irrigation`, `weeding`, `follow_up_fertilization`, `growth_check`, `pest_control`)* | `maintenance` |
| `harvest` | - | `harvest` |

Pembersihan lahan, pengolahan tanah, dan pemupukan kandang dikelola sebagai
persiapan subblok/blok dan bukan langkah SOP. Pembuatan aktivitas pada kategori
`soil_preparation` dan `maintenance` tidak lagi mewajibkan tabel detail anak
khusus karena luas realisasi tersimpan pada `farm_activities.task_details`.

## Jadwal SOP dan histori

- `sop_templates.team_id` dan `commodity_id` wajib. Tidak ada SOP generik tanpa
  komoditas. Versi yang dipakai batch harus berasal dari tenant yang sama dan
  komoditasnya harus sama dengan komoditas `planting_cycles.variety_id` batch.
- Setiap kebun dapat menunjuk satu `default_sop_template_id`. Saat Petugas
  mencatat penanaman, sistem memakai SOP default kebun tersebut tanpa pilihan
  manual. Jika belum diatur, penanaman tidak boleh memakai SOP otomatis.
- Template memiliki versi draft, published, dan retired. Versi published berikut
  pekerjaannya tidak diedit; revisi membuat versi baru.
- Setelah memiliki versi published atau dipakai batch, tenant dan komoditas
  template tidak diganti. Kebutuhan di tenant/komoditas lain memakai template baru.
- Saat dipakai, isi pekerjaan disalin ke `planting_cycle_tasks`. Referensi sumber
  tetap disimpan untuk penelusuran, tetapi histori batch tidak bergantung pada
  teks template yang bisa berubah.
- Perubahan template atau versi terbaru hanya digunakan batch berikutnya.
  Jadwal dan isi pekerjaan batch berjalan tetap memakai snapshot versi semula.
- Setiap pekerjaan SOP memiliki `activity_category` (`maintenance` atau
  `harvest`, sedangkan `activity_subcategory` selalu `null` sejak
  `2026_11_16_000002`); stage subblok diturunkan dari kategori tersebut saat
  task subblok dibuat.
- Semua offset pekerjaan SOP memakai HST dari acuan `H+0` subblok (`planted_at`),
  yang selalu ditetapkan **satu hari setelah tanggal aktivitas penanaman
  pertama** pada subblok (`planting_started_on + 1 hari`). `planting` tetap
  menjadi pemicu jangkar HST, bukan langkah SOP; pekerjaan persiapan tidak
  menjadi langkah SOP.
- Tanggal acuan memakai `planting_cycles.planted_at` jika sudah ada. Selama
  persiapan sebelum realisasi tanam, `planned_planting_at` tetap menjadi tanggal
  rencana, tetapi pembuatan task SOP menunggu realisasi penanaman pertama.
  Perhitungan hari mengikuti Asia/Jakarta.
- `scheduled_date = planted_at + offset_days`;
  `due_date = scheduled_date + tolerance_days`. Pada hari penanaman pertama,
  HST masih belum dimulai (`null`) sampai keesokan harinya (`0 HST`). Jika
  `planted_at` belum tersedia, tanggal jadwal task boleh kosong.
- Koreksi tanggal tanam batch merupakan koreksi data realisasi oleh Admin
  tenant, bukan penerapan revisi SOP. Koreksi dapat menghitung ulang tanggal task
  yang belum dimulai dari offset snapshot yang sama; riwayat perubahan wajib
  dicatat. Task yang sudah dikerjakan atau diajukan tidak digeser diam-diam.
- Beberapa laporan aktivitas boleh merealisasikan satu pekerjaan, misalnya
  pekerjaan dilakukan bertahap. Penyelesaian pekerjaan ditetapkan secara
  melalui pengajuan dan approval; keberadaan satu laporan tidak otomatis berarti
  seluruhnya selesai.
- Pekerjaan atau stage yang dilewati harus memiliki alasan.
- Pekerjaan tambahan manual memiliki `sop_template_task_id = null`.
- Pekerjaan yang terlambat ditentukan dari tanggal dan status; tidak perlu
  menyimpan status overdue terpisah yang dapat basi.

### Distribusi lintas tenant

SOP tidak disalin antar kebun dalam tenant yang sama karena semua kebun memakai
template tenant. Penyalinan lintas tenant berada di luar draft ini;
`copied_from_version_id` tetap disediakan untuk kebutuhan tersebut nanti.

## Pengajuan selesai dan approval task

`planting_cycle_tasks` menyimpan keadaan pekerjaan. Tabel baru
`planting_cycle_task_submissions` menyimpan satu baris per percobaan pengajuan,
termasuk pengaju, waktu, catatan, snapshot bukti, dan reviewer. Status pekerjaan
dibedakan dari keputusan review agar pengajuan yang ditolak tidak hilang.

| Tindakan | Aktor | Status task sesudahnya | Status pengajuan |
| --- | --- | --- | --- |
| Mulai bekerja | Petugas pada kebun task | `in_progress` | Belum ada |
| Kirim hasil kerja | Petugas pada kebun task | `submitted` | Baris baru `submitted` |
| Setujui | Admin tenant kebun task atau SuperAdmin | `completed` | `approved` |
| Tolak dengan alasan | Admin tenant kebun task atau SuperAdmin | `in_progress` | `rejected`, tetap disimpan |
| Perbaiki dan ajukan ulang | Petugas pada kebun task | `submitted` | Baris baru, `attempt_number` berikutnya |

Draft transisi penolakan/pengajuan ulang di atas adalah rincian teknis yang
masih dapat direview sebelum implementasi. Prinsip yang sudah disepakati tetap:
Petugas mengajukan dan hanya Admin tenant atau SuperAdmin yang memberi approval.

### Isi dan histori pengajuan

- `submitted_by` diambil dari sesi Petugas aktif, bukan payload. Task harus
  berada di satu kebun yang ditugaskan kepadanya dan tenant yang diikutinya.
  Pengajuan diperbolehkan dari task `pending` atau `in_progress`; pengajuan
  langsung dari `pending` tetap masuk review, bukan langsung `completed`.
- `attempt_number` dimulai dari 1 dan diterbitkan server dengan lock task.
  Beberapa Petugas pada kebun yang sama boleh mengerjakan task, tetapi hanya
  satu pengajuan `submitted` aktif per task agar tidak terjadi review paralel
  atas dua pengajuan penyelesaian yang bersaing.
- `evidence_snapshot` dibentuk server dari task dan aktivitas terkait yang
  dipakai saat submit. Aktivitas harus benar-benar menunjuk task, batch, dan
  kebun yang sama serta terlihat oleh pengaju. JSON ini bukan input bebas klien
  atau sumber data pengganti laporan. Pengajuan wajib memiliki minimal satu
  aktivitas terkait; pengajuan tanpa aktivitas tidak diperbolehkan.
- Isi pengajuan dan snapshot dibekukan setelah submit. Koreksi dilakukan lewat
  pengajuan baru setelah penolakan; baris sebelumnya tidak digunakan ulang.
  Pengajuan menyimpan bukti pada waktu itu walaupun laporan asli kemudian
  dikoreksi atau dihapus secara sah. Riwayat review tidak mengikuti perubahan
  laporan asli secara diam-diam; pembukaan kembali task approved belum diatur.
- `reported_completed_at` opsional mencatat waktu selesai menurut Petugas dan
  tidak boleh setelah `submitted_at`. Waktu submit dan review ditetapkan server.

### Keputusan review dan konsistensi status

- Pengajuan `submitted` memiliki `reviewed_by` dan `reviewed_at` kosong.
  `approved` atau `rejected` wajib memiliki keduanya; `reviewed_at` tidak boleh
  sebelum `submitted_at`. Penolakan wajib memiliki `review_note` berisi alasan.
- Reviewer diperiksa berdasarkan hak saat tindakan: Admin aktif harus masih
  menjadi anggota tenant kebun task; Petugas dan Owner tidak boleh
  melakukan approval. SuperAdmin tetap mengikuti bypass developer.
- Review hanya boleh dilakukan sekali pada pengajuan yang masih `submitted`.
  Perubahan keputusan final tidak menimpa baris lama. Penolakan mengembalikan
  task ke `in_progress` dan tidak mengisi `completed_at`.
- Approval memperbarui pengajuan menjadi `approved` dan task menjadi
  `completed` pada transaksi yang sama. `task.completed_at` adalah waktu
  approval (`reviewed_at`), bukan waktu pengajuan atau waktu selesai yang
  dilaporkan Petugas. Maksimal satu pengajuan approved per task dalam flow ini.
- Task `completed`, `skipped`, atau `cancelled` tidak menerima pengajuan baru.
  Task `submitted` tidak dilewati/dibatalkan sebelum pengajuannya diselesaikan
  melalui review. Skip/cancel tetap tindakan Admin tenant dengan alasan, bukan
  jalan pintas Petugas untuk menyelesaikan pekerjaan.
- Penyelesaian stage memerlukan task wajib yang telah `completed` melalui
  approval atau dilewati secara sah dengan alasan. Task `submitted` belum
  dihitung selesai; stage/batch tidak otomatis selesai karena ada satu laporan.
- Lock task lalu pengajuan ketika submit/review, validasi status sebelumnya,
  serta gunakan partial unique index untuk satu `submitted` dan satu `approved`
  per task. Unique `(task_id, attempt_number)` sendiri belum cukup. DBML tidak
  menyatakan partial index atau aturan lintas tabel ini sebagai FK biasa.
- Soft delete pengguna atau perubahan membership tidak menghapus histori pengajuan
  maupun review lama. Data tidak dihapus melalui cascade dari task/user;
  gunakan restrict/arsip ketika merancang migration fitur ini nanti.

## Luas, jumlah bibit, dan siklus hidup

- Luas blok positif; jumlah luas blok aktif yang digunakan tidak boleh melebihi
  luas lahan.
- Sejak `2026_11_16_000001`, subblok baru tidak lagi meminta alokasi tetap
  `reserved_area_ha > 0` di awal. `planted_area_ha` dimulai dari `0` selama
  persiapan dan bertambah dinamis dari akumulasi realisasi aktivitas
  penanaman (`planting`), sedangkan `reserved_area_ha` dipertahankan sebagai
  kolom legacy yang disinkronkan ke `planted_area_ha` untuk subblok (`>= 0`).
- Kapasitas blok aktif dan kebun dihitung dari total `planted_area_ha` subblok
  berstatus `planned` / `active` (ditambah siklus legacy aktif tanpa blok).
  Validasi mengunci blok dan kebun saat membuat subblok atau mencatat penanaman
  agar permintaan bersamaan tidak melampaui kapasitas blok maupun kebun.
- Pembuatan subblok baru (dapat dilakukan oleh Admin maupun Petugas pada kebun
  tugasnya) dicegah apabila sisa kapasitas blok atau kebun sudah habis (`<= 0`).
- Luas tertanam tidak boleh negatif dan penambahannya saat penanaman tidak
  boleh melebihi sisa kapasitas blok maupun kebun yang tersedia. Perubahan
  agregat diperbarui pada transaksi database yang sama.
- Input penanaman aktif mencatat luas area (`area` + `area_unit`), nama bibit
  (`seed_name`), serta jumlah/berat benih (`quantity` + `quantity_unit`), lalu
  menyimpan hasil normalisasinya pada `planting_details.area_ha` dan
  `quantity_kg` (dengan payload mentah pada `farm_activities.task_details`).
  Kolom historis `seedling_count` / `planting_pattern` dipertahankan untuk data
  lama.
- Input panen aktif mencatat luas panen (`harvested_area_ha`) dan berat kotor
  (`gross_weight_ton`); kolom kualitas lama (`moisture_percentage`, `color`,
  `ripeness`, `cleanliness`) dipertahankan untuk histori.
- Koreksi laporan bukan subblok baru dan tidak boleh menggandakan luas atau
  benih. Penanaman baru pada area terpisah membuat subblok baru selama sisa
  kapasitas blok masih tersedia.
- Nomor subblok/batch tidak didaur ulang setelah pembatalan dan tidak reset saat
  panen. Urutan boleh memiliki celah; penerbitan nomor aman terhadap
  konkurensi.
- Panen dapat bertahap dan memiliki beberapa aktivitas/detail panen pada subblok
  yang sama. Luas panen per laporan tidak otomatis dijumlahkan menjadi luas unik,
  karena panen berulang dapat terjadi pada area yang sama.
- Subblok berstatus `harvested` dan melepas pemakaian kapasitas blok/kebun
  setelah ditutup/dinyatakan selesai, bukan otomatis pada laporan panen
  pertama. Pembatalan mempertahankan histori dan melepas pemakaian kapasitas.
- Ukuran blok yang berubah tidak boleh membuat total `planted_area_ha` subblok
  berjalan menjadi melebihi kapasitas blok. Total luas saja belum dapat
  memvalidasi tumpang tindih lokasi tanpa data batas geografis.

## Integritas dan batas akses

File DBML memperlihatkan struktur relasi; tidak menggantikan implementasi
constraint/check/trigger. Ketika diimplementasikan, hal berikut wajib dijaga:

1. Blok (dan periode pada rancangan historis `planting_rounds`) harus berasal dari
   lahan batch/subblok yang sama (`planting_cycles.block_id` langsung pada
   schema aktif).
2. Versi SOP berasal dari tenant batch yang sama, memiliki komoditas yang sama
   dengan batch, dan sudah published saat diterapkan. Komoditas template wajib
   terisi; varietas dan kebun tidak menentukan.
3. Source task adalah milik versi SOP batch dan stage yang sesuai.
4. Stage jadwal adalah milik batch yang sama. Relasi ini didokumentasikan
   dengan FK komposit.
5. Aktivitas yang menunjuk jadwal harus mengisi batch dan memiliki jenis
   aktivitas yang cocok dengan `activity_category` task (`maintenance` atau
   `harvest`, dengan `activity_subcategory = null`); nullability tidak boleh
   melewati pemeriksaan FK.
6. Mapping detail yang menunjuk blok harus satu lahan dengan aktivitas.
7. Urutan tanggal stage, jadwal, penutupan batch, dan panen konsisten. Panen
   memerlukan realisasi tanam. Maksimal satu stage in_progress per batch;
   perpindahan stage memerlukan penyelesaian atau alasan skip tahap sebelumnya.
8. Constraint trigger detail aktivitas mewajibkan 1:1 baris detail untuk
   kategori bertipe (`land_cleaning`, `base_fertilization`, `planting`,
   `harvest`, `incident`, `other`, `block_mapping`), sedangkan
   `soil_preparation` dan `maintenance` mengizinkan 0 baris detail anak dengan
   luas tersimpan di `farm_activities.task_details` (`jsonb`) sambil tetap
   menjaga baris detail lama (`loosening_details`, `weeding_details`,
   `bed_preparation_details`, `liming_details`, `irrigation_details`,
   `pesticide_details`). Trigger kapasitas (`farmer_ic_check_block_capacity`)
   menjumlahkan `planted_area_ha` untuk subblok `planned` / `active` (dan siklus
   legacy aktif tanpa blok).
9. Data jadwal dan realisasi tidak boleh hilang akibat penghapusan template,
   versi, blok, periode atau batch. Histori dilindungi dengan restrict/arsip.
   Aksi ON DELETE existing, seperti detail milik aktivitas dan FK nullable,
   harus ditinjau dan dipertahankan sesuai arti histori, bukan disalin membabi buta.
10. Membership tim dan penugasan kebun tetap berasal dari relasi persisten.
    Admin memerlukan membership pada satu tenant nonpersonal;
    Petugas memerlukan membership dan satu `assigned_farm_id`. Keberadaan
    membership tidak menggantikan grant tindakan atau mengizinkan lintas tenant.
11. SOP merupakan resource milik tenant. Scope `any` Admin dibatasi tenant melalui
    `sop_templates.team`, bukan `created_by`. Versi dan task template menurunkan
    tenant dari template; stage/task/pengajuan batch menurunkannya dari
    `planting_cycles.farm`. Semua model baru memerlukan Policy dan `visibleTo`
    sebelum daftar, pagination, jumlah, total, ekspor, dan serialisasi.
12. Pengajuan aktif dan keputusan review harus konsisten dengan status task.
    FK `submitted_by`/`reviewed_by` hanya membuktikan akun ada; role, keaktifan,
    membership, dan penugasan saat tindakan tetap diperiksa melalui Policy.
    Status serta actor review tidak boleh dimass-assign dari payload Petugas.
13. Histori pengajuan, snapshot, dan keputusan tidak dihapus atau ditimpa ketika
    role/penugasan berubah atau laporan dikoreksi. Aturan partial unique,
    keterkaitan task, waktu review, dan transisi perlu check/trigger serta
    transaksi service; relasi FK sendiri belum membuktikan semuanya.

Hak fitur mengikuti batas RBAC yang diperbarui:

| Resource atau tindakan | Owner | Admin | Petugas | SuperAdmin |
| --- | --- | --- | --- | --- |
| Blok, subblok/batch, stage dan jadwal task | Baca dalam tenant | Kelola kebun, blok, subblok, dan jadwal dalam tenant (pembuatan subblok dicegah bila blok penuh) | Baca kebun tugas dan dapat membuat subblok pada blok aktif kebun tugas selama kapasitas blok/kebun > 0; tidak mengelola master blok/jadwal SOP | Semua |
| Template, versi, dan task SOP | Baca dalam tenant | Kelola SOP dalam tenant, versi published tidak diedit | Baca petunjuk/versi yang diterapkan ke task kebunnya; tidak mengelola katalog | Semua, tetap menjaga histori versi |
| Pengajuan penyelesaian | Baca dalam tenant | Baca pengajuan dalam tenant | Membuat pengajuan pada kebun tugas; tidak mengubah pengajuan yang sudah dikirim | Semua |
| Approval atau penolakan | Tidak | Hanya dalam tenant | Tidak | Semua |

Pengelolaan akun mengikuti versi remote: Admin dapat membuat Admin/Petugas
dalam tenant yang sama, tetapi tidak mengubah role atau tenant akun existing.
Owner dapat membaca User Management lintas tenant yang diikutinya tanpa mutasi.
Soft delete dan restore mengikuti Policy remote. Draft tidak menambahkan portal
Petugas atau aturan PIC. Izin model SOP/task baru ditambahkan ke
`config/access.php` ketika fitur diimplementasikan, bukan pada revisi draft.

Poin lintas beberapa tabel atau agregat kapasitas tidak dapat dibuktikan hanya
oleh FK sederhana. Implementasi perlu memilih constraint trigger dan transaksi
service yang sesuai. Seluruh pemeriksaan aplikasi tetap memakai MVC + Service,
Form Request, Policy, dan Inertia; tidak ada Repository atau API eksternal baru.

## Hubungan dengan modul existing

- Finance tetap menunjuk `planting_cycles`, sehingga biaya dan pendapatan yang
  sudah terkait siklus tetap dapat dibaca per batch. Tidak dibuat tautan
  otomatis antara laporan tanam/panen dan transaksi uang.
- Monitoring, risiko, insight AI dan target operasional tetap menunjuk batch
  melalui `planting_cycle_id`.
- Percakapan dan dokumen RAG tetap memakai struktur tim yang ada. Tidak ada
  perubahan sumber pengetahuan AI atau penggantian workflow AI dalam draft ini.
- Tidak menghapus atau menggabungkan `ai_usage_logs` dan `ai_usage_events`.
- Tidak menambahkan master pupuk/pestisida atau referensi wilayah dalam scope
  ini; saran normalisasi sebelumnya tetap terpisah dari kebutuhan phase 1.
- Tidak menambah tabel subblok permanen atau tabel transaksi tanam/panen kedua:
  batch ada pada planting_cycles, realisasinya pada farm_activities dan detail.

## Contoh identitas batch

| Lahan | Blok | Periode | Label subblok opsional | Nomor batch |
| --- | --- | --- | --- | --- |
| Lahan A | Blok 1 | Periode 1 | A | 1 |
| Lahan A | Blok 1 | Periode 1 | B | 2 |
| Lahan A | Blok 1 | Periode 1 | C | 3 |
| Lahan A | Blok 1 | Periode 2 | A | 4 |
| Lahan A | Blok 1 | Periode 2 | B | 5 |

Label A/B boleh dipakai kembali pada periode lain. Identitas batch adalah UUID
dan nomor batch; label lokasi bukan kunci yang menimpa histori. Ukuran pembagian
baru bebas berbeda setelah alokasi batch sebelumnya dilepas.

## Transisi data lama saat implementasi nanti

1. Tambahkan tabel serta FK baru secara bertahap. Jangan langsung mewajibkan
   kolom baru pada data existing yang belum memiliki blok/periode.
2. Identifikasi kebun, siklus, luas, dan aktivitas lama. Pemetaan ke blok serta
   periode perlu keputusan berdasarkan data; jangan mengarang pembagian fisik.
3. Pertahankan ID planting_cycles agar Finance, aktivitas, panen, monitoring,
   dan target operasional tidak terputus.
4. Isi nomor batch secara deterministik sesuai scope yang disepakati,
   tanpa mengubah tanggal atau mengarang urutan tanam historis.
5. Pertahankan luas realisasi existing. Rekonsiliasi laporan lama yang tidak
   lengkap sebelum mengklaim semua total dapat dihitung ulang dari detail.
6. Tanggal tanam lama tetap ada. Tidak mengarang waktu cleaning/preparation,
   versi SOP historis atau status bahwa enam stage telah diselesaikan.
7. Aktifkan kolom wajib serta constraint baru setelah data terpetakan.
   Perubahan trigger kapasitas dan detail aktivitas harus diuji pada PostgreSQL.
8. Buat seeder demo, Policy, query scope, form, service, controller, halaman
   Inertia dan tes terintegrasi setelah rancangan disepakati.
9. Pertahankan penugasan Petugas yang sudah ada. Jangan membuat pivot PIC Admin
   atau menebak penugasan Petugas lama. Tabel PIC eksperimental yang mungkin
   masih ada pada DB lokal tidak dipakai sebagai sumber otorisasi.
10. Template contoh lama yang belum punya tenant/komoditas harus dipetakan secara
    eksplisit sebelum constraint wajib diaktifkan. Jangan mengubah SOP generik
    menjadi SOP semua tenant tanpa otorisasi.
11. Task/laporan historis tidak otomatis dibuatkan pengajuan approved. Histori
    yang belum memiliki bukti review tidak boleh diberi reviewer atau waktu
    approval fiktif. Uji partial unique dan submit/review bersamaan di PostgreSQL.

## Inventaris seluruh tabel dan kolom

Tipe, nullable, PK/FK dan unique key lengkap ada pada file DBML pendamping.
Daftar berikut mencakup seluruh tabel, termasuk modul yang tidak berubah.

### Akun, tim, dan lahan

| Tabel | Status | Kolom lengkap |
| --- | --- | --- |
| `roles` | Tetap | `id`, `name`, `description` |
| `users` | Tetap | `id`, `role_id`, `name`, `email`, `password`, `avatar_url`, `is_active`, `created_at`, `updated_at`, `current_team_id`, `email_verified_at`, `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`, `remember_token`, `assigned_farm_id`, `deleted_at` |
| `teams` | Tetap | `id`, `name`, `slug`, `is_personal`, `created_at`, `updated_at`, `deleted_at` |
| `team_members` | Tetap | `id`, `team_id`, `user_id`, `role`, `created_at`, `updated_at` |
| `team_invitations` | Tetap | `id`, `code`, `team_id`, `email`, `role`, `invited_by`, `expires_at`, `accepted_at`, `created_at`, `updated_at` |
| `farms` | Tetap | `id`, `owner_id`, `created_by`, `name`, `address`, `city`, `district`, `province`, `total_area_ha`, `latitude`, `longitude`, `image_url`, `created_at`, `updated_at`, `team_id`, `land_tenure`, `default_sop_template_id` |
| `farm_blocks` | Baru | `id`, `farm_id`, `code`, `name`, `area_ha`, `description`, `is_active`, `created_by`, `created_at`, `updated_at` |
| `commodities` | Tetap | `id`, `name`, `description`, `image_url` |
| `crop_varieties` | Tetap | `id`, `commodity_id`, `name`, `description` |
| `planting_rounds` | Baru *(Historis usulan; dihapus pada schema aktif)* | `id`, `block_id`, `round_number`, `name`, `started_at`, `finished_at`, `status`, `notes`, `created_by`, `created_at`, `updated_at` |
| `planting_cycles` | Disesuaikan | `id`, `farm_id`, `planting_round_id`, `variety_id`, `sop_template_version_id`, `batch_number`, `sub_block_code`, `reserved_area_ha` *(legacy `>= 0`/nullable)*, `planted_area_ha` *(dinamis)*, `planned_planting_at`, `planted_at`, `estimated_harvest_at`, `estimated_weight_ton`, `closed_at`, `status`, `created_by`, `created_at`, `updated_at` |

### Template SOP dan timeline batch

| Tabel | Status | Kolom lengkap |
| --- | --- | --- |
| `sop_templates` | Baru | `id`, `team_id`, `commodity_id`, `copied_from_version_id`, `name`, `description`, `is_active`, `created_by`, `created_at`, `updated_at` |
| `sop_template_versions` | Baru | `id`, `sop_template_id`, `version`, `notes`, `status`, `created_by`, `published_by`, `published_at`, `created_at`, `updated_at` |
| `sop_template_tasks` | Baru | `id`, `sop_template_version_id`, `name`, `instructions`, `offset_days`, `tolerance_days`, `is_required`, `created_at`, `updated_at`, `activity_category` *(`maintenance` \| `harvest`)*, `activity_subcategory` *(selalu `null`)* |
| `planting_cycle_stages` | Baru | `id`, `planting_cycle_id`, `stage_code`, `position`, `planned_start_at`, `planned_end_at`, `started_at`, `completed_at`, `status`, `skip_reason`, `created_at`, `updated_at` |
| `planting_cycle_tasks` | Baru | `id`, `planting_cycle_id`, `planting_cycle_stage_id`, `sop_template_task_id`, `activity_category` *(`maintenance` \| `harvest`)*, `activity_subcategory` *(selalu `null`)*, `activity_type_id`, `name`, `instructions`, `offset_days`, `tolerance_days`, `scheduled_date`, `due_date`, `position`, `is_required`, `status`, `completed_at`, `skip_reason`, `created_at`, `updated_at` |
| `planting_cycle_task_submissions` | Baru *(Belum dimigrasikan)* | `id`, `planting_cycle_task_id`, `attempt_number`, `submitted_by`, `submitted_at`, `reported_completed_at`, `submission_note`, `evidence_snapshot`, `status`, `reviewed_by`, `reviewed_at`, `review_note`, `created_at`, `updated_at` |

### Aktivitas dan laporan penanaman/panen

| Tabel | Status | Kolom lengkap |
| --- | --- | --- |
| `activity_types` | Disesuaikan | `id`, `code`, `name`, `description` *(6 kategori utama + `incident`, `other`, internal `block_mapping`; kolom taxonomy aktif ada di `database/schema.dbml`)* |
| `daily_activity_logs` | Disesuaikan | `id`, `farm_id`, `recorded_by`, `work_date`, `created_at`, `updated_at` |
| `farm_activities` | Disesuaikan | `id`, `farm_id`, `planting_cycle_id`, `activity_type_id`, `performed_by`, `activity_date`, `description`, `status`, `created_at`, `updated_at`, `daily_activity_log_id`, `position`, `obstacle_status`, `obstacles`, `proposed_solution`, `action_taken`, `task_details`, `planting_cycle_task_id` |
| `activity_photos` | Tetap | `id`, `activity_id`, `label`, `caption`, `image_url`, `uploaded_at` |
| `block_mapping_details` | Disesuaikan | `activity_id`, `form_version`, `block_name`, `length_m`, `width_m`, `row_spacing_cm`, `plant_spacing_cm`, `rows_per_bed`, `block_id` |
| `land_cleaning_details` | Baru | `activity_id`, `area_ha`, `clearing_method`, `equipment` *(schema aktif memakai `area_ha` + kolom legacy)* |
| `soil_preparation_details` | Baru *(Historis usulan; tidak dipakai di schema aktif)* | `activity_id`, `area_ha`, `preparation_method`, `tillage_depth_cm` |
| `planting_details` | Disesuaikan | `activity_id`, `seed_name`, `seedling_count` *(histori)*, `quantity_kg`, `planting_pattern` *(histori)*, `area_ha` |
| `fertilization_details` | Tetap *(Pemupukan Kandang & histori)* | `activity_id`, `fertilization_stage`, `fertilizer_type`, `brand_name`, `subsidy_status`, `application_method`, `quantity_kg` |
| `irrigation_details` | Tetap *(Histori sebelum penyederhanaan)* | `activity_id`, `water_source`, `irrigation_method`, `machine_type`, `duration_hours` |
| `pesticide_details` | Tetap *(Histori sebelum penyederhanaan)* | `activity_id`, `pesticide_category`, `purpose`, `brand_name`, `active_ingredient`, `dosage_per_tank`, `tank_size_liters`, `tank_count` |
| `incident_details` | Tetap | `activity_id`, `incident_category`, `severity`, `affected_area_ha`, `proposed_solution` |
| `harvest_details` | Tetap | `activity_id`, `harvested_area_ha`, `gross_weight_ton`, `moisture_percentage` *(histori)*, `color` *(histori)*, `ripeness` *(histori)*, `cleanliness` *(histori)* |

### Monitoring dan target operasional

| Tabel | Status | Kolom lengkap |
| --- | --- | --- |
| `plant_observations` | Tetap | `id`, `planting_cycle_id`, `activity_id`, `observed_by`, `observed_at`, `growth_phase`, `average_height_cm`, `disease_indication`, `pest_indication`, `health_status`, `growth_percentage`, `plant_condition`, `notes` |
| `weather_records` | Tetap | `id`, `farm_id`, `recorded_at`, `condition`, `temperature_c`, `humidity_percentage`, `data_source` |
| `farm_risks` | Tetap | `id`, `planting_cycle_id`, `observation_id`, `risk_description`, `severity`, `recommendation`, `status`, `detected_at` |
| `ai_insights` | Tetap | `id`, `planting_cycle_id`, `observation_id`, `summary`, `next_step`, `confidence_score`, `model_name`, `generated_at` |
| `operational_targets` | Tetap | `id`, `farm_id`, `planting_cycle_id`, `task_name`, `target_unit`, `target_value`, `completed_value`, `target_date`, `status`, `created_by` |

### Finance existing

| Tabel | Status | Kolom lengkap |
| --- | --- | --- |
| `financial_categories` | Tetap | `id`, `name`, `transaction_type` |
| `financial_transactions` | Tetap | `id`, `farm_id`, `planting_cycle_id`, `category_id`, `recorded_by`, `transaction_date`, `transaction_type`, `amount`, `description`, `created_at` |
| `financial_budgets` | Tetap | `id`, `farm_id`, `category_id`, `period_start`, `period_end`, `budget_amount`, `created_by`, `created_at` |
| `financial_projections` | Tetap | `id`, `farm_id`, `planting_cycle_id`, `period_start`, `period_end`, `projected_income`, `projected_expense`, `calculated_at` |

### AI/RAG existing

| Tabel | Status | Kolom lengkap |
| --- | --- | --- |
| `ai_conversations` | Tetap | `id`, `user_id`, `title`, `created_at`, `updated_at`, `team_id`, `status`, `knowledge_snapshot`, `last_message_at` |
| `ai_messages` | Tetap | `id`, `ai_conversation_id`, `role`, `content`, `model`, `status`, `created_at`, `user_id`, `provider`, `request_id`, `input_tokens`, `output_tokens`, `latency_ms`, `error_message`, `metadata`, `updated_at` |
| `ai_attachments` | Tetap | `id`, `ai_message_id`, `mime_type`, `path`, `created_at`, `ai_conversation_id`, `user_id`, `disk`, `original_name`, `size`, `sha256`, `kind`, `status`, `metadata`, `updated_at` |
| `ai_knowledge_documents` | Tetap | `id`, `title`, `content`, `file_url`, `created_by`, `created_at`, `updated_at`, `team_id`, `reviewed_by`, `status`, `source`, `content_hash`, `version`, `published_at`, `locale`, `domain`, `index_status`, `index_version`, `indexed_at`, `index_error` |
| `ai_knowledge_chunks` | Tetap | `id`, `ai_knowledge_document_id`, `team_id`, `chunk_index`, `content`, `content_hash`, `chunking_version`, `embedding_model`, `qdrant_point_id`, `token_count`, `metadata`, `status`, `indexed_at`, `created_at`, `updated_at` |
| `ai_usage_logs` | Tetap | `id`, `user_id`, `message_id`, `model_name`, `input_tokens`, `output_tokens`, `estimated_cost`, `created_at` |
| `ai_usage_events` | Tetap | `id`, `team_id`, `user_id`, `ai_conversation_id`, `ai_message_id`, `provider`, `model`, `event_type`, `request_id`, `input_tokens`, `output_tokens`, `latency_ms`, `metadata`, `created_at`, `updated_at` |

### Notifikasi dan audit existing

| Tabel | Status | Kolom lengkap |
| --- | --- | --- |
| `notifications` | Tetap | `id`, `user_id`, `title`, `message`, `is_read`, `created_at`, `read_at` |
| `audit_logs` | Tetap | `id`, `user_id`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `created_at` |

### Pendukung Laravel

| Tabel | Status | Kolom lengkap |
| --- | --- | --- |
| `password_reset_tokens` | Tetap | `email`, `token`, `created_at` |
| `sessions` | Tetap | `id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity` |
| `cache` | Tetap | `key`, `value`, `expiration` |
| `cache_locks` | Tetap | `key`, `owner`, `expiration` |
| `jobs` | Tetap | `id`, `queue`, `payload`, `attempts`, `reserved_at`, `available_at`, `created_at` |
| `job_batches` | Tetap | `id`, `name`, `total_jobs`, `pending_jobs`, `failed_jobs`, `failed_job_ids`, `options`, `cancelled_at`, `created_at`, `finished_at` |
| `failed_jobs` | Tetap | `id`, `uuid`, `connection`, `queue`, `payload`, `exception`, `failed_at` |
| `migrations` | Tetap | `id`, `migration`, `batch` |

## Validasi dokumen

Pemeriksaan struktur pada rancangan awal memeriksa jumlah/nama tabel, kolom target
FK, keberadaan unique key parent untuk FK komposit, serta ketercakupan seluruh
tabel baseline saat dokumen disusun (**56 tabel**, **93 referensi FK inline**,
dan **11 referensi FK komposit**, mencakup 38 tabel dan 345 kolom saat itu).
Pemeriksaan tersebut bukan pengujian migration atau simulasi aturan bisnis.

Pada pembaruan 16 November 2026:
- Implementasi aktif pada PostgreSQL diverifikasi melalui
  [database/schema.dbml](../../database/schema.dbml) dan suite pengujian
  migration (`FarmerIcMigrationsTest`), termasuk perubahan `2026_11_09_000001`
  (penghapusan `planting_rounds`), `2026_11_16_000001` (luas subblok dinamis
  dari `planted_area_ha` dan batas kapasitas blok/kebun), serta
  `2026_11_16_000002` (penyederhanaan 6 kategori budidaya utama, `Pemupukan
  Kandang`, penonaktifan `activity_subcategory`, serta penyimpanan
  `task_details` JSONB).
- Dokumen usulan `phase-1-erd.dbml` dan `phase-1-erd.md` ini diperbarui pada
  kolom, constraint, aturan bisnis, RBAC subblok Petugas, dan catatan historis
  agar selaras dengan perubahan tersebut sambil mempertahankan struktur target
  approval (`planting_cycle_task_submissions`) serta tabel historis usulan
  (`planting_rounds`, `soil_preparation_details`) yang diberi label eksplisit.
Parser DBML khusus tidak tersedia di dependency proyek, sehingga file proposal
belum diuji melalui layanan visualisasi DBML eksternal; kontrak schema aktif
tetap diuji langsung pada PostgreSQL melalui `FarmerIcMigrationsTest`.

