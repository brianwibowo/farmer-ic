<?php

namespace App\Enums;

enum IncidentSeverity: string
{
    case Light = 'light';
    case Medium = 'medium';
    case Emergency = 'emergency';

    public static function fromStored(?string $value): ?self
    {
        return match (strtolower(trim((string) $value))) {
            'light', 'low', 'ringan' => self::Light,
            'medium', 'moderate', 'sedang' => self::Medium,
            'heavy', 'high', 'berat', 'emergency', 'critical', 'darurat' => self::Emergency,
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Light => 'Ringan',
            self::Medium => 'Sedang',
            self::Emergency => 'Darurat',
        };
    }

    public function priority(): int
    {
        return match ($this) {
            self::Emergency => 0,
            self::Medium => 1,
            self::Light => 2,
        };
    }

    /** @return list<string> */
    public static function labels(): array
    {
        return ['Ringan', 'Sedang', 'Darurat'];
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
