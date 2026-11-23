# Latexmk configuration for Buku Panduan Farm Management
# Use XeLaTeX so UTF-8 characters render natively across Windows, macOS, Linux, and CI.
@default_files = ('main.tex');
$pdf_mode = 5;
$out_dir = 'build';
$jobname = 'panduan-penggunaan-admin-petugas';
$xelatex = 'xelatex -interaction=nonstopmode -halt-on-error -synctex=1 %O %S';
