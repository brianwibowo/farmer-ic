# Acuan Input Form Aktivitas

Dokumen ini adalah acuan untuk field input form aktivitas, bukan desain schema database.

## Keputusan model

- Activity log tetap berupa **aktivitas individual**, bukan rekap harian.
- Default: satu submit = satu aktivitas.
- Pengecualian: **block mapping boleh batch** — satu sesi pengukuran bisa mencatat beberapa blok sekaligus. Setiap blok tetap menjadi satu aktivitas individual.
- Beberapa aktivitas pada tanggal yang sama boleh memakai **header harian yang sama** agar pekerja tidak mengisi tanggal, waktu, dan jumlah pekerja berulang kali.
- Rekap berbentuk logbook harian adalah **future work**. Nanti data individual di-group berdasarkan tanggal dan header harian.
- HOK **tidak dipakai lagi** dan dihapus dari form.
- Foto dokumentasi **wajib minimal satu** per aktivitas.
- Paraf/approval adalah **post-activity**, bukan input pekerja.
- Satu tim lapangan berbagi satu akun petugas. Header harian karena itu terikat pada akun, farm, dan tanggal.
- Block mapping menyimpan label blok dan dimensi; belum ada tabel master `blocks`/`sub_blocks`.
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
- offline/draft queue
- tabel master blok/sub-blok

## Prinsip form

1. **Header minimal.** Cukup tanggal, waktu mulai/selesai, jumlah pekerja, dan catatan.
2. **Progressive disclosure.** Tampilkan field inti lebih dulu; field lanjutan dan input ada di bagian yang bisa dibuka.
3. **Auto-fill.** Luas blok, HST, dan pola tanam sebaiknya dihitung otomatis dan bisa dikoreksi.
4. **Satuan konsisten.** Default area m², berat kg, cairan liter, panjang cm/m. Konversi ke Ha/Ton hanya untuk laporan.
5. **Jelas mana yang wajib.** Field wajib ditandai; sisanya opsional.
6. **Jangan masukkan harga/biaya ke form pekerja** kecuali memang pekerja yang mencatat pembelian. Harga lebih cocok untuk admin/keuangan.
7. **Dropdown untuk data yang akan direkap.** Gunakan pilihan baku + "Lainnya" untuk kategori, fase, jenis pupuk, sumber air, mesin, dan kualitas panen.
8. **Foto wajib.** Minimal satu foto per aktivitas sebagai bukti pekerjaan. Foto diunggah saat submit; jika gagal, tampilkan opsi retry.
9. **Conditional fields.** Contoh: ukuran sak muncul hanya jika satuan = sak; mesin pompa dan bahan bakar muncul hanya jika metode = pompa.
10. **Offline ditunda.** Tidak ada draft queue atau sync lokal di MVP; form membutuhkan koneksi saat submit.

---

## Header Harian

Header ini mengikuti model backend yang sudah ada.

| Field          | Type    | Unit  | Wajib       | Catatan                                                       |
| -------------- | ------- | ----- | ----------- | ------------------------------------------------------------- |
| Tanggal kerja  | date    |       | Ya          | Tanggal aktual pekerjaan, bukan tanggal submit                |
| Waktu mulai    | time    |       | Ya          | Format HH:mm, zona Asia/Jakarta                               |
| Waktu selesai  | time    |       | Ya          | Harus setelah waktu mulai                                     |
| Jumlah pekerja | integer | orang | Ya          | Jumlah orang yang terlibat                                    |
| Catatan harian | text    |       | Tidak       | Catatan umum untuk hari itu                                   |
| Pelapor        | user    |       | Server-side | Diambil dari user yang login, tidak ditampilkan sebagai input |

Yang **tidak** perlu masuk header:

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
    - akun petugas yang sama (satu tim lapangan berbagi satu akun)
    - header yang masih terbuka

2. Jika ditemukan, tampilkan banner ringan:

    > **Melanjutkan log hari ini** — 28 Okt 2026, 08:00–16:00, 6 pekerja.
    > [Lanjutkan] [Ubah info] [Sesi baru]

3. Jika pekerja menekan **Lanjutkan**, aktivitas baru otomatis masuk ke header tersebut.
4. Jika aktivitas dilakukan di luar rentang waktu header, tampilkan pilihan:
    - perpanjang waktu selesai header, atau
    - buat sesi/header baru.
5. Jika tidak ada header hari ini, form membuat header baru.
6. Jika pekerja lupa dan membuat header kedua di hari yang sama, jangan blokir. Backend mengizinkan lebih dari satu header per hari; nanti bisa dibuat alat merge atau export per sesi.

Catatan kepemilikan header:

- Satu tim lapangan berbagi satu akun petugas, jadi header terikat pada `farm_id`, `recorded_by`, dan `work_date`.
- Karena akun yang sama bisa dipakai di lebih dari satu perangkat, dua header pada hari yang sama masih mungkin terjadi. Backend mengizinkan lebih dari satu header per hari; pilih yang terbaru atau tampilkan sebagai sesi terpisah.
- Mode offline/draft queue **ditunda** dan tidak termasuk MVP.

Catatan: untuk MVP, waktu header adalah rentang kerja harian. Jika aktivitas berikutnya dilakukan di luar rentang itu, cukup perpanjang `end_time` atau buat sesi baru. Waktu per aktivitas bisa ditambahkan nanti jika memang dibutuhkan.

## Implementasi Minimal di Aplikasi

Backend saat ini memakai pola **batch create**: satu request membuat header sekaligus satu atau banyak aktivitas block mapping. Untuk form individual, pola yang lebih cocok adalah **create once, append later**.

Catatan: halaman yang sudah ada memakai route web Inertia `/kebun/{farm}/daily-logs`. Contoh `/api/...` di bawah adalah bentuk konseptual; route JSON saat ini dibatasi untuk local/testing.

Alur yang disarankan:

1. Aktivitas pertama hari itu:

```http
POST /api/kebun/{farm}/daily-logs
```

Body berisi header + satu aktivitas. Server membuat `daily_activity_logs` dan `farm_activities` sekaligus.

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
- Jika jumlah pekerja berbeda jauh, tawarkan sesi baru.
- Untuk export logbook, group `farm_activities` berdasarkan `daily_activity_log_id`.

---

## Field Umum per Aktivitas

Field ini muncul untuk semua aktivitas. `Subkategori` hanya wajib jika kategori yang dipilih memang memiliki subkategori.

| Field             | Type   | Unit | Wajib       | Catatan                                                                                |
| ----------------- | ------ | ---- | ----------- | -------------------------------------------------------------------------------------- |
| Kategori          | select |      | Ya          | Dari daftar kategori di bawah                                                          |
| Subkategori       | select |      | Kondisional | Wajib jika kategori punya subkategori; untuk Penanaman/Panen/Insiden tidak ditampilkan |
| Blok / sub-blok   | select |      | Ya          | Perlu relasi blok; saat ini hanya `block_mapping` yang menyimpan nama blok             |
| Kendala           | text   |      | Tidak       | Boleh kosong                                                                           |
| Solusi            | text   |      | Tidak       | Boleh kosong                                                                           |
| Tindakan          | text   |      | Tidak       | Boleh kosong                                                                           |
| Foto aktivitas    | file   |      | Ya          | Minimal 1 foto; bisa lebih dari satu                                                   |
| Catatan aktivitas | text   |      | Tidak       | Untuk detail yang tidak masuk field baku                                               |

Field `Kendala`, `Solusi`, `Tindakan`, `Foto`, dan `Catatan` tersedia untuk semua aktivitas lewat field umum, sehingga tidak diulang di setiap tabel. `Foto` wajib minimal satu.

Catatan:

- **Luas area tidak dimasukkan ke field umum** karena artinya berbeda per subkategori: luas tergarap, luas terdampak, luas panen, dan sebagainya.
- **Hasil juga tidak dimasukkan ke field umum** karena bentuknya berbeda: angka, teks, atau kualitas.
- `Kendala`, `Solusi`, dan `Tindakan` mengikuti kolom logbook, tetapi tidak perlu diwajibkan untuk semua aktivitas.
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
| Tindakan segera     | text           |         | Ya    | Apa yang dilakukan saat itu                                            |
| Status              | select         |         | Tidak | Open / In Progress / Resolved                                          |
| Foto                | file           |         | Ya    | Bukti visual insiden                                                   |

Catatan: `Deskripsi` **bukan** field dengan satuan Ton. Ini kesalahan yang harus dihapus.

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

## 7.2 Drainase, Embung, dan Toren

| Field            | Type           | Unit   | Wajib | Catatan                                    |
| ---------------- | -------------- | ------ | ----- | ------------------------------------------ |
| Jenis pekerjaan  | select         |        | Ya    | Drainase / embung / toren / pipa / lainnya |
| Panjang / volume | number (float) | m / m³ | Tidak | Tergantung jenis pekerjaan                 |
| Material         | text           |        | Tidak |                                            |

## 7.3 Tenda dan Bangunan Sementara

| Field    | Type | Unit | Wajib | Catatan           |
| -------- | ---- | ---- | ----- | ----------------- |
| Ukuran   | text |      | Ya    | Contoh: 2 m × 3 m |
| Material | text |      | Tidak |                   |

## 7.4 Lainnya

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
| Pembuatan tenda       | Sekali/proyek                 | 7.3 Tenda dan Bangunan Sementara |
| Embung/toren          | Sekali/proyek                 | 7.2 Drainase, Embung, dan Toren  |
| Pengangkutan saprotan | Per siklus/logistik           | 7.1 Pengangkutan Saprotan        |
| Insiden               | Ad hoc                        | 6. Pelaporan Insiden             |
| Pekerjaan tak terduga | Ad hoc                        | 7.4 Lainnya                      |

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
- Jumlah pekerja minimal 1.
- Jika satuan = sak, ukuran sak wajib diisi dan > 0.
- Jika metode pengairan = pompa, mesin pompa wajib diisi.
- Jika bahan bakar diisi, satuannya liter.
- Jika insiden, deskripsi dan tindakan segera wajib diisi.
- Jika panen, berat hasil panen wajib diisi.
- Foto minimal 1 per aktivitas.
- Subkategori wajib hanya jika kategori memiliki subkategori.
- Tanggal kerja tidak boleh di masa depan.
