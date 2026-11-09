<?php

namespace App\AI\Services;

use Illuminate\Support\Facades\File;

/**
 * Resolves a named garden from the canonical frontend demo corpus.
 *
 * Image generation must use the same records as the garden detail page.
 * Names, aliases and IDs are matched explicitly, never by fuzzy similarity.
 * Visual plans keep presentation choices separate from canonical data facts.
 */
class FarmSnapshotService
{
    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     city: string,
     *     district: string,
     *     address: string,
     *     used_hectare: int|float|null,
     *     available_hectare: int|float|null,
     *     seed_type: string,
     *     seed_type_detail: string,
     *     health_status: string,
     *     image: string,
     *     insight: array<string, mixed>
     * }|null
     */
    public function resolve(string $prompt): ?array
    {
        $root = $this->loadRoot();
        if ($root === null) {
            return null;
        }

        $promptText = $this->normalise($prompt);
        if ($promptText === '') {
            return null;
        }

        foreach ((array) ($root['kebun'] ?? []) as $garden) {
            if (! is_array($garden)) {
                continue;
            }

            $id = trim((string) ($garden['id'] ?? ''));
            $name = trim((string) ($garden['name'] ?? ''));
            if ($id === '' || $name === '') {
                continue;
            }

            $normalisedId = $this->normalise($id);
            $normalisedName = $this->normalise($name);
            if (! $this->containsPhrase($promptText, $normalisedName)
                && ! $this->containsPhrase($promptText, $normalisedId)) {
                continue;
            }

            return $this->snapshot($garden, $root);
        }

        return null;
    }

    /**
     * Plan a data infographic without allowing the prompt to supply its facts.
     * Null means this is an educational/general image, not a corpus lookup.
     * A corpus request that cannot be resolved returns clarify, never a guessed
     * farm or an instruction to let the image provider invent its numbers.
     *
     * @param  array<string, mixed>|null  $previous  Previously verified visual plan.
     * @return array<string, mixed>|null
     */
    public function plan(string $prompt, ?array $previous = null): ?array
    {
        $text = $this->normalise($prompt);
        // Finance retains its established, separately verified data workflow.
        // A negated finance clause must not hijack an agronomic farm summary.
        $positiveText = (string) preg_replace_callback('/\b(?:tanpa|kecuali|jangan\s+(?:tampilkan|sertakan|masukkan|tulis))\s+([^.!?;\n]+)/u', function (array $match): string {
            if (preg_match('/\b(?:tetapi|namun|tampilkan|sertakan|tambahkan|dengan|buat)\b/u', $match[1], $command, PREG_OFFSET_CAPTURE)) {
                return substr($match[1], $command[0][1]);
            }

            return '';
        }, $text);
        if (preg_match('/\b(keuangan|finansial|pendapatan|pemasukan|omzet|transaksi|laba|profit|saldo|keuntungan|pengeluaran|biaya|finance|financial|income|revenue|expense|expenses)\b/u', $positiveText)) {
            return null;
        }
        $followUp = $previous !== null && (bool) preg_match('/\b(ubah|ganti|tadi|tersebut|yang sama|sebelumnya|jadikan|buat ulang)\b/u', $text);
        $corpus = $this->loadCorpus();
        $root = $corpus['root'] ?? [];
        $gardens = array_values(array_filter((array) ($root['kebun'] ?? []), fn ($garden): bool => is_array($garden) && ! empty($garden['id']) && ! empty($garden['name'])));
        $matches = [];
        $excluded = [];

        foreach ($gardens as $garden) {
            $name = $this->normalise((string) $garden['name']);
            $aliases = [$name, $this->normalise((string) $garden['id']), (string) preg_replace('/^kebun\s+/u', '', $name)];
            $positions = [];
            foreach ($aliases as $alias) {
                if (preg_match_all('/(?<![\pL\pN])'.preg_quote($alias, '/').'(?=$|[^\pL\pN])/u', $text, $found, PREG_OFFSET_CAPTURE)) {
                    foreach ($found[0] as [$phrase, $position]) {
                        $prefix = substr($text, max(0, $position - 100), min(100, $position));
                        if (preg_match('/(?:tanpa|kecuali|exclude|jangan\s+(?:sertakan|tampilkan|masukkan))\s+(?:kebun\s+)?$/u', $prefix)) {
                            $excluded[(string) $garden['id']] = true;
                        } else {
                            $positions[] = $position;
                        }
                    }
                }
            }
            if ($positions !== []) {
                $matches[(string) $garden['id']] = ['garden' => $garden, 'position' => min($positions)];
            }
        }

        $hasGardenWord = (bool) preg_match('/\b(kebun|perkebunan|farm|farms|farmdemodata(?:\.json)?)\b/u', $text);
        $hasDataRequest = (bool) preg_match('/\b(ringkasan|rekap|data|dashboard|kondisi|komparasi|bandingkan|perbandingan|luas|lahan|status|risiko|resiko|pertumbuhan|panen|seluruh|semua|all)\b/u', $text);
        $newEducationalTopic = (bool) preg_match('/\b(pemupukan|pupuk|cara\s+(?:menanam|merawat)|tutorial|panduan\s+(?:tanam|menanam|pemeliharaan)|edukasi|edukatif)\b/u', $text)
            || (bool) preg_match('/\b(?:ilustrasi|gambar)\b.*\btanaman\b.*\btanpa\s+(?:teks|angka)\b/u', $text);
        $explicitDataSelection = (bool) preg_match('/\b(data|ringkasan|rekap|dashboard|komparasi|bandingkan|perbandingan)\b/u', $text)
            || ! empty($this->selectedFields($text));
        if ($newEducationalTopic && ! $explicitDataSelection && ! preg_match('/\bfarmdemodata(?:\.json)?\b/u', $text)) {
            return null;
        }
        $unknownFarm = $this->containsUnknownFarm($text, $gardens);
        if ($matches === [] && ! ($hasGardenWord && ($hasDataRequest || $unknownFarm)) && ! $followUp) {
            return null;
        }

        $base = [
            'status' => 'clarify',
            'message' => null,
            'source' => $corpus['source'] ?? ['name' => 'farmDemoData.json', 'version' => null, 'generated_at' => null, 'sha256' => ''],
            'farms' => [],
            'fields' => [],
            'layout' => 'summary',
            'orientation' => 'portrait',
            'title' => 'Ringkasan Kebun',
            'theme' => 'green',
            'facts' => [],
            'warnings' => [],
        ];
        if ($corpus === null || $gardens === []) {
            return array_replace($base, ['message' => 'Data farmDemoData.json belum dapat dibaca. Infografis tidak dibuat agar tidak menampilkan data rekaan.']);
        }
        if ($unknownFarm) {
            return array_replace($base, ['message' => 'Ada nama kebun yang tidak tercatat di farmDemoData.json. Pilih nama kebun yang tersedia atau minta seluruh kebun.']);
        }

        uasort($matches, fn (array $a, array $b): int => $a['position'] <=> $b['position']);
        $selected = array_column(array_values($matches), 'garden');
        $all = (bool) preg_match('/\b(semua|seluruh|all)\s+(?:(?:data|ringkasan|daftar|lima|5)\s+)?(?:kebun|perkebunan|farm|farms)\b|\b(?:kebun|farm)\s+(?:semuanya|seluruhnya)\b/u', $text);
        $fromCorpus = (bool) preg_match('/\bfarmdemodata(?:\.json)?\b/u', $text);
        $filter = $this->farmFilters($text);
        if ($all || ($selected === [] && ($filter !== [] || $fromCorpus))) {
            $selected = $gardens;
        } elseif ($selected === [] && $followUp) {
            $ids = array_column((array) ($previous['farms'] ?? []), 'id');
            $byId = array_column($gardens, null, 'id');
            foreach ($ids as $id) {
                if (isset($byId[$id])) {
                    $selected[] = $byId[$id];
                }
            }
        }
        $selected = array_values(array_filter($selected, function (array $garden) use ($filter, $excluded): bool {
            if (isset($excluded[(string) $garden['id']])) {
                return false;
            }
            foreach ($filter as $key => $value) {
                if ($this->normalise((string) ($garden[$key] ?? '')) !== $value) {
                    return false;
                }
            }

            return true;
        }));
        if ($selected === []) {
            return array_replace($base, ['message' => $filter !== [] ? 'Tidak ada kebun dalam farmDemoData.json yang cocok dengan filter tersebut.' : 'Kebun mana yang ingin ditampilkan? Sebutkan nama kebun atau pilih seluruh kebun dari farmDemoData.json.']);
        }
        if (preg_match('/\b(bandingkan|perbandingan|komparasi|compare|versus|vs)\b/u', $text) && count($selected) < 2) {
            return array_replace($base, ['message' => 'Perbandingan memerlukan minimal dua kebun yang tercatat. Sebutkan kedua nama kebun.']);
        }

        $farms = array_map(fn (array $garden): array => $this->snapshot($garden, $root), $selected);
        $fields = $this->selectedFields($text);
        if ($fields === null) {
            return array_replace($base, ['message' => 'Semua informasi yang dipilih juga dikecualikan dalam prompt. Pilih informasi kebun yang ingin ditampilkan.']);
        }
        if ($fields === [] && $this->hasUnsupportedOnlySelection($text, $gardens)) {
            return array_replace($base, ['message' => 'Informasi yang diminta belum tersedia dalam farmDemoData.json. Pilih luas lahan, komoditas, status, pertumbuhan, risiko, tindak lanjut, bobot panen, kondisi tanaman, cuaca, tanggal, lokasi, atau alamat.']);
        }
        if ($fields === []) {
            $fields = $followUp && $matches === [] && ! $all && ! empty($previous['fields'])
                ? array_values(array_intersect((array) $previous['fields'], $this->fieldKeys()))
                : ['location', 'crop', 'status', 'used_hectare', 'available_hectare', 'growth', 'weight_ton'];
        }
        $layout = $followUp && $matches === [] ? (string) ($previous['layout'] ?? 'summary') : 'summary';
        if (preg_match('/\b(bar|batang)\b/u', $text)) {
            $layout = 'bar';
        } elseif (preg_match('/\b(pie|donut|donat|doughnut|lingkaran)\b/u', $text)) {
            $layout = 'pie';
        } elseif (preg_match('/\b(bandingkan|perbandingan|komparasi|compare|versus|vs)\b/u', $text)) {
            $layout = 'comparison';
        } elseif (in_array('risk', $fields, true) && count(array_diff($fields, ['risk', 'next_step', 'status'])) === 0) {
            $layout = 'risk';
        }
        $orientation = $followUp && $matches === [] ? (string) ($previous['orientation'] ?? 'portrait') : 'portrait';
        if (preg_match('/\b(landscape|horizontal|mendatar)\b/u', $text)) {
            $orientation = 'landscape';
        } elseif (preg_match('/\b(portrait|potrait|vertikal|tegak)\b/u', $text)) {
            $orientation = 'portrait';
        }
        $theme = $followUp && $matches === [] ? (string) ($previous['theme'] ?? 'green') : 'green';
        if (preg_match('/\b(monokrom|monochrome|hitam\s+(?:dan\s+)?putih)\b/u', $text)) {
            $theme = 'monochrome';
        } elseif (preg_match('/\b(biru|blue)\b/u', $text)) {
            $theme = 'blue';
        } elseif (preg_match('/\b(hijau|green)\b/u', $text)) {
            $theme = 'green';
        }
        $title = count($farms) === 1 ? 'Ringkasan '.$farms[0]['name'] : (count($farms) === count($gardens) ? 'Ringkasan Seluruh Kebun' : 'Perbandingan Kebun');
        if ($layout === 'risk') {
            $title = count($farms) === 1 ? 'Risiko '.$farms[0]['name'] : 'Risiko Kebun';
        }
        if (preg_match('/(?:judul|title)\s*(?::|=)?\s*["“\']([^"”\'\r\n]{1,100})["”\']/iu', $prompt, $titleMatch)) {
            $title = trim(strip_tags($titleMatch[1]));
        }

        $facts = [];
        $warnings = [];
        foreach ($farms as $farm) {
            foreach ($fields as $field) {
                $fact = $this->fact($farm, $field);
                if ($fact !== null) {
                    $facts[] = $fact;
                } else {
                    $warnings[] = $farm['name'].': '.$this->fieldLabel($field).' belum tercatat; tidak ditampilkan.';
                }
            }
        }
        if ($facts === []) {
            return array_replace($base, [
                'message' => 'Informasi yang dipilih belum tercatat dalam farmDemoData.json. Pilih informasi lain agar infografis dapat dibuat tanpa menebak data.',
                'warnings' => array_values(array_unique($warnings)),
            ]);
        }
        if (preg_match('/\b(?:\d[\d.,]*)\s*(?:ha|hektar|hectare|ton|%)\b/u', $text)) {
            $warnings[] = 'Angka dalam prompt tidak mengganti data. Semua nilai menggunakan farmDemoData.json.';
        }

        return array_replace($base, [
            'status' => 'ready', 'farms' => $farms, 'fields' => $fields,
            'layout' => in_array($layout, ['summary', 'bar', 'pie', 'comparison', 'risk'], true) ? $layout : 'summary',
            'orientation' => $orientation === 'landscape' ? 'landscape' : 'portrait',
            'title' => $title, 'theme' => in_array($theme, ['green', 'blue', 'monochrome'], true) ? $theme : 'green',
            'facts' => $facts, 'warnings' => array_values(array_unique($warnings)),
        ]);
    }

    /**
     * Build a compact, explicit context for a provider-backed image request.
     * The local renderer is preferred for named gardens, but this context is
     * also useful to callers that choose a provider for a visual variation.
     *
     * @param  array<string, mixed>  $snapshot
     */
    public function imagePromptContext(array $snapshot): string
    {
        $facts = [
            'id' => (string) ($snapshot['id'] ?? ''),
            'nama' => (string) ($snapshot['name'] ?? ''),
            'lokasi' => trim(implode(', ', array_filter([
                (string) ($snapshot['district'] ?? ''),
                (string) ($snapshot['city'] ?? ''),
            ]))),
            'alamat' => (string) ($snapshot['address'] ?? ''),
            'luas_terpakai_ha' => $snapshot['used_hectare'] ?? null,
            'lahan_tersedia_ha' => $snapshot['available_hectare'] ?? null,
            'komoditas' => (string) ($snapshot['seed_type'] ?? ''),
            'varietas' => (string) ($snapshot['seed_type_detail'] ?? ''),
            'status' => (string) ($snapshot['health_status'] ?? ''),
            'insight' => $snapshot['insight'] ?? [],
        ];

        return 'Data kebun terverifikasi dari farmDemoData.json. Gunakan hanya fakta JSON berikut; jangan menambah atau menebak atribut yang tidak tercantum. Jika atribut tidak tercantum, jangan tampilkan. '
            .json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            .' Jangan mengubah angka, nama, komoditas, lokasi, status, atau satuan.';
    }

    /** @return array<string, mixed>|null */
    private function loadRoot(): ?array
    {
        return $this->loadCorpus()['root'] ?? null;
    }

    /** @return array{root: array<string, mixed>, source: array<string, mixed>}|null */
    private function loadCorpus(): ?array
    {
        $configuredPath = config('ai.knowledge.demo_data_path', resource_path('js/data/farmDemoData.json'));
        $path = is_string($configuredPath) && $configuredPath !== ''
            ? $configuredPath
            : resource_path('js/data/farmDemoData.json');
        if (! File::exists($path)) {
            return null;
        }

        try {
            $contents = File::get($path);
            $root = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        return is_array($root) ? [
            'root' => $root,
            'source' => [
                'name' => 'farmDemoData.json',
                'version' => $root['version'] ?? null,
                'generated_at' => is_string($root['generatedAt'] ?? null) ? $root['generatedAt'] : null,
                'sha256' => hash('sha256', $contents),
            ],
        ] : null;
    }

    /** @param array<string, mixed> $garden
     * @param  array<string, mixed>  $root
     * @return array{id: string, name: string, city: string, district: string, address: string, used_hectare: int|float|null, available_hectare: int|float|null, seed_type: string, seed_type_detail: string, health_status: string, image: string, insight: array<string, mixed>}
     */
    private function snapshot(array $garden, array $root): array
    {
        $id = trim((string) ($garden['id'] ?? ''));

        return [
            'id' => $id,
            'name' => trim((string) ($garden['name'] ?? '')),
            'city' => trim((string) ($garden['city'] ?? '')),
            'district' => trim((string) ($garden['district'] ?? '')),
            'address' => trim((string) ($garden['address'] ?? '')),
            'used_hectare' => $this->number($garden['usedHectare'] ?? null),
            'available_hectare' => $this->number($garden['availableHectare'] ?? null),
            'seed_type' => trim((string) ($garden['seedType'] ?? '')),
            'seed_type_detail' => trim((string) ($garden['seedTypeDetail'] ?? '')),
            'health_status' => trim((string) ($garden['healthStatus'] ?? '')),
            'image' => trim((string) ($garden['image'] ?? '')),
            'insight' => is_array($root['kebunInsights'][$id] ?? null) ? $root['kebunInsights'][$id] : [],
        ];
    }

    /** @param list<array<string, mixed>> $gardens */
    private function containsUnknownFarm(string $text, array $gardens): bool
    {
        $knownText = $text;
        foreach ($gardens as $garden) {
            foreach ([(string) $garden['name'], (string) $garden['id']] as $known) {
                $knownText = preg_replace('/(?<![\pL\pN])'.preg_quote($this->normalise($known), '/').'(?=$|[^\pL\pN])/u', ' kebun-tercatat ', $knownText) ?? $knownText;
            }
        }
        if (preg_match('/\bkebun-\d+\b/u', $knownText)) {
            return true;
        }
        if (! preg_match_all('/\bkebun\s+([\pL][\pL\s-]*)/u', $knownText, $mentions)) {
            return false;
        }
        foreach ($mentions[1] as $mention) {
            $firstWord = explode(' ', trim($mention))[0];
            if (! in_array($firstWord, ['yang', 'berstatus', 'dengan', 'untuk', 'dari', 'pada', 'di', 'dan', 'atau', 'ini', 'itu', 'tersebut', 'saya', 'kami', 'saja', 'hanya', 'jagung', 'kedelai', 'rumput', 'sehat', 'kritis', 'seluruhnya', 'semuanya', 'berikut', 'secara', 'tanpa', 'kecuali', 'dalam'], true)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, string> */
    private function farmFilters(string $text): array
    {
        $filters = [];
        foreach (['jagung', 'kedelai', 'rumput'] as $crop) {
            if (preg_match('/\b(?:kebun|komoditas|tanaman|jenis)\s+'.preg_quote($crop, '/').'\b/u', $text)) {
                $filters['seedType'] = $crop;
            }
        }
        foreach (['perlu perhatian', 'kritis', 'sehat'] as $status) {
            if (preg_match('/\b(?:berstatus|status|kebun|kondisi)\s+'.preg_quote($status, '/').'\b/u', $text)) {
                $filters['healthStatus'] = $status;
                break;
            }
        }

        return $filters;
    }

    /** @return list<string>|null Null when every selected field is explicitly excluded. */
    private function selectedFields(string $text): ?array
    {
        $excluded = [];
        $text = (string) preg_replace_callback('/\b(?:tanpa|kecuali|jangan\s+(?:tampilkan|sertakan|masukkan))\s+([^.!?;\n]+)/u', function (array $match) use (&$excluded): string {
            $clause = $match[1];
            $positive = '';
            // Commas continue an exclusion list. A new positive instruction
            // ends that list, so "tanpa X, Y, tampilkan Z" still displays Z.
            if (preg_match('/\b(?:tetapi|namun|tampilkan|sertakan|tambahkan|dengan|hanya|only)\b/u', $clause, $command, PREG_OFFSET_CAPTURE)) {
                $positive = substr($clause, $command[0][1]);
                $clause = substr($clause, 0, $command[0][1]);
            }
            $excluded = array_merge($excluded, $this->selectedFields($clause) ?? []);

            return ' '.$positive;
        }, $text);
        $allFields = (bool) preg_match('/\b(?:semua|seluruh)\s+(?:detail|informasi|atribut|kolom)|\bdata\s+(?:lengkap|detail)\b/u', $text);
        // A selection filter does not request an extra displayed metric.
        $text = (string) preg_replace('/\b(?:berstatus|status)\s+(?:perlu perhatian|kritis|sehat)\b/u', '', $text);
        $fields = $allFields ? $this->fieldKeys() : [];
        $used = (bool) preg_match('/\b(?:terpakai|ditanami|tertanam|luas\s+tanam|lahan\s+tanam)\b/u', $text);
        $available = (bool) preg_match('/\b(?:total\s+(?:lahan|luas)|luas\s+total|kapasitas|(?:lahan|luas)\s+tersedia)\b/u', $text);
        if ($used || $available || preg_match('/\b(luas|lahan|area)\b/u', $text)) {
            if ($used || ! $available) {
                $fields[] = 'used_hectare';
            }
            if ($available || ! $used) {
                $fields[] = 'available_hectare';
            }
        }
        $patterns = [
            'crop' => '/\b(komoditas|varietas|jenis\s+tanaman)\b/u',
            'status' => '/\b(status|kesehatan)\b/u',
            'growth' => '/\b(pertumbuhan|progres|growth)\b/u',
            'risk' => '/\b(risiko|resiko|risk)\b/u',
            'next_step' => '/\b(tindak\s+lanjut|langkah\s+(?:selanjutnya|berikutnya)|rekomendasi|saran|penanganan)\b/u',
            'weight_ton' => '/\b(bobot|berat|tonase|hasil\s+panen)\b/u',
            'plant_condition' => '/\b(kondisi\s+tanaman|kondisi\s+pertanaman)\b/u',
            'weather' => '/\b(cuaca|suhu|kelembapan)\b/u',
            'planted_at' => '/\b(?:tanggal|waktu|jadwal)\s+tanam\b/u',
            'harvest_at' => '/\b(?:tanggal|waktu|jadwal)\s+panen\b/u',
            'location' => '/\b(lokasi|kota|kecamatan)\b/u',
            'address' => '/\b(alamat)\b/u',
        ];
        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $text)) {
                $fields[] = $key;
            }
        }

        if ($fields === [] && $excluded !== []) {
            $fields = ['location', 'crop', 'status', 'used_hectare', 'available_hectare', 'growth', 'weight_ton'];
        }

        $included = array_values(array_diff(array_unique($fields), $excluded));

        return $fields !== [] && $included === [] ? null : $included;
    }

    /** @param list<array<string, mixed>> $gardens */
    private function hasUnsupportedOnlySelection(string $text, array $gardens): bool
    {
        if (! preg_match('/\b(?:hanya|only)\s+([^.!?;]+)/u', $text, $selection)
            && ! preg_match('/(?:^|,)\s*([^,.!?;]+?)\s+saja\b/u', $text, $selection)) {
            return false;
        }
        $selectionText = $selection[1];
        foreach ($gardens as $garden) {
            $name = $this->normalise((string) $garden['name']);
            foreach ([$name, (string) preg_replace('/^kebun\s+/u', '', $name), (string) $garden['id']] as $known) {
                $selectionText = (string) preg_replace('/(?<![\pL\pN])'.preg_quote($known, '/').'(?=$|[^\pL\pN])/u', '', $selectionText);
            }
        }
        // Restricting which farms appear is valid without selecting a metric.
        // Anything beyond these identity/filter/presentation words requires a
        // supported field, rather than silently restoring the full summary.
        $selectionText = (string) preg_replace('/\b(kebun|perkebunan|farm|farms|ringkasan|rekap|data|infografis|infographic|grafik|gambar|buat|buatkan|tampilkan|sertakan|pilih|untuk|yang|berstatus|berkomoditas|dan|atau|ini|itu|tersebut|saja|seluruh|semua|dari|dengan|ke|di|sehat|kritis|perlu\s+perhatian|jagung|kedelai|rumput|portrait|potrait|landscape|horizontal|vertikal|bar|batang|pie|donut|donat|tema|warna|hijau|biru|monokrom|monochrome)\b/u', '', $selectionText);

        return (bool) preg_match('/[\pL\pN]/u', $selectionText);
    }

    /** @return list<string> */
    private function fieldKeys(): array
    {
        return ['used_hectare', 'available_hectare', 'crop', 'status', 'growth', 'risk', 'next_step', 'weight_ton', 'plant_condition', 'weather', 'planted_at', 'harvest_at', 'location', 'address'];
    }

    private function fieldLabel(string $key): string
    {
        return [
            'used_hectare' => 'Luas terpakai', 'available_hectare' => 'Total lahan',
            'crop' => 'Komoditas dan varietas', 'status' => 'Status kebun',
            'growth' => 'Pertumbuhan', 'risk' => 'Risiko', 'next_step' => 'Tindak lanjut',
            'weight_ton' => 'Bobot panen', 'plant_condition' => 'Kondisi tanaman',
            'weather' => 'Cuaca', 'planted_at' => 'Tanggal tanam', 'harvest_at' => 'Tanggal panen',
            'location' => 'Lokasi', 'address' => 'Alamat',
        ][$key] ?? $key;
    }

    /** @param array<string, mixed> $farm
     * @return array<string, mixed>|null
     */
    private function fact(array $farm, string $key): ?array
    {
        $insight = $farm['insight'];
        $unit = '';
        $value = match ($key) {
            'used_hectare', 'available_hectare' => $farm[$key] ?? null,
            'crop' => implode(' · ', array_filter([$farm['seed_type'], $farm['seed_type_detail']])),
            'status' => $farm['health_status'],
            'growth' => $this->number($insight['growth'] ?? null),
            'risk' => $insight['risk'] ?? null,
            'next_step' => $insight['nextStep'] ?? null,
            'weight_ton' => $this->number($insight['weightTon'] ?? null),
            'plant_condition' => $insight['plantCondition'] ?? null,
            'weather' => is_array($insight['weather'] ?? null) ? implode(' · ', array_filter(array_map(fn ($value): string => is_scalar($value) ? (string) $value : '', $insight['weather']))) : null,
            'planted_at' => $insight['plantedAt'] ?? null,
            'harvest_at' => $insight['harvestAt'] ?? null,
            'location' => implode(', ', array_filter([$farm['district'], $farm['city']])),
            'address' => $farm['address'],
            default => null,
        };
        if ($value === null || $value === '' || ! is_scalar($value)) {
            return null;
        }
        if (in_array($key, ['used_hectare', 'available_hectare', 'weight_ton', 'growth'], true)) {
            $unit = match ($key) {
                'used_hectare', 'available_hectare' => 'ha',
                'growth' => '%',
                default => 'ton',
            };
            $number = $this->number($value);
            if ($number === null) {
                return null;
            }
            $value = $number;
            $display = rtrim(rtrim(number_format($number, 6, ',', '.'), '0'), ',').($unit === '%' ? '%' : ' '.$unit);
        } else {
            $display = (string) $value;
        }

        return ['farm_id' => $farm['id'], 'farm_name' => $farm['name'], 'key' => $key, 'label' => $this->fieldLabel($key), 'value' => $value, 'unit' => $unit, 'display' => $display];
    }

    private function normalise(string $value): string
    {
        $value = trim(mb_strtolower($value, 'UTF-8'));
        $value = (string) preg_replace('/\s+/u', ' ', $value);

        return trim($value);
    }

    private function containsPhrase(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return false;
        }

        return (bool) preg_match('/(?<![\pL\pN])'.preg_quote($needle, '/').'(?=$|[^\pL\pN])/u', $haystack);
    }

    private function number(mixed $value): int|float|null
    {
        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value) ? $value : null;
        }

        return is_string($value) && is_numeric($value) && is_finite((float) $value) ? $value + 0 : null;
    }
}
