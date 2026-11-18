# Panduan Menjalankan Test dan Handover QA

Panduan ini menggunakan tool dan susunan test yang saat ini tersedia di repository. Ini bukan klaim seluruh requirement telah disetujui atau seluruhnya otomatis diuji. Lihat [`business-requirements-register.md`](business-requirements-register.md) dan [`business-requirements-test-scenarios.md`](business-requirements-test-scenarios.md).

## 1. Rekomendasi susunan test

| Jenis | Tool saat ini | Tanggung jawab | Batas penggunaan |
| --- | --- | --- | --- |
| Unit backend | PHPUnit via Laravel | Aturan/perhitungan/validator/service kecil dengan input terkontrol. | Hindari menguji seluruh database atau layar untuk aturan murni. |
| Unit frontend | Vitest | Fungsi presentasi, composable, atau komponen terisolasi. | Tidak membuktikan persistensi backend atau akses server. |
| Module / feature | Laravel Feature tests | Kemampuan dari request sampai respons dan side effect utama dalam DB test. | Pilih path penting; jangan duplikasi kombinasi yang sama di semua level. |
| Integration | Laravel Feature/service tests | Relasi model/service/event/query scope/database/transaksi. | Gunakan DB test; simulasi provider eksternal. |
| E2E browser | Skrip CDP native khusus notifikasi | Alur notifikasi dalam Edge headless dan beberapa role/viewport. | Belum mencakup seluruh UI atau alur lintas modul. |
| Build / static checks | Vite Plus, Vue TS, Pint, PHPStan | Compile, type, format, dan analisis statis. | Tidak membuktikan business flow benar. |

**Rekomendasi:** pertahankan PHPUnit + Vitest; tambah otomatisasi berdasar risiko dan traceability BR. Gunakan E2E notifikasi yang sudah ada untuk regresi itu saja. Jangan pasang paket atau mengubah CI tanpa persetujuan; framework E2E umum dapat dievaluasi kemudian sebagai keputusan terpisah.

## 2. Keselamatan database test

Test Laravel memakai koneksi `pgsql_testing`. `tests/Support/TestDatabase.php` dapat membuat database dan menjalankan `migrate:fresh` di sana.

1. Pastikan PostgreSQL lokal berjalan dan dependency terpasang.
2. Kredensial test harus menuju PostgreSQL yang boleh membuat DB (hak `CREATEDB` dibutuhkan bila DB belum ada).
3. Nama DB wajib cocok `farmer_ic_crud_test_*` atau `farmer_ic_schema_test_*`; fixture browser notifikasi mensyaratkan akhiran `_notifications_browser`.
4. DB test wajib berbeda dari DB aplikasi. Tetap periksa konfigurasi sebelum mulai.
5. Jangan arahkan test ke DB bersama, staging, atau produksi: test dapat membangun ulang schema DB khusus.
6. Pengaturan dibaca dari `TEST_DB_HOST`, `TEST_DB_PORT`, `TEST_DB_DATABASE`, `TEST_DB_USERNAME`, `TEST_DB_PASSWORD`, `TEST_DB_SSLMODE` dengan fallback tertentu ke `DB_*`. Jangan commit kredensial atau `.env`.

Jangan menjalankan reset/migrasi destruktif atau seeder terhadap DB aplikasi. Jika gagal karena izin, minta DBA membuat DB test terpisah atau memberi hak minimal yang dibutuhkan; jangan menaikkan izin akun aplikasi sendiri.

## 3. Perintah lokal (Windows PowerShell)

Jalankan dari root repository. Gunakan `npm.cmd` jika PowerShell memblokir `npm.ps1`.

| Tujuan | Perintah |
| --- | --- |
| Satu file PHP | `php artisan test --compact tests/Feature/Farms/FarmManagementTest.php` |
| Suite Unit PHP | `php artisan test --testsuite=Unit --compact` |
| Suite Feature/Integration PHP | `php artisan test --testsuite=Feature --compact` |
| Semua test PHP | `php artisan test --compact` |
| Satu file Vitest | `npx.cmd vitest run resources/js/path/to/file.test.ts` |
| Semua Vitest | `npx.cmd vitest run` |
| File AI yang terpilih | `npm.cmd run test:ai` |
| Type-check | `npm.cmd run types:check` |
| Build frontend | `npm.cmd run build` |
| Cek lint/gaya | `npm.cmd run check -- --no-fmt` |
| Pint | `vendor\bin\pint --test` |
| PHPStan | `vendor\bin\phpstan analyse --debug --memory-limit=512M --no-progress` |

`test:ai` hanya menguji daftar file AI dalam `package.json`; bukan seluruh Vitest. `php artisan test` tidak menjalankan Vitest/E2E. Build/type/lint bukan test perilaku.

`composer test` menjalankan clear config, Pint, PHPStan dan Laravel tests; bukan build frontend, full Vitest, atau E2E. CI saat ini menjalankan route generation, build, lint, type-check, Pint, PHPStan, seluruh Vitest dan Laravel tests, lalu job Docker smoke terpisah.

### Simpan hasil lokal

Contoh membuat report JUnit pada folder output lokal:

```powershell
New-Item -ItemType Directory -Force storage/app/qa-results | Out-Null
php artisan test --log-junit=storage/app/qa-results/phpunit-feature.xml --testsuite=Feature
npx.cmd vitest run --reporter=junit --outputFile=storage/app/qa-results/vitest.xml
```

Periksa bahwa file benar-benar terbentuk karena opsi bisa berbeda menurut versi CLI. Jika tidak didukung, simpan transcript terminal dan isi execution record. Jangan commit hasil atau bukti sensitif tanpa review.

## 4. E2E browser notifikasi

File: `tests/Browser/notification-fixture.php`, `notification-router.php`, `notifications.mjs`. Fixture hanya mengizinkan database `pgsql_testing` dengan akhiran `_notifications_browser`, lalu mengosongkan dan membuat ulang schema database tersebut. Edge dijalankan headless via CDP. Output berada di `storage/app/notification-browser/`: screenshot, `results.json`, fixture IDs dan profil browser.

Jalankan pada tiga terminal PowerShell. Pastikan environment testing dan DB khusus benar:

```powershell
$env:APP_ENV = 'testing'
$env:DB_CONNECTION = 'pgsql_testing'
$env:TEST_DB_DATABASE = 'farmer_ic_crud_test_notifications_browser'
php tests/Browser/notification-fixture.php
```

Terminal kedua, dengan environment sama:

```powershell
php -S 127.0.0.1:9088 tests/Browser/notification-router.php
```

Terminal ketiga:

```powershell
$env:NOTIFICATION_BROWSER_ORIGIN = 'http://127.0.0.1:9088'
node tests/Browser/notifications.mjs
```

Periksa exit code dan `storage/app/notification-browser/results.json`; screenshot per role/viewport juga ada di folder. Hentikan server setelah selesai. Script menetapkan Edge di `C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe`; lokasi berbeda membuat runner gagal. Ini hanya E2E notifikasi, bukan E2E kebun, Petugas, SOP, Owner, atau Finance.

## 5. Hasil CI/GitHub

Untuk PR, buka **Checks / Actions → PHP and frontend checks** untuk log perintah dan hasil. Ada job Docker PostgreSQL smoke terpisah. Workflow mengunggah metadata image release pada push `main`, bukan report PHPUnit/Vitest untuk setiap PR. Jadi hasil terperinci CI tersedia di log Actions; report QA lokal dapat disimpan di `storage/app/qa-results/`. Perubahan agar workflow mengunggah report JUnit/artifact setiap PR merupakan usulan terpisah; dokumen ini tidak mengubah CI.

## 6. Alur QA/handover yang disarankan

1. Tinjau BR bersama process owner; selesaikan pertanyaan dan ubah `Draft` ke `Approved` setelah disetujui.
2. Pastikan setiap BR Approved terhubung dengan TC; aturan akses merujuk register RBAC yang disepakati.
3. Buat dataset sintetis, batas nilai, empty state, aktor, expected result independen, dan rencana reset aman.
4. Jalankan unit terpilih → module/feature → integration → Vitest/browser relevan.
5. Sebelum handover jalankan regression: type/lint/build, semua Vitest, semua PHP tests dan E2E relevan. Hasil teknis hijau tidak berarti semua BR lulus.
6. Catat commit, environment, tool/version, hasil aktual, bukti, defect dan reviewer untuk setiap eksekusi.
7. `Failed` = expected result BR approved tidak terpenuhi; `Blocked` = keputusan/dependency/lingkungan menghalangi; `Not Run` = belum dicoba.
8. Serahkan matriks BR↔TC, ringkasan run, report/bukti, defect, risiko residual dan sign-off.

## 7. Format ringkasan run untuk handover

```text
QA Run ID:
Tanggal / zona waktu:
Commit / branch / build:
Environment (local / CI / staging):
Database test identifier (tanpa password):
Scope: BR IDs and TC IDs:
Tools and versions:
Commands executed:
Summary: Passed __ | Failed __ | Blocked __ | Not Run __
PHPUnit result/report:
Vitest result/report:
Build/type/lint result:
Browser E2E result and evidence folder:
Defect IDs, severity, owner:
Known limitations / decisions pending:
Reviewer / sign-off:
```

## 8. Batas saat ini

- Skenario ini rancangan manual/automation target; belum ditambahkan sebagai test code dan belum dijalankan.
- E2E browser otomatis yang ditemukan hanya untuk notifikasi; belum ada E2E umum lintas Admin → Petugas → Owner.
- Banyak BR masih `Draft`/`Decide` (approval, HST, rumus KPI/proyeksi, finance, kanal notifikasi, offline mobile). Jangan mengunci perilaku yang belum disepakati.
- Tidak ada dependency baru, perubahan CI, `.env`, source aplikasi, atau database dalam pekerjaan dokumentasi ini.
