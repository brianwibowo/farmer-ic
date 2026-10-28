# Backend finance

Modul finance memakai model dan skema PostgreSQL yang sudah tersedia, tanpa
perubahan migration atau penambahan paket. Controller mengotorisasi melalui
Policy, Form Request memvalidasi masukan, `FinanceService` mengelola transaksi
database dan query `visibleTo`, dan API Resource membentuk respons JSON.

## Akses dan route

Semua URL di bawah diawali `/{current_team}/finance`, dengan `current_team`
berupa slug tim, misalnya `/farm-ic-demo/finance`. Endpoint berada dalam web
middleware Laravel: gunakan sesi login, cookie sesi, `Accept: application/json`,
dan token CSRF untuk POST/PUT/PATCH/DELETE. Ini bukan API token publik.

| Method | Path | Fungsi |
| --- | --- | --- |
| GET | `/transactions` | Daftar transaksi terfilter dan terpaginasikan |
| GET | `/transactions/{transaction}` | Detail transaksi dan `can.update/delete` |
| POST | `/farms/{farm}/transactions` | Catat transaksi pada kebun tepercaya |
| PUT/PATCH | `/transactions/{transaction}` | Ubah transaksi |
| DELETE | `/transactions/{transaction}` | Hapus transaksi |
| GET | `/transactions/export` | Ekspor CSV sesuai filter dan cakupan akses |
| GET | `/summary` | Total pemasukan, pengeluaran, laba bersih, jumlah transaksi, dan rekap bulanan |
| GET/POST | `/categories` | Daftar/buat kategori |
| GET/PUT/PATCH/DELETE | `/categories/{category}` | Detail/ubah/hapus kategori |
| GET | `/budgets` | Daftar anggaran |
| POST | `/farms/{farm}/budgets` | Buat anggaran kebun |
| GET/PUT/PATCH/DELETE | `/budgets/{budget}` | Detail/ubah/hapus anggaran |
| GET | `/projections` | Daftar proyeksi |
| POST | `/farms/{farm}/projections` | Buat proyeksi kebun |
| GET/PUT/PATCH/DELETE | `/projections/{projection}` | Detail/ubah/hapus proyeksi |

UUID yang tidak valid/tidak ditemukan menghasilkan 404. Penolakan Policy atau
ketidakcocokan tim pada route menghasilkan 403. Kesalahan validasi menghasilkan
422. Kategori yang masih dipakai transaksi/anggaran tidak bisa dihapus (409).
Pembuatan menghasilkan 201, pembacaan/perubahan 200, dan penghapusan 204.

Aturan matriks yang sudah ada tetap berlaku: owner membaca, admin mengelola
sesuai batas tim, petugas tidak memiliki akses finance, dan superadmin memakai
bypass pengembang. Query tetap dibatasi tim pada URL, termasuk untuk superadmin.
Kategori merupakan katalog bersama lintas tim sesuai Policy yang sudah ada;
perubahan katalog berlaku untuk seluruh pengguna katalog, bukan hanya tim aktif.

## Contoh payload

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
  untuk menjaga nilai besar saat mengirim JSON. Respons uang selalu string.
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
| Transaksi, summary, CSV | `farm_id`, `category_id`, `planting_cycle_id`, `transaction_type`, `from_date`, `to_date`, `search` pada deskripsi |
| Kategori | `transaction_type`, `search` pada nama |
| Anggaran | `farm_id`, `category_id`, `from_date`, `to_date` |
| Proyeksi | `farm_id`, `planting_cycle_id`, `from_date`, `to_date` |

Tanggal berformat `YYYY-MM-DD`. Batas transaksi inklusif; anggaran/proyeksi
diikutkan jika periodenya beririsan dengan rentang filter. Pencarian `%` dan `_`
bersifat literal. Kolom sort transaksi: `transaction_date`, `amount`, `created_at`;
kategori: `name`, `transaction_type`; anggaran: `period_start`, `period_end`,
`budget_amount`, `created_at`; proyeksi: `period_start`, `period_end`,
`projected_income`, `projected_expense`, `calculated_at`.

Daftar menggunakan envelope Resource Laravel (`data`, `links`, `meta`).
Ringkasan menggunakan `data.source = database`, `currency = IDR`, `income`,
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

Halaman publik `/finance`, dashboard, dan laporan masih memakai service demo
frontend. AI `FinanceSnapshotService` juga tetap memakai sumber demo yang diberi
label, bukan tabel transaksi ini. Endpoint baru adalah sumber database yang siap
dipakai untuk integrasi frontend/AI berikutnya; tidak ada fallback JSON di modul
ini. Route percakapan/pesan/voice AI dan query retrieval lama belum mendapatkan
rollout RBAC penuh melalui pekerjaan ini. Route team/profile di luar cakupan
finance juga tetap mengikuti batas integrasi sebelumnya.
