<?php

namespace App\Services\Activity;

use App\Enums\IncidentSeverity;

class ActivityReportHighlightService
{
    /** @param array<string, mixed> $values */
    public function format(string $code, array $values): ?string
    {
        return match ($code) {
            'planting' => $this->planting($values),
            'harvest' => $this->harvest($values),
            'incident' => $this->incident($values),
            'land_cleaning', 'soil_preparation', 'base_fertilization', 'other' => $this->area($values),
            'maintenance' => $this->area($values) ?? $this->growthObservation($values),
            'growth_observation' => $this->growthObservation($values),
            default => null,
        };
    }

    /** @param array<string, mixed> $values */
    private function growthObservation(array $values): ?string
    {
        $phase = $this->text($values['growth_phase'] ?? null);

        return $phase === null ? null : 'Fase pertumbuhan: '.$phase;
    }

    /** @param array<string, mixed> $values */
    private function area(array $values): ?string
    {
        $area = $this->quantity($values['area'] ?? null, $values['area_unit'] ?? null, [
            'm²' => 'm²', 'm2' => 'm²', 'Ha' => 'ha', 'ha' => 'ha',
        ]) ?? $this->quantity($values['area_ha'] ?? null, 'ha', ['ha' => 'ha']);

        return $area === null ? null : 'Luas dikerjakan: '.$area;
    }

    /** @param array<string, mixed> $values */
    private function planting(array $values): ?string
    {
        $name = $this->text($values['seed_name'] ?? null);
        $quantity = $this->quantity($values['quantity'] ?? null, $values['quantity_unit'] ?? null, [
            'kg' => 'kg', 'sak' => 'sak', 'butir' => 'butir',
        ]) ?? $this->quantity($values['quantity_kg'] ?? null, 'kg', ['kg' => 'kg']);

        if ($name === null) {
            return $quantity === null ? null : 'Jumlah benih: '.$quantity;
        }

        return 'Benih: '.$name.($quantity === null ? '' : ' · '.$quantity);
    }

    /** @param array<string, mixed> $values */
    private function harvest(array $values): ?string
    {
        $weight = $this->quantity($values['weight'] ?? null, $values['weight_unit'] ?? null, [
            'kg' => 'kg', 'ton' => 'ton',
        ]) ?? $this->quantity($values['gross_weight_ton'] ?? $values['weight_ton'] ?? null, 'ton', ['ton' => 'ton']);

        return $weight === null ? null : 'Berat panen: '.$weight;
    }

    /** @param array<string, mixed> $values */
    private function incident(array $values): ?string
    {
        $category = $this->text($values['incident_category'] ?? null);
        $severity = $this->text($values['severity'] ?? null);
        $severity = $severity === null ? null : (IncidentSeverity::fromStored(strtolower($severity))?->label() ?? $severity);
        if ($category === null) {
            return $severity === null ? null : 'Tingkat insiden: '.$severity;
        }

        return 'Insiden: '.$category.($severity === null ? '' : ' · '.$severity);
    }

    /** @param array<string, string> $units */
    private function quantity(mixed $value, mixed $unit, array $units): ?string
    {
        if (! is_string($unit) || ! isset($units[$unit])) {
            return null;
        }
        $number = $this->number($value);

        return $number === null ? null : $number.' '.$units[$unit];
    }

    private function number(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }
        $value = trim((string) $value);
        if (! preg_match('/^([+-]?)([0-9]*)(?:\.([0-9]*))?(?:[eE]([+-]?[0-9]+))?$/D', $value, $parts)) {
            return null;
        }
        $integer = $parts[2];
        $fraction = $parts[3] ?? '';
        if ($integer === '' && $fraction === '') {
            return null;
        }
        $exponent = (int) ($parts[4] ?? 0);
        if (abs($exponent) > 20) {
            return null;
        }
        $digits = $integer.$fraction;
        $point = strlen($integer) + $exponent;
        if ($point <= 0) {
            $whole = '0';
            $fraction = str_repeat('0', -$point).$digits;
        } elseif ($point >= strlen($digits)) {
            $whole = $digits.str_repeat('0', $point - strlen($digits));
            $fraction = '';
        } else {
            $whole = substr($digits, 0, $point);
            $fraction = substr($digits, $point);
        }
        $whole = ltrim($whole, '0') ?: '0';
        $whole = preg_replace('/\B(?=(?:\d{3})+(?!\d))/', '.', $whole);
        $fraction = rtrim($fraction, '0');

        return ($parts[1] === '-' ? '-' : '').$whole.($fraction === '' ? '' : ','.$fraction);
    }

    private function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
