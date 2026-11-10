<?php

// Reuses the dedicated database guards; never touches application demo data.
require __DIR__.'/petugas-task-fixture.php';

Illuminate\Support\Facades\Gate::forUser($admin)->authorize('create', [App\Models\PlantingCycle::class, $block]);
$planned = app(App\Services\FarmBlockService::class)->createBatch($block, $admin, $team, [
    'subblock_label' => 'B', 'reserved_area_ha' => '1',
    'planned_planting_at' => today()->subDays(3)->toDateString(),
]);
$sops = app(App\Services\SopTemplateService::class);
Illuminate\Support\Facades\Gate::forUser($admin)->authorize('create', [App\Models\SopTemplate::class, $team]);
$template = $sops->create($admin, $team, [
    'commodity_id' => $commodity->id, 'name' => 'SOP Jagung Cileuksa', 'is_active' => true,
]);
$version = $template->versions()->firstOrFail();
Illuminate\Support\Facades\Gate::forUser($admin)->authorize('create', [App\Models\SopTemplateTask::class, $version]);
$sops->createTask($version, [
    'activity_category' => 'maintenance', 'activity_subcategory' => 'irrigation',
    'name' => 'Pengairan awal jagung', 'offset_days' => 7,
    'tolerance_days' => 2, 'is_required' => true,
]);
Illuminate\Support\Facades\Gate::forUser($admin)->authorize('update', $version);
$sops->publish($version, $admin);
Illuminate\Support\Facades\Gate::forUser($admin)->authorize('update', $farm);
$farm->forceFill(['default_sop_template_id' => $template->id])->save();
App\Models\ActivityType::firstOrCreate(['code' => 'planting'], ['name' => 'Penanaman']);
echo json_encode(['cycle' => $planned->id, 'variety' => $variety->id], JSON_THROW_ON_ERROR).PHP_EOL;
