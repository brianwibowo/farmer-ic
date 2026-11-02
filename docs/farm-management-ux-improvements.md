# Perbaikan UX Farm Management

2 November 2026. Melanjutkan temuan pada `farm-management-ux-review.md`.

## Perubahan

- Alamat memakai pencarian kecamatan dengan konteks kota/kabupaten dan provinsi,
  lalu dua dropdown induk. Kota disabled sebelum kecamatan dipilih, provinsi
  disabled sebelum kota dipilih. Mengganti kecamatan mereset kedua pilihan
  berikutnya. Pencarian mendukung keyboard, debounce, pembatalan request lama,
  loading, hasil kosong, dan retry. Mengetik tanpa memilih hasil tidak dapat
  disimpan sebagai pilihan wilayah.
- Master `administrative_regions` menyimpan kode, nama, level, dan parent_code.
  `farms.district_code` adalah referensi nullable ke master tersebut. Alamat lama
  tidak di-backfill berdasarkan kemiripan nama; alamat yang belum cocok tetap
  dipertahankan pada edit yang tidak mengubahnya. Data demo baru menggunakan kode
  jika master sudah diisi; rerun tidak memaksa pemetaan alamat legacy.
- Payload dropdown menyertakan kode kecamatan dan ketiga nama. Backend memeriksa
  level kecamatan dan relasi induknya, menolak kode tak dikenal atau kombinasi
  yang tidak sesuai. Kontrak API lama tanpa `district_code` tetap didukung untuk
  kompatibilitas klien lama; migrasi klien tersebut ke payload berkode diperlukan
  untuk mendapatkan validasi relasi yang sama. Menyediakan `district_code: null`
  bersama nama baru tidak melewati validasi kontrak dropdown.
- Filter daftar kota/provinsi menggunakan pilihan dari kebun yang visible untuk
  actor/team, bukan isian bebas yang menuntut ejaan persis. Reset juga mencakup
  sort, arah, dan ukuran halaman. Statistik mobile diringkas tanpa menghilangkan
  satuan; filter tambahan dapat dibuka saat diperlukan.
- Detail/edit kebun menjelaskan blok aktif, reservasi tanam, realisasi aktif,
  dan batas minimum. Form blok menampilkan kapasitas maksimum/minimum dan jumlah
  batch berjalan sebelum submit. Angka blok dan batch tidak dijumlahkan sebagai
  luas lahan yang berbeda; guard transaksi server tetap berlaku.
- Ringkasan error menerima fokus setelah gagal simpan, menyediakan tautan ke
  field, dan mempertahankan inline error serta draft. Form kebun/blok/batch
  memperingatkan ketika perubahan yang belum disimpan akan ditinggalkan.
- Owner yang tidak terlihat diberi pilihan “Pertahankan pemilik saat ini · akses
  terbatas”; frontend tidak mengirim ulang owner_id yang tidak diubah. Tidak ada
  perluasan UserPolicy atau bocoran identitas owner.
- Dialog hapus kebun memuat pemeriksaan penghalang melalui endpoint terotorisasi
  sebelum tombol akhir aktif. Detail blok menonaktifkan hapus ketika ada histori
  yang terlihat. Preview adalah bantuan UX; pemeriksaan integritas penuh di
  transaksi penghapusan tetap menjadi penentu, termasuk record tersembunyi.
- Halaman pemilihan kebun, daftar blok, form blok, dan detail subblok/batch memakai
  FarmManagementLayout ketika shared navigation grant mengizinkan Farm Management.
  Sidebar, header, palet, judul, input admin-field, tombol, dan dialog mengikuti
  Admin. Menu Manajemen Kebun tetap aktif pada route blok. Profil Farmer tetap
  digunakan untuk Petugas/Owner yang tidak mendapat navigasi Admin tersebut.
  Logbook menyediakan jalur kembali ke detail kebun sesuai grant yang sama.
- Ikon informasi pada kedua angka kapasitas membuka tooltip dengan rumus dan
  contoh blok/batch. Mendukung hover, fokus keyboard, klik/tap, Escape, dan klik
  di luar. Target ikon 44 px; warna portal mengikuti profil Admin/Farmer.
- Panel batch menempatkan kapasitas dan form berdampingan pada desktop lebar,
  lalu berurutan pada mobile. Form blok mengikuti lebar form kebun dan statistik
  daftar blok memakai tiga kolom ringkas yang tetap menampilkan satuan.
- Logbook menawarkan blok aktif yang visible dari kebun saat ini dan mengirim
  block_id. “Tambah kegiatan pemetaan” membedakannya dari membuat master blok.
  block_name tetap snapshot pengukuran; laporan lama tidak ditulis ulang.
- Warna error/success dark dan foreground tombol batch diperbaiki dalam scope
  Farmer block, tanpa mengganti palet Admin/Farmer secara global. Timeline
  menampilkan tahap aktif terlebih dahulu dan enam tahap dapat diperluas.
- Halaman menjelaskan bahwa penyelesaian tahap, realisasi/panen, dan pembatalan
  batch belum tersedia. Review UX-05 ditangani dengan penjelasan batas fitur;
  lifecycle operasional baru **belum diimplementasikan**.

## Dataset dan pemasangan

Snapshot [cahyadsn/wilayah](https://github.com/cahyadsn/wilayah) dari commit
`d68e8d5516f969d1905d0b2940f20034becb0db7`, file `db/wilayah.sql`, disaring menjadi
38 provinsi, 514 kota/kabupaten, dan 7.285 kecamatan. Ini sumber komunitas yang
merujuk data Kemendagri, bukan API resmi pemerintah. CSV, metadata commit,
checksum, dan lisensi MIT disertakan di `database/data/wilayah/`.

Seeder memverifikasi checksum, memasukkan parent sebelum child, dan melakukan
upsert tanpa menghapus kode referensi. Frontend tidak menghubungi penyedia luar
saat form dibuka. DatabaseSeeder mengisi master sebelum demo kebun.

Untuk database PostgreSQL aplikasi yang sudah dikonfigurasi:

```sh
php artisan migrate --path=database/migrations/2026_11_02_000001_create_administrative_regions.php
php artisan db:seed --class=AdministrativeRegionSeeder
npm run build
```

Migrasi bersifat aditif; tidak mereset farm, batch, logbook atau alamat lama.
Koneksi CLI biasa pada sesi ini terdeteksi SQLite dengan konfigurasi PostgreSQL
default `laravel`, sehingga **migrasi aplikasi utama tidak dijalankan dengan
menebak target**. Migrasi/seeder serta integrasi diuji pada database PostgreSQL
pengujian yang dijaga namanya. Tidak ada perubahan `.env`.

## Authorization

Model AdministrativeRegion memiliki native Policy dan visibleTo. Grant
`administrative_regions.view = any` diberikan kepada owner/admin/petugas sebagai
referensi bersama yang tetap memerlukan membership; tidak ada grant mutasi baru
bagi application roles. Gate::before Superadmin tidak berubah.

- GET `/admin/farm-regions`: auth/verified/Admin portal + region viewAny;
  pencarian dibatasi 25 hasil dan minimal dua karakter. API Resource membentuk
  hasil lookup tanpa query/authorization di dalam resource.
- GET `/admin/farm-management/{farm}/deletion-check`: auth/verified/Admin portal
    - farm delete Policy. Foreign-team request mendapat 403.
- Opsi wilayah filter, kapasitas, opsi blok logbook, dan preview visible memakai
  scope actor/team. Semua aksi simpan/hapus memakai controller Gate dan guard
  backend yang sudah ada. Tidak ada endpoint baru yang dibiarkan tanpa guard.
- FormRequest melakukan early rejection melalui Policy yang sama ketika payload
  memuat district_code, agar request lintas tenant mendapat 403 sebelum lookup
  wilayah dapat menghasilkan 422. Controller tetap mengotorisasi sebelum service;
  alasan pengecekan awal ganda ini dicatat dalam kode.
- Ini tidak mengaudit ulang seluruh route di luar flow tersebut.

## Menjalankan Impeccable

Launcher global memiliki mode 0644 sehingga pemanggilan langsung gagal dengan
exit 126. Membaca launcher lewat `sh` tidak memerlukan executable bit. Setelah
akses unduh engine resmi tersedia, launcher memverifikasi checksum dan
`engine-probe` mengembalikan `impeccable-engine 0.1.18`.

Cara yang sudah berhasil dalam sesi ini, tanpa mengubah instalasi global:

```sh
IMPECCABLE_HOME=/private/tmp/farm-impeccable sh /Users/alwierdi/.codex/skills/impeccable/scripts/impeccable engine-probe
IMPECCABLE_HOME=/private/tmp/farm-impeccable sh /Users/alwierdi/.codex/skills/impeccable/scripts/impeccable context --target resources/js/pages/farms/Form.vue
```

Cache `/private/tmp` dapat dibersihkan oleh sistem. Untuk penggunaan permanen,
pemilik file dapat memperbaiki permission launcher dan menjalankan setup standar:

```sh
chmod u+x /Users/alwierdi/.codex/skills/impeccable/scripts/impeccable
/Users/alwierdi/.codex/skills/impeccable/scripts/impeccable engine-probe
```

Perintah kedua membutuhkan internet dan izin menulis cache `~/.impeccable` jika
engine belum tersedia. Tidak memerlukan `sudo`, permission 777, atau paket npm.
Permission global belum diubah oleh pekerjaan ini. Shipped engine dan skill
memiliki nomor versi terpisah; engine 0.1.18 bukan nomor versi SKILL.md 4.8.0.

## Verifikasi

- Suite farm/blok/logbook/model authorization/seeder finance: 116 tes lulus,
  4.076 assertions pada run akhir backend. Pengujian mencakup penolakan 403
  sebelum validasi wilayah dan integritas FK referensi yang masih digunakan.
- TypeScript, lint/format target, PHPStan target backend, dan build berhasil.
- Detector Impeccable statis berjalan pada target UI yang berubah dan
  menghasilkan `[]`. Ini bukan klaim overlay/browser detector berjalan.
- Browser membuktikan pencarian dua Sukamakmur dengan konteks berbeda, pemilihan
  keyboard, dependensi disabled, reset pilihan induk, simpan alamat, persistensi
  setelah reload, edit dengan owner terbatas, pemulihan error, dan preview
  penghalang hapus. Penghapusan final tidak dilakukan.
- Data uji/preview tersimpan pada database khusus, bukan database operasional.
  Screenshot `improved-*` dan matriks viewport disimpan pada folder artefak sesi.
  Matriks Admin mencakup sepuluh halaman pada lebar 375, 390, 430, 768, dan
  1440 px, light/dark: 100 kombinasi tanpa overflow horizontal. Daftar blok dan
  batch Petugas diperiksa lagi pada 390/1440 px dalam dua tema: delapan kombinasi
  dengan profil Farmer tetap aktif. Hasil ada di farm-ux-improved-checks.json.
- Browser membuktikan pergantian 50.000 m² menjadi 5 ha, timeline enam tahap
  dapat dibuka, sidebar Admin tetap aktif, dan tidak ada tema Farmer bersarang
  di shell Admin. Detector statis ulang pada perubahan shell juga menghasilkan [].
- Halaman/layout blok secara eksplisit tidak mewariskan shared page props
  sebagai atribut DOM, memperbaiki warning Vue pada halaman dengan fragment root.
  Setelah perbaikan, kunjungan ulang daftar/form/edit/detail blok tidak mencatat
  warning/error baru. Pemeriksaan browser memakai Vite development server yang
  sudah berjalan; build produksi diverifikasi secara terpisah dan lulus.
- Tooltip diperiksa pada 20 kombinasi Admin (dua ikon, lima lebar, dua tema)
  dan empat kombinasi Petugas; seluruh popup berada di dalam viewport. Enter,
  Escape, klik di luar, dan tooltip periode Dashboard yang lama juga diverifikasi.
- Kontras foreground tombol batch Admin dark menggunakan warna dari tema bersama
  (rgb 6,78,59 pada rgb 110,231,183); koreksi Farmer tetap dibatasi scope-nya.

Tidak ada paket Composer/npm baru, perubahan baseline farmer_ic, commit, atau
reset database aplikasi. Build masih mengeluarkan peringatan fallback font opsional
dan ukuran chunk
yang sudah ada; profiling performa dan uji sentuh perangkat fisik di luar hasil ini.

## Contoh kapasitas: kebun 20 ha, dua blok 10 ha

Pembagian dua blok menghabiskan kapasitas pembuatan blok baru (20 - 10 - 10 =
0 ha). Ini berbeda dari kapasitas batch. Jika batch berjalan baru memesan 10 ha
di salah satu blok dan tidak ada tanam aktif legacy, sisa kapasitas batch seluruh
kebun adalah 20 - 10 = 10 ha. Blok yang belum memiliki batch masih dapat menerima
batch sampai 10 ha. Jika belum ada batch di kedua blok, kapasitas batch kebun
seharusnya 20 ha. Screenshot pengguna menunjukkan farm_batches_ha 10 ha; record
operasional utama yang membentuk jumlah tersebut belum dicocokkan pada sesi ini.
Logika tidak diubah hanya berdasarkan jumlah/luas blok.

## Halaman, komponen, dan asumsi

Halaman berubah: resources/js/pages/farms/{Index,Form,Show}.vue,
resources/js/pages/farmer/blocks/{Farms,Index,Form,Show}.vue, dan
resources/js/pages/farmer/DailyActivityLog.vue. Komponen utama berubah/ditambahkan:
FarmRegionFields, FormErrorSummary, DeleteFarmDialog, MetricInfoTooltip,
BlockLayout, dan AppSidebar. Theme Farmer dibatasi dalam farm-block-styling.css.

Asumsi: profil Admin mengikuti shared navigation grant backend; pembagian blok
dan reservasi batch adalah dua batas kapasitas berbeda sesuai service yang ada.
Tidak ada bagian screenshot terbaru yang sengaja dihilangkan. Referensi angka
20 ha pada tooltip diberi label Contoh, bukan data kebun saat ini. Lifecycle
operasional batch yang belum tersedia tetap dijelaskan pada halaman.
