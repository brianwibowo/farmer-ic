<?php

namespace App\Services;

use App\Models\ActivityPhoto;
use App\Models\AuditLog;
use App\Models\BlockMappingDetail;
use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmRisk;
use App\Models\FertilizationDetail;
use App\Models\FinancialBudget;
use App\Models\FinancialProjection;
use App\Models\FinancialTransaction;
use App\Models\HarvestDetail;
use App\Models\IncidentDetail;
use App\Models\IrrigationDetail;
use App\Models\OperationalTarget;
use App\Models\PesticideDetail;
use App\Models\PlantingCycle;
use App\Models\PlantingDetail;
use App\Models\PlantObservation;
use App\Models\User;
use App\Models\WeatherRecord;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class FarmDeletionService
{
    private const LABELS = [
        'planting_cycles' => 'Siklus tanam', 'daily_activity_logs' => 'Logbook harian',
        'farm_activities' => 'Aktivitas kebun', 'plant_observations' => 'Pengamatan tanaman',
        'farm_risks' => 'Risiko kebun', 'ai_insights' => 'Insight AI siklus tanam',
        'activity_photos' => 'Referensi foto kegiatan', 'block_mapping_details' => 'Detail pemetaan blok',
        'planting_details' => 'Detail penanaman', 'fertilization_details' => 'Detail pemupukan',
        'irrigation_details' => 'Detail penyiraman', 'pesticide_details' => 'Detail pestisida',
        'incident_details' => 'Detail kejadian', 'harvest_details' => 'Detail panen',
        'weather_records' => 'Riwayat cuaca', 'operational_targets' => 'Target operasional',
        'financial_transactions' => 'Transaksi keuangan', 'financial_projections' => 'Proyeksi keuangan',
        'financial_budgets' => 'Anggaran keuangan', 'users' => 'Penugasan akun yang akan dilepas',
    ];

    /** @return array{groups: list<array{key: string, label: string, count: int, effect: string}>, blockers: list<string>, token: string} */
    public function summary(User $actor, Farm $farm): array
    {
        return DB::transaction(function () use ($actor, $farm): array {
            $farm = $this->lockedFarm($actor, $farm);
            $graph = $this->graph($actor, $farm);

            return [
                'groups' => $this->groups($graph),
                'blockers' => $this->hasSharedReferences($graph) ? [$this->sharedMessage()] : [],
                'token' => $this->token($farm, $graph),
            ];
        });
    }

    public function forceDelete(User $actor, Farm $farm, string $confirmationName, string $reason, string $summaryToken): void
    {
        try {
            DB::transaction(function () use ($actor, $farm, $confirmationName, $reason, $summaryToken): void {
                $farm = $this->lockedFarm($actor, $farm);
                if ($confirmationName !== $farm->name) {
                    throw ValidationException::withMessages(['confirmation_name' => 'Nama kebun berubah. Muat ulang dan konfirmasi nama terbaru.']);
                }
                if (mb_strlen(trim($reason)) < 5 || mb_strlen($reason) > 2000) {
                    throw ValidationException::withMessages(['reason' => 'Alasan penghapusan harus 5 sampai 2.000 karakter.']);
                }
                $graph = $this->graph($actor, $farm);
                if ($this->hasSharedReferences($graph)) {
                    throw new ConflictHttpException($this->sharedMessage());
                }
                if (! hash_equals($this->token($farm, $graph), $summaryToken)) {
                    throw ValidationException::withMessages(['force_delete' => 'Data terkait kebun berubah. Muat ulang ringkasan dan konfirmasi kembali.']);
                }

                // Locking every selected parent prevents FK-backed child inserts from
                // joining this graph after confirmation. Never disable FK/trigger checks.
                DB::table('users')->whereIn('id', $graph['users'])->where('assigned_farm_id', $farm->id)
                    ->update(['assigned_farm_id' => null, 'updated_at' => now()]);
                foreach ([
                    'financial_transactions', 'financial_projections', 'financial_budgets', 'operational_targets',
                    'farm_risks', 'ai_insights', 'plant_observations', 'activity_photos',
                    'block_mapping_details', 'planting_details', 'fertilization_details', 'irrigation_details',
                    'pesticide_details', 'incident_details', 'harvest_details',
                    'farm_activities', 'daily_activity_logs', 'weather_records', 'planting_cycles',
                ] as $table) {
                    $key = str_ends_with($table, '_details') ? 'activity_id' : 'id';
                    DB::table($table)->whereIn($key, $graph[$table])->delete();
                }
                $snapshot = $farm->only(['id', 'name', 'team_id', 'land_tenure', 'total_area_ha']);
                $farm->delete();
                AuditLog::query()->create([
                    'user_id' => $actor->id, 'table_name' => 'farms', 'record_id' => $farm->id,
                    'action' => 'force_delete', 'old_values' => $snapshot,
                    'new_values' => ['reason' => trim($reason), 'summary' => $this->groups($graph)],
                ]);
            });
        } catch (QueryException $exception) {
            report($exception);
            throw new ConflictHttpException('Penghapusan gagal karena ada kendala database atau relasi lain. Tidak ada perubahan yang disimpan. Muat ulang dan periksa data terkait.', $exception);
        }
    }

    private function lockedFarm(User $actor, Farm $farm): Farm
    {
        return Farm::visibleTo($actor, $farm->team)->whereKey($farm->id)->lockForUpdate()->firstOrFail();
    }

    /** @return array<string, list<string>> */
    private function graph(User $actor, Farm $farm): array
    {
        $team = $farm->team;
        $graph = [];
        foreach ([
            'planting_cycles' => PlantingCycle::visibleTo($actor, $team)->where('farm_id', $farm->id),
            'daily_activity_logs' => DailyActivityLog::visibleTo($actor, $team)->where('farm_id', $farm->id),
            'farm_activities' => FarmActivity::visibleTo($actor, $team)->where('farm_id', $farm->id),
            'weather_records' => WeatherRecord::visibleTo($actor, $team)->where('farm_id', $farm->id),
            'financial_transactions' => FinancialTransaction::visibleTo($actor, $team)->where('farm_id', $farm->id),
            'financial_projections' => FinancialProjection::visibleTo($actor, $team)->where('farm_id', $farm->id),
            'financial_budgets' => FinancialBudget::visibleTo($actor, $team)->where('farm_id', $farm->id),
            'operational_targets' => OperationalTarget::visibleTo($actor, $team)->where('farm_id', $farm->id),
        ] as $table => $query) {
            $graph[$table] = $this->ids($query->toBase());
        }
        $graph['plant_observations'] = $this->ids(PlantObservation::visibleTo($actor, $team)->whereIn('planting_cycle_id', $graph['planting_cycles'])->toBase());
        $graph['farm_risks'] = $this->ids(FarmRisk::visibleTo($actor, $team)->whereIn('planting_cycle_id', $graph['planting_cycles'])->toBase());
        // ai_insights has no Eloquent model; scope it through the already-visible,
        // locked planting cycles. It is an internal child, not a new standalone resource.
        $graph['ai_insights'] = $this->ids(DB::table('ai_insights')->whereIn('planting_cycle_id', $graph['planting_cycles']));
        $graph['activity_photos'] = $this->ids(ActivityPhoto::visibleTo($actor, $team)->whereIn('activity_id', $graph['farm_activities'])->toBase());
        foreach ([BlockMappingDetail::class, PlantingDetail::class, FertilizationDetail::class, IrrigationDetail::class, PesticideDetail::class, IncidentDetail::class, HarvestDetail::class] as $detail) {
            $graph[(new $detail)->getTable()] = $this->ids($detail::visibleTo($actor, $team)->whereIn('activity_id', $graph['farm_activities'])->toBase(), 'activity_id');
        }
        $graph['users'] = $this->ids(User::withTrashed()->visibleTo($actor, $team)->where('assigned_farm_id', $farm->id)->toBase());

        return $graph;
    }

    /** @return list<string> */
    private function ids(Builder $query, string $key = 'id'): array
    {
        return array_values($query->orderBy($key)->lockForUpdate()->pluck($key)->map(static fn (string $id): string => $id)->all());
    }

    /** @param array<string, list<string>> $graph */
    private function hasSharedReferences(array $graph): bool
    {
        // Inspect physical inbound/outbound links, including records outside the
        // deletion graph. Such rows block deletion; they are never added to it.
        foreach ([
            ['farm_activities', 'planting_cycle_id', 'planting_cycles'],
            ['farm_activities', 'daily_activity_log_id', 'daily_activity_logs'],
            ['plant_observations', 'planting_cycle_id', 'planting_cycles'],
            ['plant_observations', 'activity_id', 'farm_activities'],
            ['farm_risks', 'planting_cycle_id', 'planting_cycles'],
            ['farm_risks', 'observation_id', 'plant_observations'],
            ['ai_insights', 'planting_cycle_id', 'planting_cycles'],
            ['ai_insights', 'observation_id', 'plant_observations'],
            ['financial_transactions', 'planting_cycle_id', 'planting_cycles'],
            ['financial_projections', 'planting_cycle_id', 'planting_cycles'],
            ['operational_targets', 'planting_cycle_id', 'planting_cycles'],
        ] as [$table, $column, $parent]) {
            if (DB::table($table)->whereIn('id', $graph[$table])->whereNotNull($column)->whereNotIn($column, $graph[$parent])->exists()
                || DB::table($table)->whereIn($column, $graph[$parent])->whereNotIn('id', $graph[$table])->exists()) {
                return true;
            }
        }

        return false;
    }

    private function sharedMessage(): string
    {
        return 'Ada relasi siklus, laporan, atau keuangan di luar kebun ini. Periksa dan pisahkan relasi tersebut sebelum force delete.';
    }

    /** @param array<string, list<string>> $graph
     * @return list<array{key: string, label: string, count: int, effect: string}>
     */
    private function groups(array $graph): array
    {
        $groups = [['key' => 'farms', 'label' => 'Kebun', 'count' => 1, 'effect' => 'delete']];
        foreach (self::LABELS as $table => $label) {
            $groups[] = ['key' => $table, 'label' => $label, 'count' => count($graph[$table]), 'effect' => $table === 'users' ? 'detach' : 'delete'];
        }

        return $groups;
    }

    /** @param array<string, list<string>> $graph */
    private function token(Farm $farm, array $graph): string
    {
        return hash_hmac('sha256', json_encode([$farm->id, $farm->name, $farm->team_id, $graph], JSON_THROW_ON_ERROR), (string) config('app.key'));
    }
}
