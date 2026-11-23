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
