# Audit dan optimasi query notifikasi

Checkpoint: 1 November 2026. Cakupan meliputi inbox HTTP, pemilihan penerima,
daily report, harvest reminder, dan validasi penerima pada queue. Pengukuran
menggunakan PostgreSQL pengujian, tanpa request Fonnte nyata.

## Hasil yang diukur

| Bagian | Sebelum | Sesudah |
| --- | --- | --- |
| Pemrosesan 15 kartu daily report Owner | 60 query | 1 query membership untuk satu operasi |
| Pemrosesan 15 kartu daily report Admin | 30–45 query | 1 query membership untuk satu operasi |
| Pilihan 2 tenant Owner | 5 query | 2 query |
| HTTP inbox: 1 vs 15 kartu | Bertambah per kartu | Jumlah query sama, diuji untuk Owner/Admin/Petugas |
| Daily report: 1 vs 8 kebun berisi aktivitas | Bertambah per kebun | Jumlah query sama |
| Reminder panen: 1 vs 30 siklus | Bertambah saat policy memuat farm/team | Jumlah query sama; H-7/H-3/H-0 tetap tercakup |
| Pilihan tenant: 1 vs 8 tenant | Bertambah per tenant | Jumlah round trip sama |
| Validasi snapshot queue: 1 vs 8 kebun | Pemeriksaan per Gate berulang | Jumlah query sama |
| Job WhatsApp saat provider dinonaktifkan | Memvalidasi data penerima dahulu | 0 query database dan 0 request provider |

Angka pemrosesan kartu hanya mencakup `messageFor()` dan Gate `update`, beserta
pembacaan membership untuk operasi tersebut. Angka itu bukan total request HTTP:
query daftar, pagination, unread count, middleware, dan navigasi tetap ada. Tes
HTTP terpisah memastikan total query tidak bertambah saat jumlah kartu bertambah.

## Keputusan implementasi

1. `RolePermissions::withMembershipSnapshot()` membaca membership tersimpan
   sekali untuk satu callback. Snapshot memakai identitas instance `User`, tidak
   disimpan di database, session, atau cache antar-request. Blok `finally`
   membuangnya, termasuk ketika callback gagal. Di luar callback, helper
   membership tetap memeriksa database seperti sebelumnya.
2. Controller inbox membatasi snapshot pada proses otorisasi dan pembuatan props.
   Setiap kartu tetap melalui Gate `update`; kepemilikan record tetap diperiksa.
3. Dispatcher menggunakan `lazyById(100)` dengan eager loading `role`, `teams`,
   dan `assignedFarm.team` untuk Petugas. `reload: false` hanya digunakan untuk
   hasil batch baru ini, bukan model lama dari request atau queue.
4. Daily report mengambil aktivitas seluruh kebun yang terlihat dalam satu batch
   per penerima–tenant, eager-load jenis aktivitas, mengambil nama pelaku melalui
   `User::visibleTo()`, lalu mengelompokkan hasil berdasarkan `farm_id`. Nama
   pelaku yang tidak terlihat atau sudah dihapus tetap memakai fallback lama.
5. Reminder mengambil tiga tanggal panen dalam satu query per penerima. Siklus
   memakai kembali instance farm tersimpan yang membatasi query tersebut, sehingga
   Gate per siklus tidak memuat farm/team kembali.
6. Pilihan tenant menggabungkan query `Team::visibleTo()` dengan SQL `UNION`.
   Scope per tenant dipertahankan tanpa menjalankan `exists()` per pilihan.
7. `validRecipient()` selalu mengambil user, role, membership, farm, dan jadwal
   terbaru untuk setiap pemanggilan. Snapshot sebelum request `/validate`
   berakhir sebelum snapshot sesudah request dibuat. Perubahan membership,
   assignment, status aktif, role, dan jadwal tetap dapat membatalkan pengiriman.

Tidak ada perubahan grant, batas tenant, assignment Petugas, atau format pesan.
Tidak ada indeks atau migrasi baru dalam optimasi ini. Indeks inbox
`(user_id, is_read, created_at)` dan aktivitas `(farm_id, activity_date)` sudah
tersedia pada skema.

## Batas kesimpulan

Pada cakupan dan skala pengujian ini, temuan N+1 High/Medium pada audit awal sudah
ditangani. Sisa risiko dinilai **Low untuk prototype**: pekerjaan tetap bertambah
sesuai jumlah penerima–tenant dan job yang memang harus dibuat; data laporan dan
payload tetap bertambah sesuai jumlah aktivitas. Ini bukan hasil load test
production atau jaminan latensi pada volume data tanpa batas. Konteks otorisasi
dibaca ulang per job; menghapus pemeriksaan itu demi mengurangi query dapat
mengirim laporan kepada penerima yang aksesnya sudah dicabut.

`NotificationQueryPerformanceTest` menguji pertumbuhan query dan masa hidup
snapshot. `NotificationWorkflowTest` juga menguji perubahan akses ketika Fonnte
sedang memvalidasi registrasi nomor. Seluruh respons provider dalam tes ditiru.

## Verifikasi checkpoint

- Suite penuh PostgreSQL: **405 lulus, 1 dilewati, 8.506 assertions** (406 tes).
  Tes yang dilewati berasal dari fitur Fortify 2FA yang dinonaktifkan.
- PHPStan pada keenam file runtime yang berubah: 0 error. Pint lulus.
- Build frontend lulus, dengan warning lama paket `fontaine` opsional dan chunk
  `Laporan` berukuran besar.
- Probe query pada database browser pengujian memakai transaksi read-only.
  Tidak ada request validasi atau pengiriman WhatsApp nyata.
- Suite penuh dijalankan dengan `max_execution_time=0` dan
  `AI_EXECUTION_TIMEOUT=0` khusus proses pengujian: controller AI yang sudah ada
  dapat mengatur kembali batas PHP menjadi 120 detik saat tes stream berjalan.
  `.env`, konfigurasi PHP aplikasi, dan kode AI tidak diubah.

Worker aplikasi yang sudah berjalan perlu memuat ulang kode melalui
`php artisan queue:restart`. Restart worker lingkungan aplikasi tidak dijalankan
dalam pengujian ini.

## File utama

- [RolePermissions](../app/Support/RolePermissions.php)
- [NotificationPolicy](../app/Policies/NotificationPolicy.php)
- [NotificationController](../app/Http/Controllers/NotificationController.php)
- [NotificationInboxService](../app/Services/Notifications/NotificationInboxService.php)
- [NotificationService](../app/Services/Notifications/NotificationService.php)
- [SendWhatsAppNotification](../app/Jobs/SendWhatsAppNotification.php)
- [Pengujian pertumbuhan query](../tests/Feature/Notifications/NotificationQueryPerformanceTest.php)
- [Pengujian alur dan perubahan akses](../tests/Feature/Notifications/NotificationWorkflowTest.php)
