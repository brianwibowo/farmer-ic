# Buku Panduan Farm Management (LaTeX Source)

Dokumentasi operasional **Admin dan Petugas/PIC** PT Bumi Hijauan ditulis secara modular menggunakan LaTeX agar mudah diedit bersama melalui Git tanpa konflik biner PDF/DOCX.

## Struktur Folder

```text
docs/panduan-pengguna/
├── main.tex                          # Entry point, cover, daftar isi, urutan bab
├── preamble.tex                      # Tema Bumi Hijauan, macro gambar, box catatan, header/footer
├── sections/
│   ├── 00-pengantar.tex              # Kata pengantar & cara membaca
│   ├── 01-istilah-dan-peran.tex      # Istilah & ringkasan tanggung jawab
│   ├── 02-persiapan-masuk.tex        # Akses & login desktop/mobile
│   ├── 03-admin-akun-petugas.tex     # Buat, ubah, hapus, pulihkan akun Petugas
│   ├── 04-mencatat-lahan.tex         # Tambah lahan & lokasi
│   ├── 05-blok-subblok-sop.tex       # Pembagian blok, subblok, & pengelolaan SOP
│   ├── 06-alur-petugas-mobile.tex    # Alur kerja Petugas di mobile
│   ├── 07-pemecahan-masalah.tex      # Tabel troubleshooting
│   └── 08-pengelolaan-dan-penutup.tex# Aturan pembaruan dokumen & penutup
├── gambar/                           # Aset tangkapan layar & ilustrasi
├── .latexmkrc                        # Konfigurasi build ke folder build/
└── .gitignore                        # Mengabaikan file sementara kompilasi
```

## Persiapan & Instalasi Dependensi

Panduan ini dikompilasi menggunakan **XeLaTeX** (lihat `.latexmkrc`) dengan font **Latin Modern** serta paket tata letak seperti `geometry`, `fontspec`, `xcolor`, `colortbl`, `booktabs`, `longtable`, `enumitem`, `caption`, `tikz`, `fancyhdr`, `titlesec`, `tcolorbox`, dan `hyperref`.

> **Catatan:** Pembaca maupun editor yang hanya mengubah teks di `sections/*.tex` dan mengirimkannya lewat Git **tidak wajib** membangun PDF sendiri. Instalasi di bawah hanya diperlukan jika Anda ingin mengompilasi PDF secara lokal. (Alternatif tanpa instalasi lokal: unggah isi folder `docs/panduan-pengguna/` ke **Overleaf** lalu pilih compiler **XeLaTeX** di menu pengaturan.)

### Windows

1. **Pasang MiKTeX** *(wajib untuk engine `xelatex` dan pengelola paket LaTeX)*:

   ```powershell
   winget install --id MiKTeX.MiKTeX -e
   ```

   Atau unduh installer resmi dari situs MiKTeX. Setelah selesai, buka aplikasi **MiKTeX Console** sekali untuk menyelesaikan setup awal, jalankan **Updates** bila diminta, dan pastikan opsi instalasi paket otomatis (**Always install missing packages on-the-fly**) aktif agar MiKTeX mengunduh paket seperti `tcolorbox`, `titlesec`, atau `pgf`/`tikz` secara otomatis saat kompilasi pertama.

2. **Pasang Strawberry Perl** *(opsional, diperlukan hanya jika ingin menjalankan `latexmk`)*:

   ```powershell
   winget install --id StrawberryPerl.StrawberryPerl -e
   ```

   Jika Perl tidak dipasang, Anda tetap dapat membangun PDF langsung menggunakan perintah `xelatex` dua kali (lihat bagian **Cara Build PDF** di bawah).

3. **Buka ulang terminal (PowerShell)** agar `PATH` terbarui, lalu pastikan perintah terbaca:

   ```powershell
   xelatex --version
   latexmk --version   # lewati jika tidak memasang Perl
   ```

### Linux (Ubuntu / Debian)

```bash
sudo apt update
sudo apt install texlive-xetex texlive-latex-recommended texlive-latex-extra texlive-pictures texlive-fonts-recommended lmodern latexmk
```

### macOS (Homebrew)

```bash
brew install --cask mactex-no-gui
```

- Gunakan `brew install --cask mactex` jika ingin menyertakan aplikasi GUI bawaan MacTeX (seperti TeXShop).
- Jika memilih `brew install --cask basictex` yang lebih ringkas, instal `latexmk` serta paket yang dibutuhkan secara terpisah melalui `sudo tlmgr install`.
- Setelah instalasi, buka ulang terminal agar binary TeX terbaca di `PATH`.

### Editor yang Disarankan (Opsional)

- **VS Code** dengan ekstensi **LaTeX Workshop** untuk melihat pratinjau PDF secara langsung (ekstensi ini otomatis mengikuti `.latexmkrc` di folder ini).

## Cara Build PDF

Dari direktori `farmer-ic/docs/panduan-pengguna/`:

```powershell
latexmk
```

Output PDF tersimpan di `build/panduan-penggunaan-admin-petugas.pdf`.

Jika menjalankan `xelatex` tanpa `latexmk`:

```powershell
xelatex -output-directory=build main.tex
xelatex -output-directory=build main.tex
```

*(Jalankan dua kali agar Daftar Isi dan nomor referensi `Gambar` terbarui.)*

## Panduan Kolaborasi Tim

1. **Edit per bagian:** Setiap bab berada di file terpisah dalam `sections/`. Ubah hanya file bab terkait agar pull request tetap fokus dan minim konflik.
2. **Jangan tulis nomor manual pada judul atau gambar:**
   - Gunakan `\section{...}`, `\subsection{...}`, dan `\subsubsection{...}` tanpa angka manual.
   - Gunakan `\caption{...}\label{fig:nama-label}` dan rujuk lewat `Gambar~\ref{fig:nama-label}` agar penomoran otomatis selalu sinkron.
   - Untuk daftar langkah bernomor yang terpotong gambar, gunakan `\begin{enumerate}[resume]` pada lanjutan langkah.
3. **Menambahkan penanda merah (highlight) pada tangkapan layar bersih:**
   Gunakan `\guideannotated` dengan koordinat ternormalisasi `(0,0)` (kiri bawah) sampai `(1,1)` (kanan atas):

   ```latex
   \guideannotated{NamaFile.png}{Keterangan gambar.}{fig:contoh}{%
     \highlightbox{0.10}{0.20}{0.90}{0.35}
     \stepbadge{0.10}{0.35}{1}
   }
   ```
4. **Update versi:** Ubah `\GuideVersion` dan `\GuideDate` di `main.tex` sebelum merilis PDF baru.
5. **Jangan commit folder `build/`:** Folder `build/` sudah diabaikan oleh `.gitignore`.

## Catatan Aset Tangkapan Layar

Seluruh tangkapan layar utama telah disesuaikan dengan tampilan terbaru. Catatan untuk pembaruan berikutnya:

- `GambarPetugas10.png` saat ini identik dengan `GambarPetugas1.png` (tampilan *Beranda* dengan kartu tugas berstatus *Dikerjakan*); bila diperlukan, dapat diganti dengan tangkapan layar halaman **Tugas** penuh untuk variasi visual.
