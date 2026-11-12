# Notifikasi operasional

Scheduler Laravel memanggil NotificationService dan mengantrekan pekerjaan setelah
transaksi terluar commit. Rollback tidak mengirim pekerjaan. Seluruh tanggal dan
jadwal berikut menggunakan Asia/Jakarta (WIB).

## Jenis dan jadwal

| Jenis / command | Jadwal | Penerima | Saat kosong |
| --- | --- | --- | --- |
| Laporan Weekly / weekly-report | Minggu 20:00 | Admin dan Owner aktif | Admin tetap mendapat ringkasan jika belum ada aktivitas; Owner tetap mendapat target dan keterangan tidak ada panen. Penerima tanpa kebun yang terlihat tidak menerima apa pun. |
| Reminder Tugas SOP / task-reminder | Setiap hari 06:00 | Petugas kebun penugasannya | Tidak mengirim apa pun jika tidak ada tugas H-3, H-1, atau H-0. Admin tidak menerima reminder ini. |
| Laporan Ketidaksesuaian SOP / sop-deviation | Setiap hari 06:00 | Admin tenant | Tidak mengirim apa pun jika tidak ada tugas melewati toleransi. Diulang setiap hari selama masih bermasalah. |

Laporan harian dan reminder panen berbasis estimated_harvest_at tidak lagi
dijadwalkan atau didukung oleh command dispatch.

## Laporan Weekly

Periode adalah Senin 00:00 sampai Minggu 20:00 pada minggu pengiriman. Aktivitas
Minggu setelah 20:00 berada di luar batas rekap tersebut.

Admin menerima satu inbox per tenant dan satu job/pesan WhatsApp per kebun.
Kontennya adalah ringkasan FarmActivity yang dilaporkan Petugas selama periode:
jumlah aktivitas per jenis, jumlah laporan hambatan beserta kutipan singkat
hambatan yang paling sering dilaporkan (maksimal 100 karakter), serta semua IncidentDetail
yang dikelompokkan berdasarkan kategori dan tingkat keparahan beserta jumlahnya.
Petugas yang sudah soft-deleted tetap tercakup melalui performer withTrashed.
Kegiatan dari seluruh subblok diringkas ke bloknya; nama/ID subblok dan catatan
aktivitas panjang tidak ditampilkan. Aktivitas tanpa tautan blok tetap dicakup
pada bagian Tanpa blok. Kebun tanpa aktivitas menampilkan
"Belum ada aktivitas yang dilaporkan Petugas minggu ini."

Owner menerima satu inbox lintas seluruh tenant yang berhak diaksesnya.
Inbox menampilkan target per komoditas pada level total seluruh kebun dan per
kabupaten/kota, beserta realisasi panen minggu tersebut. Tidak ada rincian
kebun/blok/subblok. WhatsApp Owner dipisahkan menjadi satu job/pesan per
kabupaten/kota, menggabungkan kebun lintas tenant; total seluruh kebun hanya
ditampilkan di inbox, tidak diulang pada setiap pesan WhatsApp.

Target selalu memakai HarvestProjectionService dengan period=cycle agar sama
dengan dashboard. Kalkulator saat ini memakai luas seluruh kebun × produktivitas
komoditas, sekali per kebun-komoditas dengan siklus aktif; beberapa siklus aktif
komoditas sama tidak melipatgandakan luas. Ini adalah target per cycle, bukan
target mingguan dan bukan perhitungan tahunan.

Realisasi memakai FarmActivity jenis harvest dengan activity_date dalam periode
dan HarvestDetail yang terlihat: berat panen bruto (ton) dan luas panen tercatat
(ha), dikelompokkan berdasarkan komoditas siklus atau komoditas kebun jika siklus
tidak tersedia. Data komoditas/target/berat yang hilang diberi keterangan,
tidak disamarkan sebagai hasil nol. Aktivitas panen tanpa detail berat tetap
dihitung sebagai laporan panen dengan berat belum tercatat.
Kabupaten tanpa panen tetap mendapat target dan pesan
"Tidak ada panen yang terjadi minggu ini."

Nama provinsi dan kabupaten/kota yang tersimpan menjadi identitas gabungan
(setelah normalisasi spasi/huruf), sehingga kebun berkode wilayah dan data legacy
dapat digabung. Nama kabupaten sama di provinsi berbeda tetap terpisah. Kode
wilayah menjadi fallback jika nama belum lengkap. Kebun tanpa data kabupaten
tetap dicakup dalam total dan bagian "Kabupaten belum diisi" agar panen tidak hilang.

Laporan Owner gabungan menyimpan team_id=null dan daftar team_ids JSONB yang
berasal dari konteks backend berizin. Filter tenant memilih laporan gabungan
yang memuat tenant tersebut; isinya tetap satu rekap lintas tenant milik Owner.
Laporan Admin dan reminder tetap mempunyai team_id tunggal. Payload weekly
format lama yang belum membedakan audience Admin/Owner tidak dikirim lagi;
riwayat inbox yang sudah tersimpan tetap dapat dibaca.

## Reminder dan ketidaksesuaian

Sumber jadwal adalah PlantingCycleTask, bukan tanggal perkiraan panen.
Tugas harus pending atau in_progress pada siklus active yang sudah ditanam.
Completed dan skipped dikeluarkan.

Tanggal efektif = rescheduled_date jika tersedia, selain itu scheduled_date.
Batas toleransi = tanggal efektif + tolerance_days.

Reminder memeriksa selisih tanggal lokal 3, 1, atau 0 hari. Tanggal efektif
menggantikan jadwal lama setelah reschedule. Task tanpa tanggal tidak dikirim.

Ketidaksesuaian berarti tanggal pemeriksaan sudah lebih besar daripada batas
toleransi, bukan tepat pada batasnya. Contoh: jadwal 10 November dan toleransi
2 hari masih diperbolehkan sampai akhir 12 November; mulai pemeriksaan pagi
13 November, tugas yang belum selesai dilaporkan.
Reschedule boleh melewati batas jadwal lama dan tidak otomatis dianggap
pelanggaran. Batas baru mengikuti tanggal reschedule beserta toleransinya.

Kedua jenis ini masing-masing digabung menjadi satu record inbox dan satu pesan
WhatsApp per penerima, tenant, dan tanggal. Tugas subblok yang memiliki kebun,
blok, nama tugas, jadwal, dan batas toleransi sama diringkas menjadi satu baris
dengan jumlah tugas. Pesan hanya menampilkan kebun dan blok.

## Kanal, konfigurasi dan proses

- OPERATIONAL_NOTIFICATIONS_ENABLED=true mengaktifkan ketiga jenis.
- FONNTE_ENABLED=false secara default. WhatsApp hanya mengirim saat true,
  token Fonnte tersedia, nomor penerima valid, dan terdaftar di WhatsApp.
- Fonnte nonaktif tidak menghentikan inbox.
- Antrean notifications menyimpan inbox melalui InAppChannel.
- Antrean whatsapp memanggil Fonnte validate lalu send melalui HTTPS.
- Keduanya memakai NOTIFICATION_QUEUE_CONNECTION (default database).

Recipient discovery membatasi akun aktif dan role bisnis yang tepat; Gate
notifications.weeklyReport, notifications.taskReminder, dan
notifications.sopDeviation memeriksa role, membership dan grant pembacaan.
Data Farm, FarmBlock, PlantingCycle, PlantingCycleTask, FarmActivity, IncidentDetail,
HarvestDetail dan referensi komoditas dibaca
melalui visibleTo sebelum pengelompokan/total/serialisasi. Admin tidak mendapat
data tenant lain, Petugas hanya mendapat kebun yang ditugaskan. Superadmin
tidak dipilih sebagai penerima bisnis.

Channel/job memeriksa ulang keaktifan, role sesuai audience, membership, visibilitas,
identitas kabupaten dan tanggal payload pada saat eksekusi. Semua tenant dalam
snapshot Owner diverifikasi kembali; membership yang dicabut membatalkan payload
tersebut, termasuk setelah validasi nomor Fonnte. Reminder/ketidaksesuaian memeriksa ulang tiap tugas
sebelum render, termasuk setelah validasi nomor Fonnte. Tugas yang telah selesai,
berpindah konteks atau berubah jadwal dikeluarkan; tugas lain yang masih valid
tetap dikirim. Jika hasil akhirnya kosong, tidak ada pengiriman.
Payload hanya berlaku pada tanggal pengiriman; tidak dikirim setelah tengah malam.

Payload antrean berisi snapshot data tetapi tidak menyimpan nomor telepon/token.
Batasi akses dan retensi tabel jobs/failed_jobs. Pengiriman transient dicoba
maksimal tiga kali dengan backoff 60/300/900 detik, selama masih ada waktu sebelum
kedaluwarsa. Respons provider yang permanen atau hasil kirim yang tidak pasti
tidak diulang secara buta. Respons accepted bukan konfirmasi pesan sampai ke ponsel.

ID inbox deterministik mencegah record ganda untuk penerima/audience/minggu atau
penerima/tenant/hari yang sama.
WhatsApp belum mempunyai deduplikasi persisten: menjalankan command berulang
dapat mengirim pesan lagi. withoutOverlapping melindungi eksekusi scheduler yang
bertumpuk, bukan pengulangan command manual setelah pekerjaan sebelumnya selesai.

## Inbox dan kompatibilitas

Notifikasi baru menyimpan kolom content JSONB versi 1 sebagai snapshot ringkasan
pada saat pengiriman. Inbox membuka rincian langsung di dalam kartu:
Owner melihat total target/panen serta rincian kabupaten, Admin melihat aktivitas,
hambatan dan semua kategori insiden per kebun/blok. Reminder dan ketidaksesuaian
memakai kartu terpisah dengan jadwal serta batas toleransi. Dropdown membantu
menyaring kabupaten, kebun atau H-0/H-1/H-3 di dalam laporan.

Tabel pada kartu lebar berubah menjadi rincian berlabel pada kartu sempit.
Membuka kartu tidak otomatis menandainya dibaca. Rincian berasal dari snapshot
tersimpan, tanpa query tambahan untuk setiap kebun/blok ketika inbox dibuka.
Pesan WhatsApp tetap menggunakan template teks.

Halaman /notifications menampilkan jenis baru weekly_report, task_reminder dan
sop_deviation. Riwayat daily_report dan harvest_reminder tetap bisa dibaca;
kelas lamanya dipertahankan untuk deserialisasi antrean lama. Payload jenis lama
ditolak saat diproses, sehingga tidak menghasilkan pengiriman dengan aturan lama.

Tab Belum dibaca menampilkan seluruh notifikasi pribadi belum dibaca. Tab Semua
menyediakan rentang tanggal diterima (inklusif dalam WIB), serta filter tenant
khusus Owner. Semua filter diterapkan setelah scope penerima. Tenant ditampilkan
pada badge Owner; pesan mingguan tidak mengulang header tenant ketika badge ada.
Admin/Petugas tidak memperoleh nama tenant dari template reminder/ketidaksesuaian.

## Menjalankan dan menguji

Sebelum memakai kode ini, terapkan migrasi additive
2026_11_11_000001_add_team_ids_to_notifications dan
2026_11_12_000001_add_content_to_notifications pada database aplikasi yang benar.
CD saat ini menolak deployment jika masih ada migrasi pending; migrasi perlu
operasi deployment yang ditinjau, bukan menghapus gate tersebut.

Scheduler dan worker notifications,whatsapp harus aktif dengan environment yang
sama. Setelah deployment, buat ulang cache konfigurasi jika digunakan dan restart
worker/scheduler agar kode serta konfigurasi baru berlaku.

Command berikut benar-benar mengantrekan pengiriman sesuai konfigurasi, sehingga
gunakan akun demo dan nomor yang sudah diizinkan saat mencoba:

~~~sh
php artisan notifications:dispatch weekly-report
php artisan notifications:dispatch task-reminder
php artisan notifications:dispatch sop-deviation
~~~

Tes menggunakan PostgreSQL terisolasi dan HTTP fake untuk Fonnte:

~~~sh
php artisan test tests/Feature/Notifications
~~~

WeeklyReportRevisionTest membuktikan kesamaan target dengan dashboard, penggabungan
kabupaten lintas tenant, semua kategori insiden, batas periode panen, filter inbox
gabungan, data yang belum lengkap dan pencabutan akses saat pengiriman.
NotificationWorkflowTest mencakup role, tenant, assignment, batas minggu,
milestone, reschedule, toleransi, kasus kosong dan perubahan saat job berjalan.
NotificationCommitTest menjalankan database queue worker nyata untuk commit,
rollback, retry dan kegagalan provider. NotificationQueryPerformanceTest
memeriksa bahwa jumlah query tidak tumbuh per kebun/tugas.

Diagram operational-notifications-backend.svg dan .excalidraw adalah dokumentasi
historis alur daily/harvest lama; aturan aktif ada di dokumen ini dan kode.
