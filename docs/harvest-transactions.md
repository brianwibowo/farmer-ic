# Transaksi panen (admin)

Halaman admin `/admin/transaksi-panen` adalah ledger operasional baca-saja
untuk laporan realisasi panen. Ini bukan transaksi keuangan dan tidak menambah
tabel transaksi kedua. Panen bertahap tetap menjadi beberapa laporan pada
`farm_activities` dan `harvest_details`.

## Sumber data

- `farm_activities` dengan `activity_types.code = 'harvest'`
- `harvest_details` untuk luas panen, berat kotor, kadar air, warna, kematangan,
  dan kebersihan hasil
- `planting_cycles` untuk batch/subblok, varietas, dan luas tanam
- `crop_varieties` dan `commodities`
- `farm_blocks` untuk blok
- `daily_activity_logs` untuk tautan logbook harian
- `activity_photos` untuk bukti foto privat

Semua query memakai `visibleTo` dan dibatasi ke kebun dalam tenant aktif.
Angka ringkasan dihitung dari query yang sama sebelum pagination, sehingga
filter aktif ikut memengaruhi total laporan, luas panen, dan berat panen.

## Filter dan kolom

Filter: pencarian bebas, kebun, blok, varietas, rentang tanggal, dan ukuran
halaman. Pencarian mencakup kebun, kode/nama blok, subblok, varietas, nama
Petugas, dan catatan.

Kolom utama: tanggal laporan, kebun, blok, batch/subblok, varietas/komoditas,
luas panen, berat kotor, catatan, foto, dan tautan ke logbook harian. Pelaksana
tetap tersimpan pada `farm_activities.performed_by`, sumber laporan pada
`planting_cycle_task_id`, dan kualitas hasil pada `harvest_details`, tetapi
ketiganya belum ditampilkan di ledger.

## Batas akses

Route berada di grup `admin` (`can:admin.access`). Controller juga memeriksa
`viewAny` untuk `FarmActivity`, `HarvestDetail`, dan `ActivityPhoto`.
Petugas dan Owner tidak dapat membuka halaman ini. Tidak ada aksi tulis,
ekspor, atau perubahan data pada halaman ini.
