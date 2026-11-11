# Acuan Input Form dan Penjadwalan Aktivitas

Dokumen ini adalah acuan untuk field input form aktivitas, aturan blok/sub-blok, dan penjadwalan berbasis SOP. Bukan desain schema database.

## Keputusan model

- Activity log tetap berupa **aktivitas individual**, bukan rekap harian.
- Satu submit boleh berisi **satu atau beberapa aktivitas**; setiap baris tetap menjadi satu aktivitas individual.
- **Header harian bersama per kebun + tanggal kerja**, dibuat saat aktivitas pertama dan dipakai lintas Petugas yang berwenang.
- Block mapping tetap form terpisah; batch bukan lagi pengecualian khusus block mapping.
- Beberapa aktivitas pada tanggal dan farm yang sama memakai **header harian yang sama** agar pekerja tidak mengisi tanggal dan jam berulang kali.
- Scheduled tasks **tidak dibagi daily/weekly/monthly**. Task dibuat dari langkah **SOP modular** pada offset **HST** tertentu.
- Field dibagi menjadi **blok**. Jika penanaman hanya mencakup sebagian blok, bagian yang ditanami menjadi **sub-blok**.
- Penanaman subblok dibatasi **tiga hari kalender inklusif**: 26–28; tanggal 29 memakai subblok lain yang disiapkan Admin. Acuan HST adalah tanggal penanaman aktual kedua yang berbeda jika ada, atau tanggal pertama jika hanya satu hari.
- **Jumlah pekerja dicatat per aktivitas** dan boleh berbeda antar aktivitas.
- **Catatan harian tetap ada**, terpisah dari catatan aktivitas; jam selesai boleh dilengkapi kemudian.
- Aktivitas **tidak mencatat kendala, solusi, atau tindakan**. Pengecualian: form insiden memiliki `Tindakan segera` yang opsional.
- HOK **tidak dipakai lagi** dan dihapus dari form.
- Foto dokumentasi **wajib minimal satu** per aktivitas.
- Paraf/approval adalah **post-activity**, bukan input pekerja.
- Setiap pekerja bisa memiliki akun sendiri, tetapi biasanya **satu petugas ditunjuk untuk menginput log tim**. Pembuat pertama disimpan di header; pelaksana masing-masing aktivitas tetap disimpan terpisah.
- Block adalah unit field management; sub-blok adalah bagian blok yang ditanami pada batch/cycle. Block mapping mencatat dimensi dan layout; master blok perlu tersedia, sedangkan sub-blok menjadi label batch, bukan master permanen.
- Block mapping tidak lagi menjadi kategori aktivitas; block mapping tetap menjadi form field management terpisah.
- Form harus ringan. Field yang jarang dipakai disembunyikan di bagian "Detail tambahan".

## Status implementasi saat ini

Sudah ada:

- tabel `daily_activity_logs`
- relasi `farm_activities.daily_activity_log_id`
- `block_mapping_details`
- halaman Inertia daftar dan detail logbook
- form batch untuk beberapa kegiatan block mapping
- validasi, policy, dan test untuk slice block mapping
- tabel dan halaman admin definisi SOP: `sop_templates`, `sop_template_versions`, `sop_template_tasks`
- publikasi versi SOP
- SOP default per kebun dan penentuan SOP berdasarkan komoditas
- generate `planting_cycle_tasks` berbasis HST dari definisi SOP
- snapshot versi SOP pada subblok
- hierarki `activity_types` untuk kategori dan subkategori aktivitas
- query task list Petugas dari `planting_cycle_tasks`

Checkpoint 4 November 2026 juga sudah mencakup reuse/append header otomatis,
laporan SOP dan mandiri seluruh kategori selectable, foto privat, master blok,
riwayat terfilter, serta penanaman bertahap dan rekonsiliasi HST.
Detail input disimpan dalam `farm_activities.task_details` dan tabel detail bertipe
yang tersedia, dengan satuan asli tetap tersimpan. `worker_count` dan `notes`
harian sengaja dipertahankan. Kolom kendala lama dipertahankan untuk kompatibilitas
data lama, tetapi tidak ditampilkan pada form baru.

Belum diimplementasikan: approval/paraf dan lifecycle pengajuan selesai tugas.
Tugas diselesaikan langsung oleh Petugas setelah minimal satu laporan; Admin
tetap dapat melakukan skip/reschedule. Lihat
[`petugas-activity-handoff.md`](petugas-activity-handoff.md) untuk keputusan lengkap.

## Hierarki Kategori Aktivitas

`activity_types` sekarang memakai relasi self-reference:

- `parent_id` — `null` untuk kategori root, diisi untuk subkategori.
- `detail_key` — menentukan detail table yang dipakai. `null` berarti detail table belum tersedia.
- `is_selectable` — `false` untuk parent kategori dan `block_mapping`; aktivitas baru harus memilih baris selectable.
- `sort_order` / `is_active` — urutan dropdown dan status.

Tree saat ini:

```text
land_cleaning
soil_preparation
├── loosening
├── beds
├── liming
└── base_fertilization
planting
maintenance
├── irrigation
├── weeding
├── follow_up_fertilization
├── growth_check
└── pest_control
harvest
incident
other
block_mapping   (system-only, bukan pilihan form aktivitas)
```

`farm_activities.activity_type_id` menunjuk ke baris paling spesifik. Kategori
diturunkan dari `parent_id`. Detail table mengikuti `detail_key`:

```text
planting                  -> planting_details
irrigation                -> irrigation_details
follow_up_fertilization   -> fertilization_details (stage susulan)
base_fertilization        -> fertilization_details (stage dasar)
growth_check              -> plant_observations
pest_control              -> pesticide_details
incident                  -> incident_details
harvest                   -> harvest_details
block_mapping             -> block_mapping_details
land_cleaning             -> land_cleaning_details
loosening                 -> loosening_details
weeding                   -> weeding_details
beds                      -> bed_preparation_details
liming                    -> liming_details
other                     -> other_details
```

Semua kategori selectable sudah memiliki typed detail. `soil_preparation` dan
`maintenance` adalah parent non-selectable sehingga tidak punya detail table.
Snapshot submit tetap disimpan di `farm_activities.task_details`; form mandiri
Petugas menerima seluruh kategori selectable.

## Prinsip form

1. **Header minimal dan dapat dipakai ulang.** Cukup tanggal, waktu mulai/selesai, dan pelapor. Satu submit boleh berisi beberapa aktivitas dan header yang sama dipakai lagi.
2. **Jumlah pekerja harian bersama.** Total orang berbeda, bukan penjumlahan pekerja tiap aktivitas.
3. **Progressive disclosure.** Tampilkan field inti lebih dulu; field lanjutan dan input ada di bagian yang bisa dibuka.
4. **Auto-fill.** Luas blok, HST, dan pola tanam sebaiknya dihitung otomatis dan bisa dikoreksi.
5. **Satuan konsisten.** Default area m², berat kg, cairan liter, panjang cm/m. Konversi ke Ha/Ton hanya untuk laporan.
6. **Jelas mana yang wajib.** Field wajib ditandai; sisanya opsional.
7. **Jangan masukkan harga/biaya ke form pekerja** kecuali memang pekerja yang mencatat pembelian. Harga lebih cocok untuk admin/keuangan.
8. **Dropdown untuk data yang akan direkap.** Gunakan pilihan baku + "Lainnya" untuk kategori, fase, jenis pupuk, sumber air, mesin, dan kualitas panen.
9. **Foto wajib.** Minimal satu foto per aktivitas sebagai bukti pekerjaan. Foto diunggah saat submit; jika gagal, tampilkan opsi retry.
10. **Conditional fields.** Contoh: ukuran sak muncul hanya jika satuan = sak; mesin pompa dan bahan bakar muncul hanya jika metode = pompa.

---

## Header Harian

Header merangkum seluruh pekerjaan kebun pada tanggal itu. Header dapat dipakai
ulang dan dilengkapi bertahap melalui aksi terpisah **Ubah info harian**. Header
menyimpan jam kerja dan catatan umum; jumlah pekerja disimpan pada masing-masing
aktivitas.

| Field         | Type | Unit | Wajib       | Catatan                                                       |
| ------------- | ---- | ---- | ----------- | ------------------------------------------------------------- |
| Tanggal kerja | date |      | Ya          | Tanggal aktual pekerjaan, bukan tanggal submit                |
| Waktu mulai   | time |      | Ya          | Format HH:mm, zona Asia/Jakarta                               |
| Waktu selesai | time |      | Tidak       | Bila diisi harus setelah waktu mulai                          |
| Catatan harian | text |      | Tidak       | Catatan umum seluruh pekerjaan hari itu                        |
| Pelapor       | user |      | Server-side | Diambil dari user yang login, tidak ditampilkan sebagai input |

Yang **tidak** perlu masuk header:

- blok/sub-blok → simpan pada batch/aktivitas; header tidak menyimpan lokasi
- foto → simpan di aktivitas dan wajib
- paraf/approval → **post-activity**, bukan input pekerja
- cuaca → tidak masuk MVP; pertimbangkan field opsional atau auto-fill API nanti
- HOK → **tidak dipakai lagi**

## Cara Reuse Header Harian

Header tidak dipilih manual dari daftar. Sistem mencarinya otomatis.

1. Saat pekerja membuka form aktivitas, cari header untuk:
    - tanggal kerja yang sama
    - farm yang sama
    - header dipakai bersama oleh Petugas yang berwenang pada kebun; `visibleTo` dan unique constraint menjaga satu header

2. Jika ditemukan, tampilkan banner ringan:

    > **Melanjutkan log hari ini** — 28 Okt 2026, 08:00–16:00.
    > Informasi ini dipakai otomatis. [Ubah info harian]

3. Laporan baru otomatis masuk ke header tersebut tanpa menimpa isinya.
4. Jam kerja diperbarui melalui aksi terpisah yang diotorisasi; jumlah pekerja
   tetap dicatat pada aktivitas masing-masing.
5. Jika belum ada, simpan header dan aktivitas bersama dalam transaksi dengan lock farm.
6. Duplikat header ditolak constraint; input bersamaan memakai satu header.

Catatan kepemilikan header:

- Identitas unik header adalah `farm_id` + `work_date`; `recorded_by` merupakan pembuat pertama.
- Dua Petugas yang berwenang pada kebun yang sama memakai header yang sama.
- Petugas/Admin sesuai policy boleh mengubah info harian; Owner hanya membaca.
- Jumlah pekerja tidak disimpan di header; setiap aktivitas menyimpan `worker_count` sendiri.

Timestamp aktivitas memakai tanggal kerja + jam mulai header saat disimpan.
Pengubahan header tidak menulis ulang timestamp aktivitas yang sudah ada.

## Block, Sub-blok, Siklus, dan HST

- **Field/kebun** adalah unit lokasi.
- Field dibagi menjadi **blok**.
- Jika hanya sebagian blok yang ditanami, bagian yang ditanami menjadi **sub-blok**.
- Penanaman satu subblok boleh terjadi pada tanggal pertama sampai dua hari setelahnya (26–28).
- Tanggal 29 memerlukan subblok lain yang sudah disiapkan Admin. Laporan terlambat untuk tanggal 26–28 tetap diterima.
- Blok/sub-blok adalah unit **spasial**; cycle adalah unit **waktu**.
- Pada schema aplikasi, satu `planting_cycle` mewakili satu subblok/batch; tanggal acuan yang sama tidak menggabungkan record antarsubblok.
- Satu blok dapat memiliki beberapa sub-blok/cycle sepanjang waktu.

Contoh:

```text
Field
├── Blok A
│   ├── Sub-blok A1 → Cycle 1 → HST 0
│   └── Sub-blok A2 → Cycle 2 → HST 0
└── Blok B
    └── Sub-blok B1 → Cycle 1 → HST 0
```

Diagram di atas hanya pengelompokan waktu konseptual; setiap subblok memiliki record cycle sendiri.
HST memakai tanggal aktual kedua yang berbeda: 26/27/28 → 27; 26/28 → 28;
hanya 26 → 26. Tanggal pertama disimpan terpisah sebagai batas jendela.

## Scheduled Tasks Berbasis SOP

- Scheduled tasks **tidak** memakai recurrence daily/weekly/monthly.
- Admin menyusun **SOP modular**: nama langkah, kategori/subkategori aktivitas, offset HST, instruksi, dan bukti yang diminta.
- SOP dikaitkan ke **komoditas**, bukan varietas. Setiap kebun memiliki satu **SOP default**; Petugas tidak memilih SOP saat mencatat penanaman.
- SOP mencakup langkah **Perawatan dan Panen**; pembersihan lahan dan pengolahan tanah dikelola sebagai persiapan batch/blok. **Penanaman** dicatat sebagai aktivitas tanam dan menjadi acuan HST, bukan langkah SOP.
- Setelah realisasi tanam dicatat, sistem membuat task untuk setiap langkah SOP **Perawatan dan Panen** pada tanggal `planted_at` + offset HST. `planned_planting_at` bukan acuan pembuatan tugas.
- Task terikat pada batch/sub-blok; cycle diperoleh dari batch.
- Petugas mencatat activity log yang terhubung ke task. Laporan pertama
  memindahkan task ke `in_progress`; Petugas menandai `completed` secara
  eksplisit setelah minimal satu laporan. Tidak ada approval/review.
- Jika langkah tidak relevan, Admin dapat melakukan skip/reschedule dengan alasan.

## Dampak ke Field Management

- Field management harus menampilkan **blok dan sub-blok**, bukan hanya kebun.
- Setiap blok/sub-blok menampilkan cycle aktif, tanggal tanam, HST, dan task berikutnya.
- Block mapping tidak lagi sekadar label; blok perlu master data, sedangkan sub-blok menjadi label batch/cycle.
- Laporan dapat dikelompokkan per blok, sub-blok, atau cycle.
- Jangan menimpa blok saat penanaman parsial; buat sub-blok.

## Rancangan API batch historis (bukan kontrak endpoint Petugas saat ini)

Bagian berikut mempertahankan rancangan awal untuk referensi. Aturan sesi per
inputter, pekerja per aktivitas, dan header baru di sini sudah digantikan keputusan
4 November di atas. Kontrak aktif adalah endpoint web pada handoff, yang melakukan
resolve/append header secara otomatis; tidak ada endpoint API append generik baru.

Backend saat ini memakai pola **batch create**: satu request membuat header sekaligus satu atau banyak aktivitas block mapping. Targetnya tetap **create once, append one or more later**: header dibuat sekali, lalu satu atau beberapa aktivitas dapat ditambahkan pada submit yang sama atau submit berikutnya. Tidak harus menunggu akhir hari.

Catatan: halaman yang sudah ada memakai route web Inertia `/kebun/{farm}/daily-logs`. Contoh `/api/...` di bawah adalah bentuk konseptual; route JSON saat ini dibatasi untuk local/testing.

Alur yang disarankan:

1. Aktivitas pertama pada sesi/header:

```http
POST /api/kebun/{farm}/daily-logs
```

Body berisi header (tanggal dan waktu) + `activities[]` berisi satu atau beberapa aktivitas. Server membuat `daily_activity_logs` dan `farm_activities` sekaligus.

2. Aktivitas berikutnya:

```http
POST /api/kebun/{farm}/daily-logs/{dailyLog}/activities
```

Body berisi `activities[]` tanpa header. Server menambahkan satu atau beberapa `farm_activities` baru dengan `daily_activity_log_id` yang sama.

3. Sebelum menampilkan form, frontend mencari header hari ini:

```http
GET /api/kebun/{farm}/daily-logs?from_date=2026-10-28&to_date=2026-10-28&recorded_by={user}
```

Jika ada, tampilkan banner “Melanjutkan log hari ini” dan gunakan `dailyLog.id`. Jika tidak ada, tampilkan field header.

Pseudocode service:

```text
findTodayLog(farm, user, date):
    return latest daily_activity_logs
           where farm_id = farm
           and recorded_by = user
           and work_date = date
           order by created_at desc, id desc
           limit 1

createActivities(farm, user, header, activities):
    log = findTodayLog(farm, user, header.work_date)
    if log is null:
        log = createDailyLog(header)
    lock log
    positions = next positions after max(position)
    return log.activities.createMany(activities with positions)
```

Catatan implementasi:

- `recorded_by` diambil dari user yang login, bukan input form.
- Saat ini endpoint create hanya menerima `block_mapping`; target create dan append harus menerima semua kategori aktivitas.
- Jika ada aktivitas dalam batch di luar `start_time`/`end_time`, perbarui `end_time` atau pisahkan ke header baru.
- Jumlah pekerja disimpan per aktivitas, bukan di header.
- `position` unik per `daily_activity_log_id`; hitung posisi berikutnya di bawah lock header.
- Setiap aktivitas dalam batch tetap memerlukan minimal satu foto.
- `activity_date` diisi per aktivitas; jika form batch tidak meminta waktu per aktivitas, gunakan `work_date` + `start_time` header.
- Untuk export logbook, group `farm_activities` berdasarkan `daily_activity_log_id`.
- Block mapping tetap form terpisah dan tidak termasuk kategori aktivitas.
- Generate scheduled task dari definisi SOP ke batch belum termasuk implementasi minimal ini; definisi template/versi/task sudah tersedia.
- Jika aktivitas berasal dari scheduled task, simpan relasi task → activity log saat completion.

---

## Field Umum per Aktivitas

Field ini muncul untuk semua aktivitas. `Subkategori` hanya wajib jika kategori yang dipilih memang memiliki subkategori.

| Field             | Type    | Unit  | Wajib       | Catatan                                                                                                                  |
| ----------------- | ------- | ----- | ----------- | ------------------------------------------------------------------------------------------------------------------------ |
| Kategori          | select  |       | Ya          | Dari daftar kategori di bawah                                                                                            |
| Subkategori       | select  |       | Kondisional | Wajib jika kategori punya subkategori; untuk kategori tanpa subkategori tidak ditampilkan                                |
| Blok / sub-blok   | select  |       | Kondisional | Wajib untuk aktivitas berbasis blok; opsional untuk Lainnya; master blok perlu tersedia dan sub-blok menjadi label batch |
| Jumlah pekerja    | integer | orang | Ya          | Dapat berbeda antar aktivitas                                                                                            |
| Foto aktivitas    | file    |       | Ya          | Minimal 1 foto; bisa lebih dari satu                                                                                     |
| Catatan aktivitas | text    |       | Tidak       | Untuk detail yang tidak masuk field baku                                                                                 |

`Foto`, `Catatan aktivitas`, dan `Jumlah pekerja` tersedia untuk semua kategori;
foto wajib minimal satu.

Catatan:

- **Luas area tidak dimasukkan ke field umum** karena artinya berbeda per subkategori: luas tergarap, luas terdampak, luas panen, dan sebagainya.
- **Hasil juga tidak dimasukkan ke field umum** karena bentuknya berbeda: angka, teks, atau kualitas.
- Aktivitas tidak mencatat kendala, solusi, atau tindakan. `Tindakan segera` hanya ada di form insiden dan opsional.
- `HOK` dihapus dari form karena tidak dipakai lagi.
- `Paraf/approval` bukan input pekerja; ini bagian dari alur persetujuan setelah aktivitas dikirim.

---

# 1. Pembersihan Lahan

| Field            | Type           | Unit    | Wajib       | Catatan                        |
| ---------------- | -------------- | ------- | ----------- | ------------------------------ |
| Luas area        | number (float) | m² / Ha | Ya          | Pilih satuan                   |
| Metode           | select         |         | Ya          | Manual / Herbisida / Lainnya   |
| Nama produk      | text / select  |         | Kondisional | Muncul jika metode = Herbisida |
| Dosis per tangki | number (float) | ml/L    | Kondisional | Muncul jika metode = Herbisida |
| Volume air       | number (float) | liter   | Kondisional | Muncul jika metode = Herbisida |
| Jumlah tangki    | integer        | tangki  | Kondisional | Muncul jika metode = Herbisida |

# 2. Pengolahan Tanah

## 2.1 Penggemburan

| Field     | Type           | Unit    | Wajib | Catatan                       |
| --------- | -------------- | ------- | ----- | ----------------------------- |
| Luas area | number (float) | m² / Ha | Ya    |                               |
| Metode    | select         |         | Ya    | Manual / cultivator / lainnya |
| Kedalaman | number (float) | cm      | Ya    | Contoh: 10 cm                 |

## 2.2 Pembuatan Bedengan

| Field              | Type           | Unit   | Wajib    | Catatan                                     |
| ------------------ | -------------- | ------ | -------- | ------------------------------------------- |
| Jumlah bedeng      | integer        | bedeng | Ya       |                                             |
| Panjang            | number (float) | m      | Ya       |                                             |
| Lebar              | number (float) | m      | Ya       |                                             |
| Tinggi             | number (float) | cm     | Tidak    |                                             |
| Jarak antar bedeng | number (float) | cm     | Tidak    |                                             |
| Luas area          | number (float) | m²     | Otomatis | Bisa dihitung dari jumlah × panjang × lebar |

## 2.3 Penebaran Dolomit

| Field          | Type           | Unit           | Wajib       | Catatan                             |
| -------------- | -------------- | -------------- | ----------- | ----------------------------------- |
| Luas area      | number (float) | m² / Ha        | Ya          |                                     |
| Jenis material | select         |                | Ya          | Dolomit / kapur pertanian / lainnya |
| Merek          | text           |                | Tidak       | Contoh: KEBOMAS                     |
| Jumlah         | number (float) | kg / sak / ton | Ya          | Pilih satuan                        |
| Ukuran sak     | number (float) | kg             | Kondisional | Muncul jika satuan = sak            |
| Metode         | select         |                | Ya          | Tabur / cover / lainnya             |

## 2.4 Pemupukan Dasar

| Field       | Type           | Unit           | Wajib       | Catatan                    |
| ----------- | -------------- | -------------- | ----------- | -------------------------- |
| Luas area   | number (float) | m² / Ha        | Ya          |                            |
| Jenis pupuk | select         |                | Ya          | Kandang / kompos / lainnya |
| Merek       | text           |                | Tidak       |                            |
| Jumlah      | number (float) | kg / sak / ton | Ya          |                            |
| Ukuran sak  | number (float) | kg             | Kondisional | Muncul jika satuan = sak   |
| Metode      | select         |                | Ya          | Tabur / benam / lainnya    |

# 3. Penanaman

| Field           | Type           | Unit             | Wajib       | Catatan                                                              |
| --------------- | -------------- | ---------------- | ----------- | -------------------------------------------------------------------- |
| Varietas        | text / select  |                  | Ya          | Contoh: BISI 18                                                      |
| Jumlah benih    | number (float) | kg / sak / butir | Ya          | Pilih satuan                                                         |
| Ukuran sak      | number (float) | kg               | Kondisional | Muncul jika satuan = sak                                             |
| Pola tanam      | text / select  |                  | Otomatis    | Ambil dari pemetaan blok: jarak tanam, jarak baris, baris per bedeng |
| Luas area       | number (float) | m² / Ha          | Ya          |                                                                      |
| Kedalaman tanam | number (float) | cm               | Tidak       |                                                                      |

Catatan:

- `Pola tanam` sebaiknya tidak diketik ulang. Ambil dari data pemetaan blok; tampilkan sebagai read-only dengan opsi override.
- Contoh `20 sak × 1 kg` perlu dikonfirmasi. Jika 1 sak = 1 kg, total benih hanya 20 kg; ini tidak wajar untuk 2,5 Ha. Kemungkinan ukuran sak salah.

---

# 4. Perawatan

## 4.1 Pengairan

| Field       | Type           | Unit    | Wajib       | Catatan                                     |
| ----------- | -------------- | ------- | ----------- | ------------------------------------------- |
| Luas area   | number (float) | m² / Ha | Ya          |                                             |
| Sumber air  | select         |         | Ya          | Sumur bor / irigasi / embung / lainnya      |
| Metode      | select         |         | Ya          | Irigasi parit / sprinkler / pompa / lainnya |
| Mesin pompa | select / text  |         | Kondisional | Muncul jika memakai pompa                   |
| Durasi      | number (float) | jam     | Ya          |                                             |

## 4.2 Pendangiran

| Field     | Type           | Unit    | Wajib | Catatan                            |
| --------- | -------------- | ------- | ----- | ---------------------------------- |
| Luas area | number (float) | m² / Ha | Ya    |                                    |
| Kedalaman | number (float) | cm      | Tidak |                                    |
| Metode    | select         |         | Ya    | Manual / cangkul / mesin / lainnya |

## 4.3 Pemupukan Susulan

| Field       | Type           | Unit             | Wajib       | Catatan                         |
| ----------- | -------------- | ---------------- | ----------- | ------------------------------- |
| Luas area   | number (float) | m² / Ha          | Ya          |                                 |
| Jenis pupuk | select         |                  | Ya          | Urea / NPK / KCl / lainnya      |
| Merek       | text           |                  | Tidak       |                                 |
| Jumlah      | number (float) | kg / sak / liter | Ya          |                                 |
| Ukuran sak  | number (float) | kg               | Kondisional | Muncul jika satuan = sak        |
| Metode      | select         |                  | Ya          | Tabur / benam / kocor / lainnya |

## 4.4 Pengecekan Pertumbuhan

| Field             | Type           | Unit    | Wajib | Catatan                                                  |
| ----------------- | -------------- | ------- | ----- | -------------------------------------------------------- |
| Fase pertumbuhan  | select         |         | Ya    | Vegetatif / pembungaan / pengisian / pemasakan / lainnya |
| Tinggi tanaman    | number (float) | cm      | Ya    | Rata-rata                                                |
| Jumlah sampel     | integer        | tanaman | Tidak | Jika ingin menyimpan berapa sampel yang diukur           |
| Indikasi hama     | select         |         | Ya    | Aman / Ringan / Sedang / Berat                           |
| Indikasi penyakit | select         |         | Ya    | Aman / Ringan / Sedang / Berat                           |

Catatan:

- Jika pekerja mengukur beberapa sampel, keputusan yang perlu diambil: simpan semua sampel, atau simpan rata-rata + jumlah sampel. Untuk MVP, rata-rata + jumlah sampel lebih ringan.
- Skala hama dan penyakit harus sama. Jangan memakai `Darurat` untuk penyakit dan `Berat` untuk hama.

## 4.5 Pengendalian Hama dan Penyakit

| Field            | Type           | Unit    | Wajib | Catatan                                       |
| ---------------- | -------------- | ------- | ----- | --------------------------------------------- |
| Luas area        | number (float) | m² / Ha | Ya    |                                               |
| Jenis            | select         |         | Ya    | Insektisida / fungisida / pestisida / lainnya |
| Target           | select / text  |         | Ya    | Hama / penyakit / gulma / lainnya             |
| Nama produk      | text / select  |         | Ya    |                                               |
| Dosis per tangki | number (float) | ml/L    | Ya    |                                               |
| Volume air       | number (float) | liter   | Ya    |                                               |
| Jumlah tangki    | integer        | tangki  | Ya    |                                               |
| Metode           | select         |         | Ya    | Merata / spot / lainnya                       |

---

# 5. Panen

| Field             | Type           | Unit     | Wajib | Catatan                                                 |
| ----------------- | -------------- | -------- | ----- | ------------------------------------------------------- |
| Luas area         | number (float) | m² / Ha  | Ya    |                                                         |
| Berat hasil panen | number (float) | kg / ton | Ya    | Seluruh tanaman, termasuk jagung/kelobot; tidak dipisah |
| Kadar air         | number (float) | %        | Tidak | Jika diukur                                             |
| Metode panen      | select         |          | Tidak | Manual / mesin / lainnya                                |

Catatan:

- Sudah dikonfirmasi: panen jagung untuk pakan ternak dilakukan sebagai **seluruh tanaman**, termasuk jagung, dan tidak dipisah. `Berat hasil panen` adalah berat seluruh tanaman.
- Kualitas panen dapat dicatat di `Catatan aktivitas`; jangan hardcode field jagung ke semua komoditas.
- Harga jual dan biaya panen tidak masuk form pekerja; masuk ke modul keuangan/admin.

---

# 6. Pelaporan Insiden

| Field               | Type           | Unit    | Wajib | Catatan                                                                |
| ------------------- | -------------- | ------- | ----- | ---------------------------------------------------------------------- |
| Kategori insiden    | select         |         | Ya    | Hama / penyakit / cuaca / banjir / alat rusak / tenaga kerja / lainnya |
| Deskripsi           | text           |         | Ya    | Jelaskan kejadian                                                      |
| Luas area terdampak | number (float) | m² / Ha | Ya    | Pilih satuan                                                           |
| Tingkat kedaruratan | select         |         | Ya    | Ringan / Sedang / Berat / Darurat                                      |
| Tindakan segera     | text           |         | Tidak | Penanganan awal, khusus insiden                                        |
| Status              | select         |         | Tidak | Open / In Progress / Resolved                                          |

Catatan: `Tindakan segera` adalah field opsional khusus insiden, bukan field umum aktivitas. `Deskripsi` **bukan** field dengan satuan Ton. Ini kesalahan yang harus dihapus.

---

# 7. Lainnya

| Field     | Type           | Unit    | Wajib | Catatan      |
| --------- | -------------- | ------- | ----- | ------------ |
| Deskripsi | text           |         | Ya    | Wajib diisi  |
| Luas area | number (float) | m² / Ha | Tidak | Jika relevan |

Aktivitas yang tidak cocok dengan kategori di atas dicatat di sini.

---

## Aturan Validasi

- Area harus > 0.
- Berat harus > 0.
- Persentase harus 0–100.
- Waktu selesai harus setelah waktu mulai.
- Jumlah pekerja harian bersama minimal 1.
- Jika satuan = sak, ukuran sak wajib diisi dan > 0.
- Jika metode pengairan = pompa, mesin pompa wajib diisi.
- Jika insiden, deskripsi wajib diisi; `Tindakan segera` opsional.
- Jika panen, berat hasil panen wajib diisi.
- Foto minimal 1 per aktivitas.
- Satu submit minimal berisi 1 aktivitas.
- Satu header boleh memiliki banyak aktivitas; `position` minimal 1, unik per header, dan wajib untuk aktivitas baru berheader.
- Tanggal `activity_date` aktivitas baru harus sama dengan `work_date` header (Asia/Jakarta).
- Subkategori wajib hanya jika kategori memiliki subkategori.
- Blok/sub-blok wajib untuk aktivitas berbasis blok; `Lainnya` boleh tanpa blok.
- Tanggal kerja tidak boleh di masa depan.
