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

## Responsivitas Owner untuk tablet, laptop, dan TV (versi sebelum revisi Proyeksi)

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

## Revisi Beranda dan navigasi Proyeksi (5 November 2026)

Beranda sekarang menampilkan Total lahan dan Luas lahan dari `landTable.total`
serta `landTable.total_area_ha`, tabel Ringkasan lahan selebar konten, lalu
pie Komoditas di kiri dan Peta lahan di kanan mulai 768px (bertumpuk pada layar
lebih sempit). Total lahan adalah jumlah record Farm dalam scope dan filter
aktif, bukan jumlah blok/subblok. Tabel mempertahankan header dan Site sticky,
tautan site/resume dan total hektar. `Lihat detail` di kanan atas tabel menuju
`/proyeksi`. Filter kebun dan refresh tetap aktif; filter periode dan panel
aktivitas/siklus/tahap/batch tidak lagi ditampilkan pada Beranda.

Pie memakai seluruh `dashboard.commodities`, berdasarkan `areaValue` (jumlah
planted_area_ha pada siklus active/harvested), dengan nama/ha/persentase sebagai
legend. OwnerReadService memanggil summary dengan `commodityLimit: null`,
sedangkan Admin tetap menggunakan default tiga komoditas. Query visibleTo,
scope tenant dan farm filter tidak berubah. Komoditas planned/cancelled atau
tenant lain tetap tidak masuk. Nol/tidak ada luas tanam menghasilkan empty
state. Persentase terhadap total luas tanam komoditas, bukan total luas Farm.

Navigasi Owner menjadi Beranda, Proyeksi, Lainnya. Proyeksi memakai route
`/proyeksi` (`owner.projection`), action `OwnerController::projection`, dan
halaman Inertia `owner/Projection`. Ringkasan lahan dan navigasi membuka halaman
yang sama; filter Cycle/Tahun tetap memakai URL tersebut. Controller memeriksa
policy Farm sebelum `HarvestProjectionService` membaca data melalui `visibleTo`
dan batas tenant aktif. Grup route tetap `auth`, `verified`, `can:owner.access`.
Keuangan disembunyikan dari Lainnya. Route/layanan AI dan
keuangan tetap dipertahankan, bersama formulir profil dan navigasi Petugas.
Tidak ada perubahan grant maupun query/rute baru yang tidak diproteksi.

Proyeksi per cycle menggunakan total luas Farm (ha) × 40 ton untuk Jagung,
× 100 ton untuk Rumput, dan × 45 ton untuk Sorgum. Satu Farm dihitung sekali
per jenis tanaman aktif, meskipun memiliki beberapa batch tanaman yang sama.
Proyeksi tahun mengalikan hasil per cycle dengan 3, 5, dan 3 secara berurutan.
Card dan tabel memakai kalkulator backend yang sama. Path lama
`/kebun/proyeksi` tidak didaftarkan sebagai halaman terpisah.

Verifikasi revisi Beranda sebelum integrasi data halaman Proyeksi:

- 47 tes backend / 1.445 assertion lolos (Owner, dashboard dan pemilihan
  tenant), termasuk akses `/proyeksi`, distribusi lima komoditas Owner,
  Admin tetap tiga komoditas, filter kebun dan penolakan data tenant lain.
- Smoke render SFC pie asli: 5 kasus lolos, mencakup satu komoditas 100%,
  komposisi 6/4 menjadi 60%/40%, lima sektor, serta dataset kosong/nol.
  Tidak ada output NaN/Infinity.
- Browser memeriksa 11 viewport dari 320×568 sampai 3840×2160, termasuk
  tablet portrait dan ponsel landscape. Dua metrik, satu tabel, satu pie dan
  satu peta; tidak ada overflow horizontal halaman. Pie/peta berdampingan
  mulai 768px, dengan urutan komoditas lalu peta pada layar sempit.
- Lihat detail dan navigasi Proyeksi membuka placeholder dengan status aktif
  yang sesuai. Menu Lainnya tidak menampilkan AI/Keuangan. Site membuka
  detail kebun Owner; filter farm_id dan refresh berhasil. Freeze Site tetap
  pada offset 0px setelah scroll horizontal 444px.
- Tema terang/gelap, lint, Vue typecheck dan build lolos. Browser tidak
  mencatat warning/error; build masih memberi peringatan ukuran chunk besar
  yang sudah ada pada modul lain. Tidak ada perubahan formulir profil.

## Penyempurnaan visual Komoditas (5 November 2026)

Komoditas kini berupa donut dengan total luas tanam di tengah, persentase pada
irisan minimal 8%, serta ringkasan komoditas terbesar. Legenda memakai tombol
berupa baris yang konsisten, badge warna/inisial (centang ketika dipilih),
badge persentase dan bar proporsi. Pilihan menyorot irisan dan memperbarui
ringkasan tengah; `Tampilkan total` menghapus pilihan. Preview mouse/fokus
bersifat sementara, dan pilihan yang hilang akibat filter dibersihkan.
Tidak ada perubahan pada data, perhitungan backend, profil atau AI.

Lint, Vue typecheck dan build lolos. Smoke render SFC asli mencakup enam kasus:
distribusi 5 komoditas/19 ha, satu komoditas 100%, pembagian 60/40, kosong,
nol, dan persentase sangat kecil tanpa NaN/Infinity. Browser diperiksa pada
320, 390, 430, 768, 844 (landscape), 1024, 1440 dan 2560px; tidak ada overflow
halaman/card, dan tombol legenda memiliki tinggi minimal 68px. Pilihan melalui
keyboard/sentuhan, reset, tema terang/gelap, serta filter Sukamaju (satu Padi,
5 ha, 100%) berfungsi. Pilihan Jagung dibersihkan ketika berpindah ke filter
tersebut. Browser tidak mencatat warning/error.

## Ringkasan lahan pada peta dan identitas Hijauan (5 November 2026)

Marker pada Beranda Owner membuka dialog dengan nama/lokasi/luas, peta satelit
lahan terpilih, jenis tanaman beserta varietas, PIC yang ditugaskan, dan tautan
Detail kebun. Kondisi tanaman dan proyeksi pendapatan tidak ditampilkan pada
dialog. Jenis tanaman berasal dari baris tabel yang sama. PIC memakai
`farms.pic_user_id` yang memiliki penugasan Petugas aktif pada lahan dan tenant
tersebut; PIC kosong dinyatakan apa adanya, tanpa mengambil pengguna lain.
Perubahan payload hanya berlaku untuk Owner dan mempertahankan scope/filter.

Array lahan pada dialog stabil dan inisialisasi Leaflet memiliki nomor generasi:
proses lama tidak boleh membuat/menghapus peta milik proses terbaru. Ini
mengatasi kegagalan buka pertama yang sebelumnya baru pulih setelah Coba lagi.
Marker mempunyai target 44px dan mendukung Enter/Space. Header/footer dialog
tetap terlihat; bagian tengah dapat digeser dengan sentuhan atau keyboard.
Escape mengembalikan fokus ke marker. Peta Owner menyesuaikan bingkai ketika
ukuran container berubah.

Logo resmi disimpan di `public/images/logo-hijauan.jpeg` dan dipakai pada
header, sidebar, autentikasi, startup, ikon browser, email, dan laporan. Warna
asli JPEG dipertahankan pada kedua tema. Nama tampilan dan teks sumber telah
dibersihkan dari label Demo. Data lama diperbarui dengan
`PresentationNamesSeeder`, sementara seed baru menghasilkan nama bersih.
Petunjuk perintah dan kompatibilitas identitas teknis ada pada
`docs/demo-seed-credentials.md`.

Verifikasi akhir:

- 62 tes Owner/dashboard, 2.068 assertion lulus pada PostgreSQL terpisah;
  meliputi scope lintas tenant, PIC yang tidak sah, kompatibilitas Admin,
  pembaruan nama idempoten, serta penjagaan akun dan nilai operasional.
- 78 tes AI, 349 assertion lulus, termasuk judul sumber lama/baru.
  Tes frontend AI juga lulus 38/38. Dataset tetap disebut sebagai referensi.
- Pembaruan database lokal menjaga fingerprint seluruh field selain teks
  yang diminta: akun/kredensial/relasi/tanggal/angka tetap, enam lahan
  berjumlah 44,5 ha dan komoditas tertanam tetap 19 ha.
- Browser: buka pertama dan buka-tutup enam lahan tanpa Coba lagi, navigasi
  detail sesuai lahan, pemulihan fokus, dan hanya satu peta setelah dialog
  ditutup. Portrait 320/390/430px, tablet 768px, landscape 844×390 dan desktop
  1440px diperiksa; dialog muat pada viewport dan scroll tengah berfungsi.
  Tema terang/gelap dan logo berfungsi; Beranda/Lainnya tanpa label Demo.
- Vue typecheck dan build lulus. Peringatan chunk besar Laporan yang sudah
  ada tetap muncul. Tidak ada perubahan grant, route, atau formulir profil.
- Pemeriksaan akhir lint frontend, Pint dan PHPStan pada service/seeder yang
  diubah lulus. Unit referensi AI diulang setelah penyesuaian tipe dokumen:
  10 tes / 48 assertion lulus.
