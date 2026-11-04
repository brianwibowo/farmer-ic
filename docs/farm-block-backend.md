# Backend blok dan batch persiapan

Implementasi 30 Oktober 2026 menggunakan Laravel MVC + Service, sesi login,
Policy native, dan Inertia. Tidak ada endpoint API eksternal baru. Halaman
minimal disediakan agar backend dapat digunakan langsung melalui form Inertia.

## Alur yang tersedia

1. Admin atau Petugas PIC membuat master blok dalam kebunnya. `land_clearing_started_at` diisi
   server; Land Clearing langsung berlangsung, termasuk bila blok disimpan
   nonaktif untuk sementara.
2. Petugas pada kebun tugasnya atau Admin membuat subblok/batch dengan alokasi
   luas. Label kosong mendapat A, B, ..., Z, AA, dan seterusnya.
3. Batch pertama menyelesaikan Land Clearing blok otomatis, tanpa persetujuan
   Admin. Tanggal selesai ini tidak berubah saat batch berikutnya dibuat.
4. Satu batch disimpan pada `planting_cycles`, bukan tabel subblok permanen.
   Enam baris `planting_cycle_stages` dibuat dalam transaksi yang sama:
   `land_clearing` completed, `soil_preparation` in_progress, empat sisanya upcoming.
   Land Clearing batch merujuk waktu persiapan bersama dari blok; tahap berikutnya
   memiliki timeline masing-masing. Tidak ada persentase progres buatan.
5. Batch persiapan berstatus siklus `planned`, luas realisasi 0, tanggal tanam
   realisasi dan varietas belum diisi. Tanggal rencana tanam
   (`planned_planting_at`) boleh diisi saat pembuatan batch dan menjadi acuan
   HST sementara untuk jadwal tugas; nilainya tetap berlabel rencana sampai
   realisasi tanam dicatat. Alokasi luas bukan realisasi tanam. Siklus lama
   mempertahankan data dan ID-nya, tanpa blok/periode rekaan.

Nomor batch **berurutan per kebun**, dilindungi unique key dan lock kebun.
Periode dikelompokkan per blok. Batch berikutnya memakai periode aktif yang sama
selama masih ada batch planned/active. Jika seluruh batch periode tersebut sudah
terminal (harvested/cancelled), pembuatan berikutnya menutup periode lama dan
membuat periode baru. Nomor batch berlanjut, sedangkan label A/B dapat dipakai
lagi. Ini menetapkan asumsi draft sebelumnya untuk implementasi awal.

Modul ini belum menyediakan aksi menyelesaikan tahap berikutnya, mengajukan atau
menyetujui task SOP, realisasi tanam, panen, pembatalan batch, atau pengulangan
Land Clearing pada periode berikutnya. Endpoint status bebas tidak ditambahkan.
Penyelesaian seluruh panen harus dibedakan dari laporan panen sebagian ketika
modul panen diimplementasikan; satu laporan tidak boleh otomatis menutup batch.

## Route dan halaman

Petugas membaca blok pada farm penugasannya dengan `farm_blocks.view = own`.
Grant `farm_blocks.create/update/delete = own` juga mensyaratkan `isPic()` pada
farm penugasan melalui FarmBlockPolicy. Farm target tetap harus sesuai penugasan
dan keanggotaan tenant. Petugas non-PIC tetap hanya membaca master blok; aturan
batch terpisah tidak berubah. Admin tetap CRUD dalam tenant, Owner hanya baca,
dan Superadmin memakai bypass yang sudah ada.

Seluruh route memakai `auth` dan `verified`. Semua aksi controller memeriksa
Gate sebelum service; hasil baca memakai `visibleTo` sebelum serialisasi,
pagination, jumlah dan total. Parameter block harus milik farm di URL.

| Method | Route | Hasil |
| --- | --- | --- |
| GET | `/blocks` | Pilih kebun yang terlihat dalam konteks tim aktif |
| GET | `/kebun/{farm}/blocks` | Daftar, pencarian, filter status, pagination dan ringkasan |
| GET | `/kebun/{farm}/blocks/create` | Form tambah |
| POST | `/kebun/{farm}/blocks` | Simpan dan redirect detail |
| GET | `/kebun/{farm}/blocks/{block}` | Detail, periode dan timeline batch |
| GET | `/kebun/{farm}/blocks/{block}/edit` | Form edit |
| PUT/PATCH | `/kebun/{farm}/blocks/{block}` | Edit informasi/status, redirect detail |
| DELETE | `/kebun/{farm}/blocks/{block}` | Hapus hanya jika tanpa histori |
| POST | `/kebun/{farm}/blocks/{block}/batches` | Buat batch persiapan, redirect detail |

Halaman: `farmer/blocks/Farms`, `Index`, `Form`, `Show`. Layout bersama
`BlockLayout` mempertahankan profil Farmer pada desktop/mobile, menyediakan
pilihan tema eksplisit, dan menggunakan komponen `BatchTimeline`.
Detail kebun Admin mendapat tautan **Buka blok kebun**. Petugas/Owner dapat
membuka `/blocks`; halaman demo kebun publik belum diganti dengan direktori ini.

## Batas akses

| Resource | Owner | Admin | Petugas | Superadmin |
| --- | --- | --- | --- | --- |
| Master blok | Baca dalam tenant terkait | CRUD dalam tenant | Baca kebun tugas; CRUD hanya PIC kebun tugas | Semua |
| Periode | Baca | Baca; buat otomatis bersama batch | Baca; buat otomatis pada kebun tugas | Semua |
| Batch persiapan | Baca | Buat sesuai izin planting_cycles existing | Buat pada kebun tugas | Semua |
| Timeline | Baca | Baca | Baca kebun tugas | Semua |

Prefix baru: `farm_blocks`, `planting_rounds`, `planting_cycle_stages`.
Parent create FarmBlock adalah Farm; parent PlantingRound adalah FarmBlock.
PlantingCyclePolicy menerima Farm existing atau FarmBlock baru. ResourcePolicy
hanya menambahkan resolusi FarmBlock -> farm sebagai parent terpercaya.
Timeline dibuat internal sebagai konsekuensi pembuatan batch, bukan endpoint
konfigurasi terpisah. Round ditutup internal hanya ketika seluruh batch terminal.
Tidak ada hak update/delete periode atau timeline bagi Admin/Petugas.

`created_by`, farm, periode, nomor batch dan status awal ditetapkan server.
Payload pengganti identitas/status ditolak. Izin `block_mapping_details.*`
tetap hanya mengatur laporan pemetaan; tidak memberi izin mengedit master blok.
Seeder merupakan entry point non-HTTP dan memeriksa `Gate::forUser` terlebih dahulu.

## Kapasitas, integritas dan histori

- Kode kapital unik per kebun, termasuk blok nonaktif. Luas menggunakan numeric
  PostgreSQL dan cast decimal, bukan penjumlahan float PHP.
- Total luas blok aktif harus muat dalam luas kebun. Saat edit luas kebun,
  FarmService memeriksa luas blok dan alokasi siklus berjalan.
- Total alokasi batch planned/active lintas periode harus muat dalam blok.
  Blok dengan batch berjalan tidak dapat dinonaktifkan atau diperkecil sampai
  alokasi tidak muat. Aktivasi ulang mengecek kapasitas kebun lagi.
- Siklus legacy active tanpa blok tetap memakai kapasitas kebun. Total luas
  legacy tersebut ditambah alokasi batch baru harus muat dalam kebun. Karena
  itu data demo lama dengan 8,5 ha aktif di kebun 12,5 ha menyisakan 4 ha untuk
  batch baru, sampai ada rekonsiliasi atau penutupan yang sah.
- Service mengunci kebun sebelum blok, termasuk sebelum mengalokasikan nomor.
  Constraint/trigger PostgreSQL memeriksa kapasitas, kesesuaian kebun, urutan
  stage, enam stage per batch baru, satu stage berjalan, serta tanggal/status.
  Pemeriksaan integritas fisik sengaja mencakup semua referensi yang terkait,
  tidak dibatasi permission atau filter daftar aktor.
- Perhitungan luas belum membuktikan batas geografis tidak tumpang tindih.
- Blok dengan histori periode/batch atau laporan pemetaan tidak dapat dihapus
  biasa. FK restrict menjaga histori. Penonaktifan tetap memungkinkan pembacaan.
- Flow force delete kebun yang **sudah ada dan khusus Superadmin** mengenali
  blok, periode dan stage dalam preview/confirmation graph dan transaksi delete.
  Implementasi ini tidak menjalankan force delete atau reset database aplikasi.

`block_mapping_details.block_id` opsional. Pada logbook, backend memeriksa blok
terlihat dan milik kebun laporan. Nama/dimensi hasil ukur tetap snapshot; tidak
ikut berubah ketika master blok diedit. Tidak ada pencocokan nama otomatis
untuk data lama. Input pilihan blok di UI logbook menjadi integrasi lanjutan;
backend sudah menerima ID opsional dengan validasi tersebut.

## Instalasi dan seed

Migration baru:
`2026_10_30_000001_create_farm_blocks_and_preparation_batches.php`.
Untuk menambahkan hanya modul ini ke database existing, periksa koneksi dan
migration pending, lalu jalankan migration spesifik setelah mendapat izin:

```powershell
php artisan migrate --database=pgsql --path=database/migrations/2026_10_30_000001_create_farm_blocks_and_preparation_batches.php
```

Seeder `DemoFarmBlockSeeder` ditambahkan pada DatabaseSeeder setelah
DemoFarmSeeder. Bisa dijalankan terpisah setelah identitas dan kebun demo ada:

```powershell
php artisan db:seed --class=DemoFarmBlockSeeder
```

Seeder hanya local/testing, idempotent dan tidak menimpa blok existing.
Membuat B01 Blok Utara 6 ha dan B02 Blok Selatan 6,5 ha pada kebun demo.
Tidak membuat batch atau mengarang hubungan siklus existing. Jangan menjalankan
`migrate:fresh` pada database aplikasi untuk memasang fitur ini.

## Batas integrasi authorization di luar modul

Route baru seluruhnya terlindungi; query baca baru menggunakan visibleTo.
Halaman demo publik existing `/`, `/kebun`, `/kebun/detail`, `/kebun/aktivitas`,
`/aktivitas`, `/rincian-aktivitas`, dan `/laporan` tetap belum menjadi halaman
operasional terbatasi per pengguna. Route GET `/{current_team}/ai` existing
hanya memakai auth/verified/team membership; pemeriksaan ai.access untuk halaman
itu belum ditambahkan oleh pekerjaan blok ini. Endpoint aksi AI tetap mengikuti
implementasinya yang sudah ada. Tidak dilakukan audit/perubahan menyeluruh pada
query dan route modul lain.

## Kapasitas dan satuan input batch (2 November 2026)

Detail blok menampilkan sisa alokasi blok dan kebun, pemakaian tanam aktif di
luar batch, serta maksimal batch baru (nilai terkecil dari kedua sisa).
Pesan penolakan menyebut batas yang terlampaui dan angka kapasitasnya.

Form batch menerima `area_value` dan `area_unit` (`ha`/`m2`). Konversi server
menyimpan nilai pada `reserved_area_ha`, dengan presisi 1 m² = 0,0001 ha.
Input ha menerima koma/titik desimal; m² harus bulat tanpa pemisah ribuan.
Payload lama `reserved_area_ha` tetap didukung, tetapi tidak boleh dicampur
dengan pasangan input baru. Validasi server, lock, dan constraint tetap berlaku.

Untuk instalasi demo baru, `DemoFarmSeeder` juga memanggil
`DemoFarmBlockSeeder`: kebun 12,5 ha memiliki B01 10 ha dan B02 2,5 ha.
B01 memiliki batch aktif 4,25 ha (fase pemeliharaan), B02 memiliki batch planned
1,25 ha (pengolahan tanah). Setiap batch memiliki periode dan enam tahap.
Tidak ada siklus legacy pada instalasi baru. Logbook, pengamatan, dan finance
tetap menggunakan siklus aktif yang sudah terhubung ke blok/batch.

Pengulangan seeder mempertahankan blok dan siklus existing. Bila ditemukan
siklus lama tanpa periode/blok, seeder memberi peringatan dan tidak menambahkan
batch demo di atasnya. Ini bukan migrasi atau reset data aplikasi lama.

## Hasil verifikasi implementasi awal

- 92 pengujian terkait lulus, 3.866 assertions: modul blok, dua worker bersamaan,
  matriks Policy semua model, halaman kebun, force delete kebun, finance,
  serta logbook harian. Database test terpisah dan dilindungi TestDatabase.
- Uji konkurensi memastikan kedua worker benar-benar menunggu lock kebun:
  pada sisa kapasitas 3 ha, hanya satu permintaan alokasi 2 ha yang berhasil;
  permintaan lainnya mendapat error kapasitas, tanpa duplikasi nomor batch.
- Analisis PHPStan pada kode backend yang berubah lulus. Analisis seluruh project
  masih menemukan 14 masalah existing di AdminDashboardService (properti hasil
  agregat dan tipe tanggal/nullability); file tersebut tidak diubah di task ini.
- Frontend build, TypeScript, lint, dan format PHP lulus. Halaman direktori,
  daftar, form, dan detail diperiksa pada tema terang/gelap; layout diuji pada
  lebar 375, 390, 430, 768 dan 1280 piksel tanpa overflow horizontal. Validasi
  browser kode/label memakai pola karakter yang sesuai standar input HTML.
- Browser preview menggunakan database `farmer_ic_crud_test_blocks_preview`:
  form Admin, validasi kapasitas, login Petugas, pembuatan dua batch, transisi
  otomatis, loading, feedback, dan persistence setelah reload diperiksa.
- Database aplikasi belum dimigrasikan: peninjauan otomatis menolak perintah
  penerapan migration karena belum ada izin eksplisit mengubah database aplikasi.
  Migration modul blok siap dijalankan setelah izin tersebut diberikan. Dua
  migration lahan existing juga masih pending; keduanya bukan perubahan file
  dalam pekerjaan ini.

## Dialog dan edit sub blok (3 November 2026)

Konfigurasi blok tersedia langsung dari detail kebun melalui tabel dan dialog.
URL form lama tetap berfungsi memakai komponen form bersama. Payload dialog
`return_to=farm` hanya memilih tujuan tetap detail kebun, bukan URL bebas.
Daftar tersemat, kapasitas, dan status penghapusan memakai `visibleTo` sebelum
serialisasi. Semua aksi tulis tetap melewati Policy pada controller.

Detail blok menampilkan seluruh tahap, tanggal mulai, dan tanggal selesai setiap
sub blok tanpa tombol pembuka progres. Form tambah dan ubah sub blok berada dalam
dialog, dengan validasi luas, konversi ha/m², dan indikator penyimpanan.

`PUT /kebun/{farm}/blocks/{block}/batches/{batch}` memeriksa kecocokan parent
terpersisten lalu `Gate::authorize('update', $batch)`. Admin/Superadmin dan Petugas
dalam cakupannya menggunakan grant `planting_cycles.update` existing; Owner tetap
read-only. Tidak ada perubahan matrix role. Edit hanya untuk batch planned dalam
blok aktif, tanpa realisasi tanam dan tanpa snapshot jadwal SOP. Field yang dapat
diubah adalah label, alokasi luas, dan rencana tanggal tanam. Nomor batch, periode,
status, tahap, dan tanggal progres dipertahankan.

Service mengunci kebun, blok, lalu batch. Reservasi batch yang sedang diedit tidak
ikut dikurangkan dua kali; reservasi lain dan tanam legacy tetap membatasi luas.
Label unik dalam periode yang sama. Aksi yang mengubah tahap, menghapus histori,
atau menjadwalkan ulang SOP tidak ditambahkan oleh revisi ini.

Semua route baru terlindungi dan query baca baru memakai scope. Batas integrasi
route publik/AI yang dicatat di bagian sebelumnya tetap di luar cakupan revisi;
pekerjaan ini bukan audit ulang seluruh route dan query aplikasi.
