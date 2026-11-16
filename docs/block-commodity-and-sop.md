# Komoditas dan SOP blok

Setiap blok dapat memiliki komoditas dan SOP default sendiri. SOP yang dipilih
harus berasal dari tenant yang sama dan memiliki komoditas yang cocok. Keduanya
dapat diubah tanpa mengubah riwayat subblok.

Penanaman baru menggunakan komoditas blok saat laporan pertama dicatat. Setelah
subblok ditanami, laporan lanjutan memakai varietas yang sudah tersimpan dan
harus tetap cocok dengan versi SOP yang sudah terikat, jika ada. Tugas yang
sudah dibuat tetap memakai versi dan salinan langkah sebelumnya.

Migrasi menyalin konfigurasi kebun ke blok yang sudah ada tanpa mengubah
varietas, versi SOP, atau tugas historis, kemudian menghapus kolom konfigurasi
kebun. Siklus tanpa blok tidak lagi memiliki SOP default untuk membuat tugas
baru; versi SOP yang sudah terikat tetap dipertahankan. Rollback hanya membuat
kolom kebun kosong karena konfigurasi antarblok dapat berbeda. Subblok yang sudah ditanam tetapi belum memiliki versi
SOP hanya dapat menghasilkan tugas jika komoditas varietasnya cocok dengan SOP
default blok; ketidakcocokan tidak mengganti data historis.
