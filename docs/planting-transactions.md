# Transaksi penanaman (admin)

Halaman admin `/admin/transaksi-penanaman` adalah ledger operasional baca-saja
untuk laporan realisasi penanaman. Ini bukan transaksi keuangan dan tidak
menambah tabel transaksi kedua.

## Sumber data

- `farm_activities` dengan `activity_types.code = 'planting'`
- `planting_details` untuk luas tanam, jumlah benih, nama benih, dan pola tanam
- `planting_cycles` untuk batch/subblok dan varietas
- `crop_varieties` dan `commodities`
- `farm_blocks` untuk blok
- `daily_activity_logs` untuk tautan logbook harian
- `activity_photos` untuk bukti foto privat

Semua query memakai `visibleTo` dan dibatasi ke kebun dalam tenant aktif.
Angka ringkasan dihitung dari query yang sama sebelum pagination, sehingga
filter aktif ikut memengaruhi total laporan, luas, dan benih.

## Filter dan kolom

Filter: pencarian bebas, kebun, blok, varietas, rentang tanggal, dan ukuran
halaman. Pencarian mencakup kebun, kode/nama blok, subblok, varietas, nama
benih, nama Petugas, dan catatan laporan.

Kolom utama: tanggal laporan, sumber (`Realisasi tanam` atau `Tugas SOP`),
kebun, blok, batch/subblok, varietas/komoditas, luas tanam, jumlah benih,
nama benih, pola tanam, jumlah pekerja, pelaksana, catatan, foto, dan tautan
ke logbook harian.

## Batas akses

Route berada di grup `admin` (`can:admin.access`). Controller juga memeriksa
`viewAny` untuk `FarmActivity`, `PlantingDetail`, dan `ActivityPhoto`.
Petugas dan Owner tidak dapat membuka halaman ini. Tidak ada aksi tulis,
ekspor, atau perubahan data pada halaman ini.
