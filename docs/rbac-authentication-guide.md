# Panduan Autentikasi dan RBAC

Panduan ini menjelaskan cara memakai kerangka autentikasi dan RBAC di aplikasi
Laravel 13 ini. Matriks peran dan izin yang menjadi sumber utama berada
di [`config/access.php`](../config/access.php). Dokumen ini menjelaskan alur
serta cara memakai RBAC di Router, Controller, FormRequest, Service, dan Vue.

## Alur permintaan

1. Middleware Router memastikan pengguna sudah masuk.
2. Controller memeriksa izin melalui Policy atau Gate.
3. FormRequest memvalidasi masukan. `authorize()` bersifat opsional; gunakan jika
   perlu menolak permintaan lebih awal dan delegasikan pemeriksaannya ke Policy
   atau Gate yang sudah ada.
4. Controller memanggil Service setelah pemeriksaan izin berhasil.
5. Service menerapkan `visibleTo()` untuk pencarian data, lalu menjalankan
   proses bisnis dan penyimpanan. Service tidak memeriksa peran secara langsung
   dan tidak membaca `config/access.php`.
6. Vue menggunakan informasi izin dari server untuk menampilkan tindakan yang
   tersedia. Vue bukan batas keamanan.

Dengan pembagian ini, Policy/Gate menetapkan aturan, Controller memanggil aturan
untuk tindakan HTTP, dan `visibleTo()` membatasi hasil pencarian. Hindari memeriksa
izin yang sama di FormRequest dan Controller sekaligus kecuali ada manfaat
khusus dari pemeriksaan awal. Pemeriksaan ganda menambah pemanggilan Policy dan
membuat alur lebih sulit diikuti.

Saat ini titik akhir CRUD Farm, Catatan Aktivitas Harian, dan backend finance
sudah menggunakan pemeriksaan izin. Finance mencakup transaksi, kategori,
anggaran, proyeksi, ringkasan, dan ekspor terbatasi cakupan; lihat
[kontrak backend finance](finance-backend.md). Halaman finance publik dan layanan
contoh antarmuka masih memakai data demo. Controller lain tetap mengikuti batas
integrasi yang didokumentasikan dalam kerangka ini.

## Berkas autentikasi

- `app/Models/User.php` adalah satu-satunya pengguna untuk autentikasi. Peran
  aplikasi dibaca melalui `role_id`, tim aktif melalui `current_team_id`,
  keanggotaan tim melalui `teams()`, dan penugasan petugas melalui relasi
  kolom `assigned_farm_id` melalui relasi `assignedFarm()`.
- `app/Concerns/HasTeams.php` menyediakan pemeriksaan keanggotaan tim yang
  tersimpan di basis data. Peran keanggotaan tim berbeda dari peran aplikasi.
- `routes/web.php` dan `routes/api.php` memakai middleware `auth` untuk rute
  yang memerlukan pengguna masuk.
- `app/Http/Requests/` berisi validasi masukan dan dapat melakukan pemeriksaan
  izin awal jika diperlukan.

Jangan menerima `role`, `team_id`, `farm_id`, atau `created_by` dari peramban
sebagai bukti izin. Ambil pengguna dari sesi masuk, lalu tentukan tim dan farm
melalui relasi model yang tersimpan.

## Berkas pemeriksaan izin

- `config/access.php` berisi izin eksplisit dengan format
  `{prefix}.{ability} => cakupan`. Izin yang tidak dicantumkan atau cakupan yang
  tidak valid berarti akses ditolak.
- `app/Support/RolePermissions.php` adalah satu-satunya pembaca matriks itu.
  Gunakan kelas ini untuk membaca izin, memvalidasi peran, serta memeriksa batas
  keanggotaan tim dan penugasan farm. Jangan membaca
  `config('access.roles')` dari file lain.
- `app/Policies/ResourcePolicy.php` berisi logika bersama untuk data, model
  induk, tim, farm, dan cakupan pencarian.
- `app/Policies/{Model}Policy.php` menentukan awalan izin dan relasi tepercaya
  untuk model tertentu. Laravel menemukan Policy berdasarkan nama model.
- `app/Providers/AuthorizationServiceProvider.php` mendefinisikan Gate untuk
  keputusan non-resource. `ai.access` dan `teams.manageAll` adalah Gate bernama.
  `teams.manageAll` ditolak untuk peran biasa dan hanya lolos untuk superadmin
  melalui `Gate::before`. `Gate::before`
  mengembalikan `true` hanya untuk peran pengembang `superadmin`; peran ini tidak
  memiliki daftar izin terperinci di `config/access.php`.
- Model yang mengikuti kontrak pencarian Policy menyediakan `visibleTo()` melalui
  `app/Concerns/HasVisibleScope.php`.

## Arti peran dan cakupan

### Area admin dan izin data

`/admin/dashboard` dan `/admin/farm-management` memakai `auth`, `verified`, dan
Gate `admin.access`. Gate ini hanya menerima peran aplikasi `admin` dengan
keanggotaan tim aktif yang valid; `superadmin` tetap melalui bypass pengembang
yang sudah ada. Peran Team owner/admin tidak menjadi bukti akses area admin.
Owner dan Petugas menerima 403 pada area ini, termasuk saat membuka URL langsung.
Izin baca Farm mereka tetap berlaku di jalur yang sesuai; Policy resource tidak
diubah untuk membatasi portal. Controller tetap memeriksa Policy Farm dan
Service tetap memakai `visibleTo()`.

Navigasi menerima boolean `navigation.admin` dan `navigation.farms` dari server.
Login mengarahkan Admin/SuperAdmin ke `/admin/dashboard`, serta Owner/Petugas ke
`/`. Ikon profil di antarmuka Farmer/Owner membuka ringkasan profil tanpa tautan
ke area admin. Route admin lama tanpa prefix sudah dihapus.

Nilai `any` berarti semua data yang berada dalam batas tim yang diikuti
pengguna. Cakupan ini tetap memerlukan keanggotaan tim. Nilai `own` berarti data
terkait dengan farm yang ditugaskan kepada pengguna melalui
`users.assigned_farm_id`. Notifikasi memiliki pengecualian pribadi `own`.
Data tanpa hubungan farm/tim yang dapat dibuktikan akan ditolak.

Ada dua jenis peran yang berbeda:

- Peran aplikasi disimpan melalui `users.role_id` dan menentukan izin RBAC dari
  `config/access.php`.
- `team_members.role` adalah peran keanggotaan Team (`owner`, `admin`, atau
  `member`). Fitur Team yang sudah ada memakainya untuk menentukan pemilik Team,
  menampilkan dan mengubah peran anggota, mengatur peran undangan, serta
  melindungi tindakan seperti menghapus pemilik atau keluar dari Team. Kolom ini
  bukan sumber izin RBAC aplikasi.

Jika Team nantinya hanya dipakai untuk menghubungkan pengguna dengan Team tanpa
membedakan hak antaranggota, `team_members.role` tidak diperlukan untuk asosiasi
itu sendiri. Namun, fitur Team saat ini masih bergantung padanya, jadi kolom ini
baru dapat dihapus setelah fitur-fitur tersebut disederhanakan atau diubah.

Peran aplikasi saat ini adalah:

- `owner` mendapat izin baca dan `ai.access`, serta izin AI untuk percakapan,
  pesan, lampiran, dan dokumen pengetahuan yang tercantum di matriks.
- `admin` mendapat izin CRUD dalam lingkup tim sesuai matriks.
- `petugas` mendapat izin operasional dalam lingkup farm yang ditugaskan
  (`own`).
- `superadmin` adalah peran pengembang yang ditangani oleh
  `AuthorizationServiceProvider::boot()` melalui `Gate::before`.

Superadmin dapat membuka daftar seluruh Team dan mengelola Team, anggota, serta
undangan tanpa baris keanggotaan pada `team_members`. Akses ini tidak menambah
keanggotaan dan tidak mengganti Team aktif superadmin. `EnsureTeamMembership`
mengizinkan konteks Team jika `teams.manageAll` lolos; aksi switch Team tetap
memerlukan keanggotaan tersimpan. Properti `teams` yang dipakai pemilih Team
tetap hanya berisi Team yang benar-benar diikuti pengguna.

## Cara memakai RBAC berdasarkan jenis berkas

### 1. Router

Router memastikan pengguna sudah masuk dan meneruskan parameter rute ke
Controller. Jangan menulis aturan peran atau kepemilikan di definisi rute.

Middleware `can:` dapat dipakai jika sasaran berupa model atau tim yang sudah
terikat ke rute. Namun, jika Controller juga memeriksa izin yang sama,
Policy akan dipanggil dua kali. Pola yang disarankan di proyek ini adalah
memakai `auth` di Router lalu melakukan satu pemeriksaan izin di
Controller.

```php
use App\Http\Controllers\FarmController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/farms', [FarmController::class, 'index'])->name('farms.index');
    Route::get('/farms/{farm}', [FarmController::class, 'show'])
        ->middleware('can:view,farm')
        ->name('farms.show');
});
```

Untuk rute detail di atas, pilih satu pemeriksaan: `can:view,farm` di Router
atau `Gate::authorize('view', $farm)` di Controller. Letakkan pemeriksaan di
Controller jika membutuhkan model induk tepercaya atau alur pencarian tertentu.
Semua rute yang dilindungi tetap harus memakai middleware `auth`, baik secara
langsung maupun melalui group.

### 2. Controller

Controller adalah tempat utama untuk memeriksa izin pada permintaan HTTP. Ambil
pengguna dari permintaan yang sudah terautentikasi, tentukan tim/farm dari
relasi basis data, periksa izin, lalu panggil Service.

```php
public function index(ListFarmsRequest $request): AnonymousResourceCollection
{
    $user = $request->user();
    $team = $user->currentTeam;

    Gate::authorize('viewAny', [Farm::class, $team]);

    return FarmResource::collection(
        $this->farms->paginate($user, $team, $request->validated())
    );
}

public function update(UpdateFarmRequest $request, Farm $farm): FarmResource
{
    Gate::authorize('update', $farm);

    return new FarmResource($this->farms->update($farm, $request->validated()));
}
```

Di Controller, `Gate::authorize()` memakai pengguna yang sedang masuk secara
otomatis, jadi biasanya tidak perlu menulis `Gate::forUser($user)`. Gunakan
`Gate::forUser($actor)->authorize()` jika perlu menentukan pengguna secara
eksplisit, misalnya di job atau command yang tidak berjalan dalam request
pengguna. Pemeriksaan harus berhasil sebelum Service dijalankan.

Untuk daftar data, `viewAny` mengizinkan akses ke jenis daftar tersebut. Query
Service tetap harus memakai `visibleTo()` agar hanya data dalam cakupan izin
yang dikembalikan.

Contoh Controller di atas sudah memanggil Service pada baris
`$this->farms->paginate(...)` dan `$this->farms->update(...)`. Isi Service
berada di `app/Services/FarmService.php`, bukan ditulis ulang di Controller.
Constructor Controller menerima `FarmService` melalui dependency injection:

```php
public function __construct(private readonly FarmService $farms) {}
```

Contoh isi Service untuk listing:

```php
public function paginate(User $user, Team $team, array $filters): LengthAwarePaginator
{
    return Farm::visibleTo($user, $team)
        ->when($filters['city'] ?? null, fn ($query, $city) => $query->where('city', $city))
        ->orderBy('name')
        ->paginate($filters['per_page'] ?? 15);
}
```

Urutannya: Controller memeriksa izin `viewAny`, Service mengambil data dengan
scope `visibleTo()`, lalu Controller mengirim hasilnya melalui `FarmResource`.
Service mengembalikan paginator berisi model `Farm`; Service tidak menyusun
bentuk JSON.

### Apa itu Resource Laravel?

`App\Http\Resources\FarmResource` adalah lapisan untuk menentukan bentuk
JSON yang dikirim oleh endpoint API. Resource memilih dan mengubah field model
yang boleh muncul pada respons. Contohnya, `FarmResource` menentukan field
seperti `id`, `name`, `total_area_ha`, dan waktu pembuatan.

Resource **bukan** model Eloquent, Service, atau pemeriksaan izin. Tugasnya
hanya menyajikan data. Pola pemakaiannya:

```php
// Satu model Farm menjadi satu objek JSON.
return new FarmResource($farm);

// Paginator atau kumpulan Farm menjadi kumpulan objek JSON.
return FarmResource::collection($farms);
```

Jadi, pada pola MVC + Service:

1. Model Eloquent mewakili data dan relasi database.
2. Controller memeriksa izin dan mengatur alur request.
3. Service menjalankan query atau perubahan data.
4. Resource mengatur bentuk JSON untuk respons API.

Untuk halaman Inertia, Controller biasanya mengirim props ke `Inertia::render()`.
`JsonResource` terutama berguna untuk endpoint API yang perlu memiliki bentuk
respons JSON yang konsisten.

### 3. FormRequest

FormRequest bertanggung jawab atas validasi masukan melalui `rules()`. Pemeriksaan
izin di `authorize()` bersifat opsional dan umumnya tidak perlu mengulang
pemeriksaan Controller. Jika perlu menolak permintaan lebih awal, serahkan
`authorize()` ke Policy/Gate yang sama. Jangan menulis aturan peran,
keanggotaan tim, cakupan, atau penugasan farm di FormRequest.

Contoh FormRequest yang hanya memvalidasi masukan:

```php
final class UpdateFarmRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'total_area_ha' => ['sometimes', 'required', 'numeric', 'min:0.0001'],
        ];
    }
}
```

Contoh pemeriksaan izin awal yang didelegasikan ke Gate:

```php
public function authorize(): bool
{
    $farm = $this->route('farm');

    return $farm instanceof Farm
        && Gate::forUser($this->user())->allows('update', $farm);
}
```

Jika izin sudah diperiksa di `FormRequest::authorize()`, hindari memeriksa izin
yang sama lagi di Controller kecuali memang perlu. Controller tetap mengatur
alur dan tidak boleh memanggil Service jika pemeriksaan izin gagal. Dalam kedua
pola tersebut, validasi tetap ditulis di
`rules()`.

### 4. Service

Service bertanggung jawab atas proses bisnis, pencarian Eloquent, transaksi,
dan penyimpanan data. Service tidak membaca matriks peran atau menentukan izin
berdasarkan nama peran. Untuk setiap pencarian data, terapkan `visibleTo()`
sebelum membuat halaman, menghitung jumlah, menghitung total, atau menyusun hasil.

```php
public function paginate(User $user, Team $team, array $filters): LengthAwarePaginator
{
    return Farm::visibleTo($user, $team)
        ->when($filters['city'] ?? null, fn ($query, $city) => $query->where('city', $city))
        ->orderBy('name')
        ->paginate($filters['per_page'] ?? 15);
}
```

Controller sudah memeriksa izin `viewAny`; Service menerapkan cakupan data agar
hasil pencarian, jumlah halaman, dan hitungan tetap berada dalam batas tim/farm
yang diizinkan. Service juga dapat dipanggil dari job atau command. Pemanggil
selain HTTP harus memeriksa izin secara eksplisit dengan pengguna yang sesuai
sebelum menjalankan proses terlindungi. Jangan meminta Service menebak pengguna
yang sedang masuk.

### 5. Vue

Vue menerima nilai benar/salah dari server dan menggunakannya untuk menampilkan
atau menyembunyikan kontrol. Hitung nilai tersebut di Controller atau props
Inertia bersama memakai `Gate::allows()`. Jangan mengirim seluruh matriks peran
ke Vue dan jangan membuat keputusan izin di komponen.

Contoh Controller/Inertia:

```php
return Inertia::render('Farms/Show', [
    'farm' => $farm,
    'can' => [
        'update' => Gate::allows('update', $farm),
        'delete' => Gate::allows('delete', $farm),
    ],
]);
```

Contoh Vue:

```vue
<script setup lang="ts">
defineProps<{
  farm: Farm;
  can: { update: boolean; delete: boolean };
}>();
</script>

<template>
  <Link v-if="can.update" :href="route('farms.edit', farm.id)">
    Ubah farm
  </Link>
</template>
```

Router/Controller tetap harus memeriksa izin ketika pengguna membuka halaman
atau mengirim tindakan. Menyembunyikan tombol hanya mengatur tampilan dan tidak
melindungi akses.

## Contoh pemanggilan Policy dan Gate lainnya

### Memeriksa data yang sudah ada

```php
use Illuminate\Support\Facades\Gate;

Gate::authorize('view', $farm);
Gate::authorize('update', $plantingCycle);
Gate::authorize('delete', $financialTransaction);
```

Policy membaca farm dan tim dari relasi data yang tersimpan. Jangan memakai ID
farm atau tim dari peramban sebagai dasar pemberian izin.

### Membuat data turunan

```php
Gate::authorize('create', [PlantingCycle::class, $farm]);

$cycle = $plantingCycles->create($validated, $farm);
```

Model induk harus sudah tersimpan dan jenisnya harus sesuai. Jangan menerima
`farm_id` terpisah lalu menganggapnya sebagai bukti izin.

### Memeriksa akses AI

```php
Gate::authorize('ai.access', $team);
```

Untuk command, job, atau alat tanpa permintaan HTTP, teruskan pengguna
`App\Models\User` secara eksplisit lalu gunakan
`Gate::forUser($actor)->authorize(...)` sebelum proses dijalankan. Job antrean
perlu memeriksa ulang izin saat dijalankan karena peran atau keanggotaan dapat
berubah setelah job dimasukkan ke antrean.

## Mengubah izin

1. Ubah izin di `config/access.php`.
2. Gunakan awalan yang sesuai dengan model nyata dan cakupan `any` atau `own`.
3. Jika menambahkan izin baru, tambahkan atau perbarui Policy dan pengujian izin
   sesuai.
4. Jika aplikasi memakai cache konfigurasi, jalankan `php artisan config:cache`
   saat penerapan dan mulai ulang proses worker yang berjalan lama.

Aturan kepemilikan baru memerlukan perubahan kode pada Policy dan cakupan query,
selain izin di matriks. Perubahan konfigurasi saja tidak dapat membuat relasi
yang belum tersedia.

## Perintah pemeriksaan

```powershell
php artisan test --compact
vendor\bin\phpstan analyse --memory-limit=1G
vendor\bin\pint --test
```

Pengujian integrasi PostgreSQL dan migrasi memeriksa relasi skema saat ini:
`users` dan data domain memakai UUID, `teams` memakai integer,
`farms.team_id`, serta `users.assigned_farm_id`. Jangan menjalankan migrasi
destruktif pada basis data non-demo.
