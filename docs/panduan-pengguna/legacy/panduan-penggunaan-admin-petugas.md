# Panduan Penggunaan Admin dan Petugas

**Sistem Manajemen Lahan Bumi Hijauan**  
Versi 1.4 · 23 November 2026

Panduan ini menjelaskan alur awal Admin menyiapkan akun dan data lahan, lalu alur Petugas mencatat pekerjaan melalui perangkat mobile. Gunakan istilah **lahan** untuk lokasi usaha pertanian; satu lahan dapat dibagi menjadi blok, lalu subblok untuk area tanam dan siklus yang berbeda.

![Ilustrasi lahan budidaya dengan jagung, rumput, dan sorgum.](panduan-pengguna/gambar/20-ilustrasi-lahan-penutup.png)

> **Kredensial awal:** Mintalah akun awal kepada Developer. Kata sandi bersifat sensitif dan disampaikan melalui tempat atau mekanisme terpisah yang aman, bukan melalui panduan ini.

## Kata Pengantar

Panduan ini masih dikembangkan seiring penggunaan sistem. Versi berikutnya akan disempurnakan berdasarkan masukan pengguna, data penggunaan, dan pengalaman nyata di lapangan. Sistem juga terus dikembangkan; versioning membantu pengguna meninjau pembaruan panduan dengan jelas.

## Daftar Isi

- [1. Istilah dan peran](#1-istilah-dan-peran)
- [2. Persiapan dan masuk ke aplikasi](#2-persiapan-dan-masuk-ke-aplikasi)
- [3. Alur awal Admin: buat akun Petugas](#3-alur-awal-admin-buat-akun-petugas)
- [4. Mencatat lahan](#4-mencatat-lahan)
- [5. Menyusun dan menetapkan SOP](#5-menyusun-dan-menetapkan-sop)
- [6. Alur kerja Petugas/PIC di mobile](#6-alur-kerja-petugaspic-di-mobile)
- [7. Pemecahan masalah](#7-pemecahan-masalah)
- [8. Catatan pengelolaan dokumen](#8-catatan-pengelolaan-dokumen)
- [Penutup](#penutup)

> **Cara membaca:** judul besar menandai bagian utama, subjudul menandai tahapan dalam bagian, daftar bernomor menunjukkan urutan kerja, dan bullet menunjukkan pilihan atau hal yang perlu diperhatikan.
> **Cakupan:** panduan berfokus pada Admin dan Petugas/PIC. Owner dan Superadmin dijelaskan untuk konteks akun serta batas peran. Label menu dapat berubah mengikuti pembaruan aplikasi.

## 1. Istilah dan peran

| Istilah | Arti dalam panduan |
| --- | --- |
| Owner | Pemilik usaha, dalam briefing ini Pak Satar. Memantau ringkasan usaha dan data yang menjadi hak aksesnya. |
| Superadmin | Developer atau personel yang diberi tanggung jawab mengelola akses lintas tenant dan dukungan sistem. |
| Admin | Pengurus operasional satu tenant yang menyiapkan akun, data lahan, SOP, blok, dan penugasan. |
| Petugas / PIC | Person in Charge di lapangan. Mengerjakan tugas dan mengirim laporan dari lahan yang ditugaskan. |
| Lahan | Lokasi usaha pertanian dengan identitas, luas, wilayah, titik peta, status penguasaan, dan target hasil. |
| Blok | Pembagian area di dalam lahan. Blok dapat memiliki komoditas dan SOP budidaya. |
| Subblok | Bagian blok yang disiapkan untuk satu rencana atau siklus tanam. Buat subblok sebelum memulai penanaman pada bagian tersebut. |
| SOP | Prosedur operasional budidaya yang disusun Admin. Tahap SOP menjadi dasar tugas yang ditampilkan kepada Petugas. |
| Tenant / tim | Ruang kerja organisasi yang membatasi data yang dapat dilihat dan dikelola pengguna. |

### Ringkasan tanggung jawab

| Peran | Tanggung jawab utama |
| --- | --- |
| Superadmin | Menyiapkan atau mendukung akun utama, menjaga konfigurasi akses, dan membantu Admin. Hak global hanya untuk kebutuhan pengelolaan sistem. |
| Admin | Membuat akun Admin/Petugas dalam tenant, mencatat lahan, menyusun SOP, membuat blok dan subblok, menugaskan Petugas, serta memantau laporan. |
| Petugas / PIC | Melihat lahan dan tugas yang ditugaskan, mencatat aktivitas atau realisasi tugas, serta meninjau laporan yang pernah dikirim. |
| Owner | Memantau ringkasan usaha dan informasi yang tersedia sesuai aksesnya. |

## 2. Persiapan dan masuk ke aplikasi

### Akses

- Alamat aplikasi demo: [Buka halaman masuk](https://farmer.icdigital.io/login).
- Gunakan akun yang diberikan organisasi. Empat alamat akun demo untuk lingkungan lokal tercatat di [Daftar akun demo dan informasi akses](demo-seed-credentials.md).
- Akun dengan alamat `@farmer-ic.test` adalah fixture demo/pengembangan, bukan akun untuk layanan bersama atau operasional.
- Jangan menyalin kata sandi ke dokumen panduan, pesan grup, atau dokumen yang dapat diakses publik. Bagikan melalui kanal aman dan ubah kata sandi sesuai kebijakan organisasi.

### Masuk

1. Buka halaman masuk melalui browser. Tampilan menyesuaikan ukuran layar desktop atau mobile.
2. Isi email dan kata sandi akun yang diberikan.
3. Pilih **Log in**. Jika kredensial tidak cocok, periksa ejaan email, Caps Lock, dan pastikan akun belum dinonaktifkan.
4. Setelah berhasil masuk, aplikasi mengarahkan pengguna ke area yang sesuai dengan perannya.

![Halaman masuk Bumi Hijauan pada tampilan desktop.](panduan-pengguna/gambar/05-form-login-desktop.png)

*Gambar 1A. Halaman masuk pada layar desktop.*

![Halaman masuk Bumi Hijauan pada tampilan mobile.](panduan-pengguna/gambar/06-form-login-mobile.png)

*Gambar 1B. Halaman masuk pada layar mobile.*

## 3. Alur awal Admin: buat akun Petugas

Admin perlu masuk terlebih dahulu sebelum menyiapkan akun lapangan. Akun Petugas baru dapat ditautkan ke lahan yang sudah tercatat; jika lahannya belum ada, buat akun dahulu lalu lakukan penugasan setelah lahan tersedia.

1. Buka menu **Manajemen Pengguna**. Tabel ini menampilkan akun yang sudah ada dan tombol untuk menambahkan akun (Gambar 2).

![Daftar Manajemen Pengguna.](panduan-pengguna/gambar/08-list-management-pengguna.png)

*Gambar 2. Daftar pengguna. Periksa nama, email, peran, tenant, status, dan penugasan sebelum memilih tindakan.*

2. Pilih **Tambah pengguna**. Form pembuatan akun akan terbuka (Gambar 3).

![Form untuk menambahkan pengguna.](panduan-pengguna/gambar/10-tambah-pengguna.png)

*Gambar 3. Form tambah pengguna. Lengkapi identitas, peran, kata sandi awal, dan penugasan lahan jika sudah tersedia.*

3. Isi nama lengkap, email aktif, dan nomor WhatsApp bila tersedia.
4. Pilih peran aplikasi **Petugas**. Admin dapat membuat akun Admin atau Petugas sesuai kewenangannya; pengaturan Superadmin hanya dilakukan personel yang berwenang.
5. Tetapkan kata sandi awal dan isi konfirmasinya. Berikan kredensial kepada pengguna melalui kanal aman.
6. Jika lahan sudah tersedia, pilih lahan untuk penugasan Petugas. Jika belum tersedia, simpan tanpa penugasan dan kembali setelah lahan dibuat.
7. Tinjau kembali data lalu simpan. Pastikan pengguna muncul pada daftar dan peran serta penugasannya benar.

**Praktik yang disarankan:** satu akun untuk satu orang, email tidak digunakan bersama, dan akun Petugas diberi penugasan lahan yang benar. Penugasan membatasi data operasional yang tersedia untuk Petugas. Pada model saat ini satu akun Petugas memiliki paling banyak satu lahan tugas; satu lahan dapat ditugaskan kepada beberapa Petugas.

### Mengelola akun yang sudah dibuat

- Gunakan pencarian nama/email dan filter untuk menemukan akun. Menu tindakan di ujung baris membuka opsi detail, ubah, dan hapus sesuai izin (Gambar 4).

![Menu tindakan lanjutan pada pengguna.](panduan-pengguna/gambar/09-tindakan-lanjut-pengguna.png)

*Gambar 4. Menu tindakan pengguna. Pilih tindakan pada akun yang benar.*

- Buka **Lihat detail** untuk memeriksa identitas, peran, tenant, dan lahan tugas (Gambar 5).

![Halaman detail pengguna.](panduan-pengguna/gambar/11-detail-pengguna.png)

*Gambar 5. Detail pengguna menampilkan informasi akun dan lahan yang ditugaskan.*

- Pilih **Ubah pengguna** untuk memperbarui informasi yang diizinkan (Gambar 6). Admin tenant tidak dapat mengganti peran akun yang telah ada; perubahan tersebut ditangani Superadmin.

![Form untuk mengubah informasi pengguna.](panduan-pengguna/gambar/12-edit-informasi-pengguna.png)

*Gambar 6. Form ubah pengguna. Isian kata sandi boleh dikosongkan jika tidak ingin menggantinya.*

- Kata sandi baru pada formulir edit bersifat opsional. Mengubahnya dapat mengakhiri sesi aktif pengguna lain.
- **Hapus pengguna** meminta konfirmasi (Gambar 7). Penghapusan menonaktifkan kemampuan masuk, sementara akun dan relasi operasional dipertahankan melalui soft delete.

![Dialog konfirmasi penghapusan pengguna.](panduan-pengguna/gambar/13-konfirmasi-hapus-pengguna.png)

*Gambar 7. Pastikan akun yang ditampilkan benar sebelum mengonfirmasi penghapusan.*

- Pengguna yang dihapus tersedia pada tab **Terhapus** bagi Admin yang berwenang (Gambar 8). Pilih **Pulihkan** untuk mengaktifkan kembali akun.

![Daftar pengguna yang telah dihapus.](panduan-pengguna/gambar/14-list-pengguna-dalam-keadaan-terhapus.png)

*Gambar 8. Tab Terhapus menampilkan akun yang dapat dipulihkan oleh Admin yang berwenang.*

Setelah memilih **Pulihkan**, tinjau dialog konfirmasi (Gambar 9), lalu periksa kembali status dan penugasan akun.

![Dialog konfirmasi pemulihan pengguna.](panduan-pengguna/gambar/15-konfirmasi-pemulihan-pengguna.png)

*Gambar 9. Konfirmasikan pemulihan akun yang dipilih.*

## 4. Mencatat lahan

Buka **Manajemen Lahan**, lalu pilih **Tambah lahan**. Isi informasi berdasarkan dokumen dan kondisi lapangan. Isian bertanda bintang wajib dilengkapi.

![Daftar lahan pada menu Manajemen Lahan.](panduan-pengguna/gambar/07-list-management-lahan.png)

*Gambar 10. Daftar Manajemen Lahan. Gunakan pencarian atau filter untuk menemukan lahan; pilih **Tambah lahan** untuk mencatat lahan baru.*

![Form tambah lahan. Penanda merah 1 menunjukkan pilihan wilayah; penanda merah 2 menunjukkan tombol Simpan lahan.](panduan-pengguna/gambar/01-form-tambah-lahan.png)

*Gambar 11. Pilih wilayah secara berurutan, lalu simpan setelah data diperiksa.*

### Informasi lahan

| Isian | Cara mengisi |
| --- | --- |
| Nama lahan | Gunakan nama yang singkat dan mudah dikenali, misalnya “Lahan Cariu”. Hindari nama yang sama dalam satu ruang kerja. |
| Luas lahan (ha) | Masukkan luas resmi/terverifikasi dalam hektare. Perhatikan satuan; 1 ha = 10.000 m². |
| Target volume panen (ton) | Masukkan target total sesuai rencana usaha. Jika target dihitung dari luas × produktivitas per hektare, pastikan komoditas, satuan, periode, dan angka produktivitas disepakati terlebih dahulu. Form meminta nilai target secara langsung. |
| Status penguasaan lahan | Pilih Milik sendiri, Sewa, atau Kerja sama sesuai dokumen. Untuk Sewa/Kerja sama, catat pihak terkait jika diminta. |
| PIC lahan | Opsional. Pilih akun Petugas yang telah tersedia. Penugasan dapat dilakukan atau diperbarui kemudian. |

### Lokasi dan dokumentasi

1. Tulis alamat yang membantu menemukan lokasi, termasuk desa/dusun atau petunjuk akses. Hindari memasukkan nomor telepon atau data pribadi pada alamat.
2. Pilih **Provinsi**, kemudian **Kota/kabupaten**, lalu **Kecamatan**. Pilihan wilayah berikutnya aktif setelah pilihan sebelumnya tersedia.
3. Gunakan peta untuk menaruh pin, lalu geser marker jika posisinya perlu dikoreksi. Periksa posisi terhadap alamat sebelum menyimpan. Pin menunjukkan satu titik referensi; pin bukan batas/poligon luas lahan.
4. Tautan dokumentasi menerima referensi HTTP/HTTPS, misalnya folder Google Drive. Isian tautan ini menyimpan URL, bukan mengunggah berkas. Pastikan izin akses folder sudah sesuai bagi tim yang membutuhkan.
5. Simpan lahan dan buka detailnya untuk memastikan luas, wilayah, status, PIC, dan titik lokasi benar.

## 5. Membagi lahan menjadi blok dan subblok

### Tambahkan blok

1. Dari detail lahan, pilih **Tambah blok**. Gambar 12 menunjukkan letak aksi Tambah blok dan Tambah subblok.

![Detail lahan. Penanda merah 1 menunjukkan Tambah blok; penanda merah 2 menunjukkan Tambah subblok.](panduan-pengguna/gambar/02-detail-lahan.png)

*Gambar 12. Detail lahan; gunakan Tambah blok untuk memulai pembagian lahan.*

2. Pilih **Komoditas blok** terlebih dahulu. Pilihan SOP default mengikuti komoditas yang tersedia.
3. Pilih SOP yang sesuai. Jika SOP belum tersedia, Admin perlu menyusun SOP untuk komoditas tersebut sebelum memasangnya pada blok.
4. Isi kode blok yang unik di lahan tersebut, misalnya `B01`, dan nama deskriptif, misalnya **Blok Utara**.
5. Masukkan luas blok dan pilih satuan luas yang benar. Rencanakan pembagian luas agar jumlah blok aktif tidak melebihi kapasitas lahan. Pembagian sama rata hanya salah satu cara perencanaan, bukan aturan wajib.
6. Pastikan status **Aktif** bila blok akan digunakan untuk kegiatan operasional. Catatan bersifat opsional.
7. Simpan dan periksa blok di halaman detail lahan.

![Form tambah blok. Penanda merah 1 menunjukkan komoditas, 2 menunjukkan SOP default, dan 3 menunjukkan Simpan blok.](panduan-pengguna/gambar/03-form-tambah-blok.png)

*Gambar 13. Form tambah blok. Pilih komoditas dan SOP, lalu lengkapi kode, nama, luas, serta satuan. Pada tangkapan layar, kapasitas lahan untuk blok aktif adalah 0 ha sehingga Simpan blok nonaktif; sesuaikan alokasi atau status blok sebelum menyimpan.*

### Tambahkan subblok

1. Pada blok yang tepat, pilih **Tambah subblok**. Form yang terbuka ditunjukkan pada Gambar 14.
2. Isi label bila diperlukan; jika dikosongkan, sistem dapat memberi label otomatis.
3. Isi rencana tanggal tanam bila sudah diketahui. Tanggal ini adalah rencana, bukan bukti bahwa penanaman sudah terjadi.
4. Periksa sisa luas yang tersedia pada blok, lalu simpan.
5. Setelah subblok tersedia, Petugas dapat menjalankan alur penanaman untuk bagian tersebut. Aktivitas persiapan tertentu, seperti pembersihan lahan, dapat dicatat pada tingkat blok sesuai alur aplikasi.

![Form tambah subblok. Penanda merah 1 menunjukkan label dan rencana tanggal tanam; penanda merah 2 menunjukkan tombol Tambah subblok.](panduan-pengguna/gambar/04-form-tambah-subblok.png)

*Gambar 14. Label dan rencana tanggal tanam bersifat opsional pada contoh form. Tekan Tambah subblok setelah memastikan sisa luas mencukupi.*

### Membuat, menerbitkan, dan merevisi SOP

Admin dan Superadmin membuat SOP per komoditas melalui menu **SOP**. Panduan ini menyusun langkah SOP mulai dari **perawatan setelah penanaman hingga panen**. Langkah persiapan lahan dan penanaman tidak dimasukkan ke dalam rangkaian ini.

#### 1. Periksa daftar dan buat template

1. Buka menu **SOP**. Cari berdasarkan nama atau komoditas untuk memastikan template yang dibutuhkan belum tersedia. Pilih **Tambah SOP**.

   ![Daftar SOP Admin dan Superadmin; tombol Tambah SOP diberi penanda merah.](panduan-pengguna/gambar/Gambar1SOP-panduan.png)

   *Gambar 15. Daftar template SOP. Tombol yang diberi kotak merah memulai pembuatan template baru.*

2. Pada **Identitas template**, isi nama SOP, pilih komoditas, tinjau status aktif untuk subblok baru, dan tulis deskripsi tujuan atau cakupannya. Pilih **Simpan template**.

   ![Form kosong untuk identitas template SOP dengan field dan tombol simpan ditandai merah.](panduan-pengguna/gambar/Gambar2SOP-panduan.png)

   *Gambar 16. Form identitas template baru. Penanda merah menunjukkan field nama, komoditas, dan tombol simpan.*

3. Gunakan data yang terisi sebagai contoh: nama **SOP Budidaya Rumput**, komoditas **Rumput**, status aktif, dan deskripsi yang menjelaskan tujuan SOP. Sesuaikan dengan komoditas dan praktik yang telah disepakati.

   ![Contoh identitas SOP Budidaya Rumput yang telah diisi.](panduan-pengguna/gambar/Gambar3SOP-panduan.png)

   *Gambar 17. Contoh form identitas SOP sebelum disimpan.*

#### 2. Susun langkah perawatan sampai panen

4. Setelah template tersimpan, halaman detail menampilkan versi awal berstatus **Draft**. Tambahkan langkah satu per satu. Rancang urutannya secara rinci sesuai SOP agronomi komoditas: misalnya pemantauan awal, pemeliharaan, pemupukan susulan, pengamatan pertumbuhan, pengendalian bila diperlukan, pemeriksaan kesiapan panen, lalu panen dan pencatatan hasil. Angka HST harus mengikuti SOP agronomi yang disetujui, bukan contoh generik.

   ![Detail template dengan versi Draft kosong dan tombol untuk menambahkan langkah serta memublikasikan.](panduan-pengguna/gambar/Gambar4SOP-panduan.png)

   *Gambar 18. Detail SOP setelah template dibuat. Tambahkan langkah selama versi masih Draft; tombol Publikasikan digunakan setelah daftar langkah ditinjau.*

5. Di jendela **Tambah langkah SOP**, isi nama tindakan, kategori aktivitas, offset HST, toleransi hari, dan instruksi. Estimasi HOK bersifat opsional.

   ![Jendela Tambah langkah SOP dengan nama, kategori, offset HST, toleransi, instruksi, dan tombol tambah diberi penanda merah.](panduan-pengguna/gambar/Gambar5SOP-panduan.png)

   *Gambar 19. Form langkah SOP. Penanda merah menunjukkan isian utama dan tombol untuk menambahkan langkah ke versi Draft.*

   - **Nama langkah** menjelaskan tindakan secara spesifik, seperti *Pemupukan susulan pertama*.
   - **Kategori aktivitas** mengelompokkan langkah, misalnya **Perawatan** atau **Panen**.
   - **Offset HST (hari)** menentukan hari pelaksanaan relatif terhadap tanggal tanam aktual. `H+0` berarti tanggal tanam, sedangkan `H+14` berarti 14 hari setelahnya.
   - **Toleransi (hari)** menambahkan tenggang waktu setelah tanggal jadwal. Jika batas ini terlewati, sistem dapat menandai tugas terlambat dan memicu tindak lanjut. Notifikasi deviasi SOP yang tersedia saat ini dikirim kepada Admin.
   - **Instruksi** dapat menjelaskan cara kerja, hal yang perlu diperiksa, atau catatan dari Admin/Superadmin untuk Petugas.
   - **Estimasi HOK** opsional dan merupakan perkiraan kebutuhan kerja, bukan penjadwal tugas.

6. Setelah tiap langkah ditambahkan, tinjau urutan HST, toleransi, nama, kategori, dan instruksinya. Selama masih Draft, gunakan ikon pensil untuk mengedit dan ikon tempat sampah untuk menghapus langkah yang keliru. Selesaikan daftar perawatan hingga panen sebelum publikasi.

   ![Versi SOP Draft berisi satu langkah, dengan tombol Publikasikan serta aksi edit dan hapus diberi tanda merah.](panduan-pengguna/gambar/Gambar6SOP-panduan.png)

   *Gambar 20. Tinjau dan koreksi langkah Draft. Publikasikan hanya setelah semua langkah siap.*

7. Pilih **Publikasikan**. Versi yang terbit menjadi acuan yang dapat dipilih untuk blok. Versi Published dibekukan dan langkahnya tidak diedit langsung.

   ![Detail SOP dengan status versi Published ditandai merah.](panduan-pengguna/gambar/Gambar7SOP-panduan.png)

   *Gambar 21. Versi Published. Status ini menandakan bahwa versi sudah diterbitkan dan tidak lagi diedit langsung.*

#### 3. Revisi melalui versi baru

8. Untuk mengubah SOP yang sudah diterbitkan, pilih **Versi baru**. Sistem membuat Draft baru berdasarkan versi sebelumnya.

   ![Detail SOP Published dengan tombol Versi baru diberi penanda merah.](panduan-pengguna/gambar/Gambar8SOP-panduan.png)

   *Gambar 22. Mulai revisi dengan membuat versi baru; versi Published lama tetap menjadi riwayat.*

9. Periksa Draft versi berikutnya. Edit atau hapus langkah dengan ikon tindakan, tambahkan langkah baru bila diperlukan, lalu tinjau kembali semua offset dan toleransi. Pilih **Publikasikan** untuk menerbitkan revisi setelah selesai.

   ![Versi Draft berikutnya dengan aksi edit, hapus, dan Publikasikan ditandai merah.](panduan-pengguna/gambar/Gambar9SOP-panduan.png)

   *Gambar 23. Revisi SOP pada versi Draft baru. Perubahan baru berlaku setelah versi tersebut diterbitkan.*

   Versi baru tidak otomatis mengganti snapshot SOP pada siklus yang sudah berjalan. Tinjau penerapan versi baru pada blok dan siklus yang akan dimulai.

#### 4. Tetapkan SOP pada blok

10. Buka detail lahan, lalu pilih **Tambah blok** atau edit blok yang belum memiliki SOP. Pilih komoditas blok terlebih dahulu, kemudian pilih SOP yang sesuai dari daftar. Lengkapi nama dan luas blok, lalu simpan.

   ![Form Tambah blok dengan pilihan komoditas, SOP, opsi SOP yang cocok, dan tombol simpan ditandai merah.](panduan-pengguna/gambar/Gambar10SOP-panduan.png)

   *Gambar 24. Tetapkan SOP Published pada blok. Pilih komoditas terlebih dahulu agar pilihan SOP yang sesuai dapat ditampilkan.*

**Jadwal tugas:** tugas siklus dihitung dari tanggal tanam aktual (`planted_at`) ditambah offset HST; tanggal rencana tanam belum memulai hitung mundur. Toleransi hari menjadi masa tenggang setelah jadwal. Notifikasi deviasi SOP saat ini dikirim kepada Admin; Owner belum menjadi penerima.

**Pengelolaan versi:** versi Published dibekukan. Siklus yang telah memakai snapshot versi sebelumnya tetap mengacu pada snapshot tersebut. Penerbitan versi baru tidak otomatis mengubah SOP pada semua blok atau siklus berjalan.
## 6. Alur kerja Petugas/PIC di mobile

Bagian ini mengikuti alur tugas SOP dari beranda sampai tugas ditandai selesai. Pada layar Petugas, laporan aktivitas dan penyelesaian tugas adalah dua tindakan terpisah.

### 6.1 Periksa beranda, to-do, dan insiden

1. Setelah masuk, buka **Beranda**. Bagian atas merangkum subblok pra-tanam, penanaman, dan panen.
2. Periksa kartu **Insiden aktif**. Insiden yang belum ditindaklanjuti dapat berasal dari laporan Petugas pada lahan yang sama; buka detail atau **Lihat semua** untuk meninjau informasi dan tindak lanjut.
3. Pada **To-do hari ini**, pilih kartu tugas untuk membuka detailnya. Status dan progres selesai hari ini membantu membedakan pekerjaan yang masih harus dilakukan.
4. Jika tugas atau insiden yang diharapkan tidak terlihat, pastikan lahan yang ditugaskan benar dan periksa menu Tugas bersama Admin.

   ![Beranda Petugas dengan kartu insiden aktif dan tugas hari ini yang ditandai merah.](panduan-pengguna/gambar/GambarPetugas1-panduan.png)

   *Gambar 25. Beranda Petugas: tinjau insiden aktif dan pilih tugas pada daftar To-do hari ini.*

### 6.2 Buka dan pahami detail tugas

1. Pilih tugas dari kartu To-do atau daftar **Tugas**.
2. Periksa nama tugas, lahan/blok/subblok, tanggal jadwal, target HST, varietas, serta jendela pelaporan.
3. Baca **Instruksi SOP** sebelum mulai bekerja. Instruksi menjelaskan cara kerja atau hal yang harus diperiksa.
4. Setelah pekerjaan dilakukan, tekan **Catat aktivitas tugas ini** untuk membuat laporan yang terhubung dengan tugas.

   ![Detail tugas dengan jadwal, HST, instruksi SOP, dan tombol Catat aktivitas tugas ini ditandai merah.](panduan-pengguna/gambar/GambarPetugas2-panduan.png)

   *Gambar 26. Baca target HST dan instruksi sebelum memulai pekerjaan, lalu buka form dari tombol Catat aktivitas tugas ini.*

   ![Detail tugas dengan informasi jadwal dan instruksi SOP ditandai merah.](panduan-pengguna/gambar/GambarPetugas3-panduan.png)

   *Gambar 27. Contoh tampilan detail tugas dan instruksi yang menjadi acuan pelaksanaan. Tangkapan layar ini menampilkan detail tugas yang sama.*

   ![Detail tugas dengan tindakan Catat aktivitas tugas ini ditandai merah.](panduan-pengguna/gambar/GambarPetugas7-panduan.png)

   *Gambar 28. Tindakan untuk memulai pencatatan laporan pada tugas SOP. Tampilan ini mengulang keadaan tombol yang sama agar titik mulai alur pelaporan terlihat jelas.*

### 6.3 Isi form aktivitas yang terhubung ke tugas

1. Pastikan ringkasan tugas yang terhubung sudah benar. Tanggal kerja harus sesuai hari pelaksanaan dan berada dalam jendela pelaporan yang ditampilkan.
2. Isi **Jumlah pekerja** serta **Luas area** dan satuannya sesuai pekerjaan. Form dapat berbeda menurut kategori tugas; isi field yang tampil dan wajib.
3. Lampirkan minimal satu **foto bukti pekerjaan** melalui **Tambah foto**. Form pada contoh menerima JPG, PNG, atau WebP, paling banyak lima foto, dengan batas ukuran 15 MB per foto.
4. Catatan tambahan dapat diisi jika diperlukan. Periksa kembali data dan bukti, lalu tekan **Simpan aktivitas**.
5. Jika unggahan atau penyimpanan gagal, ikuti pesan pada layar dan kirim ulang setelah memperbaiki isian atau koneksi.

   ![Form aktivitas tugas dengan jumlah pekerja, luas area, unggah foto, dan tombol simpan ditandai merah.](panduan-pengguna/gambar/GambarPetugas4-panduan.png)

   *Gambar 29. Form aktivitas tugas. Field menyesuaikan jenis pekerjaan; foto bukti wajib dilampirkan sebelum laporan dikirim.*

   ![Contoh form aktivitas terkait tugas yang sudah diisi, dengan ringkasan tugas, data pekerjaan, foto, dan tombol Simpan aktivitas ditandai merah.](panduan-pengguna/gambar/GambarPetugas8-panduan.png)

   *Gambar 30. Contoh pengisian laporan untuk tugas SOP sebelum menekan Simpan aktivitas.*

### 6.4 Tinjau laporan yang tersimpan dan selesaikan tugas

1. Setelah penyimpanan berhasil, periksa detail tugas atau buka **Laporan** untuk memastikan aktivitas muncul. Laporan tugas menampilkan relasi ke tugas dan ringkasan hasil yang dikirim.
2. Tersimpannya laporan **tidak otomatis menyelesaikan tugas**. Status tugas berubah menjadi **Dikerjakan** dan dapat tetap demikian selama masih ada pekerjaan yang harus dituntaskan.

   ![Beranda Petugas setelah laporan aktivitas tersimpan, dengan status tugas Dikerjakan yang ditandai merah.](panduan-pengguna/gambar/GambarPetugas10-panduan.png)

   *Gambar 31. Setelah laporan terkait dikirim, tugas tampak berstatus Dikerjakan; status ini belum berarti tugas selesai.*

3. Setelah seluruh pekerjaan benar-benar selesai, buka kembali detail tugas, isi catatan penyelesaian bila perlu, lalu tekan **Selesaikan tugas**.
4. Tinjau kembali status setelah konfirmasi. Menandai selesai merupakan sinyal progres untuk Admin dan sistem; setelah tugas selesai, laporan baru tidak dapat ditambahkan pada tugas tersebut.

   ![Detail laporan aktivitas yang tersimpan, dengan data hasil dan foto dokumentasi ditandai merah.](panduan-pengguna/gambar/GambarPetugas6-panduan.png)

   *Gambar 32. Contoh halaman detail laporan yang tersimpan. Periksa informasi tugas terkait untuk memastikan laporan terhubung dengan pekerjaan SOP yang benar.*

   ![Detail tugas berstatus Dikerjakan dengan kartu Selesaikan tugas dan tombolnya ditandai merah.](panduan-pengguna/gambar/GambarPetugas9-panduan.png)

   *Gambar 33. Tugas berstatus Dikerjakan dengan laporan terkait. Tekan Selesaikan tugas setelah seluruh pekerjaan tuntas.*

### 6.5 Lihat semua tugas dan gunakan filter

1. Buka tab **Tugas** untuk melihat daftar tugas lintas blok dan subblok pada lahan penugasan.
2. Gunakan ringkasan jumlah tugas terlambat, hari ini, dan mendatang sebagai gambaran beban kerja.
3. Cari nama tugas atau subblok. Gunakan pilihan status dan tombol **Filter** untuk mempersempit daftar sesuai pekerjaan yang ingin diperiksa.
4. Buka kartu tugas untuk melihat instruksi, mengirim laporan, atau menandai penyelesaian. Daftar dapat mencakup tugas yang sudah memiliki status Dikerjakan, sehingga Petugas bisa melanjutkan tindak lanjut.

   ![Halaman Tugas Petugas dengan ringkasan, tab, pencarian, filter, dan daftar lintas subblok ditandai merah.](panduan-pengguna/gambar/GambarPetugas11-panduan.png)

   *Gambar 34. Daftar tugas lebih lengkap dengan pencarian dan filter untuk meninjau pekerjaan lintas blok/subblok.*

### 6.6 Lihat lahan dan catat aktivitas mandiri

1. Buka tab **Lahan**, lalu pilih lahan yang ditugaskan untuk melihat alamat/wilayah, luas, peta titik lokasi, blok, subblok, dan ringkasan progres.
2. Untuk pekerjaan di luar daftar to-do SOP, gunakan tombol **+** pada Beranda atau Laporan. Pilih kategori kegiatan, isi tanggal/waktu, lokasi, jumlah pekerja, dan rincian yang diminta.
3. Lampirkan foto sesuai aturan form, simpan, lalu pastikan aktivitas muncul di riwayat **Laporan**.
4. Pada **Laporan**, cari berdasarkan aktivitas atau subblok dan gunakan filter tanggal, kategori, atau sumber bila perlu. Buka laporan untuk melihat detail.

   ![Contoh form aktivitas mandiri yang terisi, dengan rincian pekerjaan, bukti foto, dan tombol penyimpanan ditandai merah.](panduan-pengguna/gambar/GambarPetugas5-panduan.png)

   *Gambar 35. Contoh aktivitas mandiri di luar to-do SOP. Gunakan alur ini untuk pekerjaan yang memang tidak terkait tugas terjadwal.*

## 7. Pemecahan masalah

| Situasi | Langkah awal |
| --- | --- |
| Tidak bisa masuk | Periksa email/kata sandi dan status akun. Hubungi Admin untuk pemeriksaan akun atau reset kredensial. |
| Menu tidak terlihat | Pastikan akun menggunakan peran dan tenant yang benar. Minta Admin memeriksa akses; jangan menggunakan akun orang lain. |
| Petugas tidak melihat lahan | Periksa penugasan lahan pada detail akun dan pastikan lahan berada di tenant yang sama. |
| Pilihan SOP kosong | Periksa apakah SOP sudah dibuat, aktif/tersedia, dan sesuai komoditas yang dipilih. |
| Wilayah atau titik peta keliru | Perbaiki pilihan provinsi-kota/kabupaten-kecamatan dan geser pin sebelum menyimpan perubahan. |
| Aktivitas tidak ada di laporan | Hapus filter, periksa tanggal kerja, lalu pastikan pengiriman berhasil. Hubungi Admin jika tetap tidak muncul. |
| Lahan tidak memiliki blok/subblok | Admin membuat blok terlebih dahulu, kemudian subblok untuk area yang akan ditanam. |

## 8. Catatan pengelolaan dokumen

Panduan ini menjelaskan alur Admin–Petugas dan menghindari pencantuman kata sandi. Untuk perubahan alur, menu, atau aturan akses, perbarui bagian terkait dan tangkapan layar pada langkah yang relevan. Gunakan tangkapan layar tanpa data pribadi nyata; samarkan email, nomor telepon, koordinat sensitif, dan nama pihak bila dokumen dibagikan di luar tim.


## Penutup

Terima kasih telah menggunakan panduan ini. Pastikan data lahan dan penugasan diperiksa sebelum pekerjaan dimulai, lalu catat aktivitas sesuai kondisi lapangan dan SOP yang berlaku.

![Ilustrasi transparan lahan budidaya, daun, jagung, rumput, dan sorgum sebagai elemen dekoratif penutup panduan.](panduan-pengguna/gambar/20-ilustrasi-lahan-penutup.png)


