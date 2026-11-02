# Review UX Farm Management

Tanggal: 2 November 2026. Ini snapshot **review awal**, sebelum perbaikan.
Implementasi lanjutan dan verifikasinya dicatat di [farm-management-ux-improvements.md](farm-management-ux-improvements.md).

## Metode dan batas pemeriksaan

Menggunakan panduan **Impeccable audit / Operate** dan pencarian lokal
**UI/UX Pro Max** untuk dependent dropdown, autocomplete, error summary, dan
watcher Vue. Ini audit kode dan walkthrough browser, bukan perintah
`impeccable critique` dengan dua penilaian independen.

Preview menjalankan kode workspace pada database pengujian terpisah
`farmer_ic_crud_test_batch_area_20261102`, menggunakan akun Admin Demo.
Data operasional aplikasi utama tidak diubah. Data preview mencakup batch yang
dibuat pada pengujian sebelumnya; angka pada screenshot bukan hanya seed awal.

Delapan halaman diperiksa pada viewport browser 375×900 dan 1440×900, masing-masing
dalam light/dark: daftar kebun, tambah kebun, edit kebun, detail kebun, daftar
blok, tambah blok, edit blok, dan detail blok/batch. Seluruh 32 kombinasi tidak
menunjukkan overflow horizontal pada dokumen. Screenshot disimpan per kombinasi.
Beberapa halaman juga diperiksa pada 390×844 saat walkthrough awal.

Interaksi yang diperiksa: filter kota dan reset, hasil pencarian blok kosong,
penolakan perubahan luas kebun, penolakan pembuatan blok melebihi kapasitas,
buka/batal dialog hapus kebun dan blok, navigasi detail → blok, serta handoff ke
logbook. Kedua permintaan luas ditolak; tidak ada penambahan/perubahan domain
yang berhasil disimpan dalam review ini. Console pada akhir matriks tidak
menampilkan warning/error.

Penghapusan permanen tidak dieksekusi. Force delete diperiksa melalui kode,
bukan walkthrough Superadmin. Penyimpanan sukses, pagination banyak halaman,
semua role, gangguan jaringan peta, screen reader, reduced motion, serta gesture
sentuh perangkat fisik belum diuji ulang dalam review ini. Viewport ponsel
adalah emulasi ukuran browser, bukan bukti gesture sentuh atau perangkat asli.
Build dan tes backend tidak dijalankan ulang karena tidak ada perubahan kode.

## Penilaian audit

Implementasi memakai komponen dan tema yang konsisten di dalam masing-masing
shell, tetapi kesinambungan tugas lintas kebun/blok/logbook dan pencegahan error
belum utuh. Detector otomatis tidak berjalan; penilaian berikut berasal dari
kode, walkthrough, screenshot, dan pengukuran DOM, bukan sertifikasi WCAG atau
benchmark performa.

| Dimensi | Nilai / 4 | Dasar penilaian |
| --- | ---: | --- |
| Aksesibilitas | 2 | Label/inline error tersedia; pemulihan fokus dan dua pasangan kontras bermasalah. |
| Performa | 3 | Peta diimpor dinamis, daftar dipaginasi, tidak terlihat error runtime; belum ada profiling atau uji dataset besar. |
| Tema | 2 | Dua tema berfungsi, tetapi error form blok dark dan tombol batch light belum memenuhi kontras teks kecil. |
| Responsif | 3 | Tidak ada overflow horizontal pada 32 kombinasi; hierarki mobile masih menunda akses ke data. |
| Integritas implementasi | 2 | Gate/props dan komponen bersama dipertahankan; kapasitas/form dan relasi logbook/blok belum selaras dalam UX. |
| **Total** | **12 / 20** | **Acceptable: perbaikan signifikan masih diperlukan.** |

Tidak ditemukan P0 pada skenario yang dijalankan. Temuan: **6 P1 dan 7 P2**.
P1 pada lifecycle merupakan batas fitur, bukan klaim bahwa aksi yang sudah ada
rusak. Tidak ada temuan detector yang diklaim berhasil atau bersih.

## Hasil per flow

| Flow | Yang sudah bekerja | Masalah / tindak lanjut |
| --- | --- | --- |
| Daftar kebun | Daftar, empty state, reset, loading dan layout kartu mobile tersedia. | Filter wilayah menuntut nama persis; statistik dan filter mendominasi layar ponsel. UX-07, UX-10. |
| Urutkan / pagination | Kontrol urutan, jumlah baris, tautan halaman dan totals tersedia dari backend. | Pagination lintas banyak halaman belum diuji. Reset tidak muncul jika hanya sort/direction berubah; satukan definisi filter aktif pada iterasi berikutnya. |
| Tambah kebun | Pengelompokan informasi/lokasi/foto jelas; label dan native required tersedia. | Wilayah bebas, belum ada penjagaan draft ketika meninggalkan form. UX-01, UX-11. |
| Edit kebun | Data awal terisi dan penolakan luas tidak menghapus draft. | Batas minimum tidak dijelaskan lengkap; select pemilik bisa tampak kosong; error tidak menerima fokus. UX-02, UX-03, UX-09. |
| Detail kebun | Identitas, metrik, peta dan akses ke blok/logbook tersedia. | Ringkasan belum memperlihatkan alokasi batch persiapan; handoff keluar shell Admin kehilangan jalur kembali. UX-02, UX-08. |
| Pin peta / foto | Peta termuat; bantuan keyboard, hapus pin, tombol titik tengah, fallback error dan tautan foto ada. | Belum ada pencarian lokasi pada peta; ini peluang penyempurnaan, bukan blocker. Gangguan jaringan/gesture tidak diuji ulang. |
| Hapus kebun | Dialog menyebut permanen; fokus awal jatuh pada Batal. Server mengecek histori dan penugasan. | Penghalang baru diketahui setelah mencoba konfirmasi. UX-12. Tidak menekan tombol hapus final. |
| Force delete | Dari kode: ringkasan dampak, nama persis, alasan, token ringkasan dan blocker tersedia. | Walkthrough Superadmin belum dilakukan; tidak menyimpulkan alur ini lulus secara visual. |
| Pilih kebun / daftar blok | Kartu menyebut kebun/kode/nama; pencarian dan status tersedia. | Jalur kembali ke detail Admin tidak tersedia; hasil kosong tidak punya tombol reset. UX-08, UX-13. Halaman pemilih `/blocks` diperiksa dari kode. |
| Tambah / edit / nonaktifkan blok | Server menolak luas berlebih dan nonaktif saat masih ada batch berjalan. | Tidak menampilkan sisa alokasi/minimum secara numerik sebelum submit; kontras error dark kurang. UX-02, UX-04. Nonaktifkan diperiksa dari kode, tanpa menyimpan perubahan status. |
| Hapus blok | Dialog menjelaskan histori dan menyarankan nonaktif; guard server ada. | Sama seperti hapus kebun: tampilkan status penghalang sebelum konfirmasi. UX-12. Tidak menghapus blok. |
| Buat batch | Sisa blok/kebun, limit, ha/m², konversi, realisasi vs reservasi sudah jelas. | Kontras tombol light kurang; kapasitas dan enam tahap tidak berarti lifecycle operasional sudah tersedia. UX-04, UX-05. Penyimpanan sukses sudah diuji pada pekerjaan sebelumnya, tidak diulang. |
| Timeline / periode batch | Tahap dan tanggal terbaca; nomor per kebun dijelaskan. | Belum ada cara menyelesaikan/membatalkan batch dari UI; seluruh timeline memanjang pada mobile. UX-05, UX-10. |
| Detail → logbook | Halaman logbook terbuka pada kebun yang benar dan empty state tersedia. | Nama blok masih teks bebas; “Tambah blok” menambah baris kegiatan, bukan master blok. UX-06. Audit internal seluruh modul logbook di luar cakupan. |

## Temuan prioritas

### UX-01 — P1 — Wilayah alamat belum saling memvalidasi

- **Lokasi:** `resources/js/pages/farms/Form.vue:76`, `app/Http/Requests/Farms/SaveFarmRequest.php`.
- **Kategori:** integritas implementasi / pencegahan error.
- **Bukti:** kecamatan, kota/kabupaten, provinsi merupakan input bebas; validasi hanya string/panjang.
- **Dampak:** typo dan kombinasi wilayah yang tidak berelasi bisa lolos validasi.
- **Rekomendasi:** combobox kecamatan yang dapat dicari, label hasil dengan konteks kota/provinsi, lalu dropdown induk sesuai relasi. Kota disabled sebelum kecamatan; provinsi disabled sebelum kota. Ganti pilihan awal mereset pilihan di atasnya. Validasi relasi di backend, identifikasi dengan kode wilayah, jaga alamat legacy saat edit.
- **Rujukan sumber data yang sudah dibahas:** snapshot [cahyadsn/wilayah](https://github.com/cahyadsn/wilayah), impor master ke PostgreSQL dan catat versi/lisensi; belum diimpor oleh review ini.
- **Panduan tindak lanjut:** `$impeccable harden`.

### UX-02 — P1 — Penjelasan kapasitas belum mengikuti batas yang sebenarnya

- **Lokasi:** `resources/js/pages/farms/Form.vue:60`, `resources/js/pages/farms/Show.vue`, `resources/js/pages/farmer/blocks/Form.vue:89`, `app/Services/FarmService.php`, `app/Services/FarmBlockService.php`.
- **Kategori:** integritas implementasi / kejelasan informasi.
- **Bukti:** edit kebun menyebut tanam aktif 4,2500 ha. Input 5 ha tetap ditolak karena blok aktif berjumlah 12,5 ha. Tambah blok menawarkan form tanpa sisa kapasitas; tambahan 1 ha ditolak pada kebun yang blok aktifnya sudah penuh.
- **Dampak:** user memenuhi petunjuk yang terlihat tetapi tetap gagal menyimpan.
- **Rekomendasi:** tampilkan luas kebun, total blok aktif, reservasi berjalan, tanam aktif, serta batas minimum/maksimum yang relevan sebelum submit. Sajikan sebagai batas yang saling membatasi, bukan menjumlahkan blok dan batch sebagai dua lahan terpisah. Format angka konsisten dengan lokal Indonesia.
- **Panduan tindak lanjut:** `$impeccable clarify`.

### UX-03 — P1 — Gagal simpan belum mengantar user ke field bermasalah

- **Lokasi:** `resources/js/pages/farms/Form.vue:40`, `:86`; `resources/js/pages/farmer/blocks/Form.vue:143`.
- **Kategori:** aksesibilitas / pemulihan error.
- **Bukti:** submit edit kebun dari bagian bawah mempertahankan posisi scroll. Inline error luas berada jauh di atas; fokus setelah response berada pada BODY. Ringkasan di bawah hanya mengatakan “Periksa isian yang ditandai”.
- **Dampak:** user perlu mencari sendiri lokasi error, terutama pada form mobile sepanjang lebih dari dua layar.
- **Rekomendasi:** ringkasan error berisi tautan ke field, target fokus setelah failed submit, pertahankan inline error dan seluruh draft. Jangan memindahkan fokus pada setiap pengetikan/blur.
- **Panduan tindak lanjut:** `$impeccable harden`.

### UX-04 — P1 — Dua pasangan warna gagal batas kontras teks kecil

- **Lokasi:** `resources/js/pages/farmer/blocks/Form.vue`, `resources/js/components/farms/FarmField.vue`, tombol Buat batch pada `resources/js/pages/farmer/blocks/Show.vue`, token Farmer/shared button terkait.
- **Kategori:** aksesibilitas / tema.
- **Bukti DOM:** error dark `rgb(220,38,38)` pada card `rgb(18,28,20)` = **3,62:1**. Tombol Buat batch light memakai teks putih pada `rgb(104,187,108)` = **2,36:1**, font 14px.
- **Standar:** WCAG 1.4.3 minimum 4,5:1 untuk teks ukuran biasa. Ini pengukuran dua pasangan nyata, bukan klaim seluruh halaman memenuhi/gagal WCAG.
- **Dampak:** instruksi pemulihan dan aksi utama lebih sulit terbaca.
- **Rekomendasi:** koreksi pasangan semantic token foreground/background sesuai profil dan tema. Jangan mengganti palet Farmer secara global menjadi palet Admin. Verifikasi error, tombol aktif, hover, dan disabled secara terpisah.
- **Panduan tindak lanjut:** `$impeccable colorize`.

### UX-05 — P1 — Lifecycle batch berhenti pada pembuatan dan timeline

- **Lokasi:** `resources/js/pages/farmer/blocks/Show.vue`, `resources/js/components/blocks/BatchTimeline.vue`, `docs/farm-block-backend.md:32`.
- **Kategori:** kelengkapan alur / integritas implementasi.
- **Bukti:** enam tahap tampil, tetapi route/UI untuk menyelesaikan tahap, realisasi tanam/panen, pembatalan batch dan pengulangan Land Clearing belum tersedia. Dokumentasi menyatakan batas ini secara eksplisit.
- **Dampak:** user dapat mereservasi lahan, tetapi tidak dapat menuntaskan siklus atau membebaskan reservasi melalui flow ini.
- **Rekomendasi:** untuk scope saat ini, jelaskan batas kemampuan pada halaman. Untuk iterasi produk berikutnya, tentukan transisi tahap dan sumber bukti kegiatan; jangan sekadar menambahkan dropdown status bebas. Panen sebagian tidak otomatis menutup seluruh batch.
- **Panduan tindak lanjut:** `$impeccable shape` sebelum implementasi business flow baru.

### UX-06 — P1 — Logbook belum memilih master blok yang sudah dibuat

- **Lokasi:** `resources/js/pages/farmer/DailyActivityLog.vue:260`, `:275`; `app/Services/Activity/DailyActivityLogService.php:72`.
- **Kategori:** integritas implementasi / konsistensi istilah.
- **Bukti:** logbook menampilkan Nama blok sebagai teks bebas dan tombol Tambah blok. Payload frontend tidak menyediakan `block_id`, padahal service mendukung dan memvalidasi relasi tersebut.
- **Dampak:** user mengira mencatat pada B01 yang sama, tetapi nama teks saja tidak membentuk hubungan ke master blok; “Tambah blok” juga dapat disalahartikan sebagai membuat blok permanen.
- **Rekomendasi:** pilih blok persisted dari kebun yang sedang dibuka, gunakan `block_id` dengan scoped lookup; ubah label penambahan menjadi “Tambah kegiatan pemetaan”. Pertahankan laporan legacy dan jangan mengarang hubungan hanya dari nama.
- **Panduan tindak lanjut:** `$impeccable clarify` + `$impeccable harden`.

### UX-07 — P2 — Filter wilayah mengharuskan nama persis

- **Lokasi:** `resources/js/pages/farms/Index.vue:68`, `app/Services/FarmService.php:31`.
- **Kategori:** pencarian / integritas implementasi.
- **Bukti:** input kota “bog” menampilkan 0 hasil walau Bogor tersedia; Reset filter memulihkan 2 kebun. Filter memakai perbandingan persis sementara kolomnya tampak seperti isian pencarian biasa.
- **Dampak:** user menyimpulkan kebunnya hilang atau tidak memiliki akses.
- **Rekomendasi:** dropdown opsi wilayah dari kebun yang terlihat oleh actor, atau ubah perilaku menjadi pencarian parsial dengan petunjuk yang jelas. Untuk filter daftar, jangan mewajibkan pilih kecamatan hanya untuk memfilter provinsi.
- **Panduan tindak lanjut:** `$impeccable clarify`.

### UX-08 — P2 — Handoff keluar Admin kehilangan konteks navigasi

- **Lokasi:** `resources/js/layouts/BlockLayout.vue:23`, `resources/js/pages/farms/Show.vue`, halaman logbook.
- **Kategori:** navigasi / konsistensi.
- **Bukti:** detail Admin → blok menghilangkan sidebar dan memakai Farmer shell; tautan header menuju `/` atau `/blocks`, bukan detail kebun asal. Logbook mengarah kembali ke `/kebun`.
- **Dampak:** user perlu browser Back atau memulai lagi dari daftar kebun untuk kembali ke pekerjaan Admin.
- **Rekomendasi:** breadcrumb/jalur kembali yang mempertahankan kebun dan konteks portal; tampilkan hanya tujuan yang diizinkan backend. Jangan memberikan akses Admin kepada Petugas untuk menyamakan tampilan.
- **Panduan tindak lanjut:** `$impeccable clarify`.

### UX-09 — P2 — Select pemilik dapat terlihat kosong

- **Lokasi:** `resources/js/pages/farms/Form.vue:69`, `app/Services/FarmService.php::owners`.
- **Kategori:** state form / integritas implementasi.
- **Bukti:** pada Admin Demo, owner existing tidak ada dalam pilihan yang visible. DOM select bernilai kosong dan tidak ada option selected; screenshot menunjukkan kontrol kosong. Detail menyatukan “Belum ditentukan / akses terbatas”.
- **Dampak:** tidak jelas apakah pemilik belum diisi atau sengaja disembunyikan oleh izin. Review ini tidak membuktikan owner terhapus saat simpan.
- **Rekomendasi:** bedakan belum diisi dan tidak dapat diakses tanpa membocorkan identitas; pertahankan ID existing yang tidak sedang diubah. Jelaskan bila tidak ada opsi yang dapat dipilih. Jangan memperluas UserPolicy hanya untuk memenuhi dropdown.
- **Panduan tindak lanjut:** `$impeccable harden`.

### UX-10 — P2 — Hierarki mobile menunda pekerjaan utama

- **Lokasi:** `resources/js/pages/farms/Index.vue:50`, detail/timeline blok.
- **Kategori:** responsif / hierarki.
- **Bukti visual:** pada 375px, tiga kartu metrik lalu seluruh filter mendahului baris kebun; data pertama di luar viewport awal. Detail blok dua batch sepanjang sekitar 3.517px karena semua tahap ditampilkan vertikal.
- **Dampak:** banyak scroll untuk memilih kebun atau memeriksa tahap yang sedang berjalan.
- **Rekomendasi:** ringkas statistik mobile; pertahankan pencarian utama, kelompokkan filter tambahan dalam panel. Pada batch, tampilkan ringkasan tahap aktif dan perluas riwayat secara sadar. Jangan memotong tanggal atau menyembunyikan error.
- **Panduan tindak lanjut:** `$impeccable adapt`.

### UX-11 — P2 — Draft form dapat ditinggalkan tanpa peringatan

- **Lokasi:** `resources/js/pages/farms/Form.vue`, `resources/js/pages/farmer/blocks/Form.vue`.
- **Kategori:** pencegahan kehilangan isian.
- **Bukti:** setelah form edit berubah dan submit ditolak, Kembali langsung membuka detail; tidak ada konfirmasi draft. Sumber tidak mengatur guard `isDirty`.
- **Dampak:** alamat, pin, atau perubahan lain yang belum tersimpan mudah hilang.
- **Rekomendasi:** beri konfirmasi hanya jika draft berubah dan user meninggalkan form; jangan mengganggu navigasi setelah berhasil simpan.
- **Panduan tindak lanjut:** `$impeccable harden`.

### UX-12 — P2 — Tombol hapus belum menjelaskan penghalang sebelum konfirmasi

- **Lokasi:** `resources/js/components/farms/DeleteFarmDialog.vue`, dialog hapus blok dan service delete terkait.
- **Kategori:** pencegahan error.
- **Bukti:** kebun/blok yang jelas memiliki histori tetap membuka dialog dengan aksi hapus aktif; guard bisnis di server baru memeriksa pada submit. Dialog umum menjelaskan syarat, tetapi tidak merinci status record yang dipilih.
- **Dampak:** user melakukan langkah yang sudah dapat diperkirakan akan ditolak.
- **Rekomendasi:** backend menyediakan alasan tidak dapat dihapus yang aman untuk ditampilkan, atau preview; gunakan disabled dengan alasan dan arahan yang sesuai. Guard transaksi server tetap wajib. Ini bukan alasan mengizinkan penghapusan histori.
- **Panduan tindak lanjut:** `$impeccable harden`.

### UX-13 — P2 — Empty state pencarian blok tidak punya reset cepat

- **Lokasi:** `resources/js/pages/farmer/blocks/Index.vue`.
- **Kategori:** pemulihan pencarian.
- **Bukti:** pencarian “blok-tidak-ada” menampilkan saran mengubah pencarian/status, tetapi tidak menyediakan Reset filter seperti daftar kebun.
- **Dampak:** user harus mengosongkan input dan mengatur status secara manual sebelum menerapkan lagi.
- **Rekomendasi:** tombol reset pada filter aktif/empty state dengan label yang konsisten dan mempertahankan konteks kebun.
- **Panduan tindak lanjut:** `$impeccable clarify`.

## Pola lintas halaman dan hal yang dipertahankan

Masalah berulang adalah batas bisnis yang baru muncul setelah submit, state
form yang belum menjelaskan izin/ketersediaan data, dan handoff antarshell.
Perbaikan paling berguna bukan mengganti identitas visual, melainkan menjadikan
batas, pilihan yang valid, dan langkah berikutnya terlihat sebelum user salah.

Yang sudah baik: semantic theme utilities, layout kartu mobile/tabel desktop,
label nyata, inline error dengan `aria-describedby`, loading dan empty state,
dialog hapus yang fokus awalnya Batal, peringatan penghapusan permanen, peta
dengan alternatif tombol/keyboard, serta kapasitas dan konversi ha/m² pada batch.
Controller tetap memakai Gate dan service read tetap menggunakan scope actor;
review ini tidak memberi kesimpulan audit keamanan menyeluruh.

## Urutan tindak lanjut

1. `$impeccable harden`: dropdown wilayah dan validasi relasi, error summary/fokus,
   owner terbatas, penjagaan draft; gunakan master wilayah terversi.
2. `$impeccable clarify`: kapasitas numerik konsisten, filter wilayah/empty state,
   navigasi kembali dan istilah pemetaan blok.
3. `$impeccable colorize`: benahi pasangan kontras yang terukur di kedua tema.
4. `$impeccable shape`: rancang lifecycle batch dan relasi pelaporan sebagai
   pekerjaan fullstack tersendiri; jangan mengubah transisi bisnis secara diam-diam.
5. `$impeccable adapt`: ringkas hierarki daftar dan timeline pada ponsel.
6. `$impeccable polish`: review visual akhir, ulangi skenario form, RBAC, tema,
   ukuran layar, tes terkait dan build setelah kode berubah.

Tindak lanjut dapat dilakukan satu per satu atau bersama sesuai scope yang
disetujui. Ulangi audit setelah implementasi; skor saat ini bukan hasil perbaikan.

## Kenapa pemeriksaan otomatis Impeccable tertahan

Kedua pemanggilan langsung, `impeccable context` dan `impeccable detect`, berhenti
dengan `zsh: permission denied` dan exit code **126**. Pemeriksaan file membuktikan:

```text
-rw-r--r--  /Users/alwierdi/.codex/skills/impeccable/scripts/impeccable
```

Mode **0644** memberi izin baca/tulis kepada pemilik dan baca kepada pihak lain,
tetapi **tidak memberi execute (`x`)**. File ini adalah launcher shell dengan
shebang `#!/bin/sh`; saat dipanggil langsung, shell menolak sebelum logika
launcher/detector berjalan. Ini bukan penolakan dari kode project, RBAC,
PostgreSQL, maupun automatic approval review.

Pemeriksaan juga tidak menemukan binary bawaan pada `scripts/bin`, cache
`~/.impeccable/bin`, atau executable `impeccable` di PATH. Launcher berisi mekanisme
pencarian/download engine; oleh karena itu memperbaiki execute bit belum tentu
langsung cukup untuk menjalankan detector. Kebutuhan engine/network berikutnya
belum diuji dengan menjalankan launcher, dan tidak diklaim sebagai penyebab
exit 126 yang sudah terjadi.

Perbaikan pertama yang sesuai adalah memberi executable bit pada launcher atau
memperbaiki instalasi skill agar permission executable dipertahankan. Review ini
tidak mengubah permission/instalasi global dan tidak mengunduh binary. Panduan
Markdown Impeccable dan pencarian Python UI/UX Pro Max tetap dapat digunakan;
deteksi otomatis/overlay Impeccable tidak berjalan.

## Bukti screenshot

Artefak lokal berada di
`/Users/alwierdi/.codex/visualizations/2026/10/30/01a18b00-84c6-71c2-98c0-ba7503e9c099/`.

- `ux-{daftar,tambah,edit,detail,blok,tambah-blok,edit-blok,batch}-{375,1440}-{light,dark}.png`:
  32 kombinasi halaman/viewport/tema.
- `farm-ux-block-error-dark.png`: penolakan kapasitas blok dan error dark.
- `farm-ux-checks.json`: pengukuran lebar dokumen dan tinggi halaman per kombinasi.

Snapshot merupakan keadaan preview pada tanggal review; perubahan kode/data
berikutnya dapat mengubah angka dan tampilan.
