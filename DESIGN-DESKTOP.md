# 🖥️ DESIGN-DESKTOP.md — Web App Dashboard (Desktop View)

Dokumen ini adalah **Single Source of Truth (SSOT)** untuk pengembangan visual, arsitektur tata letak, dan implementasi komponen UI khusus untuk **Dashboard Admin Desktop (Pengelolaan Lahan & Warehousing)**. Dokumen ini menggabungkan fondasi *Atomic Design* sistem dengan pedoman fungsional dari *Farmer IC Design System*, dioptimalkan untuk tampilan *desktop* layar lebar dengan kepadatan data tinggi.

---

## 📐 1. Architecture & Layout System

Dashboard menggunakan arsitektur **2 Kolom Utama** dengan pendekatan *Clean, Modern, dan Simplified* untuk menangani data kompleks tanpa mengorbankan fungsionalitas UI/UX.

* **Column 1 (Sidebar Navigation):** Panel navigasi vertikal statis di sisi kiri.
  * **Top Section:** Logo Aplikasi.
  * **Middle Section:** Daftar Menu Utama.
  * **Menu Item States:**
    * *Default/Inactive:* Teks dan Ikon menggunakan `neutral-500`. Background transparan.
    * *Hover:* Teks dan Ikon berubah menjadi `brand-300`, Background `brand-100`.
    * *Active:* Teks dan Ikon `brand-600`, Background `brand-50`.
* **Column 2 (Main Content Area):** Area kerja dinamis di sisi kanan.
  * **Top Header (Navbar):** Berada di bagian paling atas Column 2 (memuat Global Search, Profile, Notifikasi).
  * **Dynamic Content:** Konten yang berubah-ubah (Tabel, Metric Card, Form) dirender tepat di bawah Navbar.
* **Corner Radius Default:** `12px` (`radius-lg`) untuk card, modal, dan container utama.

---

## 🎨 2. Design Tokens (Extended Color Palette)

Sistem warna menggunakan skala 50–900 untuk mendukung status dinamis, validasi, dan hierarki visual yang kontras.

### Brand Primary (Emerald Green)
Digunakan untuk aksi utama, tombol primer, dan status aktif.
* `brand-50`  : `#F0FDF4` | `brand-100` : `#D1FAE5` | `brand-200` : `#A7F3D0`
* `brand-300` : `#6EE7B7` *(Hover Text/Icon di Sidebar)*
* `brand-400` : `#34D399` | `brand-500` : `#10B981` *(Base)*
* `brand-600` : `#059669` | `brand-700` : `#047857`
* `brand-800` : `#065F46` | `brand-900` : `#064E3B`

### Neutral Slate (Surface & Typography)
Digunakan untuk latar belakang, teks pembacaan, dan batas antarmuka.
* `neutral-0`   : `#FFFFFF` | `neutral-50`  : `#F8FAFC` | `neutral-100` : `#F1F5F9`
* `neutral-200` : `#E2E8F0` | `neutral-300` : `#CBD5E1` | `neutral-400` : `#94A3B8`
* `neutral-500` : `#64748B` *(Inactive Sidebar)*
* `neutral-600` : `#475569` | `neutral-700` : `#334155`
* `neutral-800` : `#1E293B` | `neutral-900` : `#0F172A`

### Semantic: Warning (Amber)
Untuk status menipis, peringatan, atau aksi tertunda.
* `warning-50`  : `#FEFCE8` | `warning-100` : `#FEF9C3` | `warning-200` : `#FEF08A`
* `warning-300` : `#FDE047` | `warning-400` : `#FACC15` | `warning-500` : `#EAB308` *(Base)*
* `warning-600` : `#CA8A04` | `warning-700` : `#A16207`
* `warning-800` : `#854D0E` | `warning-900` : `#713F12`

### Semantic: Danger (Rose Red)
Untuk validasi error, stok habis, atau aksi destruktif (hapus).
* `danger-50`  : `#FEF2F2` | `danger-100` : `#FEE2E2` | `danger-200` : `#FECACA`
* `danger-300` : `#FCA5A5` | `danger-400` : `#F87171` | `danger-500` : `#EF4444` *(Base)*
* `danger-600` : `#DC2626` | `danger-700` : `#B91C1C`
* `danger-800` : `#991B1B` | `danger-900` : `#7F1D1D`

### Semantic: Info (Sky Blue)
Untuk instruksi, data transit, dan informasi netral.
* `info-50`  : `#F0F9FF` | `info-100` : `#E0F2FE` | `info-200` : `#BAE6FD`
* `info-300` : `#7DD3FC` | `info-400` : `#38BDF8` | `info-500` : `#0EA5E9` *(Base)*
* `info-600` : `#0284C7` | `info-700` : `#0369A1`
* `info-800` : `#075985` | `info-900` : `#0C4A6E`

---

## 🔤 3. Typography: Plus Jakarta Sans (Golden Ratio Scale)

Tipografi didasarkan pada skala *Golden Ratio* (~1.618) menggunakan **Plus Jakarta Sans** untuk menjaga harmoni proporsi antara *Heading* dan *Body*. Base size (*Body Default*) adalah `14px` untuk kepadatan data desktop.

### Font Weights
* `Regular` (400): Untuk paragraf panjang, deskripsi, dan catatan.
* `Medium` (500): Untuk label input, badge, dan teks tombol standar.
* `SemiBold` (600): Untuk sub-judul, *table headers*, dan *highlight* penting.
* `Bold` (700): Untuk angka utama (KPI Metric), *Headline* H1/H2, dan *primary actions*.

### Size & Line Height Scale
| Style | Size (px) | Line Height (px) | Weight | Penggunaan UI |
| :--- | :---: | :---: | :--- | :--- |
| **H1 Display** | `48px` | `60px` | Bold | Angka KPI Ekstra Besar, Judul Halaman Utama |
| **H2 Title** | `32px` | `40px` | Bold | Angka Metric Card, Judul Modal Utama |
| **H3 Subtitle** | `24px` | `32px` | SemiBold | Judul Section, Card Header |
| **H4 Heading** | `20px` | `28px` | SemiBold | Sub-section, Judul Pop-up ringan |
| **Body 1 (Large)**| `16px` | `24px` | Medium/Reg | Teks informasi utama, Alert Banner |
| **Body 2 (Base)** | `14px` | `20px` | Medium/Reg | **Standar Dashboard** (Teks tabel, Label input) |
| **Caption 1** | `12px` | `16px` | Medium/Reg | Metadata, Helper text, Tooltips, Status trend |
| **Caption 2** | `10px` | `14px` | Medium | Label super kecil (jarang digunakan) |

---

## 🧩 4. Component Specifications

### 4.1 Buttons & Inputs
* **Button Sizes:**
  * `Giant`: `56px` (Jarang digunakan di desktop, khusus Call to Action besar).
  * `Large`: `48px`.
  * `Medium`: `40px` (Standar form submission).
  * `Small`: `32px` (Standar aksi dalam tabel).
  * `Tiny`: `24px`.
* **Button Styles:** *Filled* (Primary), *Outline* (Bordered), *Clear* (Ghost).
* **Button States:** Default, Hover, Focus (Ring terlihat), Press, Disabled.
* **Inputs:** Tersedia dalam ukuran Large dan Medium. Memiliki varian *Filled* dan *Outline*, dilengkapi `<label>` wajib dan warna status semantik untuk validasi (Success, Error, Warning).

### 4.2 To-Do / Task List Table
Dirancang dengan konsep *Clean & Simplified* khusus untuk pengelolaan *task* / to-do harian.
* **Visual:** Tanpa garis batas vertikal (*borderless columns*). Gunakan *bottom border* horizontal (`1px solid neutral-100`) untuk memisahkan baris.
* **Header:** Teks `Caption 1` (12px), `SemiBold`, warna `neutral-500`, latar belakang `neutral-50`.
* **Data Row:** Tinggi minimal `48px`. *Hover state* merubah background menjadi `neutral-50`.
* **Status Badges:** Gunakan *pill-shaped badges* (radius-full) dengan warna token Semantik 50 (BG) dan 700 (Teks) untuk status *To-Do*, *In Progress*, *Done*.

### 4.3 Navigation & Layout Molecules
* **Tabs:** Mendukung variasi tab item individual. *State*: Default, Hover, Focus, Selected (Indikator garis bawah tebal atau *pill background* aktif), Disabled.
* **Pagination:** Item kontrol berbasis ikon dan teks. Menunjukkan halaman aktif dengan `aria-current="page"`.
* **Pop-ups / Modals:** Pola layout vertikal dan horizontal untuk tombol aksi. Selalu sertakan *Title*, *Message*, dan pastikan aksi *Close* serta aksi utama dapat diakses lewat *keyboard* (Esc / Enter).

---

## 🤖 5. Agentic Prompting & Implementation Guidelines

Bagian ini adalah instruksi keras (*hard constraints*) bagi AI / Agentic Developer (Windsurf, Cursor, Claude Dev) saat mengeksekusi kode:

1. **Tech Stack & Architecture:**
   * Gunakan **Vue 3 `<script setup>`** dan *Composition API*.
   * Pisahkan komponen *reusable* (Buttons, Inputs, Cards) di `resources/js/components/`.
   * Simpan struktur *layouting* halaman spesifik di `resources/js/pages/`.
2. **Styling Strict Execution:**
   * Gunakan *Tailwind CSS semantic utilities* yang merujuk pada token di atas (misal: `text-brand-600`, `bg-neutral-50`, `border-warning-200`). Jangan gunakan kode *hex* *hard-coded* di dalam template UI.
   * Implementasikan *hover state* pada Sidebar menu secara presisi: `hover:bg-brand-100 hover:text-brand-300 [&>svg]:hover:text-brand-300`.
3. **Dashboard CSS Grid/Flexbox Setup:**
   * Terapkan arsitektur 2 Kolom menggunakan struktur utama: `flex h-screen w-full overflow-hidden`.
   * Kolom 1 (Sidebar): `w-[260px] flex-shrink-0 border-r border-neutral-200`.
   * Kolom 2 (Main Content): `flex-1 flex flex-col overflow-y-auto`.
   * Navbar di dalam Kolom 2: `h-16 flex-shrink-0 border-b border-neutral-200`.
4. **UX States Implementation:**
   * Selalu sediakan *loading state* (skeletons/spinners), *empty state* (ilustrasi ringan + pesan), serta *success/error toast notifications* pada halaman interaktif.
   * Pastikan fokus elemen (Focus Ring) dapat terlihat dengan jelas baik pada *Light Mode* maupun *Dark Mode*.