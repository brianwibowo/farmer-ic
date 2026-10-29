# User Management untuk Superadmin, Owner, dan Admin Tenant

Halaman tersedia di **/admin/user-management**, melalui menu **User Management**
di sidebar atau navigasi header. Fitur memakai PostgreSQL, Gate/Policy Laravel,
dan Inertia. Soft delete pengguna memerlukan migration normal Laravel; tidak ada paket tambahan.

## Batas akses

- Superadmin mengelola semua user dan assignment tenant melalui Gate::before.
- Owner dapat melihat dan mencari user, tenant, serta kebun dari seluruh
  tenant non-personal yang terasosiasi dengannya. Ia memiliki empat filter
  seperti Superadmin, tetapi tidak dapat membuat, mengubah, menghapus, atau
  mengatur assignment. Akun dan pilihan role Superadmin tidak ditampilkan,
  termasuk ketika Superadmin tergabung dalam tenant yang sama. Data tenant yang
  tidak terasosiasi tidak ditampilkan.
- Admin harus memiliki tepat satu tenant non-personal yang masih aktif. Admin
  hanya melihat dan mengelola akun Admin/Petugas yang memiliki satu tenant yang
  sama; Owner, Superadmin, akun tenant lain, dan akun dengan asosiasi tenant
  tidak konsisten tidak muncul pada daftar, total, detail, maupun mutasi.
- Admin boleh membuat akun Admin dan Petugas baru dalam tenant-nya. Role akun
  yang sudah ada tidak boleh diubah oleh Admin, termasuk Admin ke Petugas atau
  sebaliknya. Field role pada edit dikunci dan request manipulasi ditolak Policy.
- Pemindahan atau penghapusan asosiasi tenant hanya dapat dilakukan Superadmin.
  Untuk Admin, tenant diambil dari membership tersimpan, bukan current_team_id
  atau ID yang dikirim peramban.
- Admin hanya dapat mengubah assignment farm Petugas dalam tenant-nya ke farm
  yang dapat dilihat dalam tenant yang sama. Assignment tidak berlaku pada Admin.
- Pada form Admin, pemilihan tenant disembunyikan karena tenant ditentukan dari
  membership Admin; hanya field farm Petugas yang ditampilkan. Superadmin tetap
  dapat memilih tenant melalui form.
- Petugas tidak memperoleh akses ke halaman User Management.

## Fitur

- Daftar user, pencarian nama/email, filter role/tenant/kebun, dan pagination.
- Filter kebun menampilkan user yang ditugaskan langsung ke kebun tersebut;
  pilihan dan hasil tetap dibatasi oleh scope kebun pengguna. Saat tenant diubah,
  pilihan kebun yang tidak termasuk tenant tersebut dikosongkan.
- Filter tenant ditampilkan untuk Superadmin dan Owner. Admin hanya melihat filter nama,
  role, dan kebun; daftar kebun tetap dibatasi pada tenant yang terasosiasi.
- Ringkasan jumlah user, owner, admin, dan petugas.
- Tambah, lihat, ubah, hapus, serta password baru opsional pada edit.
- Assignment tenant/farm dalam formulir yang sama dengan data akun.
- Konfirmasi penghapusan dan pesan validasi server.
- Halaman **User terhapus** untuk Superadmin dan Admin tenant, dengan konfirmasi
  pemulihan. Admin hanya melihat dan memulihkan Admin/Petugas pada tenant-nya;
  Superadmin dapat memulihkan seluruh akun. Owner dan Petugas tidak memiliki akses.

## Aturan assignment

| Role aplikasi | Tenant non-personal | Farm individual |
| --- | --- | --- |
| Owner | Boleh beberapa tenant | Tidak digunakan |
| Admin | Maksimal satu tenant; satu tenant boleh memiliki banyak admin | Tidak digunakan |
| Petugas | Maksimal satu tenant; satu tenant boleh memiliki banyak petugas | Maksimal satu farm dari tenant yang dipilih |
| Superadmin | Opsional; akses global tidak memerlukan membership | Tidak digunakan |

Satu farm boleh ditugaskan kepada beberapa petugas. Setiap petugas menyimpan
satu UUID pada users.assigned_farm_id; tidak ada pivot farm_user atau unique
constraint pada assigned_farm_id.

Akun tanpa assignment hanya dapat dibuat oleh Superadmin untuk onboarding.
Akun yang dibuat Admin otomatis berada di tenant Admin tersebut. Role saja tidak memberi
akses operasional tenant. Tenant personal/deleted, farm tanpa tenant, dan farm
dari tenant lain ditolak server. UI mengosongkan farm saat tenant diganti;
service memeriksa kembali relasi tersimpan dalam transaksi.

Membership personal yang sudah ada dipertahankan. Current team dipertahankan
jika membership masih ada; jika tidak, dipilih tenant baru atau membership
personal yang tersisa. Tanpa membership, current_team_id menjadi null.

Role aplikasi terpisah dari team_members.role. Membership baru memakai role
starter "member", sedangkan role membership lama dipertahankan. Izin aplikasi
tetap berasal dari users.role_id dan config/access.php. Assignment owner tidak
mengubah farm.owner_id atau atribusi catatan operasional.

## Implementasi

| Bagian | File |
| --- | --- |
| Route | routes/web.php |
| Controller | app/Http/Controllers/UserManagementController.php |
| Request | app/Http/Requests/Users/ |
| Service | app/Services/UserManagementService.php |
| Policy | app/Policies/UserPolicy.php dan ResourcePolicy.php |
| Gate | app/Providers/AuthorizationServiceProvider.php |
| Halaman | resources/js/pages/users/Index.vue, Form.vue, Show.vue |
| Assignment | resources/js/components/users/UserAssignments.vue |
| Dialog hapus | resources/js/components/users/DeleteUserDialog.vue |
| Layout | resources/js/layouts/UserManagementLayout.vue |
| Test | tests/Feature/Users/UserManagementTest.php dan TenantUserManagementTest.php |

Route memakai auth, verified, dan Gate users.manage. Gate users.manage memberi
akses Admin dengan satu tenant sah dan Owner dengan minimal satu tenant
non-personal serta grant users.view. Route admin lain tetap memakai admin.access.
Controller memanggil UserPolicy sebelum Service. Definisi normal users.manageAll
dan access.global tetap false; hanya Gate::before Superadmin yang meloloskannya.
Tidak ada daftar grant Superadmin tambahan.

CRUD mengikuti users.view/create/update/delete di config/access.php. Grant
users.configure mengatur perubahan assignment pada akun lama; grant ini tidak
mengizinkan Admin mengganti role akun lama atau tenant. Jika grant configure
dicabut, perubahan profil/password masih mengikuti users.update, sedangkan
assignment menjadi read-only. Assignment awal pada akun baru merupakan bagian
dari users.create. SaveUserRequest melakukan pemeriksaan record sebelum validasi
agar target tersembunyi ditolak lebih awal; Controller memeriksa ulang dengan
konteks role dan assignment yang diusulkan.

Query memakai visibleTo($actor). Team boleh kosong hanya untuk akses global
yang lolos access.global melalui Gate::before. Actor lain tanpa Team mendapat
query kosong; alur tenant biasa tetap memakai visibleTo($actor, $team).
Untuk Owner, service menggabungkan query visibleTo($actor, $team) untuk setiap
tenant non-personal yang terasosiasi. Scope yang sama berlaku pada pencarian,
filter, pagination, ringkasan, data tenant terkait, dan pilihan farm. Query
visibleTo($owner) tanpa Team tetap kosong dan access.global tetap ditolak.
Policy `view` menerima konteks User Management dari controller agar detail
Owner juga membutuhkan tenant non-personal bersama. Filter memakai ID sebagai
kriteria pencarian setelah scope diterapkan; ID tenant/kebun di luar scope atau
yang tidak ada menghasilkan daftar kosong.

UserPolicy membatasi query Admin pada role Admin/Petugas serta satu tenant yang
sah. Serializer hanya memuat tenant dan farm yang termasuk scope; farm di luar
tenant yang tersimpan secara tidak konsisten tidak dibocorkan, termasuk UUID-nya.
Pembatasan User ini juga berlaku bagi query referensi melalui User::visibleTo:
Admin tidak mendapat pilihan data Owner, termasuk dropdown owner pada modul Farm.

Service memvalidasi role melalui RolePermissions::isKnownRole sebelum
mengasosiasikannya. role_id tidak diterima langsung atau ditambahkan ke fillable
User. Data Inertia diserialisasikan secara eksplisit tanpa password, remember
token, atau rahasia autentikasi. Navigasi dan tombol memakai boolean dari Gate.
Model User menggunakan SoftDeletes dan kolom users.deleted_at.

Seluruh tujuh route User Management beserta query daftar, ringkasan, detail,
dan pilihan assignment sudah dilindungi. Halaman demo publik yang ada pada
route /, /finance, /kebun, /kebun/detail, /kebun/aktivitas, /aktivitas,
/rincian-aktivitas, dan /laporan tetap merupakan halaman publik; pekerjaan ini
tidak mengubahnya menjadi endpoint operasional terlindungi.

## Password dan penghapusan

Password di-hash melalui cast User. Password kosong pada edit mempertahankan
hash lama. Password baru user lain mengganti remember token dan menghapus
session database user tersebut; driver session lain mengikuti mekanisme driver.
Perubahan email menghapus timestamp verifikasi lama. Tidak ada pengiriman
email kredensial otomatis.

Akun sendiri tidak dapat dihapus atau diganti role-nya. Minimal satu
akun superadmin dipertahankan; row role developer dikunci untuk mengurutkan
perubahan bersamaan.

Penghapusan akun adalah soft delete: users.deleted_at diisi, akun tidak muncul
dalam query Eloquent biasa dan tidak dapat login. Session database dihapus dan
remember token diganti. Membership serta referensi data operasional dan AI tetap
tersimpan. Email akun yang dihapus tetap dicadangkan oleh constraint unik; belum
ada penghapusan permanen di halaman ini. Untuk memulihkan akun, buka **User
terhapus**, pilih **Pulihkan**, lalu konfirmasi. Role, membership, dan assignment
yang masih tersimpan kembali berlaku; sesi lama tetap tidak berlaku. Aksi ini
memerlukan grant `users.restore` dan `users.view` untuk Admin serta tenant yang
sah. Soft delete pada model User juga berlaku pada
alur penghapusan profil sendiri. Relasi Eloquent ke User yang dihapus tidak
ditampilkan secara default; kebutuhan riwayat harus memakai withTrashed secara
terbatas dan tetap diautorisasi.

Soft delete memperbarui `deleted_at`, sehingga foreign key `ON DELETE CASCADE`
tidak dijalankan. Ini berbeda dengan `forceDelete()` atau penghapusan fisik SQL,
yang tetap mengikuti aturan foreign key dan bukan bagian fitur ini.

## Validasi

Implementasi UI mengikuti Farm Management dan profil desktop admin pada
[design.md](../design.md): layout, warna, tipografi, field, kartu ringkasan,
tabel, dan dialog memakai pola serta kelas yang sama. Field memiliki label,
pesan validasi, dan focus state. Navbar menyediakan pengalih tema; navigasi
mobile menggunakan sidebar yang sama dengan Farm Management.

Pada validasi fitur sebelumnya, pengujian memakai PostgreSQL test terpisah,
bukan farmer_ic_dev. Dua puluh tes fitur User Management lulus dengan 467 assertion, mencakup akses global, CRUD Admin tenant,
penolakan Owner/Superadmin/lintas tenant, batas assignment, role edit yang
immutable bagi Admin, perubahan role bersamaan, rollback, password, penghapusan,
dan props tanpa rahasia. PHPStan, TypeScript, frontend lint, Pint untuk PHP yang
diubah, dan build frontend lulus pada saat itu.

Pada validasi fitur sebelumnya, browser Edge headless memeriksa CRUD Superadmin dan Admin tenant,
perubahan role oleh Superadmin, role edit yang dikunci bagi Admin, pembatasan
pilihan tenant/farm, dan reset farm saat Superadmin mengganti tenant. Tampilan
desktop/mobile 320–1.440 px diperiksa dalam mode terang dan gelap tanpa overflow
halaman atau error console. Pada penyesuaian desain berikutnya, halaman daftar,
tambah, detail, dan edit dibandingkan dengan Farm Management pada lebar 320,
375, dan 1.440 px, dalam tema terang dan gelap. Validasi visual ini memakai
sesi fixture pada database test terpisah. Build dan lint frontend lulus;
TypeScript gagal karena helper Wayfinder `.form` tidak tersedia pada beberapa
route autentikasi dan team yang sudah ada.

Suite repository juga diperiksa: 290 tes, 286 lulus, 3 gagal, 1 dilewati,
6.715 assertion. Satu skip adalah tes challenge two-factor Fortify yang sudah
memiliki alasan skip. Kegagalan pada tes yang sudah ada berkaitan dengan:

- AdminPortalTest mengharapkan dashboard tenant lama tidak tersedia, sementara
  routes/web.php masih mendaftarkannya.
- ModelAuthorizationTest mengharapkan memberships.create untuk admin; matriks
  saat ini hanya memberikan memberships.view.
- TeamTest mengharapkan teams.leave diizinkan meskipun grant-nya tidak ada.

Pada validasi berikutnya, route dashboard tenant dipertahankan dan ekspektasi
tes diselaraskan dengan grant yang berlaku. Redirect pengguna Admin yang sudah
login juga diperbaiki agar menuju dashboard Admin. Suite lengkap lulus: 295 tes,
7.363 assertion, dan satu tes dilewati karena two-factor Fortify dinonaktifkan.
