<?php

namespace Database\Seeders;

use App\Models\ActivityPhoto;
use App\Models\AuditLog;
use App\Models\Commodity;
use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\FertilizationDetail;
use App\Models\FinancialTransaction;
use App\Models\PesticideDetail;
use App\Models\PlantingCycle;
use App\Models\PlantObservation;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\WeatherRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/** Updates recognized fixture copy in place; never resets accounts or operational values. */
class PresentationNamesSeeder extends Seeder
{
    private User $actor;

    private Team $team;

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('Pembaruan nama presentasi hanya tersedia pada environment local atau testing.');
        }
        // Bootstrap only the fixed trusted actor; protected work below uses native policies.
        $actor = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['superadmin']['email'])
            ->where('is_active', true)->whereHas('role', fn ($roles) => $roles->where('name', 'superadmin'))->first();
        $team = $actor?->teams()->where('slug', 'farm-ic-demo')->where('is_personal', false)->first();
        if (! $actor instanceof User || ! $team instanceof Team) {
            throw new RuntimeException('Superadmin dan tenant seed yang sah harus tersedia sebelum memperbarui nama.');
        }
        $this->actor = $actor;
        $this->team = $team;
        Gate::forUser($actor)->authorize('view', $team);

        DB::transaction(function (): void {
            $this->team = $this->query(Team::class)->whereKey($this->team->id)->lockForUpdate()->firstOrFail();
            if ($this->team->name === 'Farm IC Demo') {
                // Team's name observer normally changes its slug; fixture identity must stay stable.
                $this->write($this->team, ['name' => 'Hijauan'], quietly: true);
            }
            $this->identities();
            $base = $this->query(Farm::class)->where('team_id', $this->team->id)
                ->whereIn('name', DemoFarmSeeder::FARM_NAMES)->lockForUpdate()->first();
            if ($base instanceof Farm) {
                $this->replace($base, ['name' => ['Kebun Cileuksa Demo' => DemoFarmSeeder::FARM_NAME]]);
                $this->baseDetails($base);
            }
            foreach (DemoOwnerPortfolioData::sites() as $site) {
                $this->portfolio($site);
            }
            // This global catalog value is recognizable by its exact old seed copy.
            foreach ($this->query(Commodity::class)->where('name', 'Jagung')->get() as $commodity) {
                $this->replace($commodity, ['description' => [
                    'Komoditas utama lahan demo untuk simulasi logbook dan analitik.' => 'Komoditas utama lahan untuk simulasi logbook dan analitik.',
                ]]);
            }
        });
    }

    private function identities(): void
    {
        foreach (DemoIdentitySeeder::ACCOUNTS as $role => $account) {
            $user = $this->query(User::class)->where('email', $account['email'])
                ->whereHas('role', fn ($roles) => $roles->where('name', $role))
                ->whereHas('teams', fn ($teams) => $teams->whereKey($this->team->id))->first();
            if ($user instanceof User) {
                $this->replace($user, ['name' => [$account['name'].' Demo' => $account['name']]]);
            }
        }
        foreach ($this->query(Role::class)->where('name', 'superadmin')->get() as $role) {
            $this->replace($role, ['description' => [
                'Akses pengembang untuk seluruh area demo.' => 'Akses pengembang untuk seluruh area aplikasi.',
            ]]);
        }
    }

    private function baseDetails(Farm $farm): void
    {
        foreach ($this->query(DailyActivityLog::class)->where('farm_id', $farm->id)->get() as $log) {
            $this->replace($log, ['notes' => ['Pemetaan awal untuk persiapan bedengan demo.' => 'Pemetaan awal untuk persiapan bedengan.']]);
        }
        foreach ($this->query(PlantingCycle::class)->where('farm_id', $farm->id)->get() as $cycle) {
            $this->replace($cycle, ['subblock_label' => ['DEMO' => 'A']]);
        }
        foreach ($this->query(WeatherRecord::class)->where('farm_id', $farm->id)->get() as $weather) {
            $this->replace($weather, ['data_source' => ['Stasiun cuaca demo' => 'Stasiun cuaca']]);
        }
        foreach ($this->query(FinancialTransaction::class)->where('farm_id', $farm->id)->get() as $transaction) {
            $this->replace($transaction, ['description' => [
                'Pembelian benih jagung Bisi 18 untuk siklus demo' => 'Pembelian benih jagung Bisi 18 untuk siklus',
            ]]);
        }
        foreach ([new FertilizationDetail, new PesticideDetail] as $detailModel) {
            [$description, $old, $new] = $detailModel instanceof FertilizationDetail
                ? ['Pemupukan dasar Blok 1', 'NPK Demo', 'NPK']
                : ['Pengendalian ulat Blok 1', 'Bt Demo', 'Bt'];
            $activities = $this->query(FarmActivity::class)->where('farm_id', $farm->id)->where('description', $description)->pluck('id');
            foreach ($this->query($detailModel::class)->whereIn('activity_id', $activities)->get() as $detail) {
                $this->replace($detail, ['brand_name' => [$old => $new]]);
            }
        }
        foreach ($this->query(AuditLog::class)->where('action', 'demo_seeded')->where('record_id', $farm->id)->get() as $audit) {
            $values = $audit->new_values;
            if (is_array($values) && ($values['farm'] ?? null) === 'Kebun Cileuksa Demo') {
                $values['farm'] = DemoFarmSeeder::FARM_NAME;
                $this->write($audit, ['new_values' => $values]);
            }
        }
    }

    /** @param array<string, mixed> $site */
    private function portfolio(array $site): void
    {
        $farm = $this->query(Farm::class)->where('team_id', $this->team->id)
            ->whereKey(DemoOwnerPortfolioData::id($this->team, 'farm:'.$site['key']))->lockForUpdate()->first();
        if (! $farm instanceof Farm) {
            return;
        }
        $this->replace($farm, ['name' => [$site['name'].' Demo' => $site['name']]]);
        foreach ($this->query(FarmBlock::class)->where('farm_id', $farm->id)->get() as $block) {
            $this->replace($block, ['description' => [
                'Blok fisik pada skenario portofolio '.$site['name'].' Demo, snapshot 5 November 2026.' => 'Blok fisik pada skenario portofolio '.$site['name'].', snapshot 5 November 2026.',
            ]]);
        }
        foreach ($site['batches'] as $batch) {
            foreach ($this->query(PlantingCycle::class)->where('farm_id', $farm->id)
                ->where('subblock_label', 'DEMO-'.$batch['subblock_label'])->get() as $cycle) {
                $this->replace($cycle, ['subblock_label' => ['DEMO-'.$batch['subblock_label'] => $batch['subblock_label']]]);
            }
            $key = $site['key'].':'.$batch['key'];
            $ids = array_map(fn (string $code) => DemoOwnerPortfolioData::id($this->team, 'activity:'.$key.':'.$code),
                ['planting', 'fertilization', 'irrigation', 'pesticide', 'growth_check', 'harvest']);
            $activities = $this->query(FarmActivity::class)->where('farm_id', $farm->id)->whereIn('id', $ids)->get();
            foreach ($activities as $activity) {
                $this->cleanFields($activity, ['description']);
            }
            $ids = $activities->pluck('id')->all();
            foreach ([new FertilizationDetail, new PesticideDetail] as $detailModel) {
                foreach ($this->query($detailModel::class)->whereIn('activity_id', $ids)->get() as $detail) {
                    $this->cleanFields($detail, ['brand_name']);
                }
            }
            $observation = $this->query(PlantObservation::class)->whereIn('activity_id', $ids)
                ->whereKey(DemoOwnerPortfolioData::id($this->team, 'observation:'.$key))->first();
            if ($observation instanceof PlantObservation) {
                $this->cleanFields($observation, ['notes']);
            }
            $photo = $this->query(ActivityPhoto::class)->whereIn('activity_id', $ids)
                ->whereKey(DemoOwnerPortfolioData::id($this->team, 'photo:'.$key))->first();
            if ($photo instanceof ActivityPhoto) {
                $this->cleanFields($photo, ['label', 'caption']);
            }
        }
        $weather = $this->query(WeatherRecord::class)->where('farm_id', $farm->id)
            ->whereKey(DemoOwnerPortfolioData::id($this->team, 'weather:'.$site['key']))->first();
        if ($weather instanceof WeatherRecord) {
            $this->cleanFields($weather, ['data_source']);
        }
    }

    /** @param array<string, array<string, string>> $fields */
    private function replace(Model $record, array $fields): void
    {
        $changes = [];
        foreach ($fields as $field => $replacements) {
            $value = $record->getAttribute($field);
            if (is_string($value) && array_key_exists($value, $replacements)) {
                $changes[$field] = $replacements[$value];
            }
        }
        $this->write($record, $changes);
    }

    /** @param list<string> $fields */
    private function cleanFields(Model $record, array $fields): void
    {
        $changes = [];
        foreach ($fields as $field) {
            $value = $record->getAttribute($field);
            if (is_string($value) && preg_match('/\bdemo\b/i', $value)) {
                $clean = preg_replace('/\bdemo\b-?/i', '', $value) ?? $value;
                $changes[$field] = trim(preg_replace('/[ \t]{2,}/', ' ', $clean) ?? $clean);
            }
        }
        $this->write($record, $changes);
    }

    /** @param array<string, mixed> $changes */
    private function write(Model $record, array $changes, bool $quietly = false): void
    {
        if ($changes === []) {
            return;
        }
        Gate::forUser($this->actor)->authorize('update', $record);
        $record->timestamps = false;
        $record->forceFill($changes);
        $quietly ? $record->saveQuietly() : $record->save();
    }

    /** @template TModel of Model
     * @param  class-string<TModel>  $class
     * @return Builder<TModel>
     */
    private function query(string $class): Builder
    {
        Gate::forUser($this->actor)->authorize('viewAny', [$class, $this->team]);

        return (new $class)->newQuery()->scopes(['visibleTo' => [$this->actor, $this->team]]);
    }
}
