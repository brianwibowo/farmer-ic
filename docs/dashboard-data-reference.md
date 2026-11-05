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

## Beranda Owner dan tabel lahan (5 November 2026)

`GET /` melalui HomeController memeriksa Owner admission, Farm.viewAny dan
filter kebun, lalu memakai OwnerReadService. Ringkasan tetap berasal dari
AdminDashboardService, dengan tambahan prop `landTable` untuk tabel live.
Semua pembacaan terkait menggunakan visibleTo dan kebun dalam tenant aktif
sebelum agregasi atau serialisasi. Owner hanya membaca data.

| Kolom | Sumber dan makna |
| --- | --- |
| Site / lahan | farms.name, tautan `/kebun/{id}` ke detail Owner. |
| Luas (ha) | farms.total_area_ha; total adalah SUM dengan scope/filter yang sama, bukan data contoh. |
| Legalitas | farms.land_tenure: Milik sendiri, Sewa, Kerja sama, atau nilai tersimpan. Belum ada field institusi TNI/POLRI; nilai kosong tidak direka. |
| Jenis tanaman | Siklus active/harvested → varietas → komoditas, sama dengan definisi komoditas tertanam Admin. |
| Progress | Jumlah tahap completed/seluruh tahap pada batch planned/active, nama tahap in_progress, dan jumlah batch berjalan. Bukan persentase pertumbuhan tanaman. |
| Resume | Satu aktivitas terbaru per kebun beserta jenis/deskripsi, tanggal, status dan tautan `/aktivitas/{id}`. Jenis yang tidak boleh dilihat tetap null. |

Filter farm_id dan perpindahan tenant memperbarui tabel, total dan ringkasan
bersama. Periode 7/30 hari hanya memengaruhi grafik aktivitas; resume tetap
laporan terakhir yang memiliki timestamp sendiri. Tidak ada perubahan role,
izin mutasi, schema, atau sumber AI. Rute/kueri yang disentuh tetap diproteksi;
modul lain tidak diaudit ulang.

### Verifikasi perubahan Owner

- 90 tes backend / 4.307 assertion lolos: Owner, dashboard Admin, pemilihan
  tenant dan authorization. Termasuk perubahan Farm melalui endpoint Admin
  yang terbaca pada permintaan Owner berikutnya, aggregate total terfilter,
  satu resume terakhir per kebun, data terkait yang dicabut izinnya, dan
  penolakan tautan/filter tenant lain.
- Build, Vue typecheck, lint frontend, Pint dan PHPStan terarah lolos.
- QA browser menggunakan akun Owner dan data database existing: site membuka
  detail Owner yang benar; total 12,5 ha sesuai ringkasan Admin. Periode 30 hari,
  filter kebun, pencarian tanpa hasil dan reset aktivitas berfungsi.
- Di portrait 375×812, tabel digeser 459 px: Site dan header pertama tetap
  pada posisi kiri yang sama. Di landscape 844×390, konten tabel digulir
  vertikal sementara offset header tetap 1 px; frame tetap 440 px dan halaman
  tidak meluber. Header juga diperiksa pada 320×568.
- Tema terang/gelap diperiksa. Header Owner tidak lagi berisi tenant selector;
  selector tersedia di Lainnya. Toggle tema berada sebelum lonceng dan hanya
  OwnerLayout yang mengaktifkan prop tersebut, sehingga portal Petugas dan
  alur profil/AI mempertahankan perilakunya.

## Responsivitas Owner untuk tablet, laptop, dan TV

OwnerLayout mengaktifkan prop adaptif pada shell yang sama; halaman, tabel,
peta, dan state filter tidak dirender dua kali. Ponsel di bawah 640px tetap
menggunakan tampilan sebelumnya. Pada 640–1023px canvas melebar untuk tablet,
dengan navigasi bawah. Mulai 1024px tersedia navigasi header dan layout desktop;
lebar maksimal 2240px membatasi baris pada layar TV. Shell lain, AI, profil,
query database dan otorisasi tidak berubah.

Dashboard menggunakan 3 kolom metrik mulai tablet, card penanaman 2 kolom tablet / 3 kolom desktop,
dan tabel/grafik berdampingan mulai 1280px. Kolom tabel desktop proporsional,
dengan header/Site tetap sticky. Aktivitas memakai satu DOM list dengan grid
2–3 kolom desktop. Tidak ada pengenalan perangkat berbasis user-agent.

Verifikasi browser pada 5 November 2026:

- 14 viewport: 320×568, 390×844, 430×932, 844×390, 640×960,
  768×1024, 1023×768, 1024×768, 1280×800, 1366×768, 1440×900,
  1920×1080, 2560×1440, dan 3840×2160. Tidak ada overflow horizontal
  pada halaman/main; overflow tabel tetap lokal pada layar sempit.
- Setiap ukuran hanya memiliki satu tabel dan satu instance Leaflet.
  Navigasi bawah tampil sebelum 1024px, navigasi header mulai 1024px.
- Pada ponsel, tabel berhasil digeser horizontal 444px dan Site tetap di
  posisi kiri. Pada landscape 844×390, header tetap di posisi atas saat
  scroll vertikal 44px. Tautan Site membuka detail kebun yang sesuai.
- Navigasi Lainnya/Beranda, filter periode 30 hari, refresh dengan feedback
  sukses, pencarian aktivitas kosong dan reset filter berfungsi. Tema
  terang/gelap desktop tidak menimbulkan overflow; 18 tile peta terisi.
- Pemeriksaan console browser tidak menemukan warning/error. Perubahan
  ini tidak mengubah backend, permission, AI, atau formulir profil.
