<?php

namespace App\AI\Services;

use Illuminate\Support\Facades\File;

/**
 * Resolves a named garden from the canonical frontend demo corpus.
 *
 * Image generation must use the same records as the garden detail page. This
 * adapter deliberately performs exact name/id matching after whitespace and
 * case normalisation; fuzzy matching could select a different garden and put
 * believable but incorrect values into an image.
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
     *     used_hectare: int|float,
     *     available_hectare: int|float,
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

            $insight = $root['kebunInsights'][$id] ?? [];
            $insight = is_array($insight) ? $insight : [];

            return [
                'id' => $id,
                'name' => $name,
                'city' => trim((string) ($garden['city'] ?? '')),
                'district' => trim((string) ($garden['district'] ?? '')),
                'address' => trim((string) ($garden['address'] ?? '')),
                'used_hectare' => $this->number($garden['usedHectare'] ?? null),
                'available_hectare' => $this->number($garden['availableHectare'] ?? null),
                'seed_type' => trim((string) ($garden['seedType'] ?? '')),
                'seed_type_detail' => trim((string) ($garden['seedTypeDetail'] ?? '')),
                'health_status' => trim((string) ($garden['healthStatus'] ?? '')),
                'image' => trim((string) ($garden['image'] ?? '')),
                'insight' => $insight,
            ];
        }

        return null;
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
        $configuredPath = config('ai.knowledge.demo_data_path', resource_path('js/data/farmDemoData.json'));
        $path = is_string($configuredPath) && $configuredPath !== ''
            ? $configuredPath
            : resource_path('js/data/farmDemoData.json');
        if (! File::exists($path)) {
            return null;
        }

        try {
            $root = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        return is_array($root) ? $root : null;
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

    private function number(mixed $value): int|float
    {
        return is_int($value) || is_float($value) ? $value : 0;
    }
}
