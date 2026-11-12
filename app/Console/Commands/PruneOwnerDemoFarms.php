<?php

namespace App\Console\Commands;

use App\Models\Farm;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\DemoFarmSeeder;
use Database\Seeders\DemoIdentitySeeder;
use Database\Seeders\DemoOwnerPortfolioData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PruneOwnerDemoFarms extends Command
{
    protected $signature = 'demo:prune-owner-farms {--apply : Permanently delete farms outside the seven approved demo locations} {--force : Skip the interactive confirmation}';

    protected $description = 'Preview or remove non-approved farms and their records from the local Owner demo tenant';

    public function handle(): int
    {

        $actor = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['superadmin']['email'])
            ->where('is_active', true)->whereHas('role', fn ($roles) => $roles->where('name', 'superadmin'))->first();
        $team = $actor?->teams()->where('slug', 'farm-ic-demo')->where('is_personal', false)->first();
        if (! $actor instanceof User || ! $team instanceof Team) {
            $this->error('Identitas Superadmin dan tenant farm-ic-demo harus tersedia. Jalankan php artisan db:seed terlebih dahulu.');

            return self::FAILURE;
        }

        Gate::forUser($actor)->authorize('view', $team);
        Gate::forUser($actor)->authorize('viewAny', [Farm::class, $team]);
        $connection = DB::connection();
        $this->line(sprintf(
            'Database tujuan: %s (host: %s)',
            $connection->getDatabaseName(),
            $connection->getConfig('host') ?? 'lokal/default',
        ));
        $keepIds = $this->approvedFarmIds($actor, $team);
        if ($keepIds === null) {
            return self::FAILURE;
        }

        $farmsToDelete = Farm::visibleTo($actor, $team)->where('team_id', $team->id)
            ->whereNotIn('id', $keepIds)->orderBy('name')->get();
        if ($farmsToDelete->isEmpty()) {
            $this->info('Tidak ada kebun tambahan di tenant demo. Tujuh kebun yang disetujui tetap tersimpan.');

            return self::SUCCESS;
        }

        $this->warn('Kebun berikut dan seluruh data operasional terkait akan dihapus permanen:');
        foreach ($farmsToDelete as $farm) {
            $this->line(sprintf('- %s | %s | %s ha', $farm->name, $farm->city ?? 'tanpa kabupaten', $farm->total_area_ha));
        }
        $this->line('Data di tenant lain tidak akan disentuh.');

        if (! $this->option('apply')) {
            $this->comment('Mode pratinjau. Jalankan dengan --apply untuk menghapus data setelah memeriksa daftar di atas.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Lanjutkan penghapusan permanen untuk kebun di atas?')) {
            $this->info('Penghapusan dibatalkan.');

            return self::SUCCESS;
        }

        $deletedCount = DB::transaction(function () use ($actor, $team, $keepIds): int {
            $farms = Farm::visibleTo($actor, $team)->where('team_id', $team->id)
                ->whereNotIn('id', $keepIds)->orderBy('name')->lockForUpdate()->get();
            foreach ($farms as $farm) {
                Gate::forUser($actor)->authorize('delete', $farm);
                $this->deleteFarmAndOperationalRecords((string) $farm->id);
            }

            return $farms->count();
        });

        $this->info(sprintf('Berhasil menghapus %d kebun tambahan beserta data terkait.', $deletedCount));

        return self::SUCCESS;
    }

    /** @return list<string>|null */
    private function approvedFarmIds(User $actor, Team $team): ?array
    {
        $baseFarm = Farm::visibleTo($actor, $team)->where('team_id', $team->id)
            ->whereIn('name', DemoFarmSeeder::FARM_NAMES)
            ->orderByRaw('CASE WHEN name = ? THEN 0 ELSE 1 END', [DemoFarmSeeder::FARM_NAME])
            ->orderBy('created_at')->first();
        if (! $baseFarm instanceof Farm) {
            $this->error('Kebun Cileuksa belum ditemukan. Jalankan php artisan db:seed terlebih dahulu.');

            return null;
        }
        if ($baseFarm->name !== DemoFarmSeeder::FARM_NAME || $baseFarm->city !== 'Kabupaten Bogor'
            || (float) $baseFarm->total_area_ha !== 100.0) {
            $this->error('Data Cileuksa belum sesuai daftar. Jalankan php artisan db:seed terlebih dahulu.');

            return null;
        }

        $ids = [(string) $baseFarm->id];
        foreach (DemoOwnerPortfolioData::sites() as $site) {
            $farmId = DemoOwnerPortfolioData::id($team, 'farm:'.$site['key']);
            $farm = Farm::visibleTo($actor, $team)->where('team_id', $team->id)->find($farmId);
            if (! $farm instanceof Farm) {
                $this->error('Satu atau lebih kebun dari daftar yang disetujui belum ditemukan. Jalankan php artisan db:seed terlebih dahulu.');

                return null;
            }
            if ($farm->name !== $site['name'] || $farm->city !== $site['city']
                || (float) $farm->total_area_ha !== (float) $site['area_ha']) {
                $this->error('Data salah satu kebun belum sesuai daftar. Jalankan php artisan db:seed terlebih dahulu.');

                return null;
            }
            $ids[] = (string) $farm->id;
        }

        if (count(array_unique($ids)) !== 7) {
            $this->error('Daftar kebun yang disetujui tidak menghasilkan tepat tujuh ID berbeda; tidak ada data yang dihapus.');

            return null;
        }

        return array_values(array_unique($ids));
    }

    private function deleteFarmAndOperationalRecords(string $farmId): void
    {
        $cycleIds = DB::table('planting_cycles')->where('farm_id', $farmId)->pluck('id')->all();

        // Remove restrictive children before their parent rows. Activity detail
        // tables and photos cascade from farm_activities; weather cascades from farms.
        foreach (['plant_observations', 'farm_risks', 'planting_cycle_stages'] as $table) {
            DB::table($table)->whereIn('planting_cycle_id', $cycleIds)->delete();
        }
        DB::table('financial_transactions')->where('farm_id', $farmId)->delete();
        DB::table('financial_budgets')->where('farm_id', $farmId)->delete();
        DB::table('financial_projections')->where('farm_id', $farmId)->delete();
        DB::table('operational_targets')->where('farm_id', $farmId)->delete();
        DB::table('farm_activities')->where('farm_id', $farmId)->delete();
        DB::table('daily_activity_logs')->where('farm_id', $farmId)->delete();
        DB::table('planting_cycles')->where('farm_id', $farmId)->delete();
        DB::table('farm_blocks')->where('farm_id', $farmId)->delete();

        $farm = Farm::query()->whereKey($farmId)->firstOrFail();
        $farm->delete();
    }
}
