from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
MD_PATH = ROOT / "docs" / "panduan-penggunaan-admin-petugas.md"
OUT_PATH = ROOT / "docs" / "panduan-penggunaan-admin-petugas.tex"

PREAMBLE = r'''% Editable LaTeX source for Buku Panduan Farm Management.
% Run XeLaTeX twice from the docs directory to refresh the table of contents.
\documentclass[11pt,a4paper]{article}
\usepackage[a4paper,margin=2.2cm,headheight=15pt]{geometry}
\usepackage{fontspec}
\setmainfont{Latin Modern Roman}
\usepackage{graphicx}
\usepackage{xcolor}
\usepackage{booktabs}
\usepackage{longtable}
\usepackage{array}
\usepackage{enumitem}
\usepackage{hyperref}
\usepackage{float}
\usepackage{caption}
\usepackage{tikz}
\usepackage{fancyhdr}
\usepackage{titlesec}
\definecolor{FarmGreen}{HTML}{087A55}
\definecolor{SoftBlack}{HTML}{30343B}
\hypersetup{colorlinks=true,linkcolor=FarmGreen,urlcolor=FarmGreen,pdftitle={Buku Panduan Farm Management},pdfauthor={PT Bumi Hijauan}}
\setcounter{tocdepth}{2}
\setcounter{secnumdepth}{3}
\setlist{leftmargin=*,itemsep=3pt,topsep=4pt}
\setlength{\parskip}{5pt}
\setlength{\parindent}{0pt}
\renewcommand{\arraystretch}{1.18}
\titleformat{\section}{\Large\bfseries\color{SoftBlack}}{\thesection}{0.65em}{}
\titleformat{\subsection}{\large\bfseries\color{FarmGreen}}{\thesubsection}{0.65em}{}
\titleformat{\subsubsection}{\normalsize\bfseries\color{SoftBlack}}{\thesubsubsection}{0.65em}{}
\pagestyle{fancy}\fancyhf{}\fancyhead[L]{\small Buku Panduan Farm Management}\fancyhead[R]{\small PT Bumi Hijauan}\fancyfoot[C]{\thepage}\renewcommand{\headrulewidth}{0.4pt}
\newcommand{\guideimage}[2]{%
  \begin{figure}[H]\centering\includegraphics[width=0.94\linewidth,height=0.63\textheight,keepaspectratio]{#1}%
  \caption*{#2}\end{figure}}
\newcommand{\imageplaceholder}[1]{%
  \begin{figure}[H]\centering\fbox{\parbox[c][5.8cm][c]{0.88\linewidth}{\centering\color{gray}Tempatkan tangkapan layar bersih di sini\\[4pt]\texttt{#1}}}%
  \caption*{Tangkapan layar belum disertakan agar penanda merah dapat ditambahkan manual.}\end{figure}}
\begin{document}
\begin{titlepage}\thispagestyle{empty}\centering\vspace*{1.3cm}
{\Large\bfseries\color{FarmGreen} PT BUMI HIJAUAN\par}\vspace{2.0cm}
{\Huge\bfseries\color{SoftBlack} Buku Panduan\\[0.25em] Farm Management\par}\vspace{0.7cm}
{\Large Panduan penggunaan Admin dan Petugas\par}\vspace{0.5cm}
{\large Versi 1.4 · 23 November 2026\par}\vfill
\includegraphics[width=0.98\linewidth,height=0.30\textheight,keepaspectratio]{panduan-pengguna/gambar/20-ilustrasi-lahan-penutup.png}\vfill
{\small Dokumen panduan operasional\\PT Bumi Hijauan\par}\vspace{0.7cm}
\end{titlepage}
\tableofcontents\clearpage
'''


def tex_escape(value: str) -> str:
    replacements = {
        "\\": r"\textbackslash{}", "&": r"\&", "%": r"\%", "$": r"\$",
        "#": r"\#", "_": r"\_", "{": r"\{", "}": r"\}",
        "~": r"\textasciitilde{}", "^": r"\textasciicircum{}",
    }
    return "".join(replacements.get(char, char) for char in value)


def inline(value: str) -> str:
    value = re.sub(r"Penanda merah(?: \d+)? menunjukkan [^.]*\.?\s*", "", value)
    value = re.sub(r"Penanda merah[^.]*\.?\s*", "", value)
    value = value.replace("ditandai merah", "ditampilkan")
    value = value.replace("diberi penanda merah", "ditampilkan")
    value = value.replace("diberi tanda merah", "ditampilkan")
    tokens: list[str] = []

    def stash(text: str) -> str:
        marker = f"ZZTOKEN{len(tokens)}ZZ"
        tokens.append(text)
        return marker

    value = re.sub(
        r"\[([^\]]+)\]\(([^)]+)\)",
        lambda m: stash(r"\href{" + m.group(2).replace("_", r"\_") + "}{" + inline(m.group(1)) + "}"),
        value,
    )
    value = re.sub(r"`([^`]+)`", lambda m: stash(r"\texttt{" + tex_escape(m.group(1)) + "}"), value)
    value = re.sub(r"\*\*([^*]+)\*\*", lambda m: stash(r"\textbf{" + inline(m.group(1)) + "}"), value)
    value = re.sub(r"\*([^*]+)\*", lambda m: stash(r"\emph{" + inline(m.group(1)) + "}"), value)
    value = tex_escape(value)
    for index, token in enumerate(tokens):
        value = value.replace(f"ZZTOKEN{index}ZZ", token)
    return value


def resolve_image(relative_path: str) -> tuple[str, bool]:
    path = Path(relative_path)
    # These four screenshots have only marked copies in the supplied folder.
    # Keep the figure slot blank so the user can insert/annotate their own image.
    if path.name in {
        "01-form-tambah-lahan.png",
        "02-detail-lahan.png",
        "03-form-tambah-blok.png",
        "04-form-tambah-subblok.png",
    }:
        return path.as_posix(), False
    for suffix in ("-panduan", "-annotated"):
        if suffix in path.stem:
            clean_candidate = path.with_name(path.stem.replace(suffix, "") + path.suffix)
            exists = (ROOT / "docs" / clean_candidate).exists()
            return clean_candidate.as_posix(), exists
    return path.as_posix(), (ROOT / "docs" / path).exists()


def convert_markdown(source: str) -> str:
    lines = source.splitlines()
    output: list[str] = []
    index = 0
    skipped_title = False

    while index < len(lines):
        line = lines[index].rstrip()
        if not line.strip():
            index += 1
            continue
        if line.startswith("# ") and not skipped_title:
            skipped_title = True  # title is typeset on the designed cover
            index += 1
            continue
        if line.startswith("**Sistem Manajemen Lahan"):
            index += 1  # version is on the cover
            continue

        image = re.match(r"!\[([^\]]*)\]\(([^)]+)\)", line.strip())
        if image:
            alt, relative_path = image.groups()
            caption = ""
            if index + 1 < len(lines) and lines[index + 1].strip().startswith("*") and lines[index + 1].strip().endswith("*"):
                caption = lines[index + 1].strip().strip("*").strip()
                index += 1
            resolved, exists = resolve_image(relative_path)
            if exists:
                # Red-marked variants are intentionally replaced by clean sibling files.
                caption = re.sub(r"Penanda merah[^.]*\.?\s*", "", caption)
                caption = caption.replace("yang diberi kotak merah", "")
                caption = caption.replace("ditandai merah", "ditampilkan")
                caption = caption.replace("diberi tanda merah", "ditampilkan")
                output.append(r"\guideimage{" + resolved.replace("_", r"\_") + "}{" + inline(caption or alt) + "}")
            else:
                output.append(r"\imageplaceholder{" + tex_escape(Path(resolved).name) + "}")
            index += 1
            continue

        if line.startswith("#### "):
            output.append(r"\subsubsection{" + inline(line[5:]) + "}")
            index += 1
            continue
        if line.startswith("### "):
            output.append(r"\subsection{" + inline(line[4:]) + "}")
            index += 1
            continue
        if line.startswith("## "):
            heading = line[3:]
            if heading != "Daftar Isi":
                output.append(r"\section{" + inline(heading) + "}")
            index += 1
            continue

        if line.lstrip().startswith("|"):
            rows = []
            while index < len(lines) and lines[index].lstrip().startswith("|"):
                row = [cell.strip() for cell in lines[index].strip().strip("|").split("|")]
                if not all(re.fullmatch(r":?-{3,}:?", cell or "-") for cell in row):
                    rows.append(row)
                index += 1
            if rows:
                columns = max(map(len, rows))
                rows = [row + [""] * (columns - len(row)) for row in rows]
                if columns == 2:
                    widths = [r"0.28\linewidth", r"0.66\linewidth"]
                elif columns == 3:
                    widths = [r"0.22\linewidth", r"0.36\linewidth", r"0.36\linewidth"]
                else:
                    widths = [f"{0.9 / columns:.3f}\\linewidth"] * columns
                spec = "".join("p{" + width + "}" for width in widths)
                output.append(r"\begin{longtable}{" + spec + r"}\toprule")
                for row_index, row in enumerate(rows):
                    output.append(" & ".join(inline(cell) for cell in row) + r" \\")
                    if row_index == 0:
                        output.append(r"\midrule")
                output.append(r"\bottomrule\end{longtable}")
            continue

        if line.startswith("> "):
            quote = []
            while index < len(lines) and lines[index].startswith("> "):
                quote.append(lines[index][2:].strip())
                index += 1
            output.append(r"\begin{quote}\small " + inline(" ".join(quote)) + r"\end{quote}")
            continue

        if re.match(r"^\s*\d+\.\s+", line) or re.match(r"^\s*-\s+", line):
            ordered = bool(re.match(r"^\s*\d+\.\s+", line))
            environment = "enumerate" if ordered else "itemize"
            output.append(r"\begin{" + environment + "}")
            while index < len(lines):
                current = lines[index]
                matched = re.match(r"^\s*\d+\.\s+", current) if ordered else re.match(r"^\s*-\s+", current)
                if not matched:
                    break
                item = re.sub(r"^\s*(?:\d+\.|-)\s+", "", current).strip()
                output.append(r"\item " + inline(item))
                index += 1
            output.append(r"\end{" + environment + "}")
            continue

        if re.fullmatch(r"---+", line.strip()):
            output.append(r"\medskip\hrule\medskip")
            index += 1
            continue

        paragraph = [line.strip()]
        index += 1
        while index < len(lines) and lines[index].strip() and not re.match(
            r"^(#{1,4} |\||> |!\[|\s*\d+\.\s+|\s*-\s+)", lines[index]
        ):
            paragraph.append(lines[index].strip())
            index += 1
        output.append(inline(" ".join(paragraph)))

    return "\n\n".join(output)


if __name__ == "__main__":
    markdown = MD_PATH.read_text(encoding="utf-8")
    body = convert_markdown(markdown)
    tikz_help = r'''% Untuk menambahkan kotak merah manual pada sebuah gambar, gunakan pola berikut di dalam figure:
% \begin{tikzpicture}[remember picture,overlay]
%   \draw[red,line width=2pt] (x1,y1) rectangle (x2,y2);
% \end{tikzpicture}
% Sesuaikan koordinat berdasarkan ukuran gambar; source screenshot yang dipakai di sini bersih.
'''
    OUT_PATH.write_text(PREAMBLE + "\n" + tikz_help + "\n" + body + "\n\\end{document}\n", encoding="utf-8")
    print(f"Wrote {OUT_PATH.relative_to(ROOT)} ({OUT_PATH.stat().st_size} bytes)")
    generated = OUT_PATH.read_text(encoding="utf-8")
    references = re.findall(r"\\guideimage\{([^}]+)\}|\\imageplaceholder\{([^}]+)\}", generated)
    placeholders = sum(bool(placeholder) for _, placeholder in references)
    image_refs = [image.replace(r"\_", "_") for image, _ in references if image]
    missing = [image for image in image_refs if not (ROOT / "docs" / image).exists()]
    print(f"Figures: {len(references)} ({placeholders} blank placeholders)")
    print(f"Missing included image files: {missing}")
