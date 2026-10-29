# Usulan ERD lengkap phase 1 pertanian

Tanggal rancangan: 29 Oktober 2026. Status: **draft diskusi**, belum menjadi
keputusan final dan belum diterapkan ke migration, model, route, atau database.

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
- Stage tetap: land cleaning, soil preparation, basic fertilizer, planting,
  maintenance, harvest.
- Fokus phase 1: data lahan, aktivitas harian, transaksi penanaman, transaksi
  panen, template SOP. Transaksi di sini merupakan laporan operasional.
- Memakai kembali `planting_cycles` untuk identitas batch, bukan menambah tabel
  batch lain dengan fungsi yang sama. Tidak ada master subblok permanen.
- Tabel Finance, AI/RAG, autentikasi, serta histori yang sudah ada dipertahankan.

## Asumsi draft yang belum disepakati

| Hal | Pilihan sementara dalam draft | Dampak jika berubah |
| --- | --- | --- |
| Cakupan nomor batch | Berurutan per lahan: unique `farm_id, batch_number` | Bila per blok atau global, ubah aturan nomor dan unique key |
| Pengelompokan periode | `planting_rounds` berlaku untuk satu blok | Periode lintas blok memerlukan rancangan pengelompokan berbeda |
| Kapan batch dibuat | Bisa dibuat saat persiapan, sebelum realisasi tanam | Jika hanya dibuat saat tanam, histori persiapan perlu dimasukkan atau dikaitkan kemudian |
| Template SOP | Milik satu tim, opsional khusus komoditas | Template bersama lintas tim memerlukan aturan distribusi dan akses tersendiri |
| Laporan penanaman baru | Default membuat batch baru; realisasi ke batch existing hanya melalui pilihan eksplisit | Aturan satu laporan vs beberapa laporan per batch belum diputuskan |
| Acuan hari SOP | H+0 adalah tanggal acuan, H+7 tujuh hari sesudahnya | Bila istilah bisnis memakai Hari 1 untuk tanggal awal, tampilan menyesuaikan tanpa menggeser tanggal |
| Lokasi fisik subblok | Dicatat sebagai alokasi luas dan label per batch | Penentuan batas/peta subblok belum diminta; pemeriksaan luas tidak membuktikan tidak ada tumpang tindih geografis |
| Persiapan bersama | Draft mendukung timeline persiapan per batch | Pembagian pekerjaan yang sekaligus mencakup satu blok penuh perlu dibahas bila diperlukan |

## Susunan dan relasi inti

| Parent | Relasi | Child | Foreign key |
| --- | --- | --- | --- |
| `teams` | 1 ke banyak | `farms` | `farms.team_id` |
| `farms` | 1 ke banyak | `farm_blocks` | `farm_blocks.farm_id` |
| `farm_blocks` | 1 ke banyak | `planting_rounds` | `planting_rounds.block_id` |
| `planting_rounds` | 1 ke banyak | `planting_cycles` | `planting_cycles.planting_round_id` |
| `crop_varieties` | 1 ke banyak | `planting_cycles` | `planting_cycles.variety_id` |
| `sop_templates` | 1 ke banyak | `sop_template_versions` | `sop_template_versions.sop_template_id` |
| `sop_template_versions` | 1 ke banyak | `sop_template_tasks` | `sop_template_tasks.sop_template_version_id` |
| `sop_template_versions` | 1 ke banyak | `planting_cycles` | `planting_cycles.sop_template_version_id`, opsional |
| `planting_cycles` | 1 ke 6 tahap | `planting_cycle_stages` | `planting_cycle_stages.planting_cycle_id` |
| `planting_cycle_stages` | 1 ke banyak | `planting_cycle_tasks` | `planting_cycle_stage_id`, wajib satu batch |
| `sop_template_tasks` | 1 ke banyak salinan | `planting_cycle_tasks` | `sop_template_task_id`, opsional untuk pekerjaan manual |
| `planting_cycles` | 1 ke banyak | `farm_activities` | `farm_activities.planting_cycle_id` |
| `planting_cycle_tasks` | 1 ke banyak realisasi | `farm_activities` | `planting_cycle_task_id`, opsional |
| `daily_activity_logs` | 1 ke banyak | `farm_activities` | `daily_activity_log_id`, wajib satu lahan jika terisi |
| `farm_activities` | 1 ke tepat 1 detail yang sesuai jenis | Tabel detail aktivitas atau `plant_observations` | `activity_id`; jenis detail saling eksklusif |
| `farm_activities` | 1 ke banyak | `activity_photos` | `activity_photos.activity_id` |

Relasi `farm_activities.planting_cycle_id` tetap opsional untuk histori lama dan
pekerjaan umum lahan. Laporan tanam/panen baru dalam flow batch mewajibkan batch.
`farm_activities.performed_by` adalah pelaksana; `daily_activity_logs.recorded_by`
adalah pencatat dan tidak harus orang yang sama.

## Penambahan dan perubahan

Draft memiliki **47 tabel aplikasi**: 38 existing dan 9 baru. Dengan 7 tabel
pendukung Laravel serta `migrations`, file DBML berisi **55 tabel**.
Lima tabel existing disesuaikan. Sisanya mempertahankan kolom yang sudah ada.

| Tabel baru | Tujuan |
| --- | --- |
| `farm_blocks` | Master blok permanen beserta luasnya |
| `planting_rounds` | Kelompok batch dalam satu periode penanaman di blok |
| `sop_templates` | Identitas template SOP milik tim |
| `sop_template_versions` | Versi SOP yang dapat dipublikasikan dan dibekukan |
| `sop_template_tasks` | Daftar pekerjaan berdasarkan stage dan hari relatif |
| `planting_cycle_stages` | Timeline enam stage masing-masing batch |
| `planting_cycle_tasks` | Salinan pekerjaan dan jadwal per batch |
| `land_cleaning_details` | Detail realisasi pembersihan lahan |
| `soil_preparation_details` | Detail realisasi pengolahan tanah |

Dua tabel detail terakhir melengkapi rancangan awal tujuh tabel struktur/SOP.
Keduanya diperlukan karena schema existing mewajibkan setiap aktivitas memiliki
tepat satu detail sesuai jenisnya. `basic_fertilizer` menggunakan jenis
`fertilization` dan `fertilization_details` yang sudah ada.

| Tabel existing | Penyesuaian |
| --- | --- |
| `planting_cycles` | Tambah periode, nomor batch, label subblok, alokasi luas, tanggal rencana, versi SOP, pembuat, waktu penutupan dan pembaruan. `planted_at` menjadi nullable selama persiapan; luas tertanam dapat 0 sebelum tanam |
| `farm_activities` | Tambah tautan opsional ke jadwal batch dan jaga kecocokan batch serta jenis aktivitas |
| `planting_details` | Tambah `seedling_count`; pertahankan berat benih untuk metode tanam berbasis berat |
| `block_mapping_details` | Tambah referensi opsional master blok; nama dan ukuran hasil pengukuran tetap menjadi snapshot historis |
| `activity_types` | Tambah kode tetap `land_cleaning` dan `soil_preparation`; sesuaikan constraint daftar jenis |

`planting_cycles.farm_id` dipertahankan untuk hubungan existing dan batas akses.
Nilainya wajib sama dengan lahan dari `planting_rounds.block_id`.
`current_stage` tidak ditambahkan pada batch: nilainya diperoleh dari timeline
agar tidak menyimpan dua status tahap yang dapat berbeda.

## Arti stage dan jenis aktivitas

| Stage | Contoh jenis aktivitas | Detail realisasi |
| --- | --- | --- |
| `land_cleaning` | `land_cleaning` | `land_cleaning_details` |
| `soil_preparation` | `soil_preparation` | `soil_preparation_details` |
| `basic_fertilizer` | `fertilization` | `fertilization_details` |
| `planting` | `planting` | `planting_details` |
| `maintenance` | `irrigation`, `fertilization`, `pesticide`, `growth_check`, `incident` | Detail sesuai jenis; `growth_check` memakai `plant_observations` |
| `harvest` | `harvest` | `harvest_details` |

Enam stage merupakan alur tetap, bukan master tahap yang dapat diedit bebas.
Jenis aktivitas dan stage adalah dua konsep berbeda: pemupukan bisa terjadi
pada basic fertilizer maupun maintenance.

## Jadwal SOP dan histori

- Template memiliki versi draft, published, dan retired. Versi published berikut
  pekerjaannya tidak diedit; revisi membuat versi baru.
- Saat dipakai, isi pekerjaan disalin ke `planting_cycle_tasks`. Referensi sumber
  tetap disimpan untuk penelusuran, tetapi histori batch tidak bergantung pada
  teks template yang bisa berubah.
- Setiap pekerjaan menunjuk salah satu dari enam stage milik batch yang sama.
- `schedule_anchor = planting_date` memakai tanggal realisasi tanam pertama.
  `stage_start` memakai tanggal mulai stage. Perhitungan hari mengikuti
  Asia/Jakarta; tanggal acuan dihitung sebagai H+0.
- `scheduled_date = tanggal acuan + offset_days`;
  `due_date = scheduled_date + tolerance_days`. Jika acuan belum tersedia,
  tanggal jadwal boleh kosong. Tanggal rencana pada batch/stage dapat dipakai
  sebagai perkiraan tampilan dan harus diberi label rencana.
- Perubahan tanggal acuan dapat menghitung ulang pekerjaan yang belum dimulai.
  Pekerjaan yang sudah dikerjakan tidak digeser diam-diam.
- Beberapa laporan aktivitas boleh merealisasikan satu pekerjaan, misalnya
  pekerjaan dilakukan bertahap. Penyelesaian pekerjaan ditetapkan secara
  eksplisit; keberadaan satu laporan tidak otomatis berarti seluruhnya selesai.
- Pekerjaan atau stage yang dilewati harus memiliki alasan.
- Pekerjaan tambahan manual memiliki `sop_template_task_id = null`.
- Pekerjaan yang terlambat ditentukan dari tanggal dan status; tidak perlu
  menyimpan status overdue terpisah yang dapat basi.

## Luas, jumlah bibit, dan siklus hidup

- Luas blok positif; jumlah luas blok yang digunakan tidak boleh melebihi luas
  lahan.
- `reserved_area_ha` adalah alokasi batch, sedangkan `planted_area_ha` adalah
  realisasi kumulatif dari laporan. Sebelum tanam, realisasi boleh 0.
- Jumlah alokasi batch yang masih menggunakan blok harus muat di luas blok,
  termasuk jika batch berada pada periode yang berbeda. Validasi harus mengunci
  blok saat membuat/mengubah alokasi agar permintaan bersamaan tidak melampaui
  kapasitas.
- Luas tertanam tidak boleh negatif atau melebihi alokasi. Perubahan laporan
  harus memperbarui agregat pada transaksi database yang sama.
- Jumlah bibit realisasi berada pada `planting_details.seedling_count`.
  Total per batch diperoleh dari laporan; tidak ditambah kolom total bibit
  kedua pada batch.
- `seedling_count` dan `quantity_kg` adalah dua ukuran berbeda. Form memilih
  sesuai metode penanaman; keduanya boleh diisi jika memang dicatat.
- Koreksi laporan bukan batch baru dan tidak boleh menggandakan luas atau bibit.
  Tombol laporan penanaman baru membuat identitas batch baru sesuai kebutuhan
  yang telah dijelaskan pengguna.
- Nomor batch tidak didaur ulang setelah pembatalan dan tidak reset saat panen.
  Urutan boleh memiliki celah; penerbitan nomor harus aman terhadap konkurensi.
- Panen dapat bertahap dan memiliki beberapa aktivitas/detail panen pada batch
  yang sama. Luas panen per laporan tidak otomatis dijumlahkan menjadi luas unik,
  karena panen berulang dapat terjadi pada area yang sama.
- Batch berstatus harvested dan melepas alokasi setelah dinyatakan selesai,
  bukan otomatis pada laporan panen pertama. Pembatalan harus mempertahankan
  histori dan menentukan pelepasan alokasi secara eksplisit.
- Ukuran blok yang berubah tidak boleh membuat alokasi aktif tidak valid.
  Total luas saja belum dapat memvalidasi tumpang tindih lokasi tanpa data batas.

## Integritas dan batas akses

File DBML memperlihatkan struktur relasi; tidak menggantikan implementasi
constraint/check/trigger. Ketika diimplementasikan, hal berikut wajib dijaga:

1. Blok dan periode benar-benar berasal dari lahan batch yang sama.
2. Versi SOP berasal dari tim lahan dan sudah published saat diterapkan.
   Jika template khusus komoditas, komoditasnya sesuai varietas batch.
3. Source task adalah milik versi SOP batch dan stage yang sesuai.
4. Stage jadwal adalah milik batch yang sama. Relasi ini didokumentasikan
   dengan FK komposit.
5. Aktivitas yang menunjuk jadwal harus mengisi batch dan memiliki jenis
   aktivitas yang sama; nullability tidak boleh melewati pemeriksaan FK.
6. Mapping detail yang menunjuk blok harus satu lahan dengan aktivitas.
7. Urutan tanggal stage, jadwal, penutupan batch, dan panen konsisten. Panen
   memerlukan realisasi tanam. Maksimal satu stage in_progress per batch;
   perpindahan stage memerlukan penyelesaian atau alasan skip tahap sebelumnya.
8. Constraint trigger detail aktivitas existing diperluas untuk dua jenis
   persiapan. Aturan satu detail sesuai jenis dan trigger luas existing juga
   disesuaikan dengan batch planned yang luas realisasinya masih 0.
9. Data jadwal dan realisasi tidak boleh hilang akibat penghapusan template,
   versi, blok, periode atau batch. Histori dilindungi dengan restrict/arsip.
   Aksi ON DELETE existing, seperti detail milik aktivitas dan FK nullable,
   harus ditinjau dan dipertahankan sesuai arti histori, bukan disalin membabi buta.
10. Membership tim dan penugasan kebun tetap berasal dari relasi persisten;
    semua model baru memerlukan Policy serta visibleTo. Daftar, hitungan,
    total dan ekspor wajib terbatasi cakupan.
11. SOP merupakan resource milik tim. Jika scope own diperlukan, semantiknya
    harus didefinisikan dan dites terlebih dahulu; tidak diasumsikan sebagai
    dibuat oleh pengguna. Matriks hak tulis role untuk fitur baru belum
    diputuskan dalam diskusi ini.

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

## Inventaris seluruh tabel dan kolom

Tipe, nullable, PK/FK dan unique key lengkap ada pada file DBML pendamping.
Daftar berikut mencakup seluruh tabel, termasuk modul yang tidak berubah.

### Akun, tim, dan lahan

| Tabel | Status | Kolom lengkap |
| --- | --- | --- |
| `roles` | Tetap | `id`, `name`, `description` |
| `users` | Tetap | `id`, `role_id`, `name`, `email`, `password`, `avatar_url`, `is_active`, `created_at`, `updated_at`, `current_team_id`, `email_verified_at`, `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`, `remember_token`, `assigned_farm_id` |
| `teams` | Tetap | `id`, `name`, `slug`, `is_personal`, `created_at`, `updated_at`, `deleted_at` |
| `team_members` | Tetap | `id`, `team_id`, `user_id`, `role`, `created_at`, `updated_at` |
| `team_invitations` | Tetap | `id`, `code`, `team_id`, `email`, `role`, `invited_by`, `expires_at`, `accepted_at`, `created_at`, `updated_at` |
| `farms` | Tetap | `id`, `owner_id`, `created_by`, `name`, `address`, `city`, `district`, `province`, `total_area_ha`, `latitude`, `longitude`, `image_url`, `created_at`, `updated_at`, `team_id` |
| `farm_blocks` | Baru | `id`, `farm_id`, `code`, `name`, `area_ha`, `description`, `is_active`, `created_by`, `created_at`, `updated_at` |
| `commodities` | Tetap | `id`, `name`, `description`, `image_url` |
| `crop_varieties` | Tetap | `id`, `commodity_id`, `name`, `description` |
| `planting_rounds` | Baru | `id`, `block_id`, `round_number`, `name`, `started_at`, `finished_at`, `status`, `notes`, `created_by`, `created_at`, `updated_at` |
| `planting_cycles` | Disesuaikan | `id`, `farm_id`, `planting_round_id`, `variety_id`, `sop_template_version_id`, `batch_number`, `sub_block_code`, `reserved_area_ha`, `planted_area_ha`, `planned_planting_at`, `planted_at`, `estimated_harvest_at`, `estimated_weight_ton`, `closed_at`, `status`, `created_by`, `created_at`, `updated_at` |

### Template SOP dan timeline batch

| Tabel | Status | Kolom lengkap |
| --- | --- | --- |
| `sop_templates` | Baru | `id`, `team_id`, `commodity_id`, `name`, `description`, `is_active`, `created_by`, `created_at`, `updated_at` |
| `sop_template_versions` | Baru | `id`, `sop_template_id`, `version`, `notes`, `status`, `created_by`, `published_by`, `published_at`, `created_at`, `updated_at` |
| `sop_template_tasks` | Baru | `id`, `sop_template_version_id`, `activity_type_id`, `stage_code`, `name`, `instructions`, `schedule_anchor`, `offset_days`, `tolerance_days`, `position`, `is_required`, `created_at`, `updated_at` |
| `planting_cycle_stages` | Baru | `id`, `planting_cycle_id`, `stage_code`, `position`, `planned_start_at`, `planned_end_at`, `started_at`, `completed_at`, `status`, `skip_reason`, `created_at`, `updated_at` |
| `planting_cycle_tasks` | Baru | `id`, `planting_cycle_id`, `planting_cycle_stage_id`, `sop_template_task_id`, `activity_type_id`, `name`, `instructions`, `schedule_anchor`, `offset_days`, `tolerance_days`, `scheduled_date`, `due_date`, `position`, `is_required`, `status`, `completed_at`, `skip_reason`, `created_at`, `updated_at` |

### Aktivitas dan laporan penanaman/panen

| Tabel | Status | Kolom lengkap |
| --- | --- | --- |
| `activity_types` | Disesuaikan | `id`, `code`, `name`, `description` |
| `daily_activity_logs` | Tetap | `id`, `farm_id`, `recorded_by`, `work_date`, `start_time`, `end_time`, `worker_count`, `notes`, `created_at`, `updated_at` |
| `farm_activities` | Disesuaikan | `id`, `farm_id`, `planting_cycle_id`, `activity_type_id`, `performed_by`, `activity_date`, `description`, `status`, `created_at`, `updated_at`, `daily_activity_log_id`, `position`, `obstacle_status`, `obstacles`, `proposed_solution`, `action_taken`, `planting_cycle_task_id` |
| `activity_photos` | Tetap | `id`, `activity_id`, `label`, `caption`, `image_url`, `uploaded_at` |
| `block_mapping_details` | Disesuaikan | `activity_id`, `form_version`, `block_name`, `length_m`, `width_m`, `row_spacing_cm`, `plant_spacing_cm`, `rows_per_bed`, `block_id` |
| `land_cleaning_details` | Baru | `activity_id`, `area_ha`, `clearing_method`, `equipment` |
| `soil_preparation_details` | Baru | `activity_id`, `area_ha`, `preparation_method`, `tillage_depth_cm` |
| `planting_details` | Disesuaikan | `activity_id`, `seed_name`, `seedling_count`, `quantity_kg`, `planting_pattern`, `area_ha` |
| `fertilization_details` | Tetap | `activity_id`, `fertilization_stage`, `fertilizer_type`, `brand_name`, `subsidy_status`, `application_method`, `quantity_kg` |
| `irrigation_details` | Tetap | `activity_id`, `water_source`, `irrigation_method`, `machine_type`, `duration_hours` |
| `pesticide_details` | Tetap | `activity_id`, `pesticide_category`, `purpose`, `brand_name`, `active_ingredient`, `dosage_per_tank`, `tank_size_liters`, `tank_count` |
| `incident_details` | Tetap | `activity_id`, `incident_category`, `severity`, `affected_area_ha`, `proposed_solution` |
| `harvest_details` | Tetap | `activity_id`, `harvested_area_ha`, `gross_weight_ton`, `moisture_percentage`, `color`, `ripeness`, `cleanliness` |

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

Validasi dokumen memeriksa jumlah/nama tabel, kolom target FK, keberadaan unique
key parent untuk FK komposit, serta ketercakupan semua tabel schema existing.
Pemeriksaan tersebut bukan pengujian migration atau simulasi aturan bisnis.
Hasil pemeriksaan struktur: 55 tabel, 89 referensi FK inline, dan 11 referensi
komposit; seluruh 38 tabel dan 343 kolom dari DBML existing tercakup. Tidak
ditemukan target FK yang hilang, tipe FK yang berbeda, atau unique key parent
komposit yang hilang. Parser DBML khusus tidak tersedia di dependency proyek,
sehingga file belum diuji melalui parser atau layanan visualisasi DBML.
Tidak ada migration, seeder, reset database, perubahan model, atau build
aplikasi yang diperlukan untuk pembuatan dokumen usulan ini.

