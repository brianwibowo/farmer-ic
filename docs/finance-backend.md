# Backend finance

Modul finance memakai model dan skema PostgreSQL yang sudah tersedia, tanpa
perubahan migration atau penambahan paket. Controller mengotorisasi melalui
Policy, Form Request memvalidasi masukan, `FinanceService` mengelola transaksi
database dan query `visibleTo`. Controller mengirim props halaman Vue melalui
`Inertia::render()`; Resource yang sudah ada dipakai untuk membentuk data props.
Finance tidak mengambil data bisnis melalui API eksternal atau endpoint JSON.

Halaman yang berubah: `resources/js/pages/farmer/Finance.vue`. Halaman baru:
`FinanceRecords.vue` dan `FinanceRecordDetail.vue` di folder yang sama.
Komponen baru: `resources/js/components/farmer/FinanceNavigation.vue` untuk
navigasi berbasis izin dan `FinanceRecordForm.vue` untuk form keempat resource.
Tampilan memakai shell Farmer dan komponen UI proyek yang sudah ada. Tidak ada
wireframe baru; susunan ringkasan/grafik sebelumnya dipertahankan dan pengelolaan
data ditambahkan melalui menu Finance.

## Akses dan route

Semua URL di bawah diawali `/finance`. Tim diperoleh dari relasi `currentTeam`
pengguna yang login, bukan input browser. Route berada langsung di `web.php`
dengan middleware `auth` dan `verified`, sesi Laravel, serta perlindungan CSRF.

Alur baca: route → Controller → Gate/Policy → FinanceService →
`Inertia::render()` → props Vue. Filter dan pagination menggunakan
`router.get()`. Form menggunakan `useForm().post/patch/delete()`, lalu Controller
mengembalikan redirect dan flash notifikasi. Tidak ada pemanggilan fetch/axios
untuk Finance dan tidak ada file route finance terpisah yang perlu di-include.

| Method | Path | Fungsi |
| --- | --- | --- |
| GET | `/` | Halaman arus kas dengan ringkasan, grafik, dan transaksi dari database |
| GET | `/transactions` | Daftar transaksi terfilter dan terpaginasikan |
| GET | `/transactions/{transaction}` | Detail transaksi dan `can.update/delete` |
| POST | `/farms/{farm}/transactions` | Catat transaksi pada kebun tepercaya |
| PUT/PATCH | `/transactions/{transaction}` | Ubah transaksi |
| DELETE | `/transactions/{transaction}` | Hapus transaksi |
| GET | `/transactions/export` | Ekspor CSV sesuai filter dan cakupan akses |
| GET/POST | `/categories` | Daftar/buat kategori |
| GET/PUT/PATCH/DELETE | `/categories/{category}` | Detail/ubah/hapus kategori |
| GET | `/budgets` | Daftar anggaran |
| POST | `/farms/{farm}/budgets` | Buat anggaran kebun |
| GET/PUT/PATCH/DELETE | `/budgets/{budget}` | Detail/ubah/hapus anggaran |
| GET | `/projections` | Daftar proyeksi |
| POST | `/farms/{farm}/projections` | Buat proyeksi kebun |
| GET/PUT/PATCH/DELETE | `/projections/{projection}` | Detail/ubah/hapus proyeksi |

UUID yang tidak valid/tidak ditemukan menghasilkan 404. Penolakan Policy atau
ketidakcocokan dengan tim aktif menghasilkan 403. Validasi kembali melalui session
errors dan ditampilkan oleh form Inertia. Kategori yang masih dipakai tidak dapat
dihapus; pesannya juga ditampilkan sebagai error form. Mutasi berhasil melakukan
redirect ke daftar resource, sehingga halaman membaca ulang data database.

Aturan matriks yang sudah ada tetap berlaku: owner membaca, admin mengelola
sesuai batas tim, petugas tidak memiliki akses finance, dan superadmin memakai
bypass pengembang. Query tetap dibatasi tim aktif, termasuk untuk superadmin.
Kategori merupakan katalog bersama lintas tim sesuai Policy yang sudah ada;
perubahan katalog berlaku untuk seluruh pengguna katalog, bukan hanya tim aktif.

## Data form

Contoh objek di bawah menunjukkan field yang dikirim `useForm` ke route web
Laravel, bukan kontrak API eksternal.

Transaksi:

```json
{
  "category_id": "<UUID kategori income>",
  "planting_cycle_id": null,
  "transaction_date": "2026-10-28",
  "transaction_type": "income",
  "amount": "7250000.15",
  "description": "Penjualan hasil panen jagung"
}
```

Kategori:

```json
{"name": "Pembelian benih", "transaction_type": "expense"}
```

Anggaran:

```json
{
  "category_id": null,
  "period_start": "2026-10-01",
  "period_end": "2026-12-31",
  "budget_amount": "6500000.00"
}
```

Proyeksi:

```json
{
  "planting_cycle_id": null,
  "period_start": "2026-10-01",
  "period_end": "2027-01-31",
  "projected_income": "48500000.00",
  "projected_expense": "17800000.00"
}
```

- `farm_id` berasal dari route saat membuat data, dan tidak dapat dipindahkan
  melalui update. ID, tim, pencatat/pembuat, serta timestamp merupakan milik server.
- Siklus tanam opsional harus dapat dilihat pengguna dan berasal dari kebun yang
  sama. Kategori harus tersedia dalam cakupan baca; jenis kategori harus cocok
  dengan jenis transaksi. `null` dapat melepas hubungan opsional.
- Nilai uang memakai maksimal 16 digit sebelum titik dan 2 digit desimal.
  Transaksi harus positif; anggaran/proyeksi boleh nol. Gunakan string desimal
  untuk menjaga nilai besar saat mengirim form. Props uang selalu string dan
  tampilan nominal menggunakan pemformatan desimal tanpa kehilangan presisi.
- Akhir periode harus sama dengan atau setelah awal periode, termasuk pada
  PATCH yang hanya mengubah satu tanggal. Proyeksi memerlukan sedikitnya satu
  nilai pemasukan/pengeluaran; nilai yang tidak diketahui tetap `null`.
- `calculated_at` mencatat waktu server menyimpan proyeksi manual. Modul ini
  tidak mengklaim menghasilkan prediksi otomatis dari model agronomi.
- PUT mensyaratkan semua field wajib, PATCH hanya field yang diubah. Field
  opsional yang tidak dikirim dipertahankan; kirim `null` untuk mengosongkannya.
- Jenis kategori yang sudah dipakai transaksi tidak boleh diubah. Referensi
  transaksi dan anggaran dijaga saat kategori akan dihapus.

## Filter dan hasil

Semua daftar mendukung `page`, `per_page` (1–100), `sort`, dan `direction`.

| Resource | Filter |
| --- | --- |
| Transaksi, ringkasan, CSV | `farm_id`, `category_id`, `planting_cycle_id`, `commodity`, `transaction_type`, `from_date`, `to_date`, `search` pada deskripsi |
| Kategori | `transaction_type`, `search` pada nama |
| Anggaran | `farm_id`, `category_id`, `from_date`, `to_date` |
| Proyeksi | `farm_id`, `planting_cycle_id`, `from_date`, `to_date` |

Tanggal berformat `YYYY-MM-DD`. Batas transaksi inklusif; anggaran/proyeksi
diikutkan jika periodenya beririsan dengan rentang filter. Pencarian `%` dan `_`
bersifat literal. Kolom sort transaksi: `transaction_date`, `amount`, `created_at`;
kategori: `name`, `transaction_type`; anggaran: `period_start`, `period_end`,
`budget_amount`, `created_at`; proyeksi: `period_start`, `period_end`,
`projected_income`, `projected_expense`, `calculated_at`.

Daftar dikirim sebagai paginator pada prop `records` dengan `data`, `total`,
`current_page`, dan tautan pagination. Prop `options` berisi pilihan kebun,
kategori, dan siklus yang terbatasi cakupan. Setiap baris memiliki `can.update`
dan `can.delete`; izin navigasi dibagikan server satu kali per request.

Halaman `/finance` menerima props `summary`, `transactions`, `farms`,
`commodities`, `filters`, `periodLabel`, dan `exportUrl`. Filter `year` dan
`period` (Q1–Q4/YTD) menentukan rentang ringkasan; YTD tahun berjalan berakhir
pada tanggal hari ini. Prop ringkasan memiliki `source = database`, `currency = IDR`, `income`,
`expense`, `net`, `transaction_count`, dan `monthly`. Ringkasan mencakup seluruh
hasil filter, bukan halaman paginator saja. Bulan tanpa transaksi tidak dibuat
sebagai data sintetis. CSV memakai filter/cakupan yang sama dan melindungi
deskripsi yang dapat ditafsirkan spreadsheet sebagai formula.

## Seeder, verifikasi, dan batas integrasi

`DatabaseSeeder` sudah memanggil `DemoFinanceSeeder` setelah identitas dan kebun.
Tidak perlu migration baru untuk modul ini. Jalankan seeder hanya pada database
demo sesuai [petunjuk seeder](demo-seed-credentials.md).

```powershell
php vendor/phpunit/phpunit/phpunit tests/Feature/Finance tests/Feature/Authorization
php vendor/bin/phpstan analyse --memory-limit=512M
php vendor/bin/pint --test
npm.cmd run build
```

Tes integrasi menggunakan `Tests\Support\TestDatabase` yang hanya mengizinkan
database pengujian PostgreSQL terpisah bernama `farmer_ic_crud_test_*` atau
`farmer_ic_schema_test_*`. Jangan memakai database aplikasi sebagai target tes.

Endpoint finance baru sudah mengotorisasi setiap aksi dan membatasi detail,
daftar, pagination, agregat, dan ekspor melalui cakupan model. Pengujian mencakup
keempat peran, pembatasan tim/kebun, grant invalid/hilang, presisi nominal besar,
validasi relasi, rollback, dan proteksi field server.

Halaman `/finance` sekarang memerlukan login dan membaca tabel Finance melalui
Inertia, termasuk daftar/detail serta form transaksi, kategori, anggaran, dan
proyeksi. Tidak ada fallback ke fixture frontend. Ekspor CSV merupakan unduhan
langsung dari route internal yang tetap terotorisasi.

Dashboard/laporan demo dan AI `FinanceSnapshotService` tetap memakai sumber demo
yang diberi label. Pekerjaan ini tidak menghubungkan snapshot AI ke transaksi.
Route percakapan/pesan/voice AI dan query retrieval lama belum mendapatkan
rollout RBAC penuh; route team/profile dan halaman demo lain di luar Finance
tetap mengikuti batas integrasi sebelumnya. Perubahan nama route dashboard admin
menjadi `admin.dashboard` menjaga nama route `dashboard` untuk dashboard tim dan
menghindari ekspor helper Wayfinder yang duplikat.

## Hasil verifikasi alur Inertia

- Finance, halaman tamu, dan dashboard: 40 tes lulus, 816 assertions pada
  PostgreSQL pengujian terpisah, termasuk pencabutan izin relasi komoditas.
- PHPStan, Pint, lint frontend Finance, pemeriksaan tipe Vue, dan build lulus.
  Build masih memberi peringatan paket font opsional dan ukuran chunk laporan.
- Browser lokal: data seeder, pencarian, daftar/detail, serta form transaksi,
  kategori, anggaran, dan proyeksi diperiksa pada lebar 375, 390, 430, dan
  desktop 1280 piksel. Tema terang/gelap diperiksa; Finance tidak menghasilkan
  error/warning console pada kunjungan baru. Tidak ada mutasi data bisnis dari
  browser; persistensi dan validasi diuji melalui HTTP tests.
- Suite authorization yang lebih luas: 24 dari 29 tes lulus. Lima kegagalan
  terkait kondisi di luar Finance: Gate `admin.access` dikomentari, route
  dashboard tim masih tersedia meskipun tes lama mengharapkan 404, dan grant
  `admin teams.view` tidak sesuai harapan tes. Halaman settings pada viewport
  mobile juga memiliki warning hydration sidebar yang sudah ada. Pekerjaan
  Finance ini tidak mengubah aturan akses admin/Team atau sidebar tersebut.
