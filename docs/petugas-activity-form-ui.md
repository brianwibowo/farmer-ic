# Form Aktivitas Petugas (frontend-only)

Dokumen ini adalah handoff teknis untuk halaman input aktivitas Petugas. Baca sebelum mengubah route, UI, validasi, atau menghubungkan form ke backend. Halaman ini adalah prototype frontend-only, bukan sumber data operasional.

## Status dan batas implementasi

| Area | Status |
| --- | --- |
| Route dan halaman Inertia | Selesai |
| Navigasi Petugas dan menu aktif pada sub-route | Selesai |
| UI responsive, mobile-first, field kondisional | Selesai |
| Validasi ringan di browser | Selesai |
| Pemilihan file foto lokal | Selesai, belum diunggah |
| Database, controller submit, request, service | Belum dibuat |
| Master blok/sub-blok sebagai pilihan | Belum dihubungkan |
| Reuse header harian, append aktivitas, riwayat | Belum dihubungkan |

Tidak ada migration, seeder, model, controller, request, policy, service, API, atau perubahan database yang dibuat oleh slice UI ini. Tombol Validasi aktivitas hanya menjalankan validasi lokal dan tidak mengirim HTTP request.

## Sumber acuan dan urutan baca

1. [AGENTS.md](../AGENTS.md) — arsitektur Laravel, RBAC, data, pengujian, dan larangan perubahan .env.
2. [design.md](../design.md) — profil Farmer/mobile, token tema, state input, aksesibilitas, dan navigasi subhalaman.
3. [update-kategori-dan-data-aktivitas.md](update-kategori-dan-data-aktivitas.md) — source of truth target field dan aturan aktivitas.
4. [daily-activity-log.md](daily-activity-log.md) — kondisi backend logbook saat ini serta perbedaannya dengan target form.
5. [farm-block-backend.md](farm-block-backend.md) — master blok, batch, cycle, dan batas akses untuk integrasi blok/sub-blok berikutnya.
6. [rbac-authentication-guide.md](rbac-authentication-guide.md) dan [authorization-scaffolding.md](authorization-scaffolding.md) — pola Gate, Policy, dan visibleTo untuk backend persisten.

Jika ada konflik, AGENTS.md dan design.md mengatur implementasi; dokumen kategori aktivitas mengatur kontrak domain form.

### Peta dokumentasi repository

| Kelompok | Dokumen | Gunakan saat |
| --- | --- | --- |
| Governance | `AGENTS.md`, `design.md` | Semua perubahan kode dan UI. |
| Auth/RBAC | `docs/rbac-authentication-guide.md`, `docs/authorization-scaffolding.md`, `docs/authorization-review.md`, `authorization_implementation_steps.md` | Menambah route, resource, policy, atau akses role. |
| Aktivitas dan lahan | `docs/update-kategori-dan-data-aktivitas.md`, `docs/daily-activity-log.md`, `docs/farm-block-backend.md`, `docs/farm-management.md`, `docs/proposals/phase-1-erd.md` | Mengubah form, daily log, blok, cycle, atau schema pertanian. |
| Dashboard dan finance | `docs/dashboard-data-reference.md`, `docs/finance-backend.md` | Mengubah card dashboard, agregat, atau data keuangan. |
| User dan demo | `docs/user-management.md`, `docs/demo-seed-credentials.md` | Mengubah manajemen user atau menyiapkan demo lokal. |
| AI/RAG | `docs/ai-chat.md`, `ai-services.md`, `rag.md`, `execution_rag.md`, `list-of-prompts-based-on-intents.md`, `resources/ai/knowledge/*.md` | Mengubah AI, knowledge base, evaluasi intent, atau RAG. |
| Operasional | `DEPLOYMENT.md`, `P4_RELEASE_CHECKLIST.md`, `docs/technical-debt.md` | Deploy, release, atau keputusan perbaikan jangka panjang. |
| Aset | `public/images/farmer/growth/ATTRIBUTION.md` | Mengubah atau menambah aset fase pertumbuhan. |

Dokumen ini tidak menggantikan dokumen-dokumen tersebut. Ia adalah indeks dan handoff untuk slice form Petugas; baca dokumen domain yang sesuai sebelum mengubah kontrak aplikasi.

## Akses dan alur pengguna

Route Petugas memakai middleware auth, verified, dan can:petugas.access.

| Method | URL | Page Inertia | Kegunaan |
| --- | --- | --- | --- |
| GET | /petugas/aktivitas | petugas/Aktivitas | Entry point aktivitas dan CTA form |
| GET | /petugas/aktivitas/buat | petugas/ActivityCreate | Form aktivitas frontend-only |

    Bottom navigation: Aktivitas
        -> /petugas/aktivitas
        -> klik Buat aktivitas
        -> /petugas/aktivitas/buat
        -> validasi lokal; tidak ada persistence

PetugasLayout mencocokkan URL exact terlebih dahulu. Untuk sub-route, ia memilih URL induk paling spesifik. Oleh karena itu /petugas/aktivitas/buat tetap menyalakan menu Aktivitas, bukan Beranda (/petugas). Pertahankan perilaku ini untuk create, detail, dan edit Petugas berikutnya.

## File inventory

### File baru

| File | Tanggung jawab |
| --- | --- |
| resources/js/pages/petugas/ActivityCreate.vue | State form lokal, kategori/subkategori, field kondisional, validasi browser, dan style field. |
| resources/js/components/petugas/activity-form/AreaField.vue | Luas area dan satuan m²/Ha. |
| resources/js/components/petugas/activity-form/TextField.vue | Input teks/angka yang dapat dipakai ulang. |
| resources/js/components/petugas/activity-form/TextAreaField.vue | Input multi-baris yang dapat dipakai ulang. |
| resources/js/components/petugas/activity-form/SelectField.vue | Select pasangan value/label yang dapat dipakai ulang. |
| docs/petugas-activity-form-ui.md | Dokumen handoff ini. |

### File existing yang diubah oleh slice ini

| File | Perubahan |
| --- | --- |
| routes/web.php | Menambah route bernama petugas.aktivitas.create. |
| resources/js/pages/petugas/Aktivitas.vue | Mengganti placeholder menjadi landing aktivitas dengan CTA form dan placeholder riwayat yang jujur. |
| resources/js/layouts/PetugasLayout.vue | Membuat navigation Petugas tetap aktif pada sub-route. |

### File yang sengaja tidak disentuh

- resources/js/routes/** adalah hasil generate/Wayfinder. Jalankan generator proyek bila memang perlu memperbaruinya; jangan mengedit output secara manual.
- Backend daily log di app/Http, app/Services, app/Models, migrations, dan tests tidak diubah oleh form UI ini.
- Perubahan dashboard, AI, atau worktree lain bukan bagian slice ini dan tidak boleh ikut di-commit tanpa audit terpisah.

## Struktur frontend

resources/js/app.ts sudah memilih PetugasLayout untuk semua page dengan prefix petugas/. ActivityCreate.vue tidak boleh membungkus dirinya dengan layout lain.

    app.ts
      -> PetugasLayout
           -> FarmerMobileShell / FarmerBottomNav
           -> ActivityCreate
                -> AreaField
                -> TextField
                -> TextAreaField
                -> SelectField

Gunakan Vue 3 script setup dan Composition API. Komponen field menerima v-model dan event bertipe TypeScript; jangan kembali ke Options API atau menyalin markup field ke halaman utama.

## Kontrak UI form

### Header aktivitas

| Field | UI sekarang | Target data |
| --- | --- | --- |
| Tanggal kerja | date, maksimal hari ini | Tanggal aktual kerja |
| Waktu mulai | time | State lokal saat ini |
| Waktu selesai | time | Harus setelah waktu mulai |
| Pelapor | Read-only dari page.props.auth.user | Kelak ditetapkan server dari user login |

### Field umum aktivitas

| Field | UI sekarang | Target domain |
| --- | --- | --- |
| Kategori | Select | Wajib |
| Subkategori | Select kondisional | Wajib bila kategori memerlukannya |
| Blok/sub-blok | Text label sementara | Select dari master blok/sub-blok |
| Jumlah pekerja | Number | Wajib, minimum 1, disimpan per aktivitas |
| Foto aktivitas | Multi-file picker | Wajib minimum satu; belum diunggah |
| Catatan aktivitas | Textarea | Opsional |

Label blok bukan bukti relasi master block. Jangan menggunakannya untuk otorisasi atau persistence. Saat backend dibuat, gunakan ID master block/sub-block yang terbukti milik farm terotorisasi.

## Kategori dan detail kondisional

| Kategori | Subkategori atau detail utama |
| --- | --- |
| Pembersihan lahan | Luas, metode; produk, dosis, air, tangki bila herbisida. |
| Pengolahan tanah | Penggemburan, bedengan, dolomit, pemupukan dasar. |
| Penanaman | Varietas, benih, pola tanam, luas, kedalaman. |
| Perawatan | Pengairan, pendangiran, pemupukan susulan, pertumbuhan, hama/penyakit. |
| Panen | Luas, berat/satuan hasil, kadar air, metode. |
| Pelaporan insiden | Kategori, deskripsi, area, tingkat kedaruratan, status, tindakan segera. |
| Lainnya | Deskripsi wajib dan luas opsional. |

Aturan domain yang wajib dipertahankan:

- Satu submit merepresentasikan satu aktivitas individual.
- Block mapping adalah form field-management terpisah dan boleh batch; jangan menjadikannya kategori aktivitas biasa.
- Foto minimal satu per aktivitas; harga/biaya dan HOK tidak masuk form Petugas.
- Kendala, solusi, dan tindakan bukan field umum; Tindakan segera hanya untuk insiden dan opsional.
- Blok/sub-blok, cycle/HST, dan task SOP adalah domain data tersendiri; jangan direka dari browser storage atau label UI.

## Validasi lokal

Fungsi validate() pada ActivityCreate.vue memeriksa:

- tanggal kerja terisi dan bukan masa depan;
- waktu selesai setelah waktu mulai;
- subkategori bila diperlukan;
- jumlah pekerja minimal satu;
- minimal satu foto dipilih;
- deskripsi untuk insiden dan kategori Lainnya.

Validasi browser bukan pengganti Form Request. Integrasi backend harus menegakkan server-side angka positif, persentase 0–100, satuan sak/pompa, blok/sub-blok, serta batas tanggal lainnya.

## Desain dan token input

Halaman memakai profil Farmer/mobile dari design.md dan token di resources/css/app.css.

| State | Implementasi petugas-field |
| --- | --- |
| Default/nonaktif | border 1px solid var(--border); background var(--background) |
| Hover | border-color var(--primary); background var(--card) |
| Focus/aktif | var(--primary) dengan focus ring primary 30% |
| Disabled | var(--disable) dan var(--muted-foreground) |
| Error text | var(--error) melalui petugas-error |

Token aplikasi menggunakan nilai hex. Dalam CSS biasa gunakan var(--token), misalnya border-color: var(--border). Jangan gunakan hsl(var(--border)); deklarasi itu invalid untuk token hex dan browser mengabaikan border.

Input memakai radius 12px (0.75rem) serta tinggi minimum 44px, sesuai target sentuh Farmer/mobile pada design.md dan AGENTS.md.

## Integrasi backend berikutnya

Jangan menghubungkan form langsung ke tabel atau membuat fetch dari Vue. Ikuti MVC + Service pada AGENTS.md:

    Inertia form
      -> route web terautentikasi
      -> Controller: Gate::authorize terhadap parent tepercaya
      -> Form Request: validasi server-side
      -> Service: transaksi dan Eloquent persistence
      -> redirect + flash/Inertia props

Target flow dari dokumen kategori aktivitas:

1. Cari daily_activity_logs terbuka untuk farm, pelapor, dan tanggal.
2. Jika belum ada, buat header berisi tanggal dan rentang kerja.
3. Simpan satu aktivitas individual pada header tersebut.
4. Aktivitas berikutnya dapat di-append ke header sama.
5. Jika dari SOP, simpan relasi task ke activity log.

Backend menyimpan `worker_count` per aktivitas (`farm_activities`) dan `notes` umum pada header harian bersama. Form Petugas mengirim jumlah pekerja bersama data aktivitas; jumlah tersebut bukan lagi field header.

### Ketentuan keamanan dan integritas

- Controller mengotorisasi farm/parent persisted lewat Gate::authorize(...).
- Jangan percaya farm_id, team_id, recorded_by, role, atau block ID dari browser sebagai bukti otoritas.
- Pelapor berasal dari request user, bukan field klien.
- Petugas hanya menulis pada farm assignment persisted; own bukan created_by.
- Read path memakai visibleTo(user, team) sebelum list, total, atau export.
- Gunakan transaksi untuk header, activity, detail, foto metadata, dan relasi task yang harus atomik.
- Tambahkan HTTP test 403 lintas team/farm dan test ID klien tidak dapat mengubah keputusan akses.

## Menjalankan dan verifikasi

1. Jalankan Laravel dan Vite dengan workflow lokal proyek.
2. Login sebagai user verified yang lolos petugas.access.
3. Buka /petugas/aktivitas, klik Buat aktivitas, atau buka langsung /petugas/aktivitas/buat.
4. Uji setiap kategori/subkategori, validasi wajib, dan pemilihan foto.
5. Pastikan bottom navigation Aktivitas tetap aktif di sub-route.
6. Uji light/dark theme dan lebar 375, 390, 430, 768, serta desktop shell.

Setelah perubahan frontend jalankan:

    npm.cmd run types:check
    npm.cmd run build

Warning optional fontaine atau chunk lebih dari 500 kB pada build perlu dicatat, tetapi bukan kegagalan build selama proses berakhir sukses.

## Checklist untuk agent atau kontributor berikutnya

- [ ] Baca seluruh dokumen pada bagian sumber acuan.
- [ ] Pastikan scope hanya UI atau telah mencakup otorisasi/backend secara jelas.
- [ ] Jangan edit route generated/Wayfinder secara manual.
- [ ] Gunakan Link Inertia dan pertahankan parent navigation match.
- [ ] Gunakan semantic token Farmer/mobile; jangan hardcode warna baru jika token tersedia.
- [ ] Untuk token hex, gunakan var(--token), bukan hsl(var(--token)).
- [ ] Jangan menjadikan state UI atau browser storage sebagai source of truth.
- [ ] Saat menambah persistence, ikuti Controller -> Policy -> Request -> Service dan tambahkan test authorization/integritas.
- [ ] Jalankan type-check dan production build.
- [ ] Perbarui dokumen ini serta dokumen kategori aktivitas bila kontrak berubah.

## Riwayat keputusan slice

- Form ditempatkan di /petugas/aktivitas/buat, tidak mengganti landing aktivitas utama.
- Landing hanya menawarkan CTA dan tidak memalsukan riwayat dari data statis.
- Blok/sub-blok masih berupa label karena master data belum menjadi prop page.
- Border field memakai token desain Farmer/mobile; perbaikan token hex dicatat agar tidak terulang.
- Tidak ada API/database integration yang disengaja pada tahap ini.

