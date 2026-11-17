# Notifikasi operasional

Semua jadwal menggunakan Asia/Jakarta (WIB). Hanya akun aktif dengan role,
membership tenant dan izin baca yang valid menjadi penerima. PIC diperlakukan
sebagai Petugas; nama PIC di halaman lahan bukan role penerima tambahan.

| Jenis | Penerima | Jadwal |
| --- | --- | --- |
| Laporan Mingguan | Owner dan Admin | Minggu 20.00 |
| Reminder Panen | Admin dan Petugas lahan terkait | Setiap hari 06.00 |
| Ketidaksesuaian SOP | Admin tenant | Pemeriksaan setiap 30 menit |
| Laporan Insiden | Admin tenant | Pemeriksaan setiap 30 menit |

## Isi inbox

Weekly merekap Senin 00.00 sampai Minggu 20.00. Owner melihat total target
per komoditas per siklus dan hasil panen aktual seluruh lahan miliknya; target
mengikuti kalkulator dashboard. Admin melihat ringkasan aktivitas, berat panen,
insiden dan hambatan dari laporan Petugas per lahan/blok. Tanpa aktivitas/panen,
weekly tetap dikirim bila penerima memiliki lahan yang terlihat. Tanpa lahan,
tidak ada pengiriman.

Reminder hanya mengambil PlantingCycleTask kategori harvest, masih pending atau
in_progress pada siklus aktif yang sudah ditanam. Jadwal efektif memakai
rescheduled_date jika ada, selain itu scheduled_date. Milestone H-3, H-1 dan H-0
masing-masing dikirim sekali per tugas dan penerima. Reschedule tidak mengulang
milestone yang pernah dikirim. Tugas selain Panen tidak mendapat reminder.

Inbox reminder menampilkan jumlah subblok dan rincian berupa bullet nama subblok,
milestone dan tanggal jadwal pada setiap kartu aktivitas. Batas toleransi hanya
ditampilkan pada laporan ketidaksesuaian SOP. Nama subblok disimpan dalam snapshot
notifikasi baru; riwayat lama tanpa nama subblok tetap dapat dibaca.
Weekly Owner pada layar sempit memakai baris label/nilai per komoditas untuk
target per siklus, panen periode laporan, dan luas panen.

Ketidaksesuaian SOP mencakup dua kejadian independen pada tugas belum selesai:
- Jadwal ulang lebih akhir daripada scheduled_date + tolerance_days.
- Tugas belum selesai setelah jadwal efektif + tolerance_days (ketat setelah
  tanggal batas, bukan pada tanggal batas).

Setiap kondisi dikirim sekali per tugas/penerima, bukan setiap hari. Jika kondisi
kedua baru terpenuhi kemudian, kondisi tersebut menjadi temuan baru.

Laporan insiden mengambil record IncidentDetail yang terkait aktivitas Petugas,
melalui scope penerima. Edit pada insiden yang sama tidak memicu laporan ulang.
Kejadian baru dalam satu pemeriksaan digabung ke satu kartu per tenant/penerima.
Maksimal 500 insiden baru per pemeriksaan; sisanya diproses pada pemeriksaan
berikutnya. Saat pertama kali diaktifkan, insiden historis yang belum tercatat
di ledger juga termasuk backlog ini. Tidak ada pengiriman situasional jika kosong.

Inbox memiliki tab Belum dibaca dan Sudah dibaca serta filter kategori yang default ke Semua.
Tandai semua dibaca berlaku untuk seluruh notifikasi pribadi yang tampil di inbox,
termasuk kategori dan halaman lain. Pada viewport di bawah 640px, aksi tandai
dibaca per kartu berada pada menu titik tiga. Header membuka/menutup detail;
tombol Tandai dibaca terpisah dari tombol expand. Weekly Admin dan insiden
menautkan lahan ke /admin/logbook/{farm}. Temuan tugas menuju /admin/tasks
dengan filter lahan/blok: overdue untuk tugas terlambat, all untuk jadwal ulang
yang masih mendatang. Reminder Panen Admin dan Petugas dikelompokkan per lahan, blok, dan komoditas.
Setiap subblok menampilkan tanggal dan label waktu pada saat reminder dikirim,
diurutkan dari tanggal terdekat. Satu tombol Lihat tugas panen membuka filter
blok dan category=harvest dengan status/view Semua, mencakup H-0, H-1, dan H-3.
Halaman tujuan mempertahankan otorisasi.

## WhatsApp dan deduplikasi

WhatsApp hanya berisi judul pemberitahuan singkat dan tautan /notifications dari
APP_URL. Tidak ada rincian tugas, lahan, kabupaten, aktivitas, panen atau insiden.
Satu prompt per kartu/penerima; weekly tidak lagi dipecah per lahan/kabupaten.
FONNTE_ENABLED=true dan nomor valid/terdaftar tetap diperlukan.

operational_notification_events mencatat identitas kejadian per penerima secara
unik. operational_notification_deliveries menyimpan payload batch dan status
pengiriman. Klaim dilakukan dalam transaksi dengan kunci per penerima dan insert
batch; tidak ada query relasi per tugas/insiden. Pemeriksaan ulang mengecualikan
kejadian lama. Ledger harus dipertahankan meskipun inbox dibersihkan.

Batch yang belum sempat masuk antrean dipulihkan oleh resumePendingDeliveries
pada awal command dispatcher. Duplikasi job aman untuk inbox melalui ID unik.
Job WhatsApp memastikan record inbox sudah tersedia dan memvalidasi ulang akses,
role, tugas/insiden serta nomor setelah validasi provider. Status pending ->
sending diklaim atomik sebelum send; accepted tidak dikirim ulang. Penolakan
transient yang pasti boleh dicoba ulang sampai tiga kali (60/300/900 detik).
Hasil tidak pasti atau proses mati setelah send tidak dicoba ulang secara buta:
status sending/failed perlu ditinjau operator agar tidak menghasilkan spam.
Accepted provider bukan bukti pesan sampai di ponsel.

Payload kedaluwarsa setelah tanggal pengiriman. Job yang kedaluwarsa, kondisi
yang tidak lagi terpenuhi atau akses yang dicabut tidak mengirim prompt.
Scheduler tidak menjanjikan exactly-once penerimaan provider saat jaringan gagal;
ledger mencegah pengiriman ulang otomatis atas hasil yang tidak pasti.

## Deployment dan pengujian

Terapkan migrasi additive 2026_11_16_000001_create_operational_notification_delivery_ledger
pada database aplikasi setelah backup/review. CD menolak migrasi pending dan
tidak menjalankan migrasi otomatis. Database aplikasi dan VPS tidak dimigrasikan
oleh pengujian. Setelah deployment, rebuild cache konfigurasi dan restart worker
notifications,whatsapp serta scheduler. APP_URL harus mengarah ke web aplikasi
production, bukan localhost.

Ledger baru mulai mengingat kejadian setelah migrasi ini;
riwayat sebelum ledger tidak dianggap sebagai bukti pengiriman sekali.

Command berikut benar-benar mengantrekan notifikasi sesuai konfigurasi:

~~~sh
php artisan notifications:dispatch weekly-report
php artisan notifications:dispatch task-reminder
php artisan notifications:dispatch sop-deviation
php artisan notifications:dispatch incident-report
~~~

Tes menggunakan PostgreSQL pengujian terisolasi dan HTTP fake Fonnte. Cakupan
mencakup batas tenant/penugasan, milestone Panen, kedua batas SOP, insiden baru,
rollback/commit, retry worker, prompt WhatsApp, deduplikasi serta jumlah query
pada banyak lahan/tugas/insiden. Tidak mengirim WhatsApp sungguhan.
