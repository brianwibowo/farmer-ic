# Pengukuran halaman aktivitas petugas

Setiap GET `/petugas/aktivitas` mencatat event `petugas_activity_timing` pada
log Laravel level `info`. Pencatatan aktif secara default dan dapat dimatikan
dengan konfigurasi `ACTIVITY_TIMING_ENABLED=false`; bila konfigurasi di-cache,
bangun ulang cache dan restart worker setelah mengubah konfigurasi. Pastikan
channel log produksi menerima level `info`.
Saat dimatikan sebelum aplikasi atau worker dimulai, listener query tidak
didaftarkan dan timer tahap controller tidak dijalankan. Perubahan konfigurasi
memerlukan proses baru agar pendaftaran listener mengikuti nilai terbaru.

Pada request yang menerima respons, cocokkan header `X-Activity-Trace-Id` di
DevTools dengan `trace_id` pada log. Filter berdasarkan nama event, misalnya
`rg petugas_activity_timing storage/logs/laravel.log` bila menggunakan channel
single; lokasi lain mengikuti konfigurasi logging deployment.

- `laravel_ms`: waktu dari awal middleware global sampai respons selesai
  dibentuk oleh pipeline Laravel. Tidak mencakup bootstrap sebelum middleware,
  antrean web server/PHP worker, pengiriman respons, atau jaringan.
- `listing_ms`: pemuatan, perhitungan ringkasan, dan pembentukan daftar tugas.
- `web_setup_ms`, `web_setup_queries`, `web_setup_query_ms`: waktu dan query
  dari awal middleware web hingga middleware sebelum Inertia, termasuk awal
  sesi. Penyimpanan sesi setelah respons tidak termasuk tahap ini.
- `shared_props_ms`, `shared_props_queries`, `shared_props_query_ms`: waktu dan
  query untuk membangun shared props dan keputusan navigasi yang dihitung
  langsung. Prop berupa closure yang dievaluasi kemudian tidak termasuk.
- `web_pipeline_ms`: durasi seluruh middleware web termasuk pekerjaan setelah
  respons, seperti penyimpanan sesi. `inertia_ms` adalah durasi middleware
  Inertia termasuk shared props dan route; `route_ms`, `route_queries`, dan
  `route_query_ms` mencakup middleware route, controller, serta pembentukan
  respons. Ketiganya saling mencakup, jadi tidak boleh dijumlahkan.
- `controller_ms`: durasi aksi controller hingga objek respons Inertia
  dikembalikan, termasuk pemuatan data dan pemeriksaan izin. `route_ms` juga
  mencakup middleware route dan pembentukan respons sesudah controller.
  `inertia_visit` menunjukkan header `X-Inertia: true`; `false` dapat berupa
  kunjungan HTML awal. Tahap-tahap tersebut saling mencakup.
- `ssr_ms` dan `ssr_result` mencatat durasi serta hasil panggilan HTTP ke
  gateway Inertia SSR jika dijalankan; `null` berarti tidak ada panggilan SSR
  yang tercatat. URL, isi request, dan pesan kegagalan tidak dicatat.
- `ssr_vite_ms` adalah waktu yang diukur middleware endpoint SSR development
  Vite, sejak request tiba hingga respons disiapkan; nilai `null` berarti
  header timing tidak tersedia (misalnya SSR produksi atau panggilan gagal).
  `ssr_ms` mencakup waktu tersebut ditambah perjalanan HTTP dan antrean di
  luar middleware. Selisihnya bukan ukuran murni latensi jaringan. Vite membaca
  `ACTIVITY_TIMING_ENABLED` saat server dimulai; restart Vite setelah mengubah
  pengaturan, dan pastikan cache konfigurasi Laravel sesuai.
- `permissions_ms`: loop pemeriksaan ketersediaan tindakan per tugas, termasuk
  query dan aturan workflow. Ini bagian dari `laravel_ms`, bukan tambahan.
- `query_count` dan `query_ms`: jumlah dan total durasi query yang tercatat
  selama middleware aktif, termasuk query dalam kedua tahap di atas.
- `task_count`: jumlah tugas pada halaman yang diproses, setelah filter dan
  pagination backend; bukan jumlah seluruh tugas yang cocok dengan filter.
- `page` dan `per_page`: nomor halaman dan ukuran halaman yang digunakan oleh
  paginator. Keduanya `null` jika paginator tidak dijalankan, misalnya ketika
  validasi gagal atau petugas belum mendapat kebun.
- `status`: status respons; `null` jika exception keluar dari pipeline sebelum
  respons terbentuk. Pada kasus itu trace ID hanya ada di log.

Tahap yang tidak dijalankan, misalnya ketika petugas belum mendapat kebun,
dicatat sebagai `null`. Log tidak memuat SQL, parameter, ID akun, atau isi
request. Pengukuran ini tidak memecah antrean dan jaringan dari TTFB; bandingkan
dengan DevTools dan metrik proxy/worker. Query yang gagal sebelum Laravel
memancarkan event `QueryExecuted` tidak termasuk hitungan.

## Pengukuran halaman laporan

GET `/petugas/laporan` menggunakan konfigurasi `ACTIVITY_TIMING_ENABLED` yang
sama dan mencatat event `petugas_report_timing`. Cocokkan `trace_id` dengan
header respons `X-Report-Trace-Id`. `laravel_ms`, `query_count`, `query_ms`,
`page`, dan `per_page` memiliki arti dan batas yang sama seperti di atas.
`report_count` adalah jumlah laporan pada halaman yang diproses; `listing_ms`
mencakup pagination dan pembentukan ringkasan laporan, `stats_ms` menghitung
jumlah SOP dan mandiri, dan `categories_ms` menyiapkan pilihan aktivitas.
Ketiga waktu tahap tersebut termasuk dalam `laravel_ms` dan dapat mencakup
waktu query, sehingga tidak dijumlahkan dengan `query_ms`. Tahap yang tidak
dijalankan dicatat `null`; SQL, binding, filter, dan identitas tidak dicatat.
