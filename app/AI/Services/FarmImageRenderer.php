<?php

namespace App\AI\Services;

use InvalidArgumentException;

/**
 * Renders a named garden's verified fields as a local SVG.
 *
 * Text in a generative bitmap can be silently changed by the model. Keeping
 * this visual local makes the garden name, location, crop and area values
 * exactly match farmDemoData.json and the garden detail page.
 */
class FarmImageRenderer
{
    /**
     * @param array{
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
     * } $snapshot
     */
    public function render(array $snapshot): string
    {
        $name = trim($snapshot['name']);
        if ($name === '') {
            throw new InvalidArgumentException('Garden snapshot is missing a name.');
        }

        $location = trim(implode(', ', array_filter([
            $snapshot['district'],
            $snapshot['city'],
        ])));
        $crop = trim(implode(' ', array_filter([
            $snapshot['seed_type'],
            $snapshot['seed_type_detail'],
        ])));
        $used = $this->formatNumber($snapshot['used_hectare']).' ha';
        $available = $this->formatNumber($snapshot['available_hectare']).' ha';
        $status = $snapshot['health_status'];
        $address = $snapshot['address'];
        $image = $snapshot['image'];
        $insight = $snapshot['insight'];

        $plantCondition = (string) ($insight['plantCondition'] ?? '');
        $growth = $this->formatOptional($insight['growth'] ?? null, '%');
        $plantedAt = (string) ($insight['plantedAt'] ?? '');
        $harvestAt = (string) ($insight['harvestAt'] ?? '');
        $weather = is_array($insight['weather'] ?? null) ? $insight['weather'] : [];
        $weatherLabel = trim(implode(' · ', array_filter([
            (string) ($weather['condition'] ?? ''),
            (string) ($weather['temperature'] ?? ''),
            (string) ($weather['humidity'] ?? ''),
        ])));

        $title = $this->escape($name);
        $escapedLocation = $this->escape($location);
        $escapedCrop = $this->escape($crop);
        $escapedUsed = $this->escape($used);
        $escapedAvailable = $this->escape($available);
        $escapedStatus = $this->escape($status);
        $escapedAddress = $this->escape($address);
        $escapedImage = $this->escape($image);
        $escapedCondition = $this->escape($plantCondition);
        $escapedGrowth = $this->escape($growth);
        $escapedPlantedAt = $this->escape($plantedAt);
        $escapedHarvestAt = $this->escape($harvestAt);
        $escapedWeather = $this->escape($weatherLabel);

        $imageMarkup = $image !== ''
            ? '<image href="'.$escapedImage.'" x="70" y="208" width="635" height="470" preserveAspectRatio="xMidYMid slice" clip-path="url(#crop)"/> '
            : '<rect x="70" y="208" width="635" height="470" rx="20" fill="#d9eadc"/> ';

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="860" viewBox="0 0 1200 860" role="img" aria-labelledby="title description">
  <title id="title">Kondisi Kebun {$title}</title>
  <desc id="description">Data terverifikasi {$title}: {$escapedCrop}, {$escapedLocation}, luas terpakai {$escapedUsed}, status {$escapedStatus}.</desc>
  <defs>
    <clipPath id="crop"><rect x="70" y="208" width="635" height="470" rx="20"/></clipPath>
    <linearGradient id="hero" x1="0" x2="1" y1="0" y2="1"><stop offset="0" stop-color="#e8f6ea"/><stop offset="1" stop-color="#f7fbf8"/></linearGradient>
  </defs>
  <rect width="1200" height="860" fill="#f5faf6"/>
  <rect x="32" y="32" width="1136" height="796" rx="30" fill="#ffffff" stroke="#d2e3d6" stroke-width="2"/>
  <rect x="32" y="32" width="1136" height="145" rx="30" fill="url(#hero)"/>
  <circle cx="92" cy="103" r="34" fill="#1d6b45"/>
  <path d="M77 112c12-22 22-27 32-34-1 17-9 30-27 38" fill="none" stroke="#ffffff" stroke-width="6" stroke-linecap="round"/>
  <path d="M81 120c11-7 20-14 27-24" fill="none" stroke="#ffffff" stroke-width="4" stroke-linecap="round"/>
  <text x="148" y="98" fill="#153f2b" font-family="Arial, sans-serif" font-size="35" font-weight="700">Kondisi Kebun {$title}</text>
  <text x="148" y="134" fill="#587365" font-family="Arial, sans-serif" font-size="21">Data kebun terverifikasi · farmDemoData.json</text>

  {$imageMarkup}
  <rect x="70" y="610" width="635" height="68" rx="16" fill="#144d34" fill-opacity="0.94"/>
  <text x="96" y="653" fill="#ffffff" font-family="Arial, sans-serif" font-size="25" font-weight="700">{$escapedCrop}</text>

  <g transform="translate(750 208)">
    <rect width="350" height="470" rx="22" fill="#f7fbf8" stroke="#cfe2d4" stroke-width="2"/>
    <text x="28" y="48" fill="#173b29" font-family="Arial, sans-serif" font-size="26" font-weight="700">Detail Kebun</text>
    <line x1="28" y1="70" x2="322" y2="70" stroke="#d2e3d6" stroke-width="2"/>
    <text x="28" y="111" fill="#587365" font-family="Arial, sans-serif" font-size="17">LOKASI</text>
    <text x="28" y="143" fill="#173b29" font-family="Arial, sans-serif" font-size="22" font-weight="700">{$escapedLocation}</text>
    <text x="28" y="172" fill="#587365" font-family="Arial, sans-serif" font-size="16">{$escapedAddress}</text>
    <line x1="28" y1="196" x2="322" y2="196" stroke="#d2e3d6" stroke-width="2"/>
    <text x="28" y="232" fill="#587365" font-family="Arial, sans-serif" font-size="17">LAHAN TERPAKAI</text>
    <text x="28" y="265" fill="#173b29" font-family="Arial, sans-serif" font-size="25" font-weight="700">{$escapedUsed}</text>
    <text x="28" y="300" fill="#587365" font-family="Arial, sans-serif" font-size="17">LAHAN TERSEDIA</text>
    <text x="28" y="333" fill="#173b29" font-family="Arial, sans-serif" font-size="25" font-weight="700">{$escapedAvailable}</text>
    <line x1="28" y1="356" x2="322" y2="356" stroke="#d2e3d6" stroke-width="2"/>
    <text x="28" y="392" fill="#587365" font-family="Arial, sans-serif" font-size="17">STATUS</text>
    <text x="28" y="426" fill="#146c43" font-family="Arial, sans-serif" font-size="25" font-weight="700">{$escapedStatus}</text>
  </g>

  <rect x="70" y="710" width="1030" height="82" rx="18" fill="#eef7f0"/>
  <text x="94" y="744" fill="#587365" font-family="Arial, sans-serif" font-size="16">PEMANTAUAN TERCATAT</text>
  <text x="94" y="774" fill="#173b29" font-family="Arial, sans-serif" font-size="18">{$escapedCondition}</text>
  <text x="744" y="744" fill="#587365" font-family="Arial, sans-serif" font-size="16">PERTUMBUHAN</text>
  <text x="744" y="774" fill="#173b29" font-family="Arial, sans-serif" font-size="18" font-weight="700">{$escapedGrowth}</text>
  <text x="872" y="744" fill="#587365" font-family="Arial, sans-serif" font-size="16">CUACA</text>
  <text x="872" y="774" fill="#173b29" font-family="Arial, sans-serif" font-size="18" font-weight="700">{$escapedWeather}</text>
</svg>
SVG;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function formatNumber(int|float $value): string
    {
        if (is_float($value) && fmod($value, 1.0) !== 0.0) {
            return number_format($value, 1, ',', '.');
        }

        return number_format((float) $value, 0, ',', '.');
    }

    private function formatOptional(mixed $value, string $suffix = ''): string
    {
        if (! is_int($value) && ! is_float($value) && ! is_string($value)) {
            return '';
        }

        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        return $text.$suffix;
    }
}
