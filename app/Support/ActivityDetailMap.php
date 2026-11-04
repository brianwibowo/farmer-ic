<?php

namespace App\Support;

use App\Models\ActivityDetail;
use App\Models\BedPreparationDetail;
use App\Models\BlockMappingDetail;
use App\Models\FertilizationDetail;
use App\Models\HarvestDetail;
use App\Models\IncidentDetail;
use App\Models\IrrigationDetail;
use App\Models\LandCleaningDetail;
use App\Models\LimingDetail;
use App\Models\LooseningDetail;
use App\Models\OtherDetail;
use App\Models\PesticideDetail;
use App\Models\PlantingDetail;
use App\Models\PlantObservation;
use App\Models\WeedingDetail;

/** Single mapping from activity_types.detail_key to typed detail storage. */
class ActivityDetailMap
{
    /** @return class-string<ActivityDetail>|class-string<PlantObservation>|null */
    public static function model(?string $detailKey): ?string
    {
        return match ($detailKey) {
            'planting' => PlantingDetail::class,
            'fertilization' => FertilizationDetail::class,
            'irrigation' => IrrigationDetail::class,
            'pesticide' => PesticideDetail::class,
            'incident' => IncidentDetail::class,
            'harvest' => HarvestDetail::class,
            'growth_check' => PlantObservation::class,
            'block_mapping' => BlockMappingDetail::class,
            'land_cleaning' => LandCleaningDetail::class,
            'loosening' => LooseningDetail::class,
            'weeding' => WeedingDetail::class,
            'bed_preparation' => BedPreparationDetail::class,
            'liming' => LimingDetail::class,
            'other' => OtherDetail::class,
            default => null,
        };
    }

    public static function table(?string $detailKey): ?string
    {
        return match ($detailKey) {
            'planting' => 'planting_details',
            'fertilization' => 'fertilization_details',
            'irrigation' => 'irrigation_details',
            'pesticide' => 'pesticide_details',
            'incident' => 'incident_details',
            'harvest' => 'harvest_details',
            'growth_check' => 'plant_observations',
            'block_mapping' => 'block_mapping_details',
            'land_cleaning' => 'land_cleaning_details',
            'loosening' => 'loosening_details',
            'weeding' => 'weeding_details',
            'bed_preparation' => 'bed_preparation_details',
            'liming' => 'liming_details',
            'other' => 'other_details',
            default => null,
        };
    }
}
