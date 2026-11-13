# Kebun Cariu Baru: impor logbook dan skenario panen

`CariuBaruSeeder` adalah impor **terarah dan opsional**, tidak dipanggil oleh
`DatabaseSeeder`. Jalankan hanya setelah seed dasar menyediakan tenant
`farm-ic-demo`, akun Superadmin/Owner, komoditas Jagung dan katalog aktivitas:

```sh
php artisan --env=local db:seed --class=CariuBaruSeeder
```

Pengaman membatasi seeder pada PostgreSQL lokal `127.0.0.1/farmer_ic_dev` atau
database PostgreSQL test terisolasi dengan nama yang diizinkan. Seeder
transaksional ini membuat record baru saja: apabila ID/nama kebun atau email
bertabrakan dengan data lain, impor dibatalkan. Pengulangan pada fixture yang
masih utuh tidak memperbarui atau menghapus apa pun, termasuk perubahan yang
dibuat pengguna. Seeder tidak mereset database, tidak memindahkan Petugas yang
sudah ada, dan tidak mengubah `Kebun Cariu` yang lain.

**Penting:** perintah lama `demo:prune-owner-farms --apply` memangkas kebun di
tenant demo yang berada di luar tujuh ID portofolio; Kebun Cariu Baru termasuk
dalam target pangkas. Jangan jalankan perintah tersebut bila fixture ini ingin
dipertahankan. Karena email Petugas ditinggalkan oleh beberapa operasi
penghapusan, seeder sengaja tidak mengadopsi akun yang sudah ada secara diam-diam.

## Akses demo

| Pengguna | Email | Kata sandi demo lokal |
| --- | --- | --- |
| Petugas SIMULASI Cariu Baru | `petugas.cariu@farmer-ic.test` | `CariuDemo123!` |

Ini adalah identitas fiktif pencatat, **bukan nama pelapor di spreadsheet**.
Petugas baru hanya ditugaskan pada kebun ini. Akun demo lama tidak berubah.
Riwayat tersedia di `/petugas/laporan`, tugas di `/petugas/tasks`, dan Admin
dapat membuka `/admin/logbook`. Kredensial ini khusus lingkungan demo lokal.

## Sumber dan konversi

Snapshot dua tab berada di `database/seeders/fixtures/cariu-baru/` dan berasal
dari [spreadsheet logbook Cariu](https://docs.google.com/spreadsheets/d/1WtVL9wvGzDi66ZEBbBp1W0rEotG9hhXgswPSgtZSRWo/edit?gid=0#gid=0).
Impor merekam seluruh **42 baris yang memiliki kegiatan**, 5 Oktober–7 November
2026; baris-baris tanpa kegiatan tidak dijadikan kejadian. Kolom hasil, kendala,
solusi, tindakan, SDM, tempat, jam, referensi dokumentasi dan paraf disimpan
dalam catatan masing-masing laporan. Referensi dokumentasi adalah teks, bukan
foto yang tersedia. Teks sumber yang ganjil, termasuk tanggal 2025 pada
laporan 7 Oktober, dipertahankan, bukan dikoreksi sebagai fakta.

Satu header harian memuat rentang gabungan; tiap kegiatan menyimpan jam sumber
sendiri di catatan. Salah ketik `17.:15` dinormalisasi menjadi `17:15` hanya
untuk kolom waktu dan tulisan aslinya tetap di catatan. Pembulatan hektare ke
empat desimal memakai presisi 1 m²; angka m² asli tetap di teks sumber.

- Pembersihan gulma/herbisida memakai `land_cleaning`; rotary memakai
  `loosening`; pemupukan dasar memakai `base_fertilization`; tanam memakai
  `planting` dengan detail yang sesuai.
- Empat laporan gabungan rotary/pemupukan memiliki satu laporan utama dan satu
  laporan turunan jenis pupuk; SDM dan kendala **tidak diduplikasi** pada
  laporan turunan. Informasi cangkul, pemetaan, dan rincian lainnya tetap pada
  teks sumber utama.
- Pemetaan tanpa panjang/lebar, pengukuran tanah, survei, drainase, rapat,
  logistik, dan kegiatan lain yang tidak memiliki detail sesuai dimasukkan
  sebagai `other`, tanpa menciptakan ukuran yang tidak diketahui.
- Survei lahan Tanjung Sari 187 ha adalah catatan proyek di lokasi lain, bukan
  luas Kebun Cariu Baru. Luas kebun 1,39 ha adalah **luas poligon Lahan PM
  sementara**, bukan pernyataan luas keseluruhan proyek.

## Asumsi dan batasan simulasi

Spreadsheet tidak menentukan pembagian subblok, varietas, luas tanam 7
November, tanggal panen, hasil panen, atau identitas Petugas. Blok `PM-DEMO`
adalah agregat asumsi, bukan peta blok aktual. Pembagian berikut hanya agar
alur aplikasi yang membatasi penanaman tiga hari kalender dapat didemokan:

| Subblok simulasi | Tanggal laporan tanam | Luas tersimpan | Panen simulasi |
| --- | --- | --- | --- |
| A | 29 dan 31 Oktober | 0,1800 ha | 29 Januari 2027 |
| B | 1–3 November | 0,2940 ha | 31 Januari 2027 |
| C | 4–5 November | 0,3930 ha | 3 Februari 2027 |
| D | 7 November | 0,2356 ha | 5 Februari 2027 |

Untuk D, angka 2.356 m² adalah **asumsi skenario**, dibulatkan dari sisa
2.356,45 m² yang disebut pada 5 November; sumber 7 November menulis `...`,
sehingga tidak membuktikan luas yang ditanam hari itu. Total 1,1026 ha meliputi
asumsi tersebut dan bukan luas aktual terverifikasi. Nama varietas secara
eksplisit menyatakan tidak diketahui; jumlah benih tidak diisi.

SOP contoh berisi pengairan, penyiangan, pengamatan, pemupukan dan panen 90
hari setelah tanggal jangkar tanam. Seluruh 24 aktivitas SOP, nilai hasil,
SDM, tanggal penyelesaian dan 4 penutupan panen adalah **simulasi**, bukan
kejadian yang telah diverifikasi maupun rekomendasi budidaya. Tugas, subblok,
nama Petugas, detail dan catatan diberi penanda `SIMULASI`. Data panen masa
depan sengaja disimpan sebagai riwayat selesai agar alur akhir dapat didemokan;
halaman ringkasan/total aplikasi tetap dapat menghitungnya seperti data panen
lain. Jangan gunakan angka agregat dari database demo ini sebagai realisasi
operasional, dan jangan campurkan dengan database produksi.

## Batas AI/RAG

Seeder ini tidak mengubah atau mengindeks korpus AI/RAG. Integrasi AI yang ada
masih memiliki sumber referensi `farmDemoData.json` dan tidak otomatis mencerminkan
record Kebun Cariu Baru maupun perubahan database terkini. Gunakan halaman
logbook/kebun sebagai sumber untuk skenario ini; jangan menganggap jawaban AI
berbasis dataset referensi sebagai laporan aktual kebun ini.
