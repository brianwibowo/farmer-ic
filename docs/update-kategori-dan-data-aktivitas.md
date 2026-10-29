# Acuan Input Form dan Penjadwalan Aktivitas

Dokumen ini adalah acuan untuk field input form aktivitas, aturan blok/sub-blok, dan penjadwalan berbasis SOP. Bukan desain schema database.

## Keputusan model

- Activity log tetap berupa **aktivitas individual**, bukan rekap harian.
- Default: satu submit = satu aktivitas.
- Pengecualian: **block mapping boleh batch** — satu sesi pengukuran bisa mencatat beberapa blok sekaligus. Setiap blok tetap menjadi satu aktivitas individual.
- Beberapa aktivitas pada tanggal yang sama boleh memakai **header harian yang sama** agar pekerja tidak mengisi tanggal dan waktu berulang kali.
- Scheduled tasks **tidak dibagi daily/weekly/monthly**. Task dibuat dari langkah **SOP modular** pada offset **HST** tertentu.
- Field dibagi menjadi **blok**. Jika penanaman hanya mencakup sebagian blok, bagian yang ditanami menjadi **sub-blok**.
- Blok/sub-blok yang ditanam dalam **window 3 hari** dianggap satu **cycle** dan memakai **HST yang sama**. Penanaman setelah window tersebut membentuk sub-blok dan cycle/HST baru.
- **Jumlah pekerja dicatat per aktivitas** dan boleh berbeda antar aktivitas.
- **Catatan harian dihapus** karena akan berlaku untuk semua aktivitas pada hari itu; catatan hanya disimpan per aktivitas.
- Aktivitas **tidak mencatat kendala, solusi, atau tindakan**. Pengecualian: form insiden memiliki `Tindakan segera` yang opsional.
- HOK **tidak dipakai lagi** dan dihapus dari form.
- Foto dokumentasi **wajib minimal satu** per aktivitas.
- Paraf/approval adalah **post-activity**, bukan input pekerja.
- Setiap pekerja bisa memiliki akun sendiri, tetapi biasanya **satu petugas ditunjuk untuk menginput log tim**. Header terikat pada akun inputter, farm, dan tanggal.
- Block/sub-blok adalah unit field management. Block mapping mencatat dimensi dan layout; master blok/sub-blok perlu tersedia.
- Form harus ringan. Field yang jarang dipakai disembunyikan di bagian "Detail tambahan".

## Status implementasi saat ini

Sudah ada:

- tabel `daily_activity_logs`
- relasi `farm_activities.daily_activity_log_id`
- `block_mapping_details`
- halaman Inertia daftar dan detail logbook
- form batch untuk beberapa kegiatan block mapping
- validasi, policy, dan test untuk slice block mapping

Belum ada:

- endpoint append aktivitas ke header yang sudah ada
- reuse header otomatis di frontend
- kategori/subkategori selain block mapping
- upload foto
- approval/paraf
- tabel master blok/sub-blok
- definisi SOP modular dan generate task berbasis HST
- pemindahan `worker_count` dari header ke aktivitas
- penghapusan `notes` harian dan field kendala/solusi/tindakan

Backend saat ini masih menyimpan `worker_count` dan `notes` di `daily_activity_logs`, serta `obstacles`, `proposed_solution`, dan `action_taken` di `farm_activities`. Form target memindahkan jumlah pekerja ke aktivitas dan menghapus field-field tersebut kecuali `Tindakan segera` khusus insiden.

## Prinsip form

1. **Header minimal.** Cukup tanggal, waktu mulai/selesai, dan pelapor.
2. **Jumlah pekerja per aktivitas.** Jumlah pekerja dapat berbeda antar aktivitas.
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

Header ini adalah target form. Backend saat ini masih menyimpan jumlah pekerja dan catatan di header; keduanya perlu dipindah/dihapus.

| Field         | Type | Unit | Wajib       | Catatan                                                       |
| ------------- | ---- | ---- | ----------- | ------------------------------------------------------------- |
| Tanggal kerja | date |      | Ya          | Tanggal aktual pekerjaan, bukan tanggal submit                |
| Waktu mulai   | time |      | Ya          | Format HH:mm, zona Asia/Jakarta                               |
| Waktu selesai | time |      | Ya          | Harus setelah waktu mulai                                     |
| Pelapor       | user |      | Server-side | Diambil dari user yang login, tidak ditampilkan sebagai input |

Yang **tidak** perlu masuk header:

- jumlah pekerja → simpan di aktivitas
- catatan harian → dihapus
- blok/sub-blok → simpan di aktivitas
- foto → simpan di aktivitas dan wajib
- paraf/approval → **post-activity**, bukan input pekerja
- cuaca → tidak masuk MVP; pertimbangkan field opsional atau auto-fill API nanti
- HOK → **tidak dipakai lagi**

## Cara Reuse Header Harian

Header tidak dipilih manual dari daftar. Sistem mencarinya otomatis.

1. Saat pekerja membuka form aktivitas, cari header untuk:
   - tanggal kerja yang sama
   - farm yang sama
   - akun inputter yang sama
   - header yang masih terbuka

2. Jika ditemukan, tampilkan banner ringan:

   > **Melanjutkan log hari ini** — 28 Okt 2026, 08:00–16:00.
   > [Lanjutkan] [Ubah info] [Sesi baru]

3. Jika pekerja menekan **Lanjutkan**, aktivitas baru otomatis masuk ke header tersebut.
4. Jika aktivitas dilakukan di luar rentang waktu header, tampilkan pilihan:
   - perpanjang waktu selesai header, atau
   - buat sesi/header baru.
5. Jika tidak ada header hari ini, form membuat header baru.
6. Jika pekerja lupa dan membuat header kedua di hari yang sama, jangan blokir. Backend mengizinkan lebih dari satu header per hari; nanti bisa dibuat alat merge atau export per sesi.

Catatan kepemilikan header:

- Header terikat pada `farm_id`, `recorded_by`, dan `work_date`.
- Setiap pekerja bisa memiliki akun sendiri, tetapi biasanya satu petugas ditunjuk menginput seluruh log tim. Header mengikuti akun inputter, bukan seluruh pekerja.
- Jika dua pekerja berbeda menginput log pada hari yang sama, saat ini akan terbentuk dua header. Keputusan apakah header harus dibagi per tim atau per akun masih perlu dikonfirmasi.
- Backend mengizinkan lebih dari satu header per hari; pilih yang terbaru atau tampilkan sebagai sesi terpisah.

Catatan: untuk MVP, waktu header adalah rentang kerja harian. Jika aktivitas berikutnya dilakukan di luar rentang itu, cukup perpanjang `end_time` atau buat sesi baru. Waktu per aktivitas bisa ditambahkan nanti jika memang dibutuhkan.

## Block, Sub-blok, Siklus, dan HST

- **Field/kebun** adalah unit lokasi.
- Field dibagi menjadi **blok**.
- Jika hanya sebagian blok yang ditanami, bagian yang ditanami menjadi **sub-blok**.
- Blok/sub-blok yang ditanam dalam **window 3 hari** dianggap satu **cycle** dan memakai **HST yang sama**.
- Penanaman setelah window 3 hari membentuk **sub-blok baru** dan **cycle/HST baru**.
- Blok/sub-blok adalah unit **spasial**; cycle adalah unit **waktu**.
- Satu cycle dapat mencakup beberapa blok/sub-blok.
- Satu blok dapat memiliki beberapa sub-blok/cycle sepanjang waktu.

Contoh:

```text
Field
├── Block A
│   ├── Sub-block A1 → Cycle 1 → HST 0
│   └── Sub-block A2 → Cycle 2 → HST 0
└── Block B
    └── Sub-block B1 → Cycle 1 → HST 0
```

Jika Block B dan sebagian Block A ditanam dalam 3 hari yang sama, keduanya masuk **Cycle 1** dan memiliki HST yang sama. Jika sisa Block A ditanam 2 minggu kemudian, bagian itu menjadi **Sub-block A2** dengan **Cycle 2** dan HST baru.

## Scheduled Tasks Berbasis SOP

- Scheduled tasks **tidak** memakai recurrence daily/weekly/monthly.
- Admin menyusun **SOP modular**: nama langkah, activity type, offset HST, instruksi, dan bukti yang diminta.
- Saat cycle dimulai, sistem membuat task untuk setiap langkah SOP pada tanggal tanam + offset HST.
- Task terikat pada cycle dan blok/sub-blok.
- Petugas menyelesaikan task dengan membuat activity log yang terhubung ke task.
- Jika langkah tidak relevan, admin dapat menonaktifkan atau menandai task sebagai skip (future work).

## Dampak ke Field Management

- Field management harus menampilkan **blok dan sub-blok**, bukan hanya kebun.
- Setiap blok/sub-blok menampilkan cycle aktif, tanggal tanam, HST, dan task berikutnya.
- Block mapping tidak lagi sekadar label; block/sub-block perlu master data.
- Laporan dapat dikelompokkan per blok, sub-blok, atau cycle.
- Jangan menimpa blok saat penanaman parsial; buat sub-blok.

## Implementasi Minimal di Aplikasi

Backend saat ini memakai pola **batch create**: satu request membuat header sekaligus satu atau banyak aktivitas block mapping. Untuk form individual, pola yang lebih cocok adalah **create once, append later**.

Catatan: halaman yang sudah ada memakai route web Inertia `/kebun/{farm}/daily-logs`. Contoh `/api/...` di bawah adalah bentuk konseptual; route JSON saat ini dibatasi untuk local/testing.

Alur yang disarankan:

1. Aktivitas pertama hari itu:

```http
POST /api/kebun/{farm}/daily-logs
```

Body berisi header (tanggal dan waktu) + satu aktivitas. Server membuat `daily_activity_logs` dan `farm_activities` sekaligus.

2. Aktivitas berikutnya:

```http
POST /api/kebun/{farm}/daily-logs/{dailyLog}/activities
```

Body hanya berisi data aktivitas. Server menambahkan `farm_activities` baru dengan `daily_activity_log_id` yang sama.

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

createActivity(farm, user, activity):
    log = findTodayLog(farm, user, today)
    if log is null:
        log = createDailyLog(header)
    return log.activities.create(activity)
```

Catatan implementasi:

- `recorded_by` diambil dari user yang login, bukan input form.
- Saat ini endpoint create hanya menerima `block_mapping`; endpoint append perlu dibuat untuk kategori lain.
- Jika aktivitas baru berada di luar `start_time`/`end_time`, perbarui `end_time` atau buat header baru.
- Jumlah pekerja disimpan per aktivitas, bukan di header.
- Untuk export logbook, group `farm_activities` berdasarkan `daily_activity_log_id`.
- Scheduled task/SOP belum termasuk implementasi minimal ini.
- Jika aktivitas berasal dari scheduled task, simpan relasi task → activity log saat completion.

---

## Field Umum per Aktivitas

Field ini muncul untuk semua aktivitas. `Subkategori` hanya wajib jika kategori yang dipilih memang memiliki subkategori.

| Field             | Type    | Unit  | Wajib       | Catatan                                                                                |
| ----------------- | ------- | ----- | ----------- | -------------------------------------------------------------------------------------- |
| Kategori          | select  |       | Ya          | Dari daftar kategori di bawah                                                          |
| Subkategori       | select  |       | Kondisional | Wajib jika kategori punya subkategori; untuk Penanaman/Panen/Insiden tidak ditampilkan |
| Blok / sub-blok   | select  |       | Ya          | Pilih master blok/sub-blok; belum tersedia di backend                                  |
| Jumlah pekerja    | integer | orang | Ya          | Dapat berbeda antar aktivitas                                                          |
| Foto aktivitas    | file    |       | Ya          | Minimal 1 foto; bisa lebih dari satu                                                   |
| Catatan aktivitas | text    |       | Tidak       | Untuk detail yang tidak masuk field baku                                               |

Field `Jumlah pekerja`, `Foto`, dan `Catatan` tersedia untuk semua aktivitas lewat field umum. `Foto` wajib minimal satu.

Catatan:

- **Luas area tidak dimasukkan ke field umum** karena artinya berbeda per subkategori: luas tergarap, luas terdampak, luas panen, dan sebagainya.
- **Hasil juga tidak dimasukkan ke field umum** karena bentuknya berbeda: angka, teks, atau kualitas.
- Aktivitas tidak mencatat kendala, solusi, atau tindakan. `Tindakan segera` hanya ada di form insiden dan opsional.
- `HOK` dihapus dari form karena tidak dipakai lagi.
- `Paraf/approval` bukan input pekerja; ini bagian dari alur persetujuan setelah aktivitas dikirim.

---

# 0. Pemetaan dan Pengukuran

## 0.1 Pemetaan Blok

| Field                   | Type           | Unit  | Wajib    | Catatan                                                                                                      |
| ----------------------- | -------------- | ----- | -------- | ------------------------------------------------------------------------------------------------------------ |
| Nama blok               | text           |       | Ya       | Label blok, bukan master data baru. Field `Blok/Sub-blok` di field umum tidak dipakai untuk subkategori ini. |
| Panjang                 | number (float) | m     | Ya       |                                                                                                              |
| Lebar                   | number (float) | m     | Ya       |                                                                                                              |
| Luas                    | number (float) | m²    | Otomatis | Panjang × lebar; bisa dikoreksi                                                                              |
| Jarak antar baris       | number (float) | cm    | Tidak    | Row spacing. Contoh logbook: 40 cm atau 75 cm                                                                |
| Jarak dalam baris       | number (float) | cm    | Tidak    | Plant spacing. Contoh logbook: 20 cm                                                                         |
| Jumlah baris per bedeng | integer        | baris | Tidak    | Logbook mencatat 2 atau 3 baris per bedeng                                                                   |

Catatan: logbook menulis jarak tanam sebagai `40 x 20 cm` dan `75 x 20 cm`. Perlu dikonfirmasi apakah urutannya `jarak antar baris x jarak dalam baris`.

## 0.2 Pengukuran Tanah

| Field               | Type           | Unit  | Wajib | Catatan                 |
| ------------------- | -------------- | ----- | ----- | ----------------------- |
| Jumlah titik sampel | integer        | titik | Tidak |                         |
| pH                  | number (float) |       | Tidak | 0–14                    |
| Kelembapan tanah    | number (float) | %     | Tidak | 0–100                   |
| Fertilitas / EC     | number (float) | µS/cm | Tidak |                         |
| Suhu tanah          | number (float) | °C    | Tidak |                         |
| Intensitas cahaya   | number (float) | lux   | Tidak | Opsional, alat spesifik |
| Kelembapan udara    | number (float) | %     | Tidak | 0–100                   |

Catatan: field ini hanya muncul jika pekerja memilih subkategori **Pengukuran Tanah**. Parameter lanjutan seperti cahaya dan kelembapan udara bisa dilipat, tetapi pH, kelembapan, dan EC sebaiknya terlihat karena itu inti pengukurannya.

## 0.3 Pemetaan Drainase

| Field                 | Type           | Unit | Wajib | Catatan          |
| --------------------- | -------------- | ---- | ----- | ---------------- |
| Panjang saluran       | number (float) | m    | Tidak |                  |
| Lebar saluran         | number (float) | m    | Tidak |                  |
| Kedalaman saluran     | number (float) | m    | Tidak |                  |
| Titik outlet          | text           |      | Tidak | Deskripsi lokasi |
| Kebutuhan pipa/selang | number (float) | m    | Tidak |                  |

---

# 1. Pembersihan Lahan

## 1.1 Penyemprotan Herbisida

| Field            | Type           | Unit    | Wajib | Catatan                   |
| ---------------- | -------------- | ------- | ----- | ------------------------- |
| Luas area        | number (float) | m² / Ha | Ya    | Pilih satuan              |
| Nama produk      | text / select  |         | Ya    | Dari master data saprotan |
| Bahan aktif      | text           |         | Tidak |                           |
| Dosis per tangki | number (float) | ml/L    | Ya    |                           |
| Volume air       | number (float) | liter   | Ya    |                           |
| Jumlah tangki    | integer        | tangki  | Ya    |                           |
| Metode           | select         |         | Ya    | Merata / spot / lainnya   |
| Target gulma     | text           |         | Tidak |                           |

## 1.2 Pembersihan Manual

| Field          | Type           | Unit    | Wajib | Catatan                   |
| -------------- | -------------- | ------- | ----- | ------------------------- |
| Luas area      | number (float) | m² / Ha | Ya    |                           |
| Metode         | select         |         | Ya    | Babat / cangkul / lainnya |
| Jenis vegetasi | text           |         | Tidak |                           |

---

# 2. Pengolahan Tanah

## 2.1 Penggemburan

| Field       | Type           | Unit    | Wajib       | Catatan                               |
| ----------- | -------------- | ------- | ----------- | ------------------------------------- |
| Luas area   | number (float) | m² / Ha | Ya          |                                       |
| Metode      | select         |         | Ya          | Manual / cultivator / lainnya         |
| Kedalaman   | number (float) | cm      | Ya          | Contoh: 10 cm                         |
| Bahan bakar | number (float) | liter   | Kondisional | Muncul jika metode = mesin/cultivator |

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

## 2.5 Pemasangan Mulsa

| Field         | Type           | Unit    | Wajib | Catatan                     |
| ------------- | -------------- | ------- | ----- | --------------------------- |
| Luas area     | number (float) | m² / Ha | Ya    |                             |
| Jenis mulsa   | select         |         | Ya    | Plastik / organik / lainnya |
| Lebar         | number (float) | m       | Ya    |                             |
| Panjang total | number (float) | m       | Ya    |                             |
| Metode        | select         |         | Ya    | Manual / mesin / lainnya    |

---

# 3. Penanaman

| Field           | Type           | Unit             | Wajib       | Catatan                                                              |
| --------------- | -------------- | ---------------- | ----------- | -------------------------------------------------------------------- |
| Varietas        | text / select  |                  | Ya          | Contoh: BISI 18                                                      |
| Jumlah benih    | number (float) | kg / sak / butir | Ya          | Pilih satuan                                                         |
| Ukuran sak      | number (float) | kg               | Kondisional | Muncul jika satuan = sak                                             |
| Pola tanam      | text / select  |                  | Otomatis    | Ambil dari pemetaan blok: jarak tanam, jarak baris, baris per bedeng |
| Luas area       | number (float) | m² / Ha          | Ya          |                                                                      |
| Kedalaman tanam | number (float) | cm               | Tidak       |                                                                      |
| Perlakuan benih | text           |                  | Tidak       | Contoh: seed treatment                                               |

Catatan:

- `Pola tanam` sebaiknya tidak diketik ulang. Ambil dari data pemetaan blok; tampilkan sebagai read-only dengan opsi override.
- Contoh `20 sak × 1 kg` perlu dikonfirmasi. Jika 1 sak = 1 kg, total benih hanya 20 kg; ini tidak wajar untuk 2,5 Ha. Kemungkinan ukuran sak salah.

---

# 4. Perawatan

## 4.1 Pengairan

| Field       | Type           | Unit       | Wajib       | Catatan                                     |
| ----------- | -------------- | ---------- | ----------- | ------------------------------------------- |
| Luas area   | number (float) | m² / Ha    | Ya          |                                             |
| Sumber air  | select         |            | Ya          | Sumur bor / irigasi / embung / lainnya      |
| Metode      | select         |            | Ya          | Irigasi parit / sprinkler / pompa / lainnya |
| Mesin pompa | select / text  |            | Kondisional | Muncul jika memakai pompa                   |
| Durasi      | number (float) | jam        | Ya          |                                             |
| Volume air  | number (float) | m³ / liter | Tidak       | Jika diukur                                 |
| Bahan bakar | number (float) | liter      | Kondisional | Muncul jika memakai mesin                   |

## 4.2 Pendangiran

| Field       | Type           | Unit    | Wajib       | Catatan                            |
| ----------- | -------------- | ------- | ----------- | ---------------------------------- |
| Luas area   | number (float) | m² / Ha | Ya          |                                    |
| Kedalaman   | number (float) | cm      | Tidak       |                                    |
| Metode      | select         |         | Ya          | Manual / cangkul / mesin / lainnya |
| Bahan bakar | number (float) | liter   | Kondisional | Muncul jika metode = mesin         |

## 4.3 Pemupukan Susulan

| Field            | Type           | Unit             | Wajib       | Catatan                                      |
| ---------------- | -------------- | ---------------- | ----------- | -------------------------------------------- |
| Luas area        | number (float) | m² / Ha          | Ya          |                                              |
| Fase pertumbuhan | select         |                  | Otomatis    | Dari HST sub-blok; boleh override jika perlu |
| Jenis pupuk      | select         |                  | Ya          | Urea / NPK / KCl / lainnya                   |
| Merek            | text           |                  | Tidak       |                                              |
| Jumlah           | number (float) | kg / sak / liter | Ya          |                                              |
| Ukuran sak       | number (float) | kg               | Kondisional | Muncul jika satuan = sak                     |
| Metode           | select         |                  | Ya          | Tabur / benam / kocor / lainnya              |

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
| Bahan aktif      | text           |         | Tidak |                                               |
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
| Catatan kualitas  | text           |          | Tidak | Warna, kematangan, kebersihan, atau catatan lain        |
| Metode panen      | select         |          | Tidak | Manual / mesin / lainnya                                |

Catatan:

- Sudah dikonfirmasi: panen jagung untuk pakan ternak dilakukan sebagai **seluruh tanaman**, termasuk jagung, dan tidak dipisah. `Berat hasil panen` adalah berat seluruh tanaman.
- Untuk komoditas lain, field kualitas panen bisa berbeda. Jangan hardcode field jagung ke semua komoditas.
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
| Foto                | file           |         | Ya    | Bukti visual insiden                                                   |

Catatan: `Tindakan segera` adalah field opsional khusus insiden, bukan field umum aktivitas. `Deskripsi` **bukan** field dengan satuan Ton. Ini kesalahan yang harus dihapus.

---

# 7. Infrastruktur dan Logistik

## 7.1 Pengangkutan Saprotan

| Field          | Type           | Unit                  | Wajib       | Catatan                                 |
| -------------- | -------------- | --------------------- | ----------- | --------------------------------------- |
| Jenis material | select / text  |                       | Ya          | Pupuk kandang / kapur / benih / lainnya |
| Jumlah         | number (float) | kg / sak / ton / unit | Ya          |                                         |
| Ukuran sak     | number (float) | kg                    | Kondisional | Jika satuan = sak                       |
| Dari           | text           |                       | Tidak       | Lokasi asal                             |
| Ke             | text           |                       | Ya          | Blok/lokasi tujuan                      |
| Kendaraan      | text / select  |                       | Tidak       | Truk / motor / lainnya                  |

## 7.2 Lainnya

Untuk pekerjaan jarang seperti drainase, embung, toren, atau tenda. Jika pekerjaan ini sering muncul, jadikan kategori tersendiri.

| Field     | Type           | Unit    | Wajib | Catatan      |
| --------- | -------------- | ------- | ----- | ------------ |
| Deskripsi | text           |         | Ya    | Wajib diisi  |
| Luas area | number (float) | m² / Ha | Tidak | Jika relevan |

---

## Aktivitas Tidak Rutin dan Penggunaan "Lainnya"

Tidak semua aktivitas perlu menjadi kategori baru. Bedakan:

| Aktivitas             | Frekuensi                     | Tempat yang disarankan           |
| --------------------- | ----------------------------- | -------------------------------- |
| Pemetaan blok         | Sekali per blok/siklus baru   | 0.1 Pemetaan Blok                |
| Pengukuran tanah      | Periodik atau sekali per blok | 0.2 Pengukuran Tanah             |
| Pemetaan drainase     | Sekali saat perencanaan       | 0.3 Pemetaan Drainase            |
| Pembuatan tenda       | Sekali/proyek                 | 7.2 Lainnya                      |
| Embung/toren          | Sekali/proyek                 | 7.2 Lainnya                      |
| Pengangkutan saprotan | Per siklus/logistik           | 7.1 Pengangkutan Saprotan        |
| Insiden               | Ad hoc                        | 6. Pelaporan Insiden             |
| Pekerjaan tak terduga | Ad hoc                        | 7.2 Lainnya                      |

Prinsipnya:

- **Jangan masukkan pemetaan dan pengukuran ke "Lainnya"** karena datanya spesifik dan perlu field terstruktur.
- **"Lainnya" hanya untuk pekerjaan yang benar-benar tidak terduga** dan tidak punya kategori yang cocok.
- **Jangan pakai "Lainnya" untuk aktivitas rutin** hanya karena malas menambah kategori; itu akan merusak rekap.
- Jika suatu aktivitas sering muncul di "Lainnya", itu tanda perlu dijadikan kategori/subkategori baru.

---

## Aturan Validasi

- Area harus > 0.
- Berat harus > 0.
- Persentase harus 0–100.
- Waktu selesai harus setelah waktu mulai.
- Jumlah pekerja per aktivitas minimal 1.
- Jika satuan = sak, ukuran sak wajib diisi dan > 0.
- Jika metode pengairan = pompa, mesin pompa wajib diisi.
- Jika bahan bakar diisi, satuannya liter.
- Jika insiden, deskripsi wajib diisi; `Tindakan segera` opsional.
- Jika panen, berat hasil panen wajib diisi.
- Foto minimal 1 per aktivitas.
- Subkategori wajib hanya jika kategori memiliki subkategori.
- Tanggal kerja tidak boleh di masa depan.
