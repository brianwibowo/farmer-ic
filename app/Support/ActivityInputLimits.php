<?php

namespace App\Support;

/** Fixed input limits shared by request validation and activity forms. */
final class ActivityInputLimits
{
    public const WORKER_COUNT = 32767;

    public const NORMALIZED_QUANTITY_KG = 999999999.999;

    public const PLANTING_AREA = 99999999;

    public const PLANTING_QUANTITY = 999999999;

    public const PLANTING_BAG_SIZE = 99999;

    public const PLANTING_DEPTH = 1000;

    public const MAPPING_LENGTH = 9999999.999;

    public const MAPPING_SPACING = 999999.99;

    public const MAPPING_ROWS = 32767;

    public const SOP_OFFSET = 3650;

    public const SOP_TOLERANCE = 365;

    /** @return array<string, int|float> */
    public static function common(): array
    {
        return ['workerCount' => self::WORKER_COUNT, 'quantityKg' => self::NORMALIZED_QUANTITY_KG];
    }

    /** @return array<string, int|float> */
    public static function planting(): array
    {
        return self::common() + [
            'area' => self::PLANTING_AREA,
            'quantity' => self::PLANTING_QUANTITY,
            'bagSize' => self::PLANTING_BAG_SIZE,
            'depth' => self::PLANTING_DEPTH,
        ];
    }

    /** @return array<string, int|float> */
    public static function mapping(): array
    {
        return self::common() + [
            'length' => self::MAPPING_LENGTH,
            'spacing' => self::MAPPING_SPACING,
            'rows' => self::MAPPING_ROWS,
        ];
    }

    /** @return array<string, int> */
    public static function sop(): array
    {
        return ['offsetDays' => self::SOP_OFFSET, 'toleranceDays' => self::SOP_TOLERANCE];
    }
}
