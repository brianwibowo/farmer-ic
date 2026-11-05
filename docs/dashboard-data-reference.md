# Referensi data dashboard admin

`GET /admin/dashboard` dijalankan oleh `DashboardController` dan membentuk
prop `dashboard` melalui `App\Services\AdminDashboardService`. Seluruh query
berjalan dengan `visibleTo($actor, $team)` dan pembatasan eksplisit ke ID kebun
dalam tenant terpilih, termasuk untuk Superadmin. Route tetap dilindungi
oleh `auth`, `verified`, dan Gate `admin.access`.

| Bagian dashboard | Tabel/model sumber | Kolom dan perhitungan |
| --- | --- | --- |
| Total kebun | `farms` / `Farm` | `COUNT(id)` dari kebun dalam scope. |
| Luas terkelola | `farms` / `Farm` | `SUM(total_area_ha)`. |
| Siklus aktif dan area aktif | `planting_cycles` / `PlantingCycle` | Baris `status = 'active'`; `COUNT(id)` dan `SUM(planted_area_ha)`. |
| Komoditas tertanam | `planting_cycles` → `crop_varieties` → `commodities` | Cycle dengan status `active` atau `harvested`, dikelompokkan per `commodities.id`; nilai kartu adalah `SUM(planting_cycles.planted_area_ha)`. Dashboard menampilkan maksimal tiga komoditas terbesar dan empty card bila data kurang dari tiga. |
| Blok terdaftar | `farm_blocks` / `FarmBlock` | Jumlah master blok dalam kebun terpilih; bukan jumlah laporan pemetaan. |
| Total sub-blok | `planting_cycles` / `PlantingCycle` | Jumlah siklus dengan planting_round_id, dalam kebun terpilih. |
| Risiko terbuka | `farm_risks` / `FarmRisk` | Risiko dengan `status` kosong atau bukan `resolved`/`closed`. |
| Tren aktivitas tujuh hari dan filter kebun | `farm_activities` / `FarmActivity`, `farms` / `Farm` | `COUNT(id)` per `activity_date`, dari hari ini mundur enam hari. Server mengirim seri global dan per `farm_id` dalam scope; filter dashboard memilih seri kebun tanpa meminta atau menyimpan data baru di browser. |
| Kebun aktif minggu ini | `farm_activities` / `FarmActivity` | `COUNT(DISTINCT farm_id)` dengan `activity_date` dalam tujuh hari terakhir. |
| Aktivitas lapangan | `farm_activities` / `FarmActivity` | Hingga 30 aktivitas terbaru: `activity_date`, `description`, `status`, `performed_by`; relasi `activity_types.name`, `farms.name`, dan `users.name`. |
| Daftar/filter petugas | `users`, `roles`, `farm_activities` | User aktif dengan `roles.name = 'petugas'` dalam scope yang sama; jumlah dan tanggal terakhir berasal dari agregasi `COUNT(*)`/`MAX(activity_date)` pada aktivitas yang dapat dilihat. Filter di halaman menyaring kumpulan aktivitas hasil query tersebut berdasarkan `performed_by`. |
| Tugas harian | `operational_targets` / `OperationalTarget` | Hingga delapan baris berdasarkan `target_date`; memakai `task_name`, `status`, `target_date`, serta relasi `farm.name` dan `creator.name`. |
| OPEX bulan berjalan | `financial_transactions` / `FinancialTransaction` | `SUM(amount)` ketika `transaction_type = 'expense'` dan `transaction_date` berada pada bulan kalender saat ini. |
| Proyeksi pemasukan, biaya, dan bersih | `financial_projections` / `FinancialProjection` | Hanya periode yang mencakup hari ini (`period_start <= hari ini <= period_end`). Nilai: `SUM(projected_income)`, `SUM(projected_expense)`, dan selisihnya. |
| Denah kebun | `farms` / `Farm` | Nama/lokasi: `name`, `city`; ukuran: `total_area_ha`; posisi: `latitude`, `longitude`. |

## Batasan denah saat ini

Tabel `farms` hanya menyimpan satu titik lokasi (`latitude`, `longitude`) dan
luas (`total_area_ha`), bukan polygon atau koordinat batas blok seperti
`x1/y1/x2/y2`. Dashboard menampilkan titik tersebut melalui Leaflet pada peta
OpenStreetMap. Memang ada `block_mapping_details` yang menyimpan `block_name`,
`length_m`, dan `width_m` melalui aktivitas pencatatan blok, tetapi belum
memiliki titik asal/geometri dan belum menjadi pembagian spasial kebun yang
permanen. Karena itu marker peta bukan batas kadaster atau polygon blok.

Untuk peta batas lahan yang presisi, domain perlu menambahkan tabel/model blok
atau polygon (misalnya `farm_blocks` dengan `farm_id` dan GeoJSON/pasangan titik
koordinat), migration, policy, scope `visibleTo`, serta editor peta. Jangan
menganggap denah dashboard saat ini sebagai batas kadaster.

## Pemilihan tenant dashboard (4 November 2026)

Superadmin menggunakan tenant aktif yang tersimpan pada `current_team_id`.
Admin tetap memakai satu tenant non-personal yang sah sesuai batas akses
existing. Superadmin tanpa tenant aktif mendapat 403. Pemilihan tenant dashboard
bukan pengurangan izin global Superadmin: Gate::before, access.global, dan akses
langsung ke record tenant lain tetap berlaku.

Service terlebih dahulu membatasi Farm dengan visibleTo dan farms.team_id,
lalu memakai subquery ID kebun tersebut untuk aktivitas, blok, siklus, risiko,
target, transaksi, proyeksi, komoditas, grafik, dan peta. Pembatasan dilakukan
sebelum limit, agregasi, dan serialisasi. Petugas dibatasi ke keanggotaan tenant
tersebut; filter kebun juga membatasi penugasannya. Transaksi tanpa kebun tidak
masuk ringkasan tenant. Tenant tanpa kebun mengembalikan aktivitas/peta kosong,
angka dan keuangan nol. Petugas aktif yang menjadi anggota tenant tetap dapat
terdaftar meskipun belum ditugaskan ke kebun.

Filter farm_id yang masih menunjuk tenant sebelumnya dikosongkan dalam props
response ketika tidak terdapat pada pilihan kebun tenant aktif. Pemeriksaan
Policy controller tetap berlaku; actor biasa tetap mendapat 403 pada record
yang tidak boleh dilihat. Ringkasan langsung membaca database, termasuk
FarmActivity yang dibuat oleh logbook; tidak ada fallback JSON atau browser
storage. Route dashboard tetap terlindungi dan semua query baca yang disentuh
memakai visibleTo serta batas tenant eksplisit. Modul lain di luar perubahan
ini tidak diaudit ulang.
