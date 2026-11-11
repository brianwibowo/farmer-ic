<?php

namespace App\Enums;

enum IncidentSeverity: string
{
    case Light = 'light';
    case Medium = 'medium';
    case Heavy = 'heavy';
    case Emergency = 'emergency';

    public static function fromStored(?string $value): ?self
    {
        return match ($value) {
            'light', 'low' => self::Light,
            'medium', 'moderate' => self::Medium,
            'heavy', 'high' => self::Heavy,
            'emergency', 'critical' => self::Emergency,
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Light => 'Ringan', self::Medium => 'Sedang',
            self::Heavy => 'Berat', self::Emergency => 'Darurat',
        };
    }
}
